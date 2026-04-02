<?php
// 1. Inclusion des dependances
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
include '../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin');

$message = "";
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie','Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';

$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Fournisseur' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $id_source = (int)$_POST['id_source'];
    $id_destination = (int)$_POST['id_destination'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    // Le prix est calculé pour affichage mais n'est pas stocké dans TransfertDetail (selon schéma)
    $id_user = $_SESSION['user_id'];

    if ($id_source === $id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : La source et la destination ne peuvent pas être identiques.</div>";
    } else {

    try {
        $pdo->beginTransaction();

        // Etape A : verifier si le stock disponible dans ce lot est suffisant
        // MODIFICATION : Utilisation des nouvelles tables StockLot et Produit
        $checkSql = "SELECT l.quantite_actuelle, l.id_produit, l.prix_achat_ttc, p.marge_pourcentage 
                     FROM StockLot l 
                     JOIN Produit p ON l.id_produit = p.id_produit 
                     WHERE l.id_lot = :id_lot FOR UPDATE";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id_lot' => $id_lot]);
        $lot = $stmtCheck->fetch();

        // Logique : Si la source est le Magasin Central (ID 1), on vérifie le stock physique
        $stock_suffisant = ($id_source !== 1) || ($lot && $lot['quantite_actuelle'] >= $quantite_demandee);

        if ($stock_suffisant) {
            
            // Etape B : Création du Transfert (En-tête)
            // Génération d'un numéro de bordereau unique basé sur la date
            $num_bordereau = 'TR-' . date('YmdHis') . '-' . rand(100, 999);
            
            $sqlTrans = "INSERT INTO Transfert (id_source, id_destination, id_user, num_bordereau) VALUES (?, ?, ?, ?)";
            $stmtTrans = $pdo->prepare($sqlTrans);
            $stmtTrans->execute([$id_source, $id_destination, $id_user, $num_bordereau]);
            $id_transfert = $pdo->lastInsertId();

            // Etape C : Création du Détail
            $sqlDet = "INSERT INTO TransfertDetail (id_transfert, id_lot, quantite_transfert) VALUES (?, ?, ?)";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([$id_transfert, $id_lot, $quantite_demandee]);

            // Etape D : mise a jour de la quantite restante
            // ON NE TOUCHE AU STOCK PHYSIQUE QUE SI CA SORT DU MAGASIN CENTRAL (ID 1)
            if ($id_source == 1) {
                $sqlUpdate = "UPDATE StockLot SET quantite_actuelle = quantite_actuelle - :qte
                              WHERE id_lot = :id_lot";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':qte'    => $quantite_demandee,
                    ':id_lot' => $id_lot
                ]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Transfert enregistré avec succès (Bordereau : $num_bordereau).</div>";
        } else {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot.</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
    }
    }
}

// 2b. Modification d'une sortie
if ($isAdmin && isset($_POST['btn_update_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];
    $new_id_lot = (int)$_POST['id_lot'];
    $new_id_source = (int)$_POST['id_source'];
    $new_id_destination = (int)$_POST['id_destination'];
    $new_qte = (int)$_POST['quantite_sortie'];

    if ($new_id_source === $new_id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : Source et destination identiques.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Récupérer l'ancien transfert pour restaurer le stock
            $stmtOld = $pdo->prepare("SELECT td.id_lot, td.quantite_transfert, t.id_source 
                                      FROM TransfertDetail td 
                                      JOIN Transfert t ON td.id_transfert = t.id_transfert 
                                      WHERE t.id_transfert = ?");
            $stmtOld->execute([$id_transfert]);
            $old = $stmtOld->fetch();

            if (!$old) throw new Exception("Transfert introuvable.");

            // Restaurer le stock (si source = 1)
            if ($old['id_source'] == 1) {
                $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?")
                    ->execute([$old['quantite_transfert'], $old['id_lot']]);
            }

            // 2. Vérifier et déduire le nouveau stock (si source = 1)
            if ($new_id_source == 1) {
                // On vérifie le stock disponible (qui inclut maintenant la quantité restaurée)
                $stmtCheck = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                $stmtCheck->execute([$new_id_lot]);
                $stockDispo = $stmtCheck->fetchColumn();

                if ($stockDispo < $new_qte) throw new Exception("Stock insuffisant pour la modification.");

                $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?")
                    ->execute([$new_qte, $new_id_lot]);
            }

            // 3. Mettre à jour les tables Transfert et TransfertDetail
            $pdo->prepare("UPDATE Transfert SET id_source = ?, id_destination = ? WHERE id_transfert = ?")
                ->execute([$new_id_source, $new_id_destination, $id_transfert]);
            
            $pdo->prepare("UPDATE TransfertDetail SET id_lot = ?, quantite_transfert = ? WHERE id_transfert = ?")
                ->execute([$new_id_lot, $new_qte, $id_transfert]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie modifiée avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur modification : " . $e->getMessage() . "</div>";
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];

    // Récupérer les infos pour restaurer le stock
    $stmt = $pdo->prepare("SELECT td.id_lot, td.quantite_transfert, t.id_source 
                           FROM TransfertDetail td 
                           JOIN Transfert t ON td.id_transfert = t.id_transfert 
                           WHERE t.id_transfert = ?");
    $stmt->execute([$id_transfert]);
    $rows = $stmt->fetchAll();

    if ($rows) {
        try {
            $pdo->beginTransaction();
            
            foreach($rows as $row) {
                // Restaurer le stock SI la source était le magasin central (ID 1)
                if ($row['id_source'] == 1) {
                    $upd = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
                    $upd->execute([$row['quantite_transfert'], $row['id_lot']]);
                }
            }

            // Supprimer le transfert (Cascade sur TransfertDetail grâce au SQL)
            $del = $pdo->prepare("DELETE FROM Transfert WHERE id_transfert = ?");
            $del->execute([$id_transfert]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Transfert supprimé et stock restauré.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// 3. Recuperation des lots disponibles
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, l.prix_achat_ttc, p.nom_medicament, p.marge_pourcentage 
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE l.quantite_actuelle > 0 
            AND part.type = 'Fournisseur'
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Récupération des points de vente
$pvs = $pdo->query("SELECT * FROM PointVente ORDER BY nom_point_vente ASC")->fetchAll();

// Historique des sorties
// On passe par Transfert et TransfertDetail
$sqlSorties = "SELECT t.id_transfert, t.num_bordereau,
                      t.id_source, t.id_destination,
                      td.quantite_transfert, 
                      td.id_lot,
                      l.num_lot, l.prix_achat_ttc,
                      p.nom_medicament, p.type_produit, p.marge_pourcentage,
                      u.nom_complet AS utilisateur, 
                      pv_src.nom_point_vente as source_nom, 
                      pv_dest.nom_point_vente as dest_nom,
                      part.type as type_partenaire
               FROM Transfert t
               JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
               JOIN StockLot l ON td.id_lot = l.id_lot
               JOIN Produit p ON l.id_produit = p.id_produit
               LEFT JOIN PointVente pv_src ON t.id_source = pv_src.id_point_vente
               LEFT JOIN PointVente pv_dest ON t.id_destination = pv_dest.id_point_vente
               LEFT JOIN Utilisateur u ON t.id_user = u.id_user
               -- Pour savoir si c'est Don ou Achat, on remonte via StockLot -> Commande -> Partenaire
               LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
               LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
               LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
               WHERE p.type_produit = ? AND part.type = 'Fournisseur'
";

$params = [$typeProduit];
if (!empty($fournisseurFilter)) {
    $sqlSorties .= " AND part.id_partenaire = ?";
    $params[] = $fournisseurFilter;
}
if (!empty($agentFilter)) {
    $sqlSorties .= " AND u.id_user = ?";
    $params[] = $agentFilter;
}
if (!empty($dateFilterStart)) {
    $sqlSorties .= " AND DATE(STR_TO_DATE(SUBSTRING(t.num_bordereau, 4, 14), '%Y%m%d%H%i%s')) >= ?";
    $params[] = $dateFilterStart;
}
if (!empty($dateFilterEnd)) {
    $sqlSorties .= " AND DATE(STR_TO_DATE(SUBSTRING(t.num_bordereau, 4, 14), '%Y%m%d%H%i%s')) <= ?";
    $params[] = $dateFilterEnd;
}
$sqlSorties .= " ORDER BY t.id_transfert DESC";

$stmtSorties = $pdo->prepare($sqlSorties);
$stmtSorties->execute($params);
$sorties_achats = $stmtSorties->fetchAll();

$groupTransfers = [];
$groupTransfersFile = __DIR__ . '/../data/group_transfers.json';
if (file_exists($groupTransfersFile)) {
    $loaded = json_decode(file_get_contents($groupTransfersFile), true);
    if (is_array($loaded)) {
        $groupTransfers = array_reverse($loaded);
    }
}

$pvNames = [];
foreach ($pvs as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

$currentUserName = $_SESSION['nom'] ?? '';
if (empty($currentUserName)) {
    $stmtUser = $pdo->prepare('SELECT nom_complet FROM Utilisateur WHERE id_user = ?');
    $stmtUser->execute([$_SESSION['user_id']]);
    $currentUserName = $stmtUser->fetchColumn() ?: '';
}
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-file-export"></i> Gestion des Transferts</h2>
    <?php if($isAdmin): ?>

    <?php echo $message; ?>
    <?php if (isset($_SESSION['group_transfer_message'])): ?>
        <?php $groupMsg = $_SESSION['group_transfer_message']; ?>
        <div class="alert alert-<?= htmlspecialchars($groupMsg['type']) ?>">
            <?= htmlspecialchars($groupMsg['text']) ?>
        </div>
        <?php unset($_SESSION['group_transfer_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5>Enregistrer un nouveau transfert</h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Selectionner le Lot</label>
                        <select name="id_lot" id="select_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>" data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>" data-marge="<?= $l['marge_pourcentage'] ?? 70 ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- <small class="text-muted">Note : les lots sont tries par date d'expiration (FIFO).</small> -->
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Destination</label>
                        <select name="id_destination" class="form-select" required>
                            <option value="">-- Choisir destination --</option>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Quantite a sortir</label>
                        <input type="number" name="quantite_sortie" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Prix Vente Est. <small class="text-muted">(Info)</small></label>
                        <input type="number" step="0.01" name="prix_vente" id="prix_vente" class="form-control" readonly>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="valider_sortie" class="btn btn-primary w-100 btn-lg">
                        Confirmer la sortie de stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h5>Préparer un transfert groupé</h5>
        </div>
        <div class="card-body">
            <form id="groupTransferForm" method="POST" action="save_group_transfer.php">
                <input type="hidden" name="action" value="initiate">
                <input type="hidden" name="items_json" id="items_json">
                <input type="hidden" name="redirect" value="sorties.php">
                <input type="hidden" name="id_source" id="group_source_hidden" value="<?= htmlspecialchars($pvs[0]['id_point_vente'] ?? 1) ?>">
                <input type="hidden" name="id_destination" id="group_destination_hidden" value="">

                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Source</label>
                        <select id="group_source" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Destination</label>
                        <select id="group_destination" class="form-select" required>
                            <option value="">-- Choisir destination --</option>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Lot</label>
                        <select id="group_lot" class="form-select">
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>"
                                        data-label="<?= htmlspecialchars($l['nom_medicament'] . ' | Lot: ' . $l['num_lot']) ?>"
                                        data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>"
                                        data-marge="<?= $l['marge_pourcentage'] ?? 0 ?>">
                                    <?= htmlspecialchars($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-bold">Qté</label>
                        <input type="number" id="group_qty" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-1">
                        <button type="button" id="btnAddGroupItem" class="btn btn-secondary w-100">Ajouter</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="groupItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Lot</th>
                                <th>Source</th>
                                <th>Destination</th>
                                <th class="text-end">PU Achat</th>
                                <th class="text-center">Marge</th>
                                <th class="text-end">PU Vente</th>
                                <th class="text-center">Agent</th>
                                <th class="text-center">Qté</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="small text-muted">Une fois la destination choisie, elle restera fixe pour tous les lots ajoutés.</div>
                    <div><strong>Total général :</strong> <span id="group_total_general">0,00 FCFA</span></div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success btn-lg">Créer transfert groupé</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5>Transferts groupés en attente</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Référence</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Agent</th>
                            <th>Articles</th>
                            <th class="text-end">Total général</th>
                            <th>Créé le</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $waitingGroups = array_filter($groupTransfers, function($group) {
                            return isset($group['status']) && $group['status'] === 'Envoyé';
                        });
                        ?>
                        <?php if (empty($waitingGroups)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun transfert groupé en attente.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($waitingGroups as $group): ?>
                                <?php
                                    $totalGeneralTransfer = 0;
                                    foreach ($group['items'] as $item) {
                                        $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : 0;
                                        $totalGeneralTransfer += $prixVente * intval($item['qty']);
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($group['id']) ?></td>
                                    <td><?= htmlspecialchars($pvNames[$group['id_source']] ?? $group['id_source']) ?></td>
                                    <td><?= htmlspecialchars($pvNames[$group['id_destination']] ?? $group['id_destination']) ?></td>
                                    <td><?= htmlspecialchars($group['agent_name'] ?? $currentUserName) ?></td>
                                    <td><?= count($group['items']) ?> lot(s)</td>
                                    <td class="text-end"><?= number_format($totalGeneralTransfer, 0, '.', ' ') ?> FCFA</td>
                                    <td><?= htmlspecialchars($group['created_at']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btnViewGroup" data-group-id="<?= htmlspecialchars($group['id']) ?>" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <form method="POST" action="save_group_transfer.php" class="d-inline">
                                            <input type="hidden" name="action" value="receive">
                                            <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
                                            <input type="hidden" name="redirect" value="sorties.php">
                                            <button type="submit" class="btn btn-sm btn-outline-primary me-1" title="Marquer reçu">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btnEditGroup" data-group-id="<?= htmlspecialchars($group['id']) ?>" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="save_group_transfer.php" class="d-inline" onsubmit="return confirm('Supprimer ce transfert groupé ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
                                            <input type="hidden" name="redirect" value="sorties.php">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Modal Group Detail -->
<div class="modal fade" id="groupDetailModal" tabindex="-1" aria-labelledby="groupDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupDetailModalLabel">Détails du transfert groupé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="groupDetailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
<!-- <form method="GET" id="searchForm" class="my-3" role="search">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (du)</label>
                <input type="date" id="dateFilterStart" name="dateStart" value="<?= htmlspecialchars($dateFilterStart) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (au)</label>
                <input type="date" id="dateFilterEnd" name="dateEnd" value="<?= htmlspecialchars($dateFilterEnd) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Agent</label>
                <select id="agentFilter" name="agent" class="form-select">
                    <option value="">Tous les agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>" <?= $agentFilter === (string)$agent['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form> -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchForm = document.getElementById('searchForm');
        if (!searchForm) return;
        var fields = searchForm.querySelectorAll('select, input[type="date"], input[type="text"], input[type="search"]');
        fields.forEach(function(el) {
            var eventName = (el.tagName.toLowerCase() === 'input' && (el.type === 'text' || el.type === 'search')) ? 'input' : 'change';
            el.addEventListener(eventName, function() {
                searchForm.submit();
            });
        });
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var groupSource = document.getElementById('group_source');
        var groupDestination = document.getElementById('group_destination');
        var groupSourceHidden = document.getElementById('group_source_hidden');
        var groupDestinationHidden = document.getElementById('group_destination_hidden');
        var groupLot = document.getElementById('group_lot');
        var groupQty = document.getElementById('group_qty');
        var btnAddGroupItem = document.getElementById('btnAddGroupItem');
        var groupItemsTable = document.getElementById('groupItemsTable');
        var itemsJsonInput = document.getElementById('items_json');
        var totalGeneralDisplay = document.getElementById('group_total_general');
        var groupTransferForm = document.getElementById('groupTransferForm');
        var currentUserName = <?= json_encode($currentUserName) ?>;

        var items = [];

        function formatCurrency(value) {
            return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' FCFA';
        }

        function updateHiddenFields() {
            if (groupSource && groupSourceHidden) {
                groupSourceHidden.value = groupSource.value;
            }
            if (groupDestination && groupDestinationHidden) {
                groupDestinationHidden.value = groupDestination.value;
            }
        }

        function renderGroupItems() {
            var tbody = groupItemsTable.querySelector('tbody');
            tbody.innerHTML = '';
            var totalGeneral = 0;

            if (items.length === 0) {
                var emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="10" class="text-center text-muted">Aucun lot ajouté.</td>';
                tbody.appendChild(emptyRow);
            } else {
                items.forEach(function(item, index) {
                    var totalRow = item.prix_vente * item.qty;
                    totalGeneral += totalRow;
                    var row = document.createElement('tr');
                    row.innerHTML =
                        '<td>' + item.label + '</td>' +
                        '<td>' + item.source + '</td>' +
                        '<td>' + item.destination + '</td>' +
                        '<td class="text-end">' + formatCurrency(item.prix_achat) + '</td>' +
                        '<td class="text-center">' + item.marge.toFixed(2) + '%</td>' +
                        '<td class="text-end">' + formatCurrency(item.prix_vente) + '</td>' +
                        '<td class="text-center">' + currentUserName + '</td>' +
                        '<td class="text-center">' + item.qty + '</td>' +
                        '<td class="text-end">' + formatCurrency(totalRow) + '</td>' +
                        '<td class="text-end">' +
                            '<button type="button" class="btn btn-sm btn-outline-danger btnRemoveGroupItem" data-index="' + index + '">' +
                                '<i class="bi bi-trash"></i>' +
                            '</button>' +
                        '</td>';
                    tbody.appendChild(row);
                });
            }

            if (totalGeneralDisplay) {
                totalGeneralDisplay.textContent = formatCurrency(totalGeneral);
            }
        }

        function updateItemsJson() {
            if (itemsJsonInput) {
                itemsJsonInput.value = JSON.stringify(items);
            }
        }

        function setSelectsState(disabled) {
            if (groupSource) groupSource.disabled = disabled;
            if (groupDestination) groupDestination.disabled = disabled;
        }

        if (groupSource) {
            groupSource.addEventListener('change', updateHiddenFields);
        }
        if (groupDestination) {
            groupDestination.addEventListener('change', updateHiddenFields);
        }

        updateHiddenFields();

        if (btnAddGroupItem) {
            btnAddGroupItem.addEventListener('click', function() {
                if (!groupLot || !groupDestination) {
                    alert('Veuillez sélectionner un lot et une destination.');
                    return;
                }

                var lotId = groupLot.value;
                var selectedOption = groupLot.options[groupLot.selectedIndex];
                var qty = parseInt(groupQty.value, 10);
                var sourceId = groupSource ? groupSource.value : '';
                var destinationId = groupDestination.value;

                if (!lotId) {
                    alert('Veuillez choisir un lot.');
                    return;
                }
                if (!destinationId) {
                    alert('Veuillez choisir une destination valide.');
                    return;
                }
                if (sourceId === destinationId) {
                    alert('Source et destination doivent être différentes.');
                    return;
                }
                if (qty <= 0 || isNaN(qty)) {
                    alert('Veuillez saisir une quantité valide.');
                    return;
                }

                var prixAchat = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                var marge = parseFloat(selectedOption.getAttribute('data-marge')) || 0;
                var prixVente = prixAchat * (1 + (marge / 100));
                var label = selectedOption.getAttribute('data-label') || selectedOption.text;

                items.push({
                    id_lot: lotId,
                    label: label,
                    source: groupSource.options[groupSource.selectedIndex].text,
                    destination: groupDestination.options[groupDestination.selectedIndex].text,
                    prix_achat: prixAchat,
                    marge: marge,
                    prix_vente: prixVente,
                    qty: qty
                });

                updateHiddenFields();
                setSelectsState(true);
                renderGroupItems();
                updateItemsJson();

                groupLot.selectedIndex = 0;
                groupQty.value = 1;
            });
        }

        if (groupItemsTable) {
            groupItemsTable.addEventListener('click', function(event) {
                if (event.target.closest('.btnRemoveGroupItem')) {
                    var button = event.target.closest('.btnRemoveGroupItem');
                    var index = parseInt(button.dataset.index, 10);
                    if (!isNaN(index)) {
                        items.splice(index, 1);
                        updateItemsJson();
                        renderGroupItems();
                        if (items.length === 0) {
                            setSelectsState(false);
                        }
                    }
                }
            });
        }

        if (groupTransferForm) {
            groupTransferForm.addEventListener('submit', function(event) {
                if (items.length === 0) {
                    alert('Ajoutez au moins un lot au transfert groupé avant de l\'envoyer.');
                    event.preventDefault();
                    return false;
                }
                updateHiddenFields();
                updateItemsJson();
            });
        }

        var groupDetailModalEl = document.getElementById('groupDetailModal');
        if (groupDetailModalEl) {
            var groupDetailModal = new bootstrap.Modal(groupDetailModalEl);
            var groupDetailModalTitle = document.getElementById('groupDetailModalLabel');
            var groupDetailModalBody = document.getElementById('groupDetailModalBody');
            var waitingGroupsData = <?= json_encode(array_values($waitingGroups), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            function renderGroupDetail(group, mode) {
                var html = '<div class="mb-3"><strong>Référence :</strong> ' + group.id + '</div>' +
                    '<div class="mb-3"><strong>Source :</strong> ' + (<?= json_encode($pvNames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>[group.id_source] || group.id_source) + '</div>' +
                    '<div class="mb-3"><strong>Destination :</strong> ' + (<?= json_encode($pvNames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>[group.id_destination] || group.id_destination) + '</div>' +
                    '<div class="mb-3"><strong>Agent :</strong> ' + (group.agent_name || 'N/A') + '</div>' +
                    '<div class="mb-3"><strong>Statut :</strong> ' + (group.status || 'N/A') + '</div>' +
                    '<div class="mb-3"><strong>Créé le :</strong> ' + (group.created_at || 'N/A') + '</div>';

                if (group.items && group.items.length) {
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">' +
                        '<thead class="table-light"><tr><th>Lot</th><th>PU Achat</th><th>Marge</th><th>PU Vente</th><th>Qté</th><th>Total ligne</th></tr></thead><tbody>';
                    var totalGeneral = 0;
                    group.items.forEach(function(item) {
                        var prixAchat = parseFloat(item.prix_achat) || 0;
                        var marge = parseFloat(item.marge) || 0;
                        var prixVente = parseFloat(item.prix_vente) || prixAchat * (1 + (marge / 100));
                        var totalLigne = prixVente * parseInt(item.qty, 10);
                        totalGeneral += totalLigne;
                        html += '<tr>' +
                            '<td>' + (item.label || 'Lot #' + item.id_lot) + '</td>' +
                            '<td class="text-end">' + prixAchat.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                            '<td class="text-center">' + marge.toFixed(2) + '%</td>' +
                            '<td class="text-end">' + prixVente.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                            '<td class="text-center">' + parseInt(item.qty, 10) + '</td>' +
                            '<td class="text-end">' + totalLigne.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table></div>';
                    html += '<div class="fw-bold text-end">Total général : ' + totalGeneral.toFixed(2).replace('.', ',') + ' FCFA</div>';
                } else {
                    html += '<p class="text-muted">Aucun article enregistré.</p>';
                }

                groupDetailModalTitle.textContent = mode === 'edit' ? 'Modifier le transfert groupé' : 'Détails du transfert groupé';
                groupDetailModalBody.innerHTML = html;
                groupDetailModal.show();
            }

            function findGroupById(groupId) {
                return waitingGroupsData.find(function(group) {
                    return group.id === groupId;
                });
            }

            document.querySelectorAll('.btnViewGroup, .btnEditGroup').forEach(function(button) {
                button.addEventListener('click', function() {
                    var groupId = button.getAttribute('data-group-id');
                    var group = findGroupById(groupId);
                    if (!group) {
                        alert('Transfert non trouvé.');
                        return;
                    }
                    renderGroupDetail(group, button.classList.contains('btnEditGroup') ? 'edit' : 'view');
                });
            });
        }
    });
    </script>

    <!-- <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Historique des sorties</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Bordereau</th>
                            <th>Med.</th>
                            <th>Type</th>
                            <th>Lot</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Qte</th>
                            <th>Valeur Est.</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($sorties_achats)): foreach($sorties_achats as $s): ?>
                            <?php 
                                // Extraction date depuis bordereau TR-YYYYMMDDHHMMSS-...
                                $parts = explode('-', $s['num_bordereau']);
                                $dateStr = isset($parts[1]) ? substr($parts[1], 6, 2).'/'.substr($parts[1], 4, 2).'/'.substr($parts[1], 0, 4) : '-';
                                
                                // Calcul prix vente estimatif
                                $prixVente = $s['prix_achat_ttc'] * (1 + ($s['marge_pourcentage'] / 100));
                                $totalEst = $prixVente * $s['quantite_transfert'];
                            ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($s['num_bordereau']) ?></td>
                                <td><?= $s['nom_medicament'] ?></td>
                                <td><?= htmlspecialchars($s['type_produit']) ?></td>
                                <td><?= $s['num_lot'] ?></td>
                                <td><span class="badge bg-secondary"><?= $s['source_nom'] ?? '?' ?></span></td>
                                <td><span class="badge bg-success"><?= $s['dest_nom'] ?? '?' ?></span></td>
                                <td class="fw-bold"><?= $s['quantite_transfert'] ?></td>
                                <td><?= number_format($totalEst, 0, '.', ' ') . ' F' ?></td>
                                <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? $s['utilisateur'] : '-' ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
                                        <button class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditTransfert"
                                                data-id="<?= $s['id_transfert'] ?>"
                                                data-lot="<?= $s['id_lot'] ?>"
                                                data-src="<?= $s['id_source'] ?>"
                                                data-dest="<?= $s['id_destination'] ?>"
                                                data-qte="<?= $s['quantite_transfert'] ?>"
                                                title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette sortie ?');">
                                            <input type="hidden" name="id_transfert" value="<?= $s['id_transfert'] ?>">
                                            <button type="submit" name="btn_delete_transfert" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="10" class="text-center text-muted">Aucune sortie enregistree.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> -->

<?php if($isAdmin): ?>
<!-- MODAL MODIFICATION TRANSFERT -->
<div class="modal fade" id="modalEditTransfert" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la sortie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_transfert" id="edit_id_transfert">

                <div class="mb-3">
                    <label class="form-label fw-bold">Lot</label>
                    <select name="id_lot" id="edit_id_lot" class="form-select" required>
                        <?php foreach($lotsDisponibles as $l): ?>
                            <option value="<?= $l['id_lot'] ?>">
                                <?= htmlspecialchars($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Si le lot d'origine n'a plus de stock, il peut ne pas apparaître ici.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" id="edit_id_source" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Destination</label>
                        <select name="id_destination" id="edit_id_destination" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Quantité</label>
                    <input type="number" name="quantite_sortie" id="edit_quantite" class="form-control" min="1" required>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="btn_update_transfert" class="btn btn-primary">Enregistrer les modifications</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calcul automatique du prix de vente avec marge dynamique
    var selectLot = document.getElementById('select_lot');
    var prixVenteInput = document.getElementById('prix_vente');
    
    if (selectLot && prixVenteInput) {
        selectLot.addEventListener('change', function() {
            var option = selectLot.options[selectLot.selectedIndex];
            var prixAchat = parseFloat(option.getAttribute('data-prix')) || 0;
            var marge = parseFloat(option.getAttribute('data-marge')) || 70;
            var prixVente = prixAchat * (1 + (marge / 100)); 
            prixVenteInput.value = prixVente.toFixed(2);
        });
    }

    // Remplissage du modal d'édition
    var modalEdit = document.getElementById('modalEditTransfert');
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            document.getElementById('edit_id_transfert').value = btn.getAttribute('data-id');
            document.getElementById('edit_id_lot').value = btn.getAttribute('data-lot');
            document.getElementById('edit_id_source').value = btn.getAttribute('data-src');
            document.getElementById('edit_id_destination').value = btn.getAttribute('data-dest');
            document.getElementById('edit_quantite').value = btn.getAttribute('data-qte');
        });
    }
});
</script>
<?php endif; ?>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>

<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "<div class='alert alert-success'>Entrée en stock enregistrée avec succès !</div>";
}

// --- ACTION 1 : PASSER UNE COMMANDE (STATUT 'En attente') ---
if ($isAdmin && isset($_POST['btn_creer_commande'])) {
    $id_p = $_POST['id_p'];
    $id_f = $_POST['id_f'];
    $qte = $_POST['qte'];
    $prix_achat_ttc = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;

    try {
        $pdo->beginTransaction();
        // Création de la commande en statut 'En attente'
        $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'En attente')");
        $stmtCmd->execute([$id_f, $_SESSION['user_id']]);
        $id_commande = $pdo->lastInsertId();

        // Création du détail (ce qu'on souhaite commander)
        $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
        $stmtDet->execute([$id_commande, $id_p, $qte]);
        
        // Note : On ne crée PAS encore de StockLot ici.

        // On sauvegarde le prix/marge potentiels dans le produit pour l'avenir, ou on pourrait le stocker dans la commande si la structure le permettait
        // Ici on laisse le produit tel quel, le prix sera confirmé à la réception.
        
        $pdo->commit();
        $message = "<div class='alert alert-info'>Commande enregistrée et mise en attente de réception.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur commande : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION 2 : VALIDER RÉCEPTION (STATUT 'Reçue' + CRÉATION LOT) ---
if ($isAdmin && isset($_POST['btn_valider_reception'])) {
    $id_cmd_det = $_POST['id_cmd_det'];
    $id_commande = $_POST['id_commande']; // Pour maj statut
    $id_p = $_POST['id_p']; // Pour maj marge produit
    
    $qte_recue = (int)$_POST['qte_recue'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $prix_achat_ttc = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;
    $marge = (isset($_POST['marge_pourcentage']) && $_POST['marge_pourcentage'] !== '') ? floatval($_POST['marge_pourcentage']) : null;

    try {
        $pdo->beginTransaction();

        // 1. Création du Lot (C'est le stock physique réel qui entre)
        $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte_recue, $exp, $prix_achat_ttc]);

        // 2. Mise à jour du statut de la commande parente -> 'Reçue'
        // (On suppose ici 1 commande = 1 ligne pour simplifier, sinon il faudrait vérifier si toutes les lignes sont reçues)
        $pdo->prepare("UPDATE Commande SET statut = 'Reçue' WHERE id_commande = ?")->execute([$id_commande]);

        // 3. Mise à jour infos produit (Marge) si changé
        if ($marge !== null) {
            $stmt = $pdo->prepare("UPDATE Produit SET marge_pourcentage = ? WHERE id_produit = ?");
            $stmt->execute([$marge, $id_p]);
        }

        $pdo->commit();
        $message = "<div class='alert alert-success'>Réception validée ! Le stock a été mis à jour.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur réception : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION : SUPPRIMER UN LOT (COMPLEXE AVEC NOUVELLE STRUCT) ---
// Pour simplifier : on interdit la suppression si mouvements, sinon on delete le StockLot et la cascade fera le reste si configuré, sinon manuel.
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    
    // Vérifier usage dans Mouvement
    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM Mouvement WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasMvt = $checkSorties->fetchColumn() > 0;

    if ($hasMvt) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce lot a des mouvements associés.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            // Récupérer le id_cmd_det pour nettoyer la commande fictive
            $stmt = $pdo->prepare("SELECT id_cmd_det FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $id_cmd_det = $stmt->fetchColumn();

            // Supprimer le lot
            $stmt = $pdo->prepare("DELETE FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);

            // Nettoyage optionnel de la commande si elle ne servait qu'à ça
            if ($id_cmd_det) {
                $pdo->prepare("DELETE FROM CommandeDetail WHERE id_cmd_det = ?")->execute([$id_cmd_det]);
                // La commande parente pourrait rester vide, on laisse pour l'instant ou on nettoie
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Lot supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression.</div>";
        }
    }
}
/* 
// --- ANCIENNE LOGIQUE SUPPRESSION (pour ref) ---
/*
    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    ...
*/

// --- ACTION : MODIFIER UN LOT ---
if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $qte_new = (int)$_POST['qte'];
    $prix_new = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;

    // On vérifie le stock actuel
    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM StockLot WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    if ($lot) {
        // Pour l'instant, on permet juste de corriger la qte actuelle directement (inventaire correction)
        // Dans un système strict, on ferait un mouvement de correction. Ici on UPDATE direct pour simplifier l'admin.
        try {
            $sql = "UPDATE StockLot SET num_lot = ?, date_expiration = ?, quantite_actuelle = ?, prix_achat_ttc = ? WHERE id_lot = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$num_lot, $exp, $qte_new, $prix_new, $id_lot]);
            $message = "<div class='alert alert-success'>Lot corrigé avec succès.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION : SUPPRIMER UNE COMMANDE EN ATTENTE ---
if ($isAdmin && isset($_POST['btn_delete_commande'])) {
    $id_cmd = (int)$_POST['id_commande'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM CommandeDetail WHERE id_commande = ?")->execute([$id_cmd]);
        $pdo->prepare("DELETE FROM Commande WHERE id_commande = ?")->execute([$id_cmd]);
        $pdo->commit();
        $message = "<div class='alert alert-success'>Commande annulée et supprimée.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur suppression : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION : MODIFIER UNE COMMANDE EN ATTENTE ---
if ($isAdmin && isset($_POST['btn_update_commande'])) {
    $id_cmd_det = (int)$_POST['id_cmd_det'];
    $qte = (int)$_POST['qte_voulue'];
    try {
        $pdo->prepare("UPDATE CommandeDetail SET quantite_voulue = ? WHERE id_cmd_det = ?")->execute([$qte, $id_cmd_det]);
        $message = "<div class='alert alert-success'>Quantité mise à jour avec succès.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Erreur modification : " . $e->getMessage() . "</div>";
    }
}

// --- RÉCUPÉRATION DES COMMANDES EN ATTENTE ---
$sqlAttente = "SELECT cmd.id_commande, cmd.date_commande, cd.id_cmd_det, cd.quantite_voulue,
                      p.id_produit, p.nom_medicament, p.type_produit, p.prix_unitaire, p.marge_pourcentage,
                      part.nom_entite, part.type as type_partenaire, u.nom_complet
               FROM Commande cmd
               JOIN CommandeDetail cd ON cmd.id_commande = cd.id_commande
               JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
               JOIN Produit p ON cd.id_produit = p.id_produit
               JOIN Utilisateur u ON cmd.id_user = u.id_user
               WHERE cmd.statut = 'En attente'
               -- On filtre aussi par type de produit pour l'affichage cohérent
               AND p.type_produit = ?
               ORDER BY cmd.date_commande ASC";
$stmtAttente = $pdo->prepare($sqlAttente);
$stmtAttente->execute([$typeProduit]);
$attentes = $stmtAttente->fetchAll();

// Récupération des données pour l'affichage
// Jointure complexe pour récupérer le partenaire via la Commande
$stmt = $pdo->prepare("SELECT l.*, p.nom_medicament, p.type_produit, p.marge_pourcentage, 
                              part.nom_entite, part.type as type_partenaire, u.nom_complet AS utilisateur,
                              cmd.date_commande AS date_enregistrement,
                              cd.quantite_voulue AS quantite_initiale
                        FROM StockLot l 
                        JOIN Produit p ON l.id_produit = p.id_produit 
                        LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
                        LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
                        LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                        LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user 
                        WHERE p.type_produit = ?
                        AND cmd.statut = 'Reçue' 
                        AND part.type = 'Fournisseur'
                        ORDER BY l.id_lot DESC");
$stmt->execute([$typeProduit]);
$achats = $stmt->fetchAll();

// récupérer produits en plaçant d'abord les médicaments (pharmacie) puis les articles laboratoire
$prods = $pdo->query("SELECT * FROM Produit ORDER BY (type_produit='Laboratoire'), nom_medicament ASC")->fetchAll();
// On ne récupère que les fournisseurs ici
$fours = $pdo->query("SELECT * FROM Partenaire ORDER BY type ASC, nom_entite ASC")->fetchAll();

include '../includes/sidebar.php';

echo '<link rel="stylesheet" href="../assets/css/entrees.css">';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Gestion des Commandes</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5> Passer une commande</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="entrees.php?type=<?= htmlspecialchars($typeFilter) ?>" class="row g-3">
                    <div class="d-flex flex-wrap gap-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Produit</label>
                            <select name="id_p" id="select_produit_entree" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php
                                // séparer en deux groupes : pharmacie (Pharmacie) puis laboratoire
                                $pharma = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Medicament');
                                $labo   = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Laboratoire');
                                ?>
                                <?php if (count($pharma)): ?>
                                    <optgroup label="Medicament">
                                        <?php foreach ($pharma as $p): ?>
                                            <option value="<?= $p['id_produit'] ?>" data-default-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                                <?= htmlspecialchars($p['nom_medicament']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (count($labo)): ?>
                                    <optgroup label="Laboratoire">
                                        <?php foreach ($labo as $p): ?>
                                            <option value="<?= $p['id_produit'] ?>" data-default-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                                <?= htmlspecialchars($p['nom_medicament']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fournisseur</label>
                            <select name="id_f" id="select_fournisseur" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php
                                // separer en deux groupes : fournisseurs puis donateurs
                                $fournisseurs = array_filter($fours, fn($f) => $f['type'] === 'Fournisseur');
                                ?>
                                <?php if (count($fournisseurs)): ?>
                                    <optgroup label="Fournisseurs" id="optgroup_fournisseurs">
                                        <?php foreach ($fournisseurs as $f): ?>
                                            <option value="<?= $f['id_partenaire'] ?>"><?= htmlspecialchars($f['nom_entite']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Quantité Voulue</label>
                            <input type="number" name="qte" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Prix Achat (TTC)</label>
                            <input type="number" step="0.01" name="prix_achat_ttc" id="input_prix_achat" class="form-control" placeholder="0.00">
                        </div>
                        <!-- Marge masquée ici, on la confirme à la réception -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Marge (%)</label>
                            <input type="number" step="0.01" name="marge_pourcentage" id="input_marge" class="form-control" placeholder="Ex: 20">
                        </div>
                    </div>

                    <!-- <br> -->
                    <div>
                        <div class="col-md-12 d-flex mt-4 align-items-end">
                            <button type="submit" name="btn_creer_commande" class="btn btn-primary w-100 btn-lg">Créer la commande</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>

    <?php endif; ?>

    <!-- FORMULAIRE RECHERCHE + FILTRE TYPE sous la barre -->
    <form method="GET" id="searchForm" class="mb-3" role="search">
        
        <div>
            <label for="typeFilter" class="form-label">Trier par type</label>
            <div class="d-flex gap-2">
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
                <button type="submit" class="btn btn-secondary">Appliquer</button>
            </div>
        </div>
    </form>

    <!-- TABLEAU COMMANDES EN ATTENTE -->
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header bg-warning-subtle text-dark">
            <strong>Liste des Commandes en Attente de Réception</strong>
        </div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date Commande</th>
                        <th>Produit</th>
                        <th>Fournisseur</th>
                        <th>Qté Commandée</th>
                        <th>Demandé par</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($attentes)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Aucune commande en attente.</td></tr>
                    <?php else: foreach($attentes as $att): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($att['date_commande'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($att['nom_medicament']) ?></td>
                            <td><?= htmlspecialchars($att['nom_entite']) ?></td>
                            <td class="fw-bold text-primary"><?= $att['quantite_voulue'] ?></td>
                            <td class="small"><?= htmlspecialchars($att['nom_complet']) ?></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-success btn-sm fw-bold" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalReception"
                                            data-id-cmd="<?= $att['id_commande'] ?>"
                                            data-id-det="<?= $att['id_cmd_det'] ?>"
                                            data-id-prod="<?= $att['id_produit'] ?>"
                                            data-nom="<?= htmlspecialchars($att['nom_medicament']) ?>"
                                            data-qte="<?= $att['quantite_voulue'] ?>"
                                            data-prix="<?= $att['prix_unitaire'] ?>"
                                            data-marge="<?= $att['marge_pourcentage'] ?>"
                                            data-source="<?= $att['type_partenaire'] ?>"
                                            title="Réceptionner">
                                        <i class="bi bi-box-seam"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm text-white" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditCommande"
                                            data-id="<?= $att['id_cmd_det'] ?>"
                                            data-nom="<?= htmlspecialchars($att['nom_medicament']) ?>"
                                            data-qte="<?= $att['quantite_voulue'] ?>"
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Annuler et supprimer cette commande ?');">
                                        <input type="hidden" name="id_commande" value="<?= $att['id_commande'] ?>">
                                        <button type="submit" name="btn_delete_commande" class="btn btn-danger btn-sm rounded-0 rounded-end" title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h4 class="mt-5 mb-3">Historique des Stocks (Reçus)</h4>

    <!-- TABLEAU DES ACHATS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><strong>Liste des Achats (Fournisseurs)</strong></div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>État</th>
                        <th>Désignation</th>
                        <th>Lot</th>
                        <th>Source</th>
                        <th>Qte Init.</th>
                        <th>Expiration</th>
                        <th>Prix Achat</th>
                        <th>Total</th>
                        <th>Agent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($achats as $e): ?>
                        <?php
                        $today = strtotime(date('Y-m-d'));
                        $exp_ts = strtotime($e['date_expiration']);
                        $isExpired = $exp_ts < $today;
                        $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);
                        if ($isExpired) {
                            $rowClass = 'table-dark text-white';
                            $statusBadge = '<span class="badge bg-white text-dark">Périmé</span>';
                        } elseif ($isCritical) {
                            $rowClass = 'table-warning';
                            $statusBadge = '<span class="badge bg-warning text-dark">Critique</span>';
                        } else {
                            $rowClass = '';
                            $statusBadge = '<span class="badge bg-success">Valide</span>';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="small"><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                            <td><?= $statusBadge ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['nom_medicament']) ?></strong>
                            </td>
                            <td><code class="text-dark"><?= htmlspecialchars($e['num_lot']) ?></code></td>
                            <td><?= htmlspecialchars($e['nom_entite']) ?></td>
                            <td><?= $e['quantite_initiale'] ?></td>
                            <td class="<?= $isExpired ? '' : '' ?>"><?= date('d/m/Y', strtotime($e['date_expiration'])) ?></td>
                            <td><?= number_format($e['prix_achat_ttc'], 0, '.', ' ') . ' F' ?></td>
                            <td><?= number_format($e['prix_achat_ttc'] * $e['quantite_initiale'], 0, '.', ' ') . ' F' ?></td>
                            <td class="<?php echo $isExpired ? 'small text-white' : 'small text-muted'; ?>"><?= htmlspecialchars($e['utilisateur']) ?></td>
                            <td class="text-nowrap">
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-sm btn-outline-primary me-1 border-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditLot"
                                        data-id="<?= $e['id_lot'] ?>"
                                        data-num="<?= htmlspecialchars($e['num_lot']) ?>"
                                        data-exp="<?= $e['date_expiration'] ?>"
                                        data-qte="<?= $e['quantite_initiale'] ?>"
                                        data-prix="<?= $e['prix_achat_ttc'] ?>"
                                        title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce lot ?');">
                                        <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                        <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- MODAL MODIFICATION LOT -->
    <div class="modal fade" id="modalEditLot" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Lot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_lot" id="edit_id_lot">

                    <label class="form-label fw-bold">Numéro de Lot</label>
                    <input type="text" name="num_lot" id="edit_num_lot" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Date d'Expiration</label>
                    <input type="date" name="exp" id="edit_exp" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Quantité Actuelle (Correction)</label>
                    <input type="number" name="qte" id="edit_qte" class="form-control mb-2" min="1" required>

                    <label class="form-label fw-bold">Prix Achat TTC</label>
                    <input type="number" step="0.01" name="prix_achat_ttc" id="edit_prix" class="form-control mb-2">
                </div>
                <div class="modal-footer"><button type="submit" name="btn_update_lot" class="btn btn-primary">Enregistrer les modifications</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL MODIFIER COMMANDE (EN ATTENTE) -->
    <div class="modal fade" id="modalEditCommande" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Modifier la commande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_cmd_det" id="cmd_edit_id">
                    <div class="mb-3">
                        <label class="form-label text-muted">Produit</label>
                        <input type="text" id="cmd_edit_nom" class="form-control-plaintext fw-bold" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantité Voulue</label>
                        <input type="number" name="qte_voulue" id="cmd_edit_qte" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="btn_update_commande" class="btn btn-primary btn-sm">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE RÉCEPTION -->
    <div class="modal fade" id="modalReception" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Valider la réception</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_commande" id="recv_id_cmd">
                    <input type="hidden" name="id_cmd_det" id="recv_id_det">
                    <input type="hidden" name="id_p" id="recv_id_prod">

                    <div class="alert alert-light border">
                        <strong>Produit :</strong> <span id="recv_nom_prod"></span>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Numéro de Lot <span class="text-danger">*</span></label>
                            <input type="text" name="num_lot" class="form-control" required placeholder="Sur la boîte">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date Expiration <span class="text-danger">*</span></label>
                            <input type="date" name="exp" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Qté Reçue (Physique) <span class="text-danger">*</span></label>
                            <input type="number" name="qte_recue" id="recv_qte" class="form-control fw-bold border-success" required>
                            <small class="text-muted">Modifiez si différent de la commande.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Prix Achat (Vérif.)</label>
                            <input type="number" step="0.01" name="prix_achat_ttc" id="recv_prix" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Marge (%)</label>
                            <input type="number" step="0.01" name="marge_pourcentage" id="recv_marge" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="btn_valider_reception" class="btn btn-success">Confirmer l'entrée en stock</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script src="../assets/js/entrees.js" defer></script>
<?php include '../includes/footer.php'; ?>
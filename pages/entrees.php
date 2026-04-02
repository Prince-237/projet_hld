<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$typeFilter = isset($_REQUEST['type']) && in_array($_REQUEST['type'], ['Pharmacie', 'Laboratoire']) ? $_REQUEST['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';

$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Fournisseur' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "<div class='alert alert-success'>Entrée en stock enregistrée avec succès !</div>";
}

// --- ACTION 1 : PASSER UNE COMMANDE (STATUT 'En attente') ---
if ($isAdmin && isset($_POST['btn_creer_commande'])) {
    $id_f = isset($_POST['id_f']) ? intval($_POST['id_f']) : 0;
    $prod_ids = $_POST['prod_ids'] ?? [];
    $prod_qtes = $_POST['prod_qtes'] ?? [];

    if (empty($prod_ids) && isset($_POST['id_p'], $_POST['qte'])) {
        $prod_ids = [intval($_POST['id_p'])];
        $prod_qtes = [intval($_POST['qte'])];
    }

    if ($id_f <= 0 || empty($prod_ids)) {
        $message = "<div class='alert alert-warning'>Veuillez sélectionner un fournisseur et ajouter au moins un produit au panier.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'En attente')");
            $stmtCmd->execute([$id_f, $_SESSION['user_id']]);
            $id_commande = $pdo->lastInsertId();

            $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($prod_ids); $i++) {
                $id_p = intval($prod_ids[$i]);
                $qte = isset($prod_qtes[$i]) ? intval($prod_qtes[$i]) : 0;
                if ($id_p > 0 && $qte > 0) {
                    $stmtDet->execute([$id_commande, $id_p, $qte]);
                }
            }

            $pdo->commit();
            $message = "<div class='alert alert-info'>Commande enregistrée et mise en attente de réception.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur commande : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION 2 : VALIDER RÉCEPTION (STATUT 'Reçue' + CRÉATION LOT) ---
if ($isAdmin && isset($_POST['btn_valider_reception'])) {
    $id_cmd_det = $_POST['id_cmd_det'];
    $id_commande = $_POST['id_commande'];
    $id_p = $_POST['id_p'];

    $qte_recue = (int)$_POST['qte_recue'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $prix_achat_ttc = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;
    $marge = (isset($_POST['marge_pourcentage']) && $_POST['marge_pourcentage'] !== '') ? floatval($_POST['marge_pourcentage']) : null;

    if ($exp <= date('Y-m-d')) {
        $message = "<div class='alert alert-danger'>Erreur réception : Impossible de réceptionner un lot périmé ou expirant aujourd'hui.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte_recue, $exp, $prix_achat_ttc]);

            $pdo->prepare("UPDATE Commande SET statut = 'Reçue' WHERE id_commande = ?")->execute([$id_commande]);

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
}

// --- ACTION : SUPPRIMER UN LOT ---
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];

    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM Mouvement WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasMvt = $checkSorties->fetchColumn() > 0;

    if ($hasMvt) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce lot a des mouvements associés.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id_cmd_det FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $id_cmd_det = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);

            if ($id_cmd_det) {
                $pdo->prepare("DELETE FROM CommandeDetail WHERE id_cmd_det = ?")->execute([$id_cmd_det]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Lot supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression.</div>";
        }
    }
}

// --- ACTION : MODIFIER UN LOT ---
if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $qte_new = (int)$_POST['qte'];
    $prix_new = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;

    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM StockLot WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    if ($lot) {
        if ($exp <= date('Y-m-d')) {
            $message = "<div class='alert alert-danger'>Erreur : La nouvelle date d'expiration est invalide (périmée ou expire aujourd'hui).</div>";
        } else {
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

// --- RÉCUPÉRATION DES COMMANDES EN ATTENTE (chargement initial) ---
$sqlAttente = "SELECT cmd.id_commande, cmd.date_commande,
                      part.nom_entite, u.nom_complet,
                      COUNT(cd.id_cmd_det) AS nb_produits,
                      SUM(cd.quantite_voulue * p.prix_unitaire) AS total_prix
               FROM Commande cmd
               JOIN CommandeDetail cd ON cmd.id_commande = cd.id_commande
               JOIN Produit p ON cd.id_produit = p.id_produit
               JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
               JOIN Utilisateur u ON cmd.id_user = u.id_user
               WHERE cmd.statut = 'En attente'
                 AND cmd.id_commande IN (
                     SELECT cd2.id_commande
                     FROM CommandeDetail cd2
                     JOIN Produit p2 ON cd2.id_produit = p2.id_produit
                     WHERE p2.type_produit = ?
                 )
               GROUP BY cmd.id_commande, cmd.date_commande, part.nom_entite, u.nom_complet
               ORDER BY cmd.date_commande ASC";
$stmtAttente = $pdo->prepare($sqlAttente);
$stmtAttente->execute([$typeProduit]);
$attentes = $stmtAttente->fetchAll();

// Récupération des données pour l'affichage
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

$prods = $pdo->query("SELECT * FROM Produit ORDER BY (type_produit='Laboratoire'), nom_medicament ASC")->fetchAll();
$fours = $pdo->query("SELECT * FROM Partenaire ORDER BY type ASC, nom_entite ASC")->fetchAll();

include '../includes/sidebar.php';

echo '<link rel="stylesheet" href="../assets/css/entrees.css">';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Gestion des Achats(Commandes) - Entrées</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5> Passer une commande</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Produit</label>
                        <select id="select_produit_entree" class="form-select">
                            <option value="">-- Sélectionner --</option>
                            <?php
                            $pharma = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Medicament');
                            $labo   = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Laboratoire');
                            ?>
                            <?php if (count($pharma)): ?>
                                <optgroup label="Medicament">
                                    <?php foreach ($pharma as $p): ?>
                                        <option value="<?= $p['id_produit'] ?>" data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>" data-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                        <option value="<?= $p['id_produit'] ?>" data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>" data-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>" <?= (isset($_GET['id_p']) && $_GET['id_p'] == $p['id_produit']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom_medicament']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
            <?php endif; ?>
                            <?php if (count($labo)): ?>
                                <optgroup label="Laboratoire">
                                    <?php foreach ($labo as $p): ?>
                                        <option value="<?= $p['id_produit'] ?>" data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>" data-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                        <option value="<?= $p['id_produit'] ?>" data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>" data-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>" <?= (isset($_GET['id_p']) && $_GET['id_p'] == $p['id_produit']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nom_medicament']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fournisseur</label>
                        <select id="select_fournisseur_temp" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach (array_filter($fours, fn($f) => $f['type'] === 'Fournisseur') as $f): ?>
                                <option value="<?= $f['id_partenaire'] ?>"><?= htmlspecialchars($f['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-bold">Quantité</label>
                        <input type="number" id="input_qte" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Prix Achat</label>
                        <input type="number" step="0.01" id="input_prix_achat" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-bold">Marge</label>
                        <div class="input-group">
                            <input type="number" step="0.01" id="input_marge" class="form-control" value="20">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="btn_ajouter_au_panier" class="btn btn-secondary w-100">
                            <i class="bi bi-plus"></i> Ajouter
                        </button>
                    </div>
                </div>

                <form method="POST" id="form_commande_panier" style="display:none;">
                    <input type="hidden" name="id_f" id="hidden_id_f">
                    <input type="hidden" name="type" id="hidden_type" value="<?= htmlspecialchars($typeFilter) ?>">
                    <table class="table table-bordered table-sm mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Fournisseur</th>
                                <th>Produit</th>
                                <th class="text-center" style="width: 100px;">Quantité</th>
                                <th class="text-end" style="width: 120px;">Prix Unit.</th>
                                <th class="text-center" style="width: 100px;">Marge</th>
                                <th class="text-end" style="width: 120px;">Prix Vente</th>
                                <th class="text-end" style="width: 150px;">Total</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="panier_body"></tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td colspan="5" class="text-start">TOTAL COMMANDE : <span class="text-end" id="panier_total_general"></span></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="text-end">
                        <button type="submit" name="btn_creer_commande" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Créer la commande
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <!-- FORMULAIRE RECHERCHE + FILTRE TYPE -->
    <form id="searchForm" class="mb-3">
        <div class="row g-2">

            <!-- TYPE -->
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>

            <!-- FOURNISSEUR -->
            <div class="col-md-2">
                <label class="form-label">Fournisseur</label>
                <select id="fournisseurFilter" name="fournisseur" class="form-select">
                    <option value="">Tous les fournisseurs</option>
                    <?php foreach ($fournisseurs as $fournisseur): ?>
                        <option value="<?= $fournisseur['id_partenaire'] ?>"><?= htmlspecialchars($fournisseur['nom_entite']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- RECHERCHE -->
            <div class="col-md-2">
                <label class="form-label">Recherche</label>
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="Fournisseur, agent...">
            </div>

            <!-- DATE -->
            <div class="col-md-2">
                <label class="form-label">Date (du)</label>
                <input type="date" id="dateFilterStart" name="dateStart" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (au)</label>
                <input type="date" id="dateFilterEnd" name="dateEnd" class="form-control">
            </div>

            <!-- AGENT -->
            <div class="col-md-2">
                <label class="form-label">Agent</label>
                <select id="agentFilter" name="agent" class="form-select">
                    <option value="">Tous les agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>"><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>
    </form>


    <!-- TABLEAU COMMANDES EN ATTENTE -->
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header bg-warning-subtle text-dark d-flex justify-content-between align-items-center">
            <strong>Liste des Commandes en Attente de Réception</strong>
        </div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date Commande</th>
                        <th>Fournisseur</th>
                        <th class="text-center">Nombre de produits</th>
                        <th class="text-end">Prix total</th>
                        <th>Demandé par</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <!-- ID DISTINCT pour le tableau des commandes en attente -->
                <tbody id="attenteTableBody">
                    <?php if (empty($attentes)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Aucune commande en attente de réception.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attentes as $att): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($att['date_commande'])) ?></td>
                                <td><?= htmlspecialchars($att['nom_entite']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= $att['nb_produits'] ?> produit(s)</span>
                                </td>
                                <td class="fw-bold text-end"><?= number_format($att['total_prix'] ?? 0, 0, '.', ' ') ?> FCFA</td>
                                <td class="small text-muted"><?= htmlspecialchars($att['nom_complet']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="details_commande_attente.php?id=<?= $att['id_commande'] ?>"
                                            class="btn btn-sm btn-outline-primary me-1"
                                            title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="reception_commande.php?id_commande=<?= $att['id_commande'] ?>"
                                            class="btn btn-sm btn-outline-success me-1" title="Réceptionner">
                                            <i class="bi bi-check2-circle"></i>
                                        </a>
                                        <a href="edit_commande.php?id=<?= $att['id_commande'] ?>"
                                            class="btn btn-sm btn-outline-secondary me-1" title="Modifier la commande">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Annuler et supprimer cette commande groupée ?');">
                                            <input type="hidden" name="id_commande" value="<?= $att['id_commande'] ?>">
                                            <button type="submit" name="btn_delete_commande" class="btn btn-sm btn-outline-danger" title="Annuler">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    

</div>

<!-- MODAL AFFICHAGE COMMANDE -->
<div class="modal fade" id="modalViewCommande" tabindex="-1" aria-labelledby="modalViewCommandeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalViewCommandeLabel">Détails de la commande</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="modalViewCommandeBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
            </div>
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

<?php if ($isAdmin): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnAjouter = document.getElementById('btn_ajouter_au_panier');
            const selectProd = document.getElementById('select_produit_entree');
            const selectFourn = document.getElementById('select_fournisseur_temp');
            const inputQte = document.getElementById('input_qte');
            const inputPrix = document.getElementById('input_prix_achat');
            const inputMarge = document.getElementById('input_marge');
            const panierBody = document.getElementById('panier_body');
            const formCommande = document.getElementById('form_commande_panier');
            const totalDisplay = document.getElementById('panier_total_general');
            const hiddenIdF = document.getElementById('hidden_id_f');

            let items = [];

            function updatePanier() {
                panierBody.innerHTML = '';
                let totalGeneral = 0;

                items.forEach((item, index) => {
                    const totalRow = item.prix * item.qte;
                    const pvEst = item.prix * (1 + (item.marge / 100));
                    totalGeneral += totalRow;

                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td class="small text-muted">${item.fournisseurNom}</td>
                    <td>
                        ${item.nom}
                        <input type="hidden" name="prod_ids[]" value="${item.id}">
                    </td>
                    <td><input type="number" name="prod_qtes[]" class="form-control form-control-sm text-center input-qte" value="${item.qte}" min="1" oninput="updateItem(${index})"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end input-prix" value="${item.prix}" oninput="updateItem(${index})"></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" class="form-control text-center input-marge" value="${item.marge}" oninput="updateItem(${index})">
                        </div>
                    </td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end input-pv" value="${pvEst.toFixed(0)}" oninput="updateItemFromPV(${index})"></td>
                    <td class="text-end total-row">${totalRow.toLocaleString()} F CFA</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger py-0" onclick="removeItem(${index})">&times;</button>
                    </td>
                `;
                    panierBody.appendChild(row);
                });

                totalDisplay.textContent = totalGeneral.toLocaleString() + ' FCFA';
                formCommande.style.display = items.length > 0 ? 'block' : 'none';
            }

            function fillProductDefaults() {
                if (!selectProd || !inputPrix || !inputMarge) return;
                const opt = selectProd.options[selectProd.selectedIndex];
                if (!opt || !opt.value) return;
                inputPrix.value = opt.getAttribute('data-prix') || 0;
                inputMarge.value = opt.getAttribute('data-marge') || 20;
            }

            selectProd.addEventListener('change', fillProductDefaults);
            fillProductDefaults();

            btnAjouter.addEventListener('click', function() {
                const prodId = selectProd.value;
                const fournId = selectFourn.value;
                const qte = parseInt(inputQte.value);
                const prix = parseFloat(inputPrix.value) || 0;
                const marge = parseFloat(inputMarge.value) || 0;
                const prodOpt = selectProd.options[selectProd.selectedIndex];
                const fournOpt = selectFourn.options[selectFourn.selectedIndex];

                if (!prodId || !fournId || qte < 1) {
                    alert("Veuillez sélectionner un produit, un fournisseur et une quantité.");
                    return;
                }

                hiddenIdF.value = fournId;
                selectFourn.disabled = true;

                items.push({
                    id: prodId,
                    nom: prodOpt.getAttribute('data-nom'),
                    fournisseurNom: fournOpt.text,
                    prix: prix,
                    marge: marge,
                    qte: qte
                });
                updatePanier();
            });

            window.updateItem = function(index) {
                const row = panierBody.children[index];
                const qte = parseInt(row.querySelector('.input-qte').value) || 0;
                const prix = parseFloat(row.querySelector('.input-prix').value) || 0;
                const marge = parseFloat(row.querySelector('.input-marge').value) || 0;

                items[index].qte = qte;
                items[index].prix = prix;
                items[index].marge = marge;

                const pvEst = prix * (1 + (marge / 100));
                row.querySelector('.input-pv').value = pvEst.toFixed(0);

                const totalRow = prix * qte;
                row.querySelector('.total-row').textContent = totalRow.toLocaleString() + ' F';

                calculateTotalGeneral();
            };

            window.updateItemFromPV = function(index) {
                const row = panierBody.children[index];
                const prix = parseFloat(row.querySelector('.input-prix').value) || 0;
                const pv = parseFloat(row.querySelector('.input-pv').value) || 0;

                let marge = 0;
                if (prix > 0) {
                    marge = ((pv / prix) - 1) * 100;
                }
                row.querySelector('.input-marge').value = marge.toFixed(2);

                items[index].marge = marge;
                calculateTotalGeneral();
            };

            function calculateTotalGeneral() {
                let totalGeneral = 0;
                items.forEach(item => {
                    totalGeneral += (item.prix * item.qte);
                });
                totalDisplay.textContent = totalGeneral.toLocaleString() + ' FCFA';
            }

            window.removeItem = function(index) {
                items.splice(index, 1);
                updatePanier();
            };

            const hiddenTypeInput = document.getElementById('hidden_type');
            const searchTypeFilter = document.getElementById('typeFilter');
            if (hiddenTypeInput && searchTypeFilter) {
                searchTypeFilter.addEventListener('change', function() {
                    hiddenTypeInput.value = this.value;
                });
            }

            // --- Ouvrir le modal d'édition de lot ---
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btnEditLot');
                if (btn) {
                    document.getElementById('edit_id_lot').value = btn.dataset.id;
                    document.getElementById('edit_num_lot').value = btn.dataset.num;
                    document.getElementById('edit_exp').value = btn.dataset.exp;
                    document.getElementById('edit_qte').value = btn.dataset.qte;
                    document.getElementById('edit_prix').value = btn.dataset.prix;
                    new bootstrap.Modal(document.getElementById('modalEditLot')).show();
                }
            });
        });

        // =========================================================
        // AJAX : rafraîchissement des DEUX tableaux selon les filtres
        // =========================================================
        const typeFilter       = document.getElementById('typeFilter');
        const fournisseurFilter = document.getElementById('fournisseurFilter');
        const searchInput      = document.getElementById('searchInput');
        const dateFilterStart  = document.getElementById('dateFilterStart');
        const dateFilterEnd    = document.getElementById('dateFilterEnd');
        const agentFilter      = document.getElementById('agentFilter');

        // Tableau des entrées reçues (géré par fetch_entrees.php)
        const tableBody        = document.getElementById('tableBody');

        // Tableau des commandes en attente (géré par fetch_commandes_attente.php)
        const attenteTableBody = document.getElementById('attenteTableBody');
        const badgeAttente     = document.getElementById('badge_attente');

        function getFilterParams() {
            return new URLSearchParams({
                type:        typeFilter.value,
                fournisseur: fournisseurFilter.value,
                search:      searchInput.value,
                dateStart:   dateFilterStart.value,
                dateEnd:     dateFilterEnd.value,
                agent:       agentFilter.value
                type:        typeFilter?.value || '',
                fournisseur: fournisseurFilter?.value || '',
                search:      searchInput?.value || '',
                dateStart:   dateFilterStart?.value || '',
                dateEnd:     dateFilterEnd?.value || '',
                agent:       agentFilter?.value || ''
            });
        }

        // Rafraîchir le tableau des entrées reçues
        function fetchEntrees() {
        function fetchAll() {
            fetch('fetch_entrees.php?' + getFilterParams())
                .then(r => r.text())
                .then(html => { tableBody.innerHTML = html; })
                .then(html => {
                    if (attenteTableBody) attenteTableBody.innerHTML = html;
                })
                .catch(err => console.error('Erreur fetch_entrees:', err));
        }

        // Rafraîchir le tableau des commandes en attente
        function fetchCommandesAttente() {
            fetch('fetch_commandes_attente.php?' + getFilterParams())
                .then(r => r.json())
                .then(data => {
                    if (data.html !== undefined) {
                        attenteTableBody.innerHTML = data.html;
                    }
                    if (badgeAttente && data.count !== undefined) {
                        badgeAttente.textContent = data.count;
                    }
                })
                .catch(err => console.error('Erreur fetch_commandes_attente:', err));
        }
        // Écouter tous les filtres
        [typeFilter, fournisseurFilter, dateFilterStart, dateFilterEnd, agentFilter].forEach(el => {
            if (el) el.addEventListener('change', fetchAll);
        });

        function fetchAll() {
            fetchEntrees();
            fetchCommandesAttente();
        if (searchInput) {
            searchInput.addEventListener('input', fetchAll);
        }

        // Écouter tous les filtres
        [typeFilter, fournisseurFilter, dateFilterStart, dateFilterEnd]
            .forEach(el => el.addEventListener('change', fetchAll));
        [searchInput, agentFilter]
            .forEach(el => el.addEventListener('input', fetchAll));

        // Chargement initial
        document.addEventListener('DOMContentLoaded', function() {
            // Déclenche une synchronisation initiale au cas où des filtres par défaut sont actifs
            // Le rendu PHP initial est déjà correct, on n'appelle fetchAll() qu'en cas de changement
        });
    </script>
<?php endif; ?>

<script src="../assets/js/entrees.js" defer></script>
<?php include '../includes/footer.php'; ?>
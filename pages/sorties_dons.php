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

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $id_source = (int)$_POST['id_source'];
    $id_destination = (int)$_POST['id_destination'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    $id_user = $_SESSION['user_id'];

    if ($id_source === $id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : La source et la destination ne peuvent pas être identiques.</div>";
    } else {

    try {
        $pdo->beginTransaction();

        $checkSql = "SELECT l.quantite_actuelle FROM StockLot l WHERE l.id_lot = :id_lot FOR UPDATE";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id_lot' => $id_lot]);
        $lot = $stmtCheck->fetch();

        $stock_suffisant = ($id_source !== 1) || ($lot && $lot['quantite_actuelle'] >= $quantite_demandee);

        if ($stock_suffisant) {
            
            $num_bordereau = 'TR-' . date('YmdHis') . '-' . rand(100, 999);
            
            $sqlTrans = "INSERT INTO Transfert (id_source, id_destination, id_user, num_bordereau) VALUES (?, ?, ?, ?)";
            $stmtTrans = $pdo->prepare($sqlTrans);
            $stmtTrans->execute([$id_source, $id_destination, $id_user, $num_bordereau]);
            $id_transfert = $pdo->lastInsertId();

            $sqlDet = "INSERT INTO TransfertDetail (id_transfert, id_lot, quantite_transfert) VALUES (?, ?, ?)";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([$id_transfert, $id_lot, $quantite_demandee]);

            if ($id_source == 1) {
                $sqlUpdate = "UPDATE StockLot SET quantite_actuelle = quantite_actuelle - :qte WHERE id_lot = :id_lot";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([':qte' => $quantite_demandee, ':id_lot' => $id_lot]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Transfert de don enregistré avec succès (Bordereau : $num_bordereau).</div>";
        } else {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot de don.</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
    }
    }
}

if ($isAdmin && isset($_POST['btn_delete_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];

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
                if ($row['id_source'] == 1) {
                    $upd = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
                    $upd->execute([$row['quantite_transfert'], $row['id_lot']]);
                }
            }

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

// 3. Recuperation des lots disponibles (uniquement ceux issus de DONS)
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, p.nom_medicament
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE l.quantite_actuelle > 0 AND part.type = 'Don'
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Récupération des points de vente
$pvs = $pdo->query("SELECT * FROM PointVente ORDER BY nom_point_vente ASC")->fetchAll();

// Historique des sorties (uniquement DONS)
$sqlSorties = "SELECT t.id_transfert, t.num_bordereau,
                      td.quantite_transfert, 
                      l.num_lot,
                      p.nom_medicament, p.type_produit,
                      u.nom_complet AS utilisateur, 
                      pv_src.nom_point_vente as source_nom, 
                      pv_dest.nom_point_vente as dest_nom
               FROM Transfert t
               JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
               JOIN StockLot l ON td.id_lot = l.id_lot
               JOIN Produit p ON l.id_produit = p.id_produit
               LEFT JOIN PointVente pv_src ON t.id_source = pv_src.id_point_vente
               LEFT JOIN PointVente pv_dest ON t.id_destination = pv_dest.id_point_vente
               LEFT JOIN Utilisateur u ON t.id_user = u.id_user
               LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
               LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
               LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
               WHERE p.type_produit = :typeProduit AND part.type = 'Don'
               ORDER BY t.id_transfert DESC";

$stmtSorties = $pdo->prepare($sqlSorties);
$stmtSorties->execute([':typeProduit' => $typeProduit]);
$sorties_dons = $stmtSorties->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4">Sorties de Dons</h2>
    <?php if($isAdmin): ?>

    <?php echo $message; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5>Enregistrer une sortie de don</h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Selectionner le Lot (Don)</label>
                        <select name="id_lot" id="select_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
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
                </div>

                <div class="mt-4">
                    <button type="submit" name="valider_sortie" class="btn btn-primary w-100 btn-lg">
                        Confirmer la sortie du don
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <form method="GET" id="searchForm" class="my-3" role="search">
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

    <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Historique des sorties (Dons)</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Bordereau</th>
                            <th>Produit</th>
                            <th>Type</th>
                            <th>Lot</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Qté</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($sorties_dons)): foreach($sorties_dons as $s): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($s['num_bordereau']) ?></td>
                                <td><?= $s['nom_medicament'] ?></td>
                                <td><?= htmlspecialchars($s['type_produit']) ?></td>
                                <td><?= $s['num_lot'] ?></td>
                                <td><span class="badge bg-secondary"><?= $s['source_nom'] ?? '?' ?></span></td>
                                <td><span class="badge bg-success"><?= $s['dest_nom'] ?? '?' ?></span></td>
                                <td><?= $s['quantite_transfert'] ?></td>
                                <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? $s['utilisateur'] : '-' ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
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
                            <tr><td colspan="9" class="text-center text-muted">Aucune sortie de don enregistrée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
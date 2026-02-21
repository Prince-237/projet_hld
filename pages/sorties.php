<?php
// 1. Inclusion des dependances
require_once('../config/db.php');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
include('../includes/header.php');
$isAdmin = ($_SESSION['role'] === 'admin');

$message = "";

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $point_vente = $_POST['point_vente'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    $prix_vente = $_POST['prix_vente'];
    $id_user = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // Etape A : verifier si le stock disponible dans ce lot est suffisant
        $checkSql = "SELECT quantite_actuelle, id_produit FROM stock_lots WHERE id_lot = :id_lot FOR UPDATE";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id_lot' => $id_lot]);
        $lot = $stmtCheck->fetch();

        if ($lot && $lot['quantite_actuelle'] >= $quantite_demandee) {
            // Etape B : calculer le montant total de la sortie
            $total_prix = $quantite_demandee * $prix_vente;

            // Etape C : enregistrer la sortie
            $sqlInsert = "INSERT INTO sorties (id_lot, nom_point_vente, quantite_sortie, prix_vente_unitaire, total_prix, id_user)
                          VALUES (:id_lot, :pv, :qte, :prix, :total, :user)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':id_lot' => $id_lot,
                ':pv'     => $point_vente,
                ':qte'    => $quantite_demandee,
                ':prix'   => $prix_vente,
                ':total'  => $total_prix,
                ':user'   => $id_user
            ]);

            // Etape D : mise a jour de la quantite restante
            $sqlUpdate = "UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - :qte
                          WHERE id_lot = :id_lot";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':qte'    => $quantite_demandee,
                ':id_lot' => $id_lot
            ]);

            // Etape E : reduire le stock total
            if (!empty($lot['id_produit'])) {
                $sqlProd = "UPDATE produits SET stock_total = stock_total - :qte WHERE id_produit = :idp";
                $stmtProd = $pdo->prepare($sqlProd);
                $stmtProd->execute([':qte' => $quantite_demandee, ':idp' => $lot['id_produit']]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie enregistree et stock mis a jour.</div>";
        } else {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot.</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur systeme : " . $e->getMessage() . "</div>";
    }
}

if ($isAdmin && isset($_POST['btn_update_sortie'])) {
    $id_sortie = (int)$_POST['id_sortie'];
    $point_vente = htmlspecialchars($_POST['point_vente']);
    $quantite_sortie = (int)$_POST['quantite_sortie'];
    $prix_vente = (float)$_POST['prix_vente'];

    $stmt = $pdo->prepare("SELECT s.id_lot, s.quantite_sortie, l.quantite_actuelle, l.id_produit FROM sorties s JOIN stock_lots l ON s.id_lot = l.id_lot WHERE s.id_sortie = ?");
    $stmt->execute([$id_sortie]);
    $row = $stmt->fetch();

    if ($row) {
        $diff = $quantite_sortie - $row['quantite_sortie'];
        if ($diff > 0 && $row['quantite_actuelle'] < $diff) {
            $message = "<div class='alert alert-danger'>Stock insuffisant pour augmenter cette sortie.</div>";
        } else {
            try {
                $pdo->beginTransaction();
                $total_prix = $quantite_sortie * $prix_vente;

                $stmt = $pdo->prepare("UPDATE sorties SET nom_point_vente = ?, quantite_sortie = ?, prix_vente_unitaire = ?, total_prix = ? WHERE id_sortie = ?");
                $stmt->execute([$point_vente, $quantite_sortie, $prix_vente, $total_prix, $id_sortie]);

                if ($diff !== 0) {
                    $stmt = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?");
                    $stmt->execute([$diff, $row['id_lot']]);

                    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
                    $stmt->execute([$diff, $row['id_produit']]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>Sortie mise a jour.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_sortie'])) {
    $id_sortie = (int)$_POST['id_sortie'];

    $stmt = $pdo->prepare("SELECT s.id_lot, s.quantite_sortie, l.id_produit FROM sorties s JOIN stock_lots l ON s.id_lot = l.id_lot WHERE s.id_sortie = ?");
    $stmt->execute([$id_sortie]);
    $row = $stmt->fetch();

    if ($row) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM sorties WHERE id_sortie = ?");
            $stmt->execute([$id_sortie]);

            $stmt = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
            $stmt->execute([$row['quantite_sortie'], $row['id_lot']]);

            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
            $stmt->execute([$row['quantite_sortie'], $row['id_produit']]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie supprimee.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// 3. Recuperation des lots disponibles
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, p.nom_medicament
            FROM stock_lots l
            JOIN produits p ON l.id_produit = p.id_produit
            WHERE l.quantite_actuelle > 0
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Historique des sorties
$sqlSorties = "SELECT s.*, l.num_lot, p.nom_medicament, u.nom_complet AS utilisateur
               FROM sorties s
               JOIN stock_lots l ON s.id_lot = l.id_lot
               JOIN produits p ON l.id_produit = p.id_produit
               LEFT JOIN utilisateurs u ON s.id_user = u.id_user
               ORDER BY s.id_sortie DESC";
$sorties = $pdo->query($sqlSorties)->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4 text-primary"><i class="fas fa-file-export"></i> Sortie de Medicaments</h2>
    <?php if($isAdmin): ?>

    <?php echo $message; ?>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label font-weight-bold">Selectionner le Lot (Medicament - Lot - Quantite dispo)</label>
                        <select name="id_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Note : les lots sont tries par date d'expiration (FIFO).</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Point de Vente / Service Destination</label>
                        <input type="text" name="point_vente" class="form-control" placeholder="Ex: Pharmacie de garde, urgences..." required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Quantite a sortir</label>
                        <input type="number" name="quantite_sortie" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Prix de vente unitaire</label>
                        <input type="number" step="0.01" name="prix_vente" class="form-control" required>
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
    <?php endif; ?>

    <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Historique des sorties</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medicament</th>
                            <th>Lot</th>
                            <th>Point de Vente</th>
                            <th>Qte</th>
                            <th>Prix U.</th>
                            <th>Total</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($sorties)): foreach($sorties as $s): ?>
                            <tr>
                                <td><?= $s['date_sortie'] ?></td>
                                <td><?= $s['nom_medicament'] ?></td>
                                <td><?= $s['num_lot'] ?></td>
                                <td><?= $s['nom_point_vente'] ?></td>
                                <td><?= $s['quantite_sortie'] ?></td>
                                <td><?= $s['prix_vente_unitaire'] ?></td>
                                <td><?= $s['total_prix'] ?></td>
                                <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? $s['utilisateur'] : '-' ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditSortie"
                                            data-id="<?= $s['id_sortie'] ?>"
                                            data-point="<?= htmlspecialchars($s['nom_point_vente']) ?>"
                                            data-qte="<?= $s['quantite_sortie'] ?>"
                                            data-prix="<?= $s['prix_vente_unitaire'] ?>"
                                            title="Modifier"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette sortie ?');">
                                            <input type="hidden" name="id_sortie" value="<?= $s['id_sortie'] ?>">
                                            <button type="submit" name="btn_delete_sortie" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="9" class="text-center text-muted">Aucune sortie enregistree.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalEditSortie" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Modifier une sortie</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_sortie" id="edit_id_sortie">
          <input type="text" name="point_vente" id="edit_point_vente" class="form-control mb-2" placeholder="Point de vente" required>
          <input type="number" name="quantite_sortie" id="edit_qte_sortie" class="form-control mb-2" placeholder="Qte" required>
          <input type="number" step="0.01" name="prix_vente" id="edit_prix_vente" class="form-control" placeholder="Prix unitaire" required>
      </div>
      <div class="modal-footer"><button type="submit" name="btn_update_sortie" class="btn btn-success">Mettre a jour</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalEditSortie');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_sortie').value = button.getAttribute('data-id');
        document.getElementById('edit_point_vente').value = button.getAttribute('data-point');
        document.getElementById('edit_qte_sortie').value = button.getAttribute('data-qte');
        document.getElementById('edit_prix_vente').value = button.getAttribute('data-prix');
    });
});
</script>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>

<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

if ($isAdmin && isset($_POST['btn_lot'])) {
    $id_p = $_POST['id_p'];
    $id_f = $_POST['id_f'];
    $qte = $_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];

    $pdo->beginTransaction();
    // Insertion du lot
    $stmt = $pdo->prepare("INSERT INTO stock_lots (id_produit, id_fournisseur, num_lot, quantite_initiale, quantite_actuelle, date_expiration, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_p, $id_f, $num_lot, $qte, $qte, $exp, $_SESSION['user_id']]);
    // Mise a jour stock total
    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
    $stmt->execute([$qte, $id_p]);
    $pdo->commit();
    header("Location: entrees.php"); exit();
}

if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $id_p = (int)$_POST['id_p'];
    $id_f = (int)$_POST['id_f'];
    $qte = (int)$_POST['qte'];
    $num_lot = htmlspecialchars($_POST['num_lot']);
    $exp = $_POST['exp'];

    $stmt = $pdo->prepare("SELECT id_produit, quantite_initiale, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    if ($lot) {
        $used = $lot['quantite_initiale'] - $lot['quantite_actuelle'];
        if ($qte < $used) {
            $message = "<div class='alert alert-danger'>Quantite invalide : deja sortie = {$used}.</div>";
        } else {
            $new_actuelle = $qte - $used;
            $old_actuelle = $lot['quantite_actuelle'];

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE stock_lots SET id_produit = ?, id_fournisseur = ?, num_lot = ?, quantite_initiale = ?, quantite_actuelle = ?, date_expiration = ? WHERE id_lot = ?");
                $stmt->execute([$id_p, $id_f, $num_lot, $qte, $new_actuelle, $exp, $id_lot]);

                if ((int)$lot['id_produit'] !== $id_p) {
                    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
                    $stmt->execute([$old_actuelle, $lot['id_produit']]);
                    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
                    $stmt->execute([$new_actuelle, $id_p]);
                } else {
                    $delta = $new_actuelle - $old_actuelle;
                    if ($delta !== 0) {
                        $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
                        $stmt->execute([$delta, $id_p]);
                    }
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>Lot mis a jour.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];

    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM sorties WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasSorties = $checkSorties->fetchColumn() > 0;

    if ($hasSorties) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce lot a des sorties associees.</div>";
    } else if ($lot) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
            $stmt->execute([$lot['quantite_actuelle'], $lot['id_produit']]);
            $stmt = $pdo->prepare("DELETE FROM stock_lots WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>Lot supprime.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

$entrees = $pdo->query("SELECT l.*, p.nom_medicament, f.nom_societe, u.nom_complet AS utilisateur FROM stock_lots l JOIN produits p ON l.id_produit = p.id_produit JOIN fournisseurs f ON l.id_fournisseur = f.id_fournisseur LEFT JOIN utilisateurs u ON l.id_user = u.id_user ORDER BY l.id_lot DESC")->fetchAll();
$prods = $pdo->query("SELECT * FROM produits")->fetchAll();
$fours = $pdo->query("SELECT * FROM fournisseurs")->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Entrees en Stock (Lots)</h2>
    <?php if($isAdmin): ?>
        <div class="card p-3 mb-4 shadow-sm border-primary">
            <h5>Enregistrer une livraison</h5>
            <form method="POST" class="row g-2">
                <div class="col-md-3"><select name="id_p" class="form-select" required><?php foreach($prods as $p) echo "<option value='{$p['id_produit']}'>{$p['nom_medicament']}</option>"; ?></select></div>
                <div class="col-md-3"><select name="id_f" class="form-select" required><?php foreach($fours as $f) echo "<option value='{$f['id_fournisseur']}'>{$f['nom_societe']}</option>"; ?></select></div>
                <div class="col-md-2"><input type="number" name="qte" class="form-control" placeholder="Qte" required></div>
                <div class="col-md-2"><input type="text" name="num_lot" class="form-control" placeholder="No Lot" required></div>
                <div class="col-md-2"><input type="date" name="exp" class="form-control" required></div>
                <div class="col-12"><button type="submit" name="btn_lot" class="btn btn-primary w-100">Valider l'entree</button></div>
            </form>
        </div>
    <?php endif; ?>

    <?php if($message): ?><?= $message ?><?php endif; ?>

    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Medicament</th>
                <th>Lot</th>
                <th>Fournisseur</th>
                <th>Qte</th>
                <th>Expiration</th>
                <th>Prix unitaire</th>
                <th>Total</th>
                <th>Utilisateur</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($entrees as $e): ?>
                <tr>
                    <td><?= $e['date_enregistrement'] ?></td>
                    <td><?= $e['nom_medicament'] ?></td>
                    <td><?= $e['num_lot'] ?></td>
                    <td><?= $e['nom_societe'] ?></td>
                    <td><?= $e['quantite_initiale'] ?></td>
                    <td><?= $e['date_expiration'] ?></td>
                    <td><?= isset($e['prix_achat_ttc']) && $e['prix_achat_ttc'] !== null ? $e['prix_achat_ttc'] : '-' ?></td>
                    <td><?php if(isset($e['prix_achat_ttc']) && $e['prix_achat_ttc'] !== null) { echo $e['prix_achat_ttc'] * $e['quantite_initiale']; } else { echo '-'; } ?></td>
                    <td><?= isset($e['utilisateur']) && $e['utilisateur'] ? $e['utilisateur'] : '-' ?></td>
                    <td class="text-nowrap">
                        <?php if($isAdmin): ?>
                            <button
                                class="btn btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditLot"
                                data-id="<?= $e['id_lot'] ?>"
                                data-idp="<?= $e['id_produit'] ?>"
                                data-idf="<?= $e['id_fournisseur'] ?>"
                                data-num="<?= htmlspecialchars($e['num_lot']) ?>"
                                data-qte="<?= $e['quantite_initiale'] ?>"
                                data-exp="<?= $e['date_expiration'] ?>"
                                title="Modifier"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce lot ?');">
                                <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalEditLot" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Modifier un lot</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_lot" id="edit_id_lot">
          <div class="mb-2">
              <label class="form-label">Medicament</label>
              <select name="id_p" id="edit_id_p" class="form-select" required>
                  <?php foreach($prods as $p) echo "<option value='{$p['id_produit']}'>{$p['nom_medicament']}</option>"; ?>
              </select>
          </div>
          <div class="mb-2">
              <label class="form-label">Fournisseur</label>
              <select name="id_f" id="edit_id_f" class="form-select" required>
                  <?php foreach($fours as $f) echo "<option value='{$f['id_fournisseur']}'>{$f['nom_societe']}</option>"; ?>
              </select>
          </div>
          <input type="text" name="num_lot" id="edit_num_lot" class="form-control mb-2" placeholder="No Lot" required>
          <input type="number" name="qte" id="edit_qte" class="form-control mb-2" placeholder="Qte" required>
          <input type="date" name="exp" id="edit_exp" class="form-control" required>
      </div>
      <div class="modal-footer"><button type="submit" name="btn_update_lot" class="btn btn-success">Mettre a jour</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalEditLot');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_lot').value = button.getAttribute('data-id');
        document.getElementById('edit_id_p').value = button.getAttribute('data-idp');
        document.getElementById('edit_id_f').value = button.getAttribute('data-idf');
        document.getElementById('edit_num_lot').value = button.getAttribute('data-num');
        document.getElementById('edit_qte').value = button.getAttribute('data-qte');
        document.getElementById('edit_exp').value = button.getAttribute('data-exp');
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

if ($isAdmin && isset($_POST['btn_ajouter'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $forme = htmlspecialchars($_POST['forme']);
    $dosage = htmlspecialchars($_POST['dosage']);
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $stmt = $pdo->prepare("INSERT INTO produits (nom_medicament, forme, dosage, prix_unitaire, seuil_alerte, stock_total) VALUES (?, ?, ?, ?, 0, 0)");
    $stmt->execute([$nom, $forme, $dosage, $prix_unitaire]);
    header("Location: produits.php"); exit();
}

if ($isAdmin && isset($_POST['btn_update_produit'])) {
    $id = (int)$_POST['id_produit'];
    $nom = htmlspecialchars($_POST['nom_medicament']);
    $forme = htmlspecialchars($_POST['forme']);
    $dosage = htmlspecialchars($_POST['dosage']);
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $seuil = floatval($_POST['seuil_alerte']);

    $stmt = $pdo->prepare("UPDATE produits SET nom_medicament = ?, forme = ?, dosage = ?, prix_unitaire = ?, seuil_alerte = ? WHERE id_produit = ?");
    $stmt->execute([$nom, $forme, $dosage, $prix_unitaire, $seuil, $id]);
    $message = "<div class='alert alert-success'>Produit modifie avec succes.</div>";
}

if ($isAdmin && isset($_POST['btn_delete_produit'])) {
    $id = (int)$_POST['id_produit'];

    $checkLots = $pdo->prepare("SELECT COUNT(*) FROM stock_lots WHERE id_produit = ?");
    $checkLots->execute([$id]);
    $hasLots = $checkLots->fetchColumn() > 0;

    if ($hasLots) {
        $message = "<div class='alert alert-danger'>Suppression impossible : des lots existent pour ce medicament.</div>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        $message = "<div class='alert alert-success'>Produit supprime.</div>";
    }
}

$produits = $pdo->query("SELECT * FROM produits ORDER BY nom_medicament ASC")->fetchAll();
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Catalogue des Produits</h2>
        <?php if($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduit">+ Nouveau Produit</button>
        <?php endif; ?>
    </div>

    <?php if($message): ?><?= $message ?><?php endif; ?>

    <table class="table table-bordered bg-white shadow-sm">
        <thead class="table-light">
            <tr>
                <th>Nom</th>
                <th>Forme</th>
                <th>Dosage</th>
                <th>Prix unitaire</th>
                <th>Stock Global</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($produits as $p): ?>
                <tr>
                    <td><?= $p['nom_medicament'] ?></td>
                    <td><?= $p['forme'] ?></td>
                    <td><?= $p['dosage'] ?></td>
                    <td><?= isset($p['prix_unitaire']) ? $p['prix_unitaire'] : '-' ?></td>
                    <td><span class="badge bg-info text-dark"><?= $p['stock_total'] ?></span></td>
                    <td class="text-nowrap">
                        <?php if($isAdmin): ?>
                            <button
                                class="btn btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditProduit"
                                data-id="<?= $p['id_produit'] ?>"
                                data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>"
                                data-forme="<?= htmlspecialchars($p['forme']) ?>"
                                data-dosage="<?= htmlspecialchars($p['dosage']) ?>"
                                data-prix="<?= isset($p['prix_unitaire']) ? $p['prix_unitaire'] : 0 ?>"
                                data-seuil="<?= $p['seuil_alerte'] ?>"
                                title="Modifier"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce medicament ?');">
                                <input type="hidden" name="id_produit" value="<?= $p['id_produit'] ?>">
                                <button type="submit" name="btn_delete_produit" class="btn btn-sm btn-outline-danger" title="Supprimer">
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
<div class="modal fade" id="modalProduit" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Ajouter une reference</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="text" name="nom" class="form-control mb-2" placeholder="Nom du medicament" required>
          <input type="text" name="forme" class="form-control mb-2" placeholder="Forme (Comprime, Sirop...)" required>
          <input type="text" name="dosage" class="form-control mb-2" placeholder="Dosage (500mg, 10ml...)" required>
          <input type="number" step="0.01" name="prix_unitaire" class="form-control" placeholder="Prix unitaire" required>
      </div>
      <div class="modal-footer"><button type="submit" name="btn_ajouter" class="btn btn-success">Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalEditProduit" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Modifier un produit</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_produit" id="edit_id_produit">
          <input type="text" name="nom_medicament" id="edit_nom" class="form-control mb-2" placeholder="Nom du medicament" required>
          <input type="text" name="forme" id="edit_forme" class="form-control mb-2" placeholder="Forme" required>
          <input type="text" name="dosage" id="edit_dosage" class="form-control mb-2" placeholder="Dosage" required>
          <input type="number" step="0.01" name="prix_unitaire" id="edit_prix" class="form-control mb-2" placeholder="Prix unitaire" required>
          <input type="number" step="0.1" name="seuil_alerte" id="edit_seuil" class="form-control" placeholder="Seuil d'alerte">
      </div>
      <div class="modal-footer"><button type="submit" name="btn_update_produit" class="btn btn-success">Mettre a jour</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalEditProduit');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_produit').value = button.getAttribute('data-id');
        document.getElementById('edit_nom').value = button.getAttribute('data-nom');
        document.getElementById('edit_forme').value = button.getAttribute('data-forme');
        document.getElementById('edit_dosage').value = button.getAttribute('data-dosage');
        document.getElementById('edit_prix').value = button.getAttribute('data-prix');
        document.getElementById('edit_seuil').value = button.getAttribute('data-seuil');
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

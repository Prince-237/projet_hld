<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

if ($isAdmin && isset($_POST['btn_ajouter'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $forme = htmlspecialchars($_POST['forme']);
    $dosage = htmlspecialchars($_POST['dosage']);
    $stmt = $pdo->prepare("INSERT INTO produits (nom_medicament, forme, dosage, seuil_alerte, stock_total) VALUES (?, ?, ?, 0, 0)");
    $stmt->execute([$nom, $forme, $dosage]);
    header("Location: produits.php"); exit();
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

    <table class="table table-bordered bg-white shadow-sm">
        <thead class="table-light">
            <tr><th>Nom</th><th>Forme</th><th>Dosage</th><th>Stock Global</th></tr>
        </thead>
        <tbody>
            <?php foreach($produits as $p): ?>
                <tr>
                    <td><?= $p['nom_medicament'] ?></td>
                    <td><?= $p['forme'] ?></td>
                    <td><?= $p['dosage'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= $p['stock_total'] ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalProduit" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Ajouter une référence</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="text" name="nom" class="form-control mb-2" placeholder="Nom du médicament" required>
          <input type="text" name="forme" class="form-control mb-2" placeholder="Forme (Comprimé, Sirop...)" required>
          <input type="text" name="dosage" class="form-control mb-2" placeholder="Dosage (500mg, 10ml...)" required>
      </div>
      <div class="modal-footer"><button type="submit" name="btn_ajouter" class="btn btn-success">Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../config/db.php';
session_start();

// 1. Logique d'insertion (Si on a cliqué sur le bouton Enregistrer du formulaire)
if (isset($_POST['btn_ajouter'])) {
    $nom = htmlspecialchars($_POST['nom_medicament']);
    $forme = htmlspecialchars($_POST['forme']);
    $dosage = htmlspecialchars($_POST['dosage']);
    $seuil = intval($_POST['seuil_alerte']);

    $sql = "INSERT INTO produits (nom_medicament, forme, dosage, seuil_alerte) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $forme, $dosage, $seuil]);
    
    header("Location: produits.php?success=1");
    exit();
}

// 2. Récupération des produits pour l'affichage
$query = $pdo->query("SELECT * FROM produits ORDER BY nom_medicament ASC");
$produits = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Produits - Laquintinie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Catalogue des Médicaments</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjout">
            + Ajouter un produit
        </button>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nom</th>
                        <th>Forme</th>
                        <th>Dosage</th>
                        <th>Seuil d'alerte</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                    <tr>
                        <td><?php echo $p['nom_medicament']; ?></td>
                        <td><?php echo $p['forme']; ?></td>
                        <td><?php echo $p['dosage']; ?></td>
                        <td><span class="badge bg-info"><?php echo $p['seuil_alerte']; ?></span></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-warning">Modifier</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjout" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nouveau Médicament</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label>Désignation</label>
            <input type="text" name="nom_medicament" class="form-control" placeholder="ex: Paracétamol" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Forme</label>
                <input type="text" name="forme" class="form-control" placeholder="ex: Comprimé">
            </div>
            <div class="col-md-6 mb-3">
                <label>Dosage</label>
                <input type="text" name="dosage" class="form-control" placeholder="ex: 500mg">
            </div>
        </div>
        <div class="mb-3">
            <label>Seuil d'alerte (Stock minimum)</label>
            <input type="number" name="seuil_alerte" class="form-control" value="10">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="btn_ajouter" class="btn btn-success">Enregistrer le produit</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
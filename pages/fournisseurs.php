<?php
// On remonte d'un dossier pour atteindre la config
require_once '../config/db.php'; 
include('../includes/header.php');

session_start();

// 1. Logique d'insertion d'un fournisseur
if (isset($_POST['btn_ajouter_fournisseur'])) {
    $nom = htmlspecialchars($_POST['nom_societe']);
    $contact = htmlspecialchars($_POST['contact_nom']);
    $tel = htmlspecialchars($_POST['telephone']);
    $email = htmlspecialchars($_POST['email']);

    try {
        $sql = "INSERT INTO fournisseurs (nom_societe, contact_nom, telephone, email) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $contact, $tel, $email]);
        header("Location: fournisseurs.php?success=1");
        exit();
    } catch (PDOException $e) {
        $erreur = "Erreur : " . $e->getMessage();
    }
}

// 2. Récupération de la liste des fournisseurs
$query = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom_societe ASC");
$fournisseurs = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fournisseurs - Laquintinie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🤝 Nos Fournisseurs</h2>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalFournisseur">
            + Nouveau Fournisseur
        </button>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">Fournisseur enregistré avec succès !</div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Société</th>
                        <th>Contact</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fournisseurs as $f): ?>
                    <tr>
                        <td><strong><?php echo $f['nom_societe']; ?></strong></td>
                        <td><?php echo $f['contact_nom']; ?></td>
                        <td><?php echo $f['telephone']; ?></td>
                        <td><?php echo $f['email']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary">Détails</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFournisseur" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Ajouter un Fournisseur</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nom de la société *</label>
            <input type="text" name="nom_societe" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nom du contact</label>
            <input type="text" name="contact_nom" class="form-control" placeholder="Ex: Mr. Zambo">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Téléphone</label>
                <input type="text" name="telephone" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
        <button type="submit" name="btn_ajouter_fournisseur" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>


<?php include '../includes/footer.php'; ?>
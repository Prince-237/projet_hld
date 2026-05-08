<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

if (!$isAdmin) {
    header("Location: ../pages/dashboard.php");
    exit();
}

$message = '';

// ======================================
// AJOUTER UNE CATÉGORIE
// ======================================
if (isset($_POST['btn_ajouter_categorie'])) {
    $nom_categorie = htmlspecialchars(trim($_POST['nom_categorie']));
    $forme = htmlspecialchars(trim($_POST['forme']));
    $dosage = htmlspecialchars(trim($_POST['dosage']));
    $description = !empty($_POST['description']) ? htmlspecialchars(trim($_POST['description'])) : null;

    // Vérifier si la catégorie existe déjà
    $check = $pdo->prepare("SELECT id_categorie FROM ProductCategory WHERE nom_categorie = ? AND forme = ? AND dosage = ?");
    $check->execute([$nom_categorie, $forme, $dosage]);

    if ($check->fetch()) {
        $message = "<div class='alert alert-warning'>Cette catégorie existe déjà !</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO ProductCategory (nom_categorie, forme, dosage, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nom_categorie, $forme, $dosage, $description])) {
            $message = "<div class='alert alert-success'>Catégorie ajoutée avec succès !</div>";
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de l'ajout de la catégorie.</div>";
        }
    }
}

include '../includes/sidebar.php';
?>
<a href="liste_categories.php" class="btn btn-outline-secondary d-flex float-end mb-3">
        Retour
    </a>
<div class="container mt-4">
    
    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Ajouter une nouvelle catégorie</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): echo $message;
                    endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                            <input type="text" name="nom_categorie" class="form-control" placeholder="Ex: Analgésique, Antibiotique, Vitamines..." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Forme <span class="text-danger">*</span></label>
                            <input type="text" name="forme" class="form-control" placeholder="Ex: Comprimé, Sirop, Injectable, Gélule..." required>
                            <div class="form-text">Forme galénique du médicament</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Dosage <span class="text-danger">*</span></label>
                            <input type="text" name="dosage" class="form-control" placeholder="Ex: 500mg, 1g, 10ml, 2ml..." required>
                            <div class="form-text">Dosage ou concentration</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Description optionnelle..."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="btn_ajouter_categorie" class="btn btn-primary">
                                Enregistrer
                            </button>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php include '../includes/footer.php'; ?>
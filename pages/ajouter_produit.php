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

// Récupérer toutes les catégories pour les listes déroulantes
$categories = $pdo->query("SELECT * FROM ProductCategory ORDER BY nom_categorie ASC, forme ASC")->fetchAll(PDO::FETCH_ASSOC);

$message = '';

// ======================================
// AJOUT PRODUIT
// ======================================
if (isset($_POST['btn_ajouter_produit'])) {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $id_categorie = (int)$_POST['id_categorie'];
    $type_produit = htmlspecialchars(trim($_POST['type_produit']));
    $prix_unitaire = !empty($_POST['prix_unitaire']) ? (float)$_POST['prix_unitaire'] : null;
    $marge = !empty($_POST['marge_pourcentage']) ? (float)$_POST['marge_pourcentage'] : 20;
    $seuil = !empty($_POST['seuil_alerte']) ? (int)$_POST['seuil_alerte'] : 0;

    // VÉRIFICATION CATÉGORIE
    $checkCategorie = $pdo->prepare("SELECT id_categorie FROM ProductCategory WHERE id_categorie = ?");
    $checkCategorie->execute([$id_categorie]);

    if ($checkCategorie->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO Produit (id_categorie, nom_medicament, type_produit, prix_unitaire, marge_pourcentage, seuil_alerte) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$id_categorie, $nom, $type_produit, $prix_unitaire, $marge, $seuil])) {
            $new_product_id = $pdo->lastInsertId();
            $new_product_name = $nom;
            $message = "<div class='alert alert-success'>Produit ajouté avec succès !</div>";
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de l'ajout du produit.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Erreur : Catégorie inexistante !</div>";
    }
}

include '../includes/sidebar.php';
?>
<a href="produits.php" class="btn btn-outline-secondary d-flex float-end mb-3">
    Retour
</a>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Ajouter un nouveau produit</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): echo $message;
                    endif; ?>

                    <form method="POST">
                        <!-- SÉLECTION CATÉGORIE -->
                        <div class="mb-3">
                            <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select name="id_categorie" class="form-select" required>
                                <option value="">Choisir une catégorie...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id_categorie'] ?>">
                                        <?= htmlspecialchars($cat['nom_categorie']) ?> - <?= htmlspecialchars($cat['forme']) ?> <?= $cat['dosage'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- BOUTON AJOUTER CATÉGORIE -->
                        <!-- <div class="d-flex gap-2 mb-3">
                            <a href="ajouter_categorie.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-plus-circle"></i> Nouvelle catégorie
                            </a>
                        </div> -->

                        <!-- TYPE PRODUIT -->
                        <div class="mb-3">
                            <label class="form-label">Type de produit <span class="text-danger">*</span></label>
                            <select name="type_produit" class="form-select" required>
                                <option value="">Choisir le type...</option>
                                <option value="Medicament">Pharmacie</option>
                                <option value="Laboratoire">Laboratoire</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nom du produit <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" placeholder="Ex: Doliprane, Paracétamol..." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Prix unitaire (FCFA)<span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="prix_unitaire" class="form-control" placeholder="Prix de référence" min="0" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Marge (%)<span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="marge_pourcentage" class="form-control" placeholder="Marge par défaut" value="20" min="0" max="50" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Seuil d'alerte<span class="text-danger">*</span></label>
                            <input type="number" name="seuil_alerte" class="form-control" placeholder="Quantité minimale avant alerte" min="0" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="btn_ajouter_produit" class="btn btn-primary">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($new_product_id)): ?>
    <!-- MODAL DE REDIRECTION APRÈS CRÉATION -->
    <div class="modal fade" id="modalPostAjout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-check-circle-fill me-2"></i> Produit enregistré</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="fs-5">Le produit <strong><?= htmlspecialchars($new_product_name ?? '') ?></strong> a été ajouté avec succès.</p>
                    <p class="text-muted">Son stock initial est de <strong>0</strong>. Souhaitez-vous enregistrer une réception de stock maintenant ?</p>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Plus tard</button>

                        <div class="dropdown">
                            <button class="btn btn-primary px-4 dropdown-toggle" type="button" id="dropdownReception" data-bs-toggle="dropdown" aria-expanded="false">
                                Oui
                            </button>
                            <ul class="dropdown-menu shadow border-0" aria-labelledby="dropdownReception">
                                <li>
                                    <h6 class="dropdown-header">Type de réception</h6>
                                </li>
                                <li><a class="dropdown-item py-2" href="entrees.php?id_p=<?= $new_product_id ?? '' ?>">Réception Fournisseur (Achat)</a></li>
                                <li><a class="dropdown-item py-2" href="dons.php?id_p=<?= $new_product_id ?? '' ?>">Réception de Don</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modalPostAjout = new bootstrap.Modal(document.getElementById('modalPostAjout'), {
                backdrop: 'static',
                keyboard: false
            });
            modalPostAjout.show();
        });
    </script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php include '../includes/footer.php'; ?>
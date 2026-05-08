<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

// ======================================
// MODIFIER UNE CATÉGORIE
// ======================================
if ($isAdmin && isset($_POST['btn_update_categorie'])) {
    $id_categorie = (int)$_POST['id_categorie'];
    $nom_categorie = htmlspecialchars(trim($_POST['nom_categorie']));
    $forme = htmlspecialchars(trim($_POST['forme']));
    $dosage = htmlspecialchars(trim($_POST['dosage']));
    
    $stmt = $pdo->prepare("UPDATE ProductCategory SET nom_categorie = ?, forme = ?, dosage = ? WHERE id_categorie = ?");
    if ($stmt->execute([$nom_categorie, $forme, $dosage, $id_categorie])) {
        $message = "<div class='alert alert-success'>Catégorie modifiée avec succès !</div>";
    } else {
        $message = "<div class='alert alert-danger'>Erreur lors de la modification de la catégorie.</div>";
    }
}

// ======================================
// SUPPRIMER UNE CATÉGORIE
// ======================================
if ($isAdmin && isset($_POST['btn_delete_categorie'])) {
    $id_categorie = (int)$_POST['id_categorie'];
    
    // Vérifier si des produits sont liés à cette catégorie
    $checkProduits = $pdo->prepare("SELECT COUNT(*) FROM Produit WHERE id_categorie = ?");
    $checkProduits->execute([$id_categorie]);
    $hasProduits = $checkProduits->fetchColumn() > 0;
    
    if ($hasProduits) {
        $message = "<div class='alert alert-danger'>Suppression impossible : des produits sont liés à cette catégorie.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM ProductCategory WHERE id_categorie = ?");
            $stmt->execute([$id_categorie]);
            $message = "<div class='alert alert-success'>Catégorie supprimée avec succès.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
        }
    }
}

// Filtre type de produit (Medicament = Pharmacie, Laboratoire)
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Medicament', 'Laboratoire']) ? $_GET['type'] : '';

// Requête pour récupérer les catégories avec le nombre de produits par type
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM Produit p WHERE p.id_categorie = c.id_categorie AND p.type_produit = 'Medicament') as nb_medicaments,
        (SELECT COUNT(*) FROM Produit p WHERE p.id_categorie = c.id_categorie AND p.type_produit = 'Laboratoire') as nb_laboratoires
        FROM ProductCategory c";

if ($typeFilter) {
    $sql .= " WHERE c.id_categorie IN (SELECT id_categorie FROM Produit WHERE type_produit = '$typeFilter')";
}

$sql .= " ORDER BY c.nom_categorie ASC, c.forme ASC";

$categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Liste des Catégories</h2>
        <?php if(isset($message)): echo $message; endif; ?>
    </div>

    <!-- FILTRE TYPE PRODUIT (Live) -->
    <form method="GET" id="filterForm" class="mb-3">
        <div class="row align-items-end">
            <div class="col-md-4">
                <label for="typeFilter" class="form-label">Filtrer par type de produit</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
        </div>
    </form>

    <!-- TABLEAU DES CATÉGORIES -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Catégorie</th>
                    <th>Forme</th>
                    <th>Dosage</th>
                    <!-- <th class="text-center">Type de produit</th> -->
                    <th class="text-center">Nb Produits</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Aucune catégorie trouvée
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($categories as $cat): 
                        $nb_total = (int)$cat['nb_medicaments'] + (int)$cat['nb_laboratoires'];
                        $type_libelle = '';
                        if ($typeFilter === 'Medicament') {
                            $type_libelle = 'Pharmacie';
                        } elseif ($typeFilter === 'Laboratoire') {
                            $type_libelle = 'Laboratoire';
                        } else {
                            // Afficher les deux types s'ils existent
                            $types = [];
                            if ((int)$cat['nb_medicaments'] > 0) $types[] = 'Pharmacie';
                            if ((int)$cat['nb_laboratoires'] > 0) $types[] = 'Laboratoire';
                            $type_libelle = implode(' / ', $types);
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['nom_categorie']) ?></td>
                            <td><?= htmlspecialchars($cat['forme']) ?></td>
                            <td><?= htmlspecialchars($cat['dosage']) ?></td>
                            <!-- <td class="text-center">
                                <?php if($typeFilter === 'Medicament'): ?>
                                    <span class="badge bg-success">Pharmacie</span>
                                <?php elseif($typeFilter === 'Laboratoire'): ?>
                                    <span class="badge bg-info">Laboratoire</span>
                                <?php else: ?>
                                    <?php if((int)$cat['nb_medicaments'] > 0): ?>
                                        <span class="badge bg-success me-1">Pharmacie</span>
                                    <?php endif; ?>
                                    <?php if((int)$cat['nb_laboratoires'] > 0): ?>
                                        <span class="badge bg-info">Laboratoire</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td> -->
                            <td class="text-center"><?= $nb_total ?></td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li><a class="dropdown-item" href="voir_produits_par_categorie.php?id_categorie=<?= $cat['id_categorie'] ?><?= $typeFilter ? '&type=' . $typeFilter : '' ?>">
                                            <i class="bi bi-eye me-2"></i>Voir les produits
                                        </a></li>
                                        <?php if($isAdmin): ?>
                                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditCategorie"
                                                data-id="<?= $cat['id_categorie'] ?>"
                                                data-nom="<?= htmlspecialchars($cat['nom_categorie']) ?>"
                                                data-forme="<?= htmlspecialchars($cat['forme']) ?>"
                                                data-dosage="<?= htmlspecialchars($cat['dosage']) ?>">
                                                <i class="bi bi-pencil me-2"></i>Modifier
                                            </button></li>
                                            <!-- <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="m-0" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                                    <input type="hidden" name="id_categorie" value="<?= $cat['id_categorie'] ?>">
                                                    <button type="submit" name="btn_delete_categorie" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash me-2"></i>Supprimer
                                                    </button>
                                                </form>
                                            </li> -->
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- <div class="mt-3 mb-5 text-muted small">
        Affichage de <span class="fw-bold"><?= count($categories) ?></span> catégorie(s)
    </div> -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live filter - soumet le formulaire automatiquement lors du changement
    document.getElementById('typeFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
    
    // Modal edit categorie
    var modalEdit = document.getElementById('modalEditCategorie');
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit_id_categorie').value = button.getAttribute('data-id');
            document.getElementById('edit_nom_categorie').value = button.getAttribute('data-nom');
            document.getElementById('edit_forme').value = button.getAttribute('data-forme');
            document.getElementById('edit_dosage').value = button.getAttribute('data-dosage');
        });
    }
});
</script>

<?php if($isAdmin): ?>
<!-- MODAL MODIFIER CATÉGORIE -->
<div class="modal fade" id="modalEditCategorie" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5>Modifier la catégorie</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_categorie" id="edit_id_categorie">
                <div class="mb-3">
                    <label class="form-label">Nom de la catégorie</label>
                    <input type="text" name="nom_categorie" id="edit_nom_categorie" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Forme</label>
                    <input type="text" name="forme" id="edit_forme" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dosage</label>
                    <input type="text" name="dosage" id="edit_dosage" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="btn_update_categorie" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>

<?php include '../includes/footer.php'; ?>
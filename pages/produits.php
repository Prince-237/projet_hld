<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$isAdmin = ($_SESSION['role'] === 'admin');

// Récupérer toutes les catégories pour les listes déroulantes
$categories = $pdo->query("SELECT * FROM product_categories ORDER BY nom_categorie ASC, forme ASC")->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// AJOUTER UNE CATÉGORIE (nouveau)
// ======================================
if ($isAdmin && isset($_POST['btn_ajouter_categorie'])) {
    $nom_categorie = htmlspecialchars(trim($_POST['nom_categorie']));
    $forme = htmlspecialchars(trim($_POST['forme']));
    $dosage = htmlspecialchars(trim($_POST['dosage']));
    
    $stmt = $pdo->prepare("INSERT INTO product_categories (nom_categorie, forme, dosage) VALUES (?, ?, ?)");
    if ($stmt->execute([$nom_categorie, $forme, $dosage])) {
        $message_categorie = "<div class='alert alert-success'>Catégorie ajoutée avec succès !</div>";
        // Recharger les catégories
        $categories = $pdo->query("SELECT * FROM product_categories ORDER BY nom_categorie ASC, forme ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message_categorie = "<div class='alert alert-danger'>Erreur lors de l'ajout de la catégorie.</div>";
    }
}

// ======================================
// AJOUT PRODUIT (avec vérification catégorie)
// ======================================
if ($isAdmin && isset($_POST['btn_ajouter_produit'])) {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $id_categorie = (int)$_POST['id_categorie'];
    $prix_unitaire = !empty($_POST['prix_unitaire']) ? (float)$_POST['prix_unitaire'] : null;
    $marge = !empty($_POST['marge_pourcentage']) ? (float)$_POST['marge_pourcentage'] : 20;
    $seuil = !empty($_POST['seuil_alerte']) ? (int)$_POST['seuil_alerte'] : 0;

    // VÉRIFICATION CATÉGORIE (comme dans le diagramme de séquence)
    $checkCategorie = $pdo->prepare("SELECT id_categorie FROM product_categories WHERE id_categorie = ?");
    $checkCategorie->execute([$id_categorie]);
    
    if ($checkCategorie->fetch()) {
        // Catégorie OK → INSERT PRODUIT
        $stmt = $pdo->prepare("INSERT INTO produits (id_categorie, nom_medicament, prix_unitaire, marge_pourcentage, seuil_alerte, stock_total) VALUES (?, ?, ?, ?, ?, 0)");
        if ($stmt->execute([$id_categorie, $nom, $prix_unitaire, $marge, $seuil])) {
            $message = "<div class='alert alert-success'>Produit ajouté avec succès !</div>";
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de l'ajout du produit.</div>";
        }
    } else {
        // ERREUR : Catégorie inexistante
        $message = "<div class='alert alert-danger'>Erreur : Catégorie inexistante !</div>";
    }
}

// UPDATE et DELETE (inchangés)
if ($isAdmin && isset($_POST['btn_update_produit'])) {
    $id = (int)$_POST['id_produit'];
    $nom = htmlspecialchars($_POST['nom_medicament']);
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $marge = (float)$_POST['marge_pourcentage'];
    $seuil = (int)$_POST['seuil_alerte'];

    $stmt = $pdo->prepare("UPDATE produits SET nom_medicament = ?, prix_unitaire = ?, marge_pourcentage = ?, seuil_alerte = ? WHERE id_produit = ?");
    $stmt->execute([$nom, $prix_unitaire, $marge, $seuil, $id]);
    $message = "<div class='alert alert-success'>Produit modifié avec succès.</div>";
}

if ($isAdmin && isset($_POST['btn_delete_produit'])) {
    $id = (int)$_POST['id_produit'];
    $checkLots = $pdo->prepare("SELECT COUNT(*) FROM stock_lots WHERE id_produit = ?");
    $checkLots->execute([$id]);
    $hasLots = $checkLots->fetchColumn() > 0;

    if ($hasLots) {
        $message = "<div class='alert alert-danger'>Suppression impossible : des lots existent pour ce médicament.</div>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        $message = "<div class='alert alert-success'>Produit supprimé.</div>";
    }
}

// Recherche
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql_base = "SELECT p.*, c.nom_categorie, c.forme, c.dosage 
             FROM produits p 
             JOIN product_categories c ON p.id_categorie = c.id_categorie";

if ($search !== '') {
    $stmt = $pdo->prepare("$sql_base WHERE p.nom_medicament LIKE ? ORDER BY p.nom_medicament ASC");
    $stmt->execute(['%' . $search . '%']);
    $produits = $stmt->fetchAll();
} else {
    $produits = $pdo->query("$sql_base ORDER BY p.nom_medicament ASC")->fetchAll();
}

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Catalogue des Produits</h2>
        <?php if($isAdmin): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalVoirCategories">
                    Voir les catégories
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduit">
                    + Nouveau Produit
                </button>                
            </div>
        <?php endif; ?>
    </div>

    <?php if(isset($message)): echo $message; endif; ?>
    <?php if(isset($message_categorie)): echo $message_categorie; endif; ?>

    <!-- FORMULAIRE RECHERCHE (inchangé) -->
    <form method="GET" id="searchForm" class="mb-3 d-flex" role="search">
        <input id="searchInput" type="search" name="q" class="form-control me-2" placeholder="Rechercher un médicament..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-primary me-2" type="submit">Rechercher</button>
        <a href="produits.php" class="btn btn-outline-primary">Réinitialiser</a>
    </form>

    <!-- TABLEAU (inchangé) -->
    <table class="table table-bordered bg-white shadow-sm">
        <thead class="table-light">
            <tr>
                <th>Nom</th>
                <th>Catégorie</th>
                <th>Prix Réf.</th>
                <th>Profit Margin %</th>
                <th>Seuil</th>
                <th>Stock Global</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($produits as $p): 
                $stock = (int)$p['stock_total'];
                $seuil = isset($p['seuil_alerte']) ? (int)$p['seuil_alerte'] : 0;
                $rowClass = $stock === 0 ? 'table-danger text-white' : ($stock <= $seuil ? 'table-warning' : '');
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                    <td><?= htmlspecialchars($p['nom_categorie']) ?> (<?= htmlspecialchars($p['forme']) ?>)</td>
                    <td><?= number_format($p['prix_unitaire'], 2) ?> FCFA</td>
                    <td><?= $p['marge_pourcentage'] ?>%</td>
                    <td><span class=""><?= $seuil ?></span></td>
                    <td><span class="text-dark"><?= $p['stock_total'] ?></span></td>
                    <td class="text-nowrap">
                        <?php if($isAdmin): ?>
                            <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditProduit"
                                data-id="<?= $p['id_produit'] ?>"
                                data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>"
                                data-prix="<?= $p['prix_unitaire'] ?>"
                                data-marge="<?= $p['marge_pourcentage'] ?>"
                                data-seuil="<?= $p['seuil_alerte'] ?>"
                                data-categorie="<?= $p['id_categorie'] ?>"
                                title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce médicament ?');">
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
<!-- MODAL AJOUT PRODUIT (avec sélection catégorie) -->
<div class="modal fade" id="modalVoirCategories" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Categories disponibles</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($categories)): ?>
                    <p class="mb-0 text-muted">Aucune categorie disponible pour le moment.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Categorie</th>
                                    <th>Forme</th>
                                    <th>Dosage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categories as $cat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cat['nom_categorie']) ?></td>
                                        <td><?= htmlspecialchars($cat['forme']) ?></td>
                                        <td><?= htmlspecialchars($cat['dosage']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-target="#modalAjouterCategorie" data-bs-toggle="modal" data-bs-dismiss="modal">
                    Creer une nouvelle categorie
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProduit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5>Ajouter un nouveau produit</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- SÉLECTION CATÉGORIE -->
                <div class="mb-3">
                    <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                    <select name="id_categorie" class="form-select" required>
                        <option value="">Choisir une catégorie...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id_categorie'] ?>">
                                <?= htmlspecialchars($cat['nom_categorie']) ?> - <?= htmlspecialchars($cat['forme']) ?> <?= $cat['dosage'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- BOUTON AJOUTER CATÉGORIE (à gauche du bouton Enregistrer) -->
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjouterCategorie">
                        <i class="bi bi-plus-circle"></i> Nouvelle catégorie
                    </button>
                </div>

                <input type="text" name="nom" class="form-control mb-2" placeholder="Nom du médicament (ex: Doliprane)" required>
                <input type="number" step="0.01" name="prix_unitaire" class="form-control mb-2" placeholder="Prix unitaire (FCFA)" min="0">
                <input type="number" step="0.01" name="marge_pourcentage" class="form-control mb-2" placeholder="Marge %" value="20" min="0" max="100">
                <input type="number" name="seuil_alerte" class="form-control" placeholder="Seuil d'alerte (ex: 30)" min="0">
            </div>
            <div class="modal-footer">
                <button type="submit" name="btn_ajouter_produit" class="btn btn-primary">Enregistrer le produit</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL AJOUT CATÉGORIE (petite, juste à gauche du bouton) -->
<div class="modal fade" id="modalAjouterCategorie" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h6>Nouvelle catégorie</h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if(isset($message_categorie)): echo $message_categorie; endif; ?>
                <div class="mb-2">
                    <input type="text" name="nom_categorie" class="form-control form-control-sm" placeholder="Nom (ex: Analgésique)" required>
                </div>
                <div class="mb-2">
                    <input type="text" name="forme" class="form-control form-control-sm" placeholder="Forme (Comprimé, Sirop...)" required>
                </div>
                <div class="mb-3">
                    <input type="text" name="dosage" class="form-control form-control-sm" placeholder="Dosage (500mg)" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="btn_ajouter_categorie" class="btn btn-primary btn-sm">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFIER PRODUIT (mise à jour avec catégorie) -->
<div class="modal fade" id="modalEditProduit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5>Modifier produit</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_produit" id="edit_id_produit">
                <input type="text" name="nom_medicament" id="edit_nom" class="form-control mb-2" required>
                <input type="number" step="0.01" name="prix_unitaire" id="edit_prix" class="form-control mb-2" min="0">
                <input type="number" step="0.01" name="marge_pourcentage" id="edit_marge" class="form-control mb-2" min="0" max="100">
                <input type="number" name="seuil_alerte" id="edit_seuil" class="form-control" min="0">
            </div>
            <div class="modal-footer">
                <button type="submit" name="btn_update_produit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript pour les modals (mis à jour) -->
<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal edit produit
    var modalEdit = document.getElementById('modalEditProduit');
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit_id_produit').value = button.getAttribute('data-id');
            document.getElementById('edit_nom').value = button.getAttribute('data-nom');
            document.getElementById('edit_prix').value = button.getAttribute('data-prix');
            document.getElementById('edit_marge').value = button.getAttribute('data-marge');
            document.getElementById('edit_seuil').value = button.getAttribute('data-seuil');
        });
    }
});
</script>
<?php endif; ?>

<!-- Script AJAX recherche (inchangé) -->
<script>
// ... (ton script AJAX existant reste identique)
</script>

<?php include '../includes/footer.php'; ?>

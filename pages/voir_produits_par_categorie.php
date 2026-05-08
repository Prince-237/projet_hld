<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$isAdmin = ($_SESSION['role'] === 'admin');

// Récupérer l'ID de la catégorie
$id_categorie = isset($_GET['id_categorie']) ? (int)$_GET['id_categorie'] : 0;

if (!$id_categorie) {
    header("Location: liste_categories.php");
    exit();
}

// Récupérer les infos de la catégorie
$catStmt = $pdo->prepare("SELECT * FROM ProductCategory WHERE id_categorie = ?");
$catStmt->execute([$id_categorie]);
$categorie = $catStmt->fetch(PDO::FETCH_ASSOC);

if (!$categorie) {
    header("Location: liste_categories.php");
    exit();
}

// Filtres
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Medicament', 'Laboratoire']) ? $_GET['type'] : '';
$provenanceFilter = isset($_GET['provenance']) && in_array($_GET['provenance'], ['Achat', 'Don']) ? $_GET['provenance'] : '';

// Requête pour récupérer les produits de cette catégorie avec calcul du stock par type d'approvisionnement
$sql_base = "SELECT p.*, c.nom_categorie, c.forme, c.dosage,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Fournisseur'), 0) AS stock_achat,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Don'), 0) AS stock_don
             FROM Produit p 
             JOIN ProductCategory c ON p.id_categorie = c.id_categorie
             WHERE p.id_categorie = ?";

$params = [$id_categorie];

if ($typeFilter) {
    $sql_base .= " AND p.type_produit = ?";
    $params[] = $typeFilter;
}

if ($search !== '') {
    $sql_base .= " AND p.nom_medicament LIKE ?";
    $params[] = '%' . $search . '%';
}

$sql_base .= " ORDER BY p.nom_medicament ASC";

$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrer par provenance si nécessaire (après la requête pour avoir les deux stocks)
if ($provenanceFilter) {
    $produits = array_filter($produits, function($p) use ($provenanceFilter) {
        if ($provenanceFilter === 'Achat') {
            return (int)$p['stock_achat'] > 0;
        } elseif ($provenanceFilter === 'Don') {
            return (int)$p['stock_don'] > 0;
        }
        return true;
    });
}

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Produits de la catégorie</h2>
            <p class="text-muted mb-0">
                <strong><?= htmlspecialchars($categorie['nom_categorie']) ?></strong> 
                (<?= htmlspecialchars($categorie['forme']) ?> - <?= htmlspecialchars($categorie['dosage']) ?>)
            </p>
        </div>
        <a href="liste_categories.php" class="btn btn-outline-secondary">
            Retour
        </a>
    </div>

    <!-- FILTRES LIVE -->
    <form method="GET" id="filterForm" class="mb-3">
        <input type="hidden" name="id_categorie" value="<?= $id_categorie ?>">
        
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="searchInput" class="form-label">Rechercher</label>
                <input type="search" id="searchInput" name="q" class="form-control" placeholder="Nom du produit..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label for="typeFilter" class="form-label">Type de produit</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="">Tous les types</option>
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="provenanceFilter" class="form-label">Provenance</label>
                <select id="provenanceFilter" name="provenance" class="form-select">
                    <option value="">Toutes les provenances</option>
                    <option value="Achat" <?= $provenanceFilter === 'Achat' ? 'selected' : '' ?>>Achat (Fournisseur)</option>
                    <option value="Don" <?= $provenanceFilter === 'Don' ? 'selected' : '' ?>>Don (Donateur)</option>
                </select>
            </div>
            <!-- <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Filtrer
                </button>
            </div> -->
        </div>
    </form>

    <!-- TABLEAU DES PRODUITS -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Nom du produit</th>
                    <th>Type</th>
                    <th class="text-center">Prix Réf.</th>
                    <th class="text-center">Marge %</th>
                    <th class="text-center">Seuil</th>
                    <th class="text-center">Stock Achat</th>
                    <th class="text-center">Stock Don</th>
                    <th class="text-center">Stock Total</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($produits)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Aucun produit trouvé pour cette catégorie
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($produits as $p): 
                        $stock_achat = (int)$p['stock_achat'];
                        $stock_don = (int)$p['stock_don'];
                        $stock_total = $stock_achat + $stock_don;
                        $seuil = isset($p['seuil_alerte']) ? (int)$p['seuil_alerte'] : 0;
                        $rowClass = $stock_total === 0 ? 'table-danger text-white' : ($stock_total <= $seuil ? 'table-warning text-dark' : '');
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                            <td>
                                <?php if($p['type_produit'] === 'Medicament'): ?>
                                    <span class="badge bg-success">Pharmacie</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Laboratoire</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= number_format($p['prix_unitaire'] ?? 0, 2) ?> F</td>
                            <td class="text-center"><?= $p['marge_pourcentage'] ?>%</td>
                            <td class="text-center"><?= $seuil ?></td>
                            <td class="text-center"><?= $stock_achat ?></td>
                            <td class="text-center"><?= $stock_don ?></td>
                            <td class="text-center fw-bold"><?= $stock_total ?></td>
                            <td class="text-center">
                                <a href="details_produit.php?id=<?= $p['id_produit'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- <div class="mt-3 mb-5 text-muted small">
        Affichage de <span class="fw-bold"><?= count($produits) ?></span> produit(s)
    </div> -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live filter sur les champs de recherche
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            document.getElementById('filterForm').submit();
        }, 500);
    });

    // Auto-submit sur les selects
    document.getElementById('typeFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
    document.getElementById('provenanceFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>
</body>
</html>
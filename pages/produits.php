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
    $prix_unitaire = !empty($_POST['prix_unitaire']) ? (float)$_POST['prix_unitaire'] : null;
    $marge = !empty($_POST['marge_pourcentage']) ? (float)$_POST['marge_pourcentage'] : 20;
    $seuil = !empty($_POST['seuil_alerte']) ? (int)$_POST['seuil_alerte'] : 0;

    $stmt = $pdo->prepare("INSERT INTO produits (nom_medicament, forme, dosage, prix_unitaire, marge_pourcentage, seuil_alerte, stock_total) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$nom, $forme, $dosage, $prix_unitaire, $marge, $seuil]);
    header("Location: produits.php");
    exit();
}

if ($isAdmin && isset($_POST['btn_update_produit'])) {
    $id = (int)$_POST['id_produit'];
    $nom = htmlspecialchars($_POST['nom_medicament']);
    $forme = htmlspecialchars($_POST['forme']);
    $dosage = htmlspecialchars($_POST['dosage']);
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $marge = (float)$_POST['marge_pourcentage'];
    $seuil = (int)$_POST['seuil_alerte'];

    $stmt = $pdo->prepare("UPDATE produits SET nom_medicament = ?, forme = ?, dosage = ?, prix_unitaire = ?, marge_pourcentage = ?, seuil_alerte = ? WHERE id_produit = ?");
    $stmt->execute([$nom, $forme, $dosage, $prix_unitaire, $marge, $seuil, $id]);

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

$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
}

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE nom_medicament LIKE ? ORDER BY nom_medicament ASC");
    $stmt->execute(['%'. $search .'%']);
    $produits = $stmt->fetchAll();
} else {
    $produits = $pdo->query("SELECT * FROM produits ORDER BY nom_medicament ASC")->fetchAll();
}
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

    <form method="GET" id="searchForm" class="mb-3 d-flex" role="search">
        <input id="searchInput" type="search" name="q" class="form-control me-2" placeholder="Rechercher un medicament..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-primary me-2" type="submit">Rechercher</button>
        <a href="produits.php" class="btn btn-outline-secondary">Réinitialiser</a>
    </form>

    <table class="table table-bordered bg-white shadow-sm">
        <thead class="table-light">
            <tr>
                <th>Nom</th>
                <th>Forme</th>
                <th>Dosage</th>
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
                $rowClass = '';
                if ($stock === 0) {
                    $rowClass = 'table-danger text-white';
                } elseif ($stock <= $seuil) {
                    $rowClass = 'table-warning';
                }
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= $p['nom_medicament'] ?></td>
                    <td><?= $p['forme'] ?></td>
                    <td><?= $p['dosage'] ?></td>
                    <td><?= $p['prix_unitaire'] ?></td>
                    <td><?= $p['marge_pourcentage'] ?>%</td>
                    <td><span class="badge bg-secondary"><?= $seuil ?></span></td>
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
                                data-prix="<?= $p['prix_unitaire'] ?>"
                                data-marge="<?= $p['marge_pourcentage'] ?>"
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
          
          <input type="number" step="0.01" name="prix_unitaire" class="form-control mb-2" placeholder="Prix d'achat">
          <input type="number" step="0.01" name="marge_pourcentage" class="form-control mb-2" placeholder="Profit Margin %" value="20">
          <input type="number" name="seuil_alerte" class="form-control" placeholder="Seuil d'alerte par défaut">
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
          <input type="number" step="0.01" name="prix_unitaire" id="edit_prix" class="form-control mb-2" placeholder="Prix d'achat par défaut">
          <input type="number" step="0.01" name="marge_pourcentage" id="edit_marge" class="form-control mb-2" placeholder="Marge %" required>
          <input type="number" name="seuil_alerte" id="edit_seuil" class="form-control" placeholder="Seuil d'alerte">
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
        document.getElementById('edit_marge').value = button.getAttribute('data-marge');
        document.getElementById('edit_seuil').value = button.getAttribute('data-seuil');
    });
});
</script>
<?php endif; ?>

<script>
// Live AJAX search (debounced) - updates table body with results from produits_search.php
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('searchInput');
    var form = document.getElementById('searchForm');
    var tbody = document.querySelector('table tbody');
    if (!input || !tbody || !form) return;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&"'<>]/g, function (s) {
            return ({'&':'&amp;','"':'&quot;','\'":"&#39;","<":"&lt;",">":"&gt;"})[s];
        });
    },

    // Correct escape map (avoid syntax issue)
    function esc(s){
        if (s===null||s===undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    },

    var timer = null;
    function doSearch(q) {
        var url = 'produits_search.php?q=' + encodeURIComponent(q || '');
        fetch(url, {credentials: 'same-origin'})
            .then(function(res){ return res.json(); })
            .then(function(data){
                // build rows
                var html = '';
                data.forEach(function(p){
                    var stock = parseInt(p.stock_total) || 0;
                    var seuil = parseInt(p.seuil_alerte) || 0;
                    var prix = parseFloat(p.prix_unitaire) || 0;
                    var marge = parseFloat(p.marge_pourcentage) || 0;
                    var rowClass = '';
                    if (stock === 0) rowClass = 'table-danger text-white';
                    else if (stock <= seuil) rowClass = 'table-warning';

                    html += '<tr class="'+ rowClass +'">';
                    html += '<td>'+ esc(p.nom_medicament) +'</td>';
                    html += '<td>'+ esc(p.forme) +'</td>';
                    html += '<td>'+ esc(p.dosage) +'</td>';
                    html += '<td>'+ esc(prix.toFixed(2)) +'</td>';
                    html += '<td>'+ esc(marge) +'%</td>';
                    html += '<td><span class="badge bg-secondary">'+ esc(seuil) +'</span></td>';
                    html += '<td><span class="badge bg-info text-dark">'+ esc(p.stock_total) +'</span></td>';
                    html += '<td class="text-nowrap">';
                    // Actions (only show admin buttons if present in page) - we include them, server will enforce permissions on submit
                    html += '<button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditProduit" '
                        + 'data-id="'+ esc(p.id_produit) +'" '
                        + 'data-nom="'+ esc(p.nom_medicament) +'" '
                        + 'data-forme="'+ esc(p.forme) +'" '
                        + 'data-dosage="'+ esc(p.dosage) +'" '
                        + 'data-prix="'+ esc(prix.toFixed(2)) +'" '
                        + 'data-marge="'+ esc(marge) +'" '
                        + 'data-seuil="'+ esc(p.seuil_alerte) +'" title="Modifier">'
                        + '<i class="bi bi-pencil"></i></button>';

                    html += '<form method="POST" class="d-inline" onsubmit="return confirm(\'Supprimer ce medicament ?\');">'
                        + '<input type="hidden" name="id_produit" value="'+ esc(p.id_produit) +'">'
                        + '<button type="submit" name="btn_delete_produit" class="btn btn-sm btn-outline-danger" title="Supprimer">'
                        + '<i class="bi bi-trash"></i></button></form>';

                    html += '</td></tr>';
                });
                tbody.innerHTML = html;
            }).catch(function(err){
                console.error('Search error', err);
            });
    }

    input.addEventListener('input', function(e){
        clearTimeout(timer);
        timer = setTimeout(function(){ doSearch(input.value.trim()); }, 300);
    });

    // Prevent form full submit (we handle via AJAX) but allow fallback if JS disabled
    form.addEventListener('submit', function(e){ e.preventDefault(); doSearch(input.value.trim()); });

    // Initialize live search if there is a prefilled value
    if (input.value && input.value.trim() !== '') {
        doSearch(input.value.trim());
    }
});
</script>

<?php include '../includes/footer.php'; ?>

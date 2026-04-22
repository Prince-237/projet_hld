<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}
$isAdmin = ($_SESSION['role'] === 'admin');

// Récupérer toutes les catégories pour les listes déroulantes
$categories = $pdo->query("SELECT * FROM ProductCategory ORDER BY nom_categorie ASC, forme ASC")->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// AJOUTER UNE CATÉGORIE (nouveau)
// ======================================
if ($isAdmin && isset($_POST['btn_ajouter_categorie'])) {
    $nom_categorie = htmlspecialchars(trim($_POST['nom_categorie']));
    $forme = htmlspecialchars(trim($_POST['forme']));
    $dosage = htmlspecialchars(trim($_POST['dosage']));
    
    $stmt = $pdo->prepare("INSERT INTO ProductCategory (nom_categorie, forme, dosage) VALUES (?, ?, ?)");
    if ($stmt->execute([$nom_categorie, $forme, $dosage])) {
        $message_categorie = "<div class='alert alert-success'>Catégorie ajoutée avec succès !</div>";
        // Recharger les catégories
        $categories = $pdo->query("SELECT * FROM ProductCategory ORDER BY nom_categorie ASC, forme ASC")->fetchAll(PDO::FETCH_ASSOC);
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
    $type_produit = htmlspecialchars(trim($_POST['type_produit']));
    $prix_unitaire = !empty($_POST['prix_unitaire']) ? (float)$_POST['prix_unitaire'] : null;
    $marge = !empty($_POST['marge_pourcentage']) ? (float)$_POST['marge_pourcentage'] : 20;
    $seuil = !empty($_POST['seuil_alerte']) ? (int)$_POST['seuil_alerte'] : 0;

    // VÉRIFICATION CATÉGORIE (comme dans le diagramme de séquence)
    $checkCategorie = $pdo->prepare("SELECT id_categorie FROM ProductCategory WHERE id_categorie = ?");
    $checkCategorie->execute([$id_categorie]);
    
    if ($checkCategorie->fetch()) {
        // Catégorie OK → INSERT PRODUIT (On ne gère plus stock_total ici)
        $stmt = $pdo->prepare("INSERT INTO Produit (id_categorie, nom_medicament, type_produit, prix_unitaire, marge_pourcentage, seuil_alerte) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$id_categorie, $nom, $type_produit, $prix_unitaire, $marge, $seuil])) {
            $new_product_id = $pdo->lastInsertId();
            $new_product_name = $nom;
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

    $stmt = $pdo->prepare("UPDATE Produit SET nom_medicament = ?, prix_unitaire = ?, marge_pourcentage = ?, seuil_alerte = ? WHERE id_produit = ?");
    $stmt->execute([$nom, $prix_unitaire, $marge, $seuil, $id]);
    $message = "<div class='alert alert-success'>Produit modifié avec succès.</div>";
}

if ($isAdmin && isset($_POST['btn_delete_produit'])) {
    $id = (int)$_POST['id_produit'];
    $checkLots = $pdo->prepare("SELECT COUNT(*) FROM StockLot WHERE id_produit = ?");
    $checkLots->execute([$id]);
    $hasLots = $checkLots->fetchColumn() > 0;

    if ($hasLots) {
        $message = "<div class='alert alert-danger'>Suppression impossible : des lots existent pour ce médicament.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM Produit WHERE id_produit = ?");
            $stmt->execute([$id]);
            $message = "<div class='alert alert-success'>Produit supprimé.</div>";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $message = "<div class='alert alert-warning'>Impossible de supprimer ce produit : il est lié à des historiques (inventaires, entrées, sorties...).</div>";
            } else {
                $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Recherche et filtre type produit (Pharmacie/Laboratoire)
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Medicament', 'Laboratoire']) ? $_GET['type'] : 'Medicament';

// Requête avec calcul dynamique du stock via sous-requête
// On regarde le type de partenaire dans la commande liée au lot pour distinguer Achat/Don
$sql_base = "SELECT p.*, c.nom_categorie, c.forme, c.dosage,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Fournisseur'), 0) AS stock_achat,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Don'), 0) AS stock_don
             FROM Produit p 
             JOIN ProductCategory c ON p.id_categorie = c.id_categorie";

$whereClauses = ['p.type_produit = ?'];
$params = [$typeFilter];

if ($search !== '') {
    $whereClauses[] = 'p.nom_medicament LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
$stmt = $pdo->prepare("$sql_base$whereSql ORDER BY p.nom_medicament ASC");
$stmt->execute($params);
$produits = $stmt->fetchAll();

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

    <!-- FORMULAIRE RECHERCHE + FILTRE TYPE sous la barre -->
    <form method="GET" id="searchForm" class="mb-3" role="search">
        <div class="d-flex gap-2 mb-2">
            <input id="searchInput" type="search" name="q" class="form-control" placeholder="Rechercher un produit par son nom ou sa catégorie" value="<?= htmlspecialchars($search) ?>">            
        </div>
        <div>
            <label for="typeFilter" class="form-label">Trier par type</label>
            <div class="d-flex gap-2">
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
        </div>
    </form>

    <!-- TABLEAU (inchangé) -->
    <table class="table table-bordered shadow-sm">
        <thead class="table-light table-hover">
            <tr>
                <th>Nom</th>
                <th>Catégorie</th>
                <th>Prix Réf.</th>
                <th>Profit Marge %</th>
                <th class="text-center">Seuil</th>
                <th class="text-center">Stock Achat</th>
                <th class="text-center">Stock Don</th>
                <th class="text-center">Stock Total</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="tableProduitsBody">
            <?php foreach($produits as $p): 
                $stock_achat = (int)$p['stock_achat'];
                $stock_don = (int)$p['stock_don'];
                $stock_total = $stock_achat + $stock_don;
                $seuil = isset($p['seuil_alerte']) ? (int)$p['seuil_alerte'] : 0;
                $rowClass = $stock_total === 0 ? 'table-danger text-white' : ($stock_total <= $seuil ? 'table-warning text-dark' : '');
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                    <td><?= htmlspecialchars($p['nom_categorie']) ?> (<?= htmlspecialchars($p['forme']) ?>)</td>
                    <td><?= number_format($p['prix_unitaire'] ?? 0, 2) ?> FCFA</td>
                    <td class="text-center"><?= $p['marge_pourcentage'] ?>%</td>
                    <td class="text-center"><?= $seuil ?></td>
                    <td class="text-center"><?= $stock_achat ?></td>
                    <td class="text-center"><?= $stock_don ?></td>
                    <td class="text-center fw-bold"><?= $stock_total ?></td>
                    <td class="text-nowrap">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="details_produit.php?id=<?= $p['id_produit'] ?>"><i class="bi bi-eye me-2"></i>Voir</a></li>
                                <?php if($isAdmin): ?>
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditProduit"
                                        data-id="<?= $p['id_produit'] ?>"
                                        data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>"
                                        data-prix="<?= $p['prix_unitaire'] ?>"
                                        data-marge="<?= $p['marge_pourcentage'] ?>"
                                        data-seuil="<?= $p['seuil_alerte'] ?>"
                                        data-categorie="<?= $p['id_categorie'] ?>">
                                        <i class="bi bi-pencil me-2"></i>Modifier
                                    </button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="m-0" onsubmit="return confirm('Supprimer ce médicament ?');">
                                            <input type="hidden" name="id_produit" value="<?= $p['id_produit'] ?>">
                                            <button type="submit" name="btn_delete_produit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Supprimer
                                            </button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Système de sélection de quantité -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-5 p-2 bg-white border rounded shadow-sm">
        <div class="text-muted small">
            Affichage de <span id="countDisplay" class="fw-bold"><?= count($produits) ?></span> produit(s)
        </div>
        <div class="d-flex align-items-center gap-2">
            <label for="limitSelector" class="form-label mb-0 small fw-bold">Afficher :</label>
            <select id="limitSelector" class="form-select form-select-sm" style="width: auto;">
                <option value="25">25</option>
                <option value="50">50</option>                
                <option value="all">Tout</option>
            </select>
        </div>
    </div>
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


                <!-- TYPE PRODUIT -->
                <div class="mb-3">
                    <label class="form-label">Type de produit <span class="text-danger">*</span></label>
                    <select name="type_produit" class="form-select" required>
                        <option value="">Choisir le type...</option>
                        <option value="Medicament">Pharmacie</option>
                        <option value="Laboratoire">Laboratoire</option>
                    </select>
                </div>

                

                <input type="text" name="nom" class="form-control mb-2" placeholder="Nom du médicament (ex: Doliprane)" required>
                <input type="number" step="0.01" name="prix_unitaire" class="form-control mb-2" placeholder="Prix unitaire (FCFA)" min="0">
                <input type="number" step="0.01" name="marge_pourcentage" class="form-control mb-2" placeholder="Marge %" value="20" min="0" max="50">
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
                <input type="number" step="0.01" name="marge_pourcentage" id="edit_marge" class="form-control mb-2" min="0" max="50">
                <input type="number" name="seuil_alerte" id="edit_seuil" class="form-control" min="0">
            </div>
            <div class="modal-footer">
                <button type="submit" name="btn_update_produit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

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
                            <li><h6 class="dropdown-header">Type de réception</h6></li>
                            <li><a class="dropdown-item py-2" href="entrees.php?id_p=<?= $new_product_id ?? '' ?>">Réception Fournisseur (Achat)</a></li>
                            <li><a class="dropdown-item py-2" href="dons.php?id_p=<?= $new_product_id ?? '' ?>">Réception de Don</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
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

    // Affichage automatique du modal de redirection si un produit vient d'être créé
    <?php if(isset($new_product_id)): ?>
    var modalPostAjout = new bootstrap.Modal(document.getElementById('modalPostAjout'), {
        backdrop: 'static',
        keyboard: false
    });
    modalPostAjout.show();
    <?php endif; ?>
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const tableBody = document.getElementById('tableProduitsBody');
    const limitSelector = document.getElementById('limitSelector');
    const countDisplay = document.getElementById('countDisplay');
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    function fetchProduits() {
        const query = searchInput.value;
        const type = typeFilter.value;
        const limit = limitSelector.value;

        fetch(`produits_search.php?q=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                countDisplay.textContent = data.length;
                renderTable(data);
            })
            .catch(error => console.error('Erreur:', error));
    }

    function renderTable(produits) {
        tableBody.innerHTML = '';
        
        if (produits.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Aucun produit trouvé.</td></tr>';
            return;
        }

        produits.forEach(p => {
            const stock_achat = parseInt(p.stock_achat) || 0;
            const stock_don = parseInt(p.stock_don) || 0;
            const stock_total = stock_achat + stock_don;
            const seuil = parseInt(p.seuil_alerte) || 0;
            
            let rowClass = '';
            if (stock_total === 0) rowClass = 'table-danger text-white';
            else if (stock_total <= seuil) rowClass = 'table-warning text-dark';

            const tr = document.createElement('tr');
            if (rowClass) tr.className = rowClass;

            let actionHtml = '-';
            if (isAdmin) {
                actionHtml = `
                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditProduit"
                        data-id="${p.id_produit}"
                        data-nom="${p.nom_medicament}"
                        data-prix="${p.prix_unitaire}"
                        data-marge="${p.marge_pourcentage}"
                        data-seuil="${p.seuil_alerte}"
                        data-categorie="${p.id_categorie}"
                        title="Modifier">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce médicament ?');">
                        <input type="hidden" name="id_produit" value="${p.id_produit}">
                        <button type="submit" name="btn_delete_produit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                `;
            }

            tr.innerHTML = `
                <td>${p.nom_medicament}</td>
                <td>${p.nom_categorie} (${p.forme})</td>
                <td>${parseFloat(p.prix_unitaire || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2})} FCFA</td>
                <td class="text-center">${p.marge_pourcentage}%</td>
                <td class="text-center">${seuil}</td>
                <td class="text-center">${stock_achat}</td>
                <td class="text-center">${stock_don}</td>
                <td class="text-center fw-bold">${stock_total}</td>
                <td class="text-nowrap">${actionHtml}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    // Debounce pour éviter trop de requêtes
    let timeout = null;
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(fetchProduits, 300);
    });

    // Le changement de filtre recharge aussi en live
    typeFilter.addEventListener('change', fetchProduits);
    
    // Le changement de limite recharge aussi en live
    limitSelector.addEventListener('change', fetchProduits);
});
</script>

<?php include '../includes/footer.php'; ?>

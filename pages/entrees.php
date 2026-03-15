<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "<div class='alert alert-success'>Entrée en stock enregistrée avec succès !</div>";
}

// --- ACTION : ENREGISTRER UN NOUVEAU LOT ---
if ($isAdmin && isset($_POST['btn_lot'])) {
    $id_p = $_POST['id_p'];
    $id_f = $_POST['id_f'];
    $qte = $_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $source = $_POST['source_provenance'];

    // Sécurité serveur : Si Don, le prix est forcé à 0
    $prix_achat_ttc = ($source === 'Don') ? 0 : (isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0);
    $marge = ($source === 'Don') ? 0 : (isset($_POST['marge_pourcentage']) && $_POST['marge_pourcentage'] !== '' ? floatval($_POST['marge_pourcentage']) : null);

    try {
        $pdo->beginTransaction();

        // Insertion du lot avec la source
        $stmt = $pdo->prepare("INSERT INTO stock_lots (id_produit, id_fournisseur, num_lot, quantite_initiale, quantite_actuelle, date_expiration, prix_achat_ttc, id_user, source_provenance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_p, $id_f, $num_lot, $qte, $qte, $exp, $prix_achat_ttc, $_SESSION['user_id'], $source]);

        // Mise à jour du stock total du produit
        if ($marge !== null) {
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ?, marge_pourcentage = ? WHERE id_produit = ?");
            $stmt->execute([$qte, $marge, $id_p]);
        } else {
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
            $stmt->execute([$qte, $id_p]);
        }

        $pdo->commit();
        header("Location: entrees.php?success=1&type=" . urlencode($typeFilter));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION : SUPPRIMER UN LOT ---
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM sorties WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasSorties = $checkSorties->fetchColumn() > 0;

    if ($hasSorties) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce lot a des sorties associées.</div>";
    } else if ($lot) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
            $stmt->execute([$lot['quantite_actuelle'], $lot['id_produit']]);
            $stmt = $pdo->prepare("DELETE FROM stock_lots WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>Lot supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression.</div>";
        }
    }
}

// --- ACTION : MODIFIER UN LOT ---
if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $qte_new = (int)$_POST['qte'];
    // Si c'est un don (vérifié via le champ hidden ou la base), le prix reste 0, sinon on prend la valeur
    $prix_new = isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0;

    $stmt = $pdo->prepare("SELECT id_produit, quantite_initiale, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    if ($lot) {
        $diff = $qte_new - $lot['quantite_initiale'];
        // Vérifier que le stock actuel ne devient pas négatif
        if ($lot['quantite_actuelle'] + $diff < 0) {
            $message = "<div class='alert alert-danger'>Modification impossible : la quantité actuelle deviendrait négative.</div>";
        } else {
            try {
                $pdo->beginTransaction();
                $sql = "UPDATE stock_lots SET num_lot = ?, date_expiration = ?, quantite_initiale = ?, quantite_actuelle = quantite_actuelle + ?, prix_achat_ttc = ? WHERE id_lot = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$num_lot, $exp, $qte_new, $diff, $prix_new, $id_lot]);

                // Mise à jour du stock total produit
                $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
                $stmt->execute([$diff, $lot['id_produit']]);

                $pdo->commit();
                $message = "<div class='alert alert-success'>Lot modifié avec succès.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur lors de la modification : " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Filtre type (Pharmacie / Laboratoire), par défaut Pharmacie = Medicament

$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';

// Récupération des données pour l'affichage
$stmt = $pdo->prepare("SELECT l.*, p.nom_medicament, p.type_produit, p.marge_pourcentage, f.nom_societe, u.nom_complet AS utilisateur 
                        FROM stock_lots l 
                        JOIN produits p ON l.id_produit = p.id_produit 
                        JOIN fournisseurs f ON l.id_fournisseur = f.id_fournisseur 
                        LEFT JOIN utilisateurs u ON l.id_user = u.id_user 
                        WHERE p.type_produit = ?
                        ORDER BY l.id_lot DESC");
$stmt->execute([$typeProduit]);
$entrees = $stmt->fetchAll();

// Séparation des Achats et des Dons
$achats = array_filter($entrees, fn($e) => $e['source_provenance'] !== 'Don');
$dons = array_filter($entrees, fn($e) => $e['source_provenance'] === 'Don');

// récupérer produits en plaçant d'abord les médicaments (pharmacie) puis les articles laboratoire
$prods = $pdo->query("SELECT * FROM produits ORDER BY (type_produit='Laboratoire'), nom_medicament ASC")->fetchAll();
$fours = $pdo->query("SELECT * FROM fournisseurs ORDER BY est_donateur ASC, nom_societe ASC")->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Gestion des Entrées en Stock (Lots)</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Enregistrer une nouvelle réception</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="d-flex flex-wrap gap-3">
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Source</label>
                            <select name="source_provenance" id="select_source" class="form-select" onchange="verifierDon()" required>
                                <option value="Achat">Fournisseur</option>
                                <option value="Don">Don</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Produit</label>
                            <select name="id_p" id="select_produit_entree" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php
                                // séparer en deux groupes : pharmacie (Pharmacie) puis laboratoire
                                $pharma = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Medicament');
                                $labo   = array_filter($prods, fn($prod) => $prod['type_produit'] === 'Laboratoire');
                                ?>
                                <?php if (count($pharma)): ?>
                                    <optgroup label="Medicament">
                                        <?php foreach ($pharma as $p): ?>
                                            <option value="<?= $p['id_produit'] ?>" data-default-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                                <?= htmlspecialchars($p['nom_medicament']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (count($labo)): ?>
                                    <optgroup label="Laboratoire">
                                        <?php foreach ($labo as $p): ?>
                                            <option value="<?= $p['id_produit'] ?>" data-default-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                                <?= htmlspecialchars($p['nom_medicament']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fournisseur / Donateur</label>
                            <select name="id_f" id="select_fournisseur" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php
                                // separer en deux groupes : fournisseurs puis donateurs
                                $fournisseurs = array_filter($fours, fn($f) => (int)$f['est_donateur'] === 0);
                                $donateurs = array_filter($fours, fn($f) => (int)$f['est_donateur'] === 1);
                                ?>
                                <?php if (count($fournisseurs)): ?>
                                    <optgroup label="Fournisseurs" id="optgroup_fournisseurs">
                                        <?php foreach ($fournisseurs as $f): ?>
                                            <option value="<?= $f['id_fournisseur'] ?>"><?= htmlspecialchars($f['nom_societe']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if (count($donateurs)): ?>
                                    <optgroup label="Donateurs" id="optgroup_donateurs">
                                        <?php foreach ($donateurs as $f): ?>
                                            <option value="<?= $f['id_fournisseur'] ?>"><?= htmlspecialchars($f['nom_societe']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">N° Lot</label>
                            <input type="text" name="num_lot" class="form-control" placeholder="Ex: LOT-2026" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Date Expir.</label>
                            <input type="date" name="exp" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Quantité</label>
                            <input type="number" name="qte" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Prix Unit. (TTC)</label>
                            <input type="number" step="0.01" name="prix_achat_ttc" id="input_prix_achat" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Marge (%)</label>
                            <input type="number" step="0.01" name="marge_pourcentage" id="input_marge" class="form-control" placeholder="Ex: 20">
                        </div>
                    </div>

                    <!-- <br> -->
                    <div>
                        <div class="col-md-12 d-flex mt-4 align-items-end">
                            <button type="submit" name="btn_lot" class="btn btn-primary w-100 btn-lg">Valider l'Entrée</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>

    <?php endif; ?>

    <!-- FORMULAIRE RECHERCHE + FILTRE TYPE sous la barre -->
    <form method="GET" id="searchForm" class="mb-3" role="search">
        
        <div>
            <label for="typeFilter" class="form-label">Trier par type</label>
            <div class="d-flex gap-2">
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
                <button type="submit" class="btn btn-secondary">Appliquer</button>
            </div>
        </div>
    </form>

    <!-- TABLEAU DES ACHATS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><strong>Liste des Achats (Fournisseurs)</strong></div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>État</th>
                        <th>Désignation</th>
                        <th>Lot</th>
                        <th>Source</th>
                        <th>Qte Init.</th>
                        <th>Expiration</th>
                        <th>Prix Achat</th>
                        <th>Total</th>
                        <th>Agent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($achats as $e): ?>
                        <?php
                        $today = strtotime(date('Y-m-d'));
                        $exp_ts = strtotime($e['date_expiration']);
                        $isExpired = $exp_ts < $today;
                        $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);
                        if ($isExpired) {
                            $rowClass = 'table-dark text-white';
                            $statusBadge = '<span class="badge bg-white text-dark">Périmé</span>';
                        } elseif ($isCritical) {
                            $rowClass = 'table-warning';
                            $statusBadge = '<span class="badge bg-warning text-dark">Critique</span>';
                        } else {
                            $rowClass = '';
                            $statusBadge = '<span class="badge bg-success">Valide</span>';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="small"><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                            <td><?= $statusBadge ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['nom_medicament']) ?></strong>
                            </td>
                            <td><code class="text-dark"><?= htmlspecialchars($e['num_lot']) ?></code></td>
                            <td><?= htmlspecialchars($e['nom_societe']) ?></td>
                            <td><?= $e['quantite_initiale'] ?></td>
                            <td class="<?= $isExpired ? '' : '' ?>"><?= date('d/m/Y', strtotime($e['date_expiration'])) ?></td>
                            <td><?= number_format($e['prix_achat_ttc'], 0, '.', ' ') . ' F' ?></td>
                            <td><?= number_format($e['prix_achat_ttc'] * $e['quantite_initiale'], 0, '.', ' ') . ' F' ?></td>
                            <td class="<?php echo $isExpired ? 'small text-white' : 'small text-muted'; ?>"><?= htmlspecialchars($e['utilisateur']) ?></td>
                            <td class="text-nowrap">
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-sm btn-outline-primary me-1 border-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditLot"
                                        data-id="<?= $e['id_lot'] ?>"
                                        data-num="<?= htmlspecialchars($e['num_lot']) ?>"
                                        data-exp="<?= $e['date_expiration'] ?>"
                                        data-qte="<?= $e['quantite_initiale'] ?>"
                                        data-prix="<?= $e['prix_achat_ttc'] ?>"
                                        title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce lot ?');">
                                        <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                        <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TABLEAU DES DONS -->
    <div class="card shadow-sm">
        <div class="card-header"><strong>Liste des Dons</strong></div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>État</th>
                        <th>Désignation</th>
                        <th>Lot</th>
                        <th>Source</th>
                        <th>Qte Init.</th>
                        <th>Expiration</th>
                        <th>Prix Achat</th>
                        <th>Total</th>
                        <th>Agent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dons as $e): ?>
                        <?php
                        $today = strtotime(date('Y-m-d'));
                        $exp_ts = strtotime($e['date_expiration']);
                        $isExpired = $exp_ts < $today;
                        $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);
                        if ($isExpired) {
                            $rowClass = 'table-dark text-white';
                            $statusBadge = '<span class="badge bg-white text-dark">Périmé</span>';
                        } elseif ($isCritical) {
                            $rowClass = 'table-warning';
                            $statusBadge = '<span class="badge bg-warning text-dark">Critique</span>';
                        } else {
                            $rowClass = '';
                            $statusBadge = '<span class="badge bg-success">Valide</span>';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="small"><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                            <td><?= $statusBadge ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['nom_medicament']) ?></strong>
                            </td>
                            <td><code class="text-dark"><?= htmlspecialchars($e['num_lot']) ?></code></td>
                            <td><?= htmlspecialchars($e['nom_societe']) ?></td>
                            <td><?= $e['quantite_initiale'] ?></td>
                            <td class="<?= $isExpired ? '' : '' ?>"><?= date('d/m/Y', strtotime($e['date_expiration'])) ?></td>
                            <td><span class="">GRATUIT</span></td>
                            <td>0 F</td>
                            <td class="<?php echo $isExpired ? 'small text-white' : 'small text-muted'; ?>"><?= htmlspecialchars($e['utilisateur']) ?></td>
                            <td class="text-nowrap">
                                <?php if ($isAdmin): ?>
                                    <button class="btn btn-sm btn-outline-primary me-1 border-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditLot"
                                        data-id="<?= $e['id_lot'] ?>"
                                        data-num="<?= htmlspecialchars($e['num_lot']) ?>"
                                        data-exp="<?= $e['date_expiration'] ?>"
                                        data-qte="<?= $e['quantite_initiale'] ?>"
                                        data-prix="0"
                                        title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce lot ?');">
                                        <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                        <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL MODIFICATION LOT -->
<?php if ($isAdmin): ?>
    <div class="modal fade" id="modalEditLot" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Lot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_lot" id="edit_id_lot">

                    <label class="form-label fw-bold">Numéro de Lot</label>
                    <input type="text" name="num_lot" id="edit_num_lot" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Date d'Expiration</label>
                    <input type="date" name="exp" id="edit_exp" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Quantité Initiale</label>
                    <input type="number" name="qte" id="edit_qte" class="form-control mb-2" min="1" required>

                    <label class="form-label fw-bold">Prix Achat TTC</label>
                    <input type="number" step="0.01" name="prix_achat_ttc" id="edit_prix" class="form-control mb-2">
                </div>
                <div class="modal-footer"><button type="submit" name="btn_update_lot" class="btn btn-primary">Enregistrer les modifications</button></div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modalEdit = document.getElementById('modalEditLot');
            if (modalEdit) {
                modalEdit.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    document.getElementById('edit_id_lot').value = button.getAttribute('data-id');
                    document.getElementById('edit_num_lot').value = button.getAttribute('data-num');
                    document.getElementById('edit_exp').value = button.getAttribute('data-exp');
                    document.getElementById('edit_qte').value = button.getAttribute('data-qte');

                    var prix = button.getAttribute('data-prix');
                    var inputPrix = document.getElementById('edit_prix');
                    inputPrix.value = prix;

                    // Si le prix est 0 (Don), on empêche la modification du prix pour rester cohérent
                    if (parseFloat(prix) === 0) {
                        inputPrix.readOnly = true;
                        inputPrix.classList.add('bg-light');
                    } else {
                        inputPrix.readOnly = false;
                        inputPrix.classList.remove('bg-light');
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<script src="../assets/js/entrees.js"></script>
<?php include '../includes/footer.php'; ?>
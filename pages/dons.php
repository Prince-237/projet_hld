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
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';

$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

// --- ACTION : ENREGISTRER UN DON (DIRECT) ---
if ($isAdmin && isset($_POST['btn_save_don'])) {
    $id_p = $_POST['id_p'];
    $id_donateur = $_POST['id_donateur'];
    $qte = (int)$_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];

    if ($exp <= date('Y-m-d')) {
        $message = "<div class='alert alert-danger'>Erreur : Impossible d'enregistrer un don de produit déjà périmé ou expirant aujourd'hui.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Création implicite de la commande (Statut 'Reçue' direct)
            // Cela permet de garder une trace administrative homogène
            $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'Reçue')");
            $stmtCmd->execute([$id_donateur, $_SESSION['user_id']]);
            $id_commande = $pdo->lastInsertId();

            // 2. Création du détail commande
            $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
            $stmtDet->execute([$id_commande, $id_p, $qte]);
            $id_cmd_det = $pdo->lastInsertId();

            // 3. Création du StockLot (Prix Achat = 0 pour les dons)
            $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, 0)");
            $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte, $exp]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Don enregistré et stock mis à jour avec succès !</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION : ENREGISTRER UN DON GROUPE ---
if ($isAdmin && isset($_POST['btn_save_group'])) {
    $id_donateur = isset($_POST['id_donateur_group']) ? intval($_POST['id_donateur_group']) : 0;
    $prod_ids = $_POST['id_p_group'] ?? [];
    $quantites = $_POST['qte_group'] ?? [];
    $num_lots = $_POST['num_lot_group'] ?? [];
    $exps = $_POST['exp_group'] ?? [];

    if ($id_donateur <= 0 || empty($prod_ids) || count($prod_ids) !== count($quantites) || count($prod_ids) !== count($num_lots) || count($prod_ids) !== count($exps)) {
        $message = "<div class='alert alert-danger'>Erreur : Donateur et tous les lots doivent être renseignés correctement.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'Reçue')");
            $stmtCmd->execute([$id_donateur, $_SESSION['user_id']]);
            $id_commande = $pdo->lastInsertId();

            $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
            $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, 0)");

            for ($i = 0; $i < count($prod_ids); $i++) {
                $id_p = intval($prod_ids[$i]);
                $qte = intval($quantites[$i]);
                $num_lot = trim($num_lots[$i]);
                $exp = trim($exps[$i]);

                if ($id_p <= 0 || $qte <= 0 || empty($num_lot) || $exp <= date('Y-m-d')) {
                    throw new Exception('Chaque lot doit avoir un produit, une quantité positive, un numéro de lot et une date dexpiration valide.');
                }

                $stmtDet->execute([$id_commande, $id_p, $qte]);
                $id_cmd_det = $pdo->lastInsertId();
                $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte, $exp]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Réception de dons enregistrée avec succès !</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la réception : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION : SUPPRIMER UN LOT DE DON ---
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];

    // Vérifier s'il y a eu des mouvements (sorties) sur ce lot
    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM TransfertDetail WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasMvt = $checkSorties->fetchColumn() > 0;

    // Vérifier également si le lot apparaît dans un inventaire
    $checkInv = $pdo->prepare("SELECT COUNT(*) FROM InventaireDetail WHERE id_lot = ?");
    $checkInv->execute([$id_lot]);
    $hasInv = $checkInv->fetchColumn() > 0;

    if ($hasMvt || $hasInv) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce don est lié à des mouvements de stock ou à un inventaire.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            // Récupérer le id_cmd_det lié pour nettoyer la trace administrative
            $stmt = $pdo->prepare("SELECT id_cmd_det FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $id_cmd_det = $stmt->fetchColumn();

            // Supprimer le lot
            $pdo->prepare("DELETE FROM StockLot WHERE id_lot = ?")->execute([$id_lot]);

            // Nettoyer le détail de commande lié au don
            if ($id_cmd_det) {
                $pdo->prepare("DELETE FROM CommandeDetail WHERE id_cmd_det = ?")->execute([$id_cmd_det]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Don supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack(); // Annule toutes les opérations de la transaction en cas d'erreur
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression du don : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION : MODIFIER UN LOT DE DON ---
if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $qte_new = (int)$_POST['qte'];

    $stmt = $pdo->prepare("SELECT id_lot FROM StockLot WHERE id_lot = ?");
    $stmt->execute([$id_lot]);

    if ($stmt->fetch()) {
        if ($exp <= date('Y-m-d')) {
            $message = "<div class='alert alert-danger'>Erreur : La nouvelle date d'expiration est invalide.</div>";
        } else {
            try {
                // On met à jour le lot (Prix Achat reste 0 car c'est un don)
                $sql = "UPDATE StockLot SET num_lot = ?, date_expiration = ?, quantite_actuelle = ? WHERE id_lot = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$num_lot, $exp, $qte_new, $id_lot]);
                $message = "<div class='alert alert-success'>Informations mise a jouravec succès.</div>";
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}


// --- RÉCUPÉRATION HISTORIQUE DONS ---
// On récupère uniquement les entrées liées à des partenaires de type 'Don'
$sqlDons = "SELECT l.*, p.nom_medicament, p.type_produit, 
                   part.nom_entite, u.nom_complet AS utilisateur,
                   cmd.date_commande AS date_enregistrement,
                   cd.quantite_voulue AS quantite_initiale
            FROM StockLot l 
            JOIN Produit p ON l.id_produit = p.id_produit 
            LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user 
            WHERE part.type = 'Don' AND p.type_produit = ?";
$params = [$typeProduit];

if (!empty($fournisseurFilter)) {
    $sqlDons .= " AND part.id_partenaire = ?";
    $params[] = $fournisseurFilter;
}
if (!empty($agentFilter)) {
    $sqlDons .= " AND u.id_user = ?";
    $params[] = $agentFilter;
}
if (!empty($dateFilterStart)) {
    $sqlDons .= " AND DATE(cmd.date_commande) >= ?";
    $params[] = $dateFilterStart;
}
if (!empty($dateFilterEnd)) {
    $sqlDons .= " AND DATE(cmd.date_commande) <= ?";
    $params[] = $dateFilterEnd;
}

$sqlDons .= " ORDER BY l.id_lot DESC LIMIT 50";
$stmtDons = $pdo->prepare($sqlDons);
$stmtDons->execute($params);

// --- GESTION AJAX ---
if (isset($_GET['ajax'])) {
    $dons_ajax = $stmtDons->fetchAll();
    if (empty($dons_ajax)) {
        echo "<tr><td colspan='9' class='text-center text-muted'>Aucun don trouvé.</td></tr>";
    } else {
        foreach ($dons_ajax as $e) {
            $today = strtotime(date('Y-m-d'));
            $exp_ts = strtotime($e['date_expiration']);
            $isExpired = $exp_ts < $today;
            $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);

            $statusBadge = $isExpired ? '<span class="badge bg-danger">Périmé</span>' : ($isCritical ? '<span class="badge bg-warning text-dark">Critique</span>' : '<span class="badge bg-success">Valide</span>');
            $rowClass = $isExpired ? 'table-secondary' : '';
            $dateReg = date('d/m/Y H:i', strtotime($e['date_enregistrement']));
            $dateExp = date('d/m/Y', strtotime($e['date_expiration']));
            $expClass = $isExpired ? 'text-danger fw-bold' : '';

            echo "<tr class='$rowClass'>
                <td>$dateReg</td>
                <td>$statusBadge</td>
                <td>" . htmlspecialchars($e['nom_medicament']) . "</td>
                <td>" . htmlspecialchars($e['num_lot']) . "</td>
                <td>" . htmlspecialchars($e['nom_entite']) . "</td>
                <td>{$e['quantite_initiale']}</td>
                <td class='$expClass'>$dateExp</td>
                <td class='small text-muted'>" . htmlspecialchars($e['utilisateur']) . "</td>
                <td class='text-nowrap'>";
            if ($isAdmin) {
                echo "<button class='btn btn-sm btn-outline-primary me-1 border-0'
                        data-bs-toggle='modal' data-bs-target='#modalEditLot'
                        data-id='{$e['id_lot']}' data-num='" . htmlspecialchars($e['num_lot']) . "'
                        data-exp='{$e['date_expiration']}' data-qte='{$e['quantite_actuelle']}' title='Modifier'>
                        <i class='bi bi-pencil'></i>
                    </button>
                    <form method='POST' class='d-inline' onsubmit='return confirm(\"Voulez-vous vraiment supprimer ce don ?\");'>
                        <input type='hidden' name='id_lot' value='{$e['id_lot']}'>
                        <button type='submit' name='btn_delete_lot' class='btn btn-sm btn-outline-danger border-0'>
                            <i class='bi bi-trash'></i>
                        </button>
                    </form>";
            }
            echo "</td></tr>";
        }
    }
    exit;
}

$dons = $stmtDons->fetchAll();

// Listes pour le formulaire
$prods = $pdo->query("SELECT * FROM Produit ORDER BY CASE WHEN type_produit = 'Medicament' THEN 1 ELSE 2 END, nom_medicament ASC")->fetchAll();
$donateurs = $pdo->query("SELECT * FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC")->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4 text-dark">Réception des Dons</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <!-- <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Enregistrer un nouveau don</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Donateur</label>
                        <select name="id_donateur" class="form-select" required>
                            <option value="">-- Choisir le donateur --</option>
                            <?php foreach ($donateurs as $d): ?>
                                <option value="<?= $d['id_partenaire'] ?>"><?= htmlspecialchars($d['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Produit Reçu</label>
                        <select name="id_p" class="form-select" required>
                            <option value="">-- Choisir le produit --</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id_produit'] ?>" <?= (isset($_GET['id_p']) && $_GET['id_p'] == $p['id_produit']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nom_medicament']) ?> (<?= $p['type_produit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Quantité Reçue</label>
                        <input type="number" name="qte" class="form-control" min="1" required>
                    </div>

                    <div class="col-md-2">
                        Espaceur
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Numéro de Lot</label>
                        <input type="text" name="num_lot" class="form-control" placeholder="Sur la boîte" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date Expiration</label>
                        <input type="date" name="exp" class="form-control" required>
                    </div>

                    <div class="col-md-12 d-flex mt-4 align-items-end">
                        <button type="submit" name="btn_save_don" class="btn btn-primary w-100 btn-lg">Valider la Réception de Don</button>
                    </div>

                </form>
            </div>
        </div> -->

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Réception de dons</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Donateur</label>
                        <select id="select_donateur_group" class="form-select">
                            <option value="">-- Choisir le donateur --</option>
                            <?php foreach ($donateurs as $d): ?>
                                <option value="<?= $d['id_partenaire'] ?>"><?= htmlspecialchars($d['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Produit</label>
                        <select id="select_produit_group" class="form-select">
                            <option value="">-- Choisir le produit --</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id_produit'] ?>" data-nom="<?= htmlspecialchars($p['nom_medicament']) ?>">
                                    <?= htmlspecialchars($p['nom_medicament']) ?> (<?= $p['type_produit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-bold">Quantité</label>
                        <input type="number" id="input_qte_group" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Numéro de lot</label>
                        <input type="text" id="input_num_lot_group" class="form-control" placeholder="Sur la boîte">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Date expiration</label>
                        <input type="date" id="input_exp_group" class="form-control">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="button" id="btn_add_don_item" class="btn btn-secondary w-100 mt-3">Ajouter</button>
                </div>
                <form method="POST" id="form_group_don" style="display:none;">
                    <input type="hidden" name="btn_save_group" value="1">
                    <input type="hidden" name="id_donateur_group" id="hidden_donateur_group" value="">
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Produit</th>
                                    <th>Lot</th>
                                    <th class="text-center">Qté</th>
                                    <th class="text-center">Expiration</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="don_group_body"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><strong>Articles ajoutés :</strong> <span id="group_items_count">0</span></div>
                        <div><strong>Total quantité :</strong> <span id="group_total_quantity">0</span></div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success btn-lg">Valider la réception</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- <form method="GET" id="searchForm" class="my-3" role="search">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select id="typeFilter" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Donateur</label>
                <select id="fournisseurFilter" name="fournisseur" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($fournisseurs as $fournisseur): ?>
                        <option value="<?= $fournisseur['id_partenaire'] ?>" <?= $fournisseurFilter === (string)$fournisseur['id_partenaire'] ? 'selected' : '' ?>><?= htmlspecialchars($fournisseur['nom_entite']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (du)</label>
                <input type="date" id="dateFilterStart" name="dateStart" value="<?= htmlspecialchars($dateFilterStart) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (au)</label>
                <input type="date" id="dateFilterEnd" name="dateEnd" value="<?= htmlspecialchars($dateFilterEnd) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Agent</label>
                <select id="agentFilter" name="agent" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>" <?= $agentFilter === (string)$agent['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form> -->



    <!-- HISTORIQUE DONS -->

<?php if ($isAdmin): ?>
    <!-- MODAL MODIFICATION DON -->
    <div class="modal fade" id="modalEditLot" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Don</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_lot" id="edit_id_lot">

                    <label class="form-label fw-bold">Numéro de Lot</label>
                    <input type="text" name="num_lot" id="edit_num_lot" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Date d'Expiration</label>
                    <input type="date" name="exp" id="edit_exp" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Quantité (Correction)</label>
                    <input type="number" name="qte" id="edit_qte" class="form-control mb-2" min="1" required>

                    <!-- <div class="alert alert-info small mt-2">
                        <i class="bi bi-info-circle"></i> Le prix d'achat reste fixé à 0 pour les dons.
                    </div> -->
                </div>
                <div class="modal-footer">
                    <button type="submit" name="btn_update_lot" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- 1. GESTION DES FILTRES AJAX ---
            const filters = ['typeFilter', 'fournisseurFilter', 'dateFilterStart', 'dateFilterEnd', 'agentFilter'];
            const tableBody = document.querySelector('.table-hover tbody');

            function updateDons() {
                const params = new URLSearchParams({
                    ajax: 1
                });
                filters.forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.value) {
                        // On enlève le suffixe 'Filter' pour correspondre aux clés attendues par le PHP
                        let key = id.replace('Filter', '');
                        params.append(key, el.value);
                    }
                });

                fetch('dons.php?' + params.toString())
                    .then(r => r.text())
                    .then(html => {
                        tableBody.innerHTML = html;
                    })
                    .catch(err => console.error('Erreur filtrage AJAX:', err));
            }

            filters.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', updateDons);
                    if (el.tagName === 'INPUT') el.addEventListener('input', updateDons);
                }
            });

            // --- 2. GESTION PANIER DONS (GROUPE) ---
            var selectDonateur = document.getElementById('select_donateur_group');
            var selectProduit = document.getElementById('select_produit_group');
            var inputQte = document.getElementById('input_qte_group');
            var inputNumLot = document.getElementById('input_num_lot_group');
            var inputExp = document.getElementById('input_exp_group');
            var btnAddItem = document.getElementById('btn_add_don_item');
            var itemsBody = document.getElementById('don_group_body');
            var formGroup = document.getElementById('form_group_don');
            var hiddenDonateur = document.getElementById('hidden_donateur_group');
            var itemsCount = document.getElementById('group_items_count');
            var totalQuantity = document.getElementById('group_total_quantity');

            if (btnAddItem && selectProduit && selectDonateur) {
                var items = [];

                function renderItems() {
                    itemsBody.innerHTML = '';
                    var quantityTotal = 0;

                    items.forEach(function(item, index) {
                        quantityTotal += item.qte;
                        var row = document.createElement('tr');
                        row.innerHTML = `
                        <td>${item.nom}<input type="hidden" name="id_p_group[]" value="${item.id_p}"></td>
                        <td>${item.num_lot}<input type="hidden" name="num_lot_group[]" value="${item.num_lot}"></td>
                        <td class="text-center">${item.qte}<input type="hidden" name="qte_group[]" value="${item.qte}"></td>
                        <td class="text-center">${item.exp}<input type="hidden" name="exp_group[]" value="${item.exp}"></td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeGroupItem(${index})">&times;</button></td>
                    `;
                        itemsBody.appendChild(row);
                    });

                    itemsCount.textContent = items.length;
                    totalQuantity.textContent = quantityTotal;
                    formGroup.style.display = items.length > 0 ? 'block' : 'none';
                    selectDonateur.disabled = items.length > 0;
                    if (items.length > 0) hiddenDonateur.value = selectDonateur.value;
                }

                window.removeGroupItem = function(index) {
                    items.splice(index, 1);
                    renderItems();
                };

                btnAddItem.addEventListener('click', function() {
                    var idDonateur = selectDonateur.value;
                    var idProduit = selectProduit.value;
                    var qte = parseInt(inputQte.value, 10);
                    var numLot = inputNumLot.value.trim();
                    var exp = inputExp.value;
                    var produitOption = selectProduit.options[selectProduit.selectedIndex];

                    if (!idDonateur || !idProduit || !qte || qte < 1 || numLot === '' || !exp) {
                        alert('Veuillez remplir tous les champs du lot.');
                        return;
                    }

                    // Vérifier si le produit avec le même lot et expiration existe déjà
                    var existingItem = items.find(item => 
                        item.id_p == idProduit && 
                        item.num_lot === numLot &&
                        item.exp === exp
                    );

                    if (existingItem) {
                        // Si le produit existe avec le même lot/exp, incrémenter la quantité
                        existingItem.qte += qte;
                    } else {
                        // Sinon, ajouter un nouvel item
                        items.push({
                            id_p: idProduit,
                            nom: produitOption.getAttribute('data-nom') || produitOption.text,
                            qte: qte,
                            num_lot: numLot,
                            exp: exp
                        });
                    }

                    selectProduit.value = '';
                    inputQte.value = 1;
                    inputNumLot.value = '';
                    inputExp.value = '';
                    renderItems();
                });
            }

            // --- 3. MODAL MODIFICATION LOT ---
            var modalEdit = document.getElementById('modalEditLot');
            if (modalEdit) {
                modalEdit.addEventListener('show.bs.modal', function(event) {
                    var btn = event.relatedTarget;
                    document.getElementById('edit_id_lot').value = btn.getAttribute('data-id');
                    document.getElementById('edit_num_lot').value = btn.getAttribute('data-num');
                    document.getElementById('edit_exp').value = btn.getAttribute('data-exp');
                    document.getElementById('edit_qte').value = btn.getAttribute('data-qte');
                });
            }
        });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
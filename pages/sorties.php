<?php
// 1. Inclusion des dependances
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin');

$message = "";
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $id_source = (int)$_POST['id_source'];
    $id_destination = (int)$_POST['id_destination'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    // Le prix est calculé pour affichage mais n'est pas stocké dans TransfertDetail (selon schéma)
    $id_user = $_SESSION['user_id'];

    if ($id_source === $id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : La source et la destination ne peuvent pas être identiques.</div>";
    } else {

        try {
            $pdo->beginTransaction();

            // Etape A : verifier si le stock disponible dans ce lot est suffisant
            // MODIFICATION : Utilisation des nouvelles tables StockLot et Produit
            $checkSql = "SELECT l.quantite_actuelle, l.id_produit, l.prix_achat_ttc, p.marge_pourcentage 
                     FROM StockLot l 
                     JOIN Produit p ON l.id_produit = p.id_produit 
                     WHERE l.id_lot = :id_lot FOR UPDATE";
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute([':id_lot' => $id_lot]);
            $lot = $stmtCheck->fetch();

            // Logique : Si la source est le Magasin Central (ID 1), on vérifie le stock physique
            $stock_suffisant = ($id_source !== 1) || ($lot && $lot['quantite_actuelle'] >= $quantite_demandee);

            if ($stock_suffisant) {

                // Etape B : Création du Transfert (En-tête)
                // Génération d'un numéro de bordereau unique basé sur la date
                $num_bordereau = 'TR-' . date('YmdHis') . '-' . rand(100, 999);

                $sqlTrans = "INSERT INTO Transfert (id_source, id_destination, id_user, num_bordereau) VALUES (?, ?, ?, ?)";
                $stmtTrans = $pdo->prepare($sqlTrans);
                $stmtTrans->execute([$id_source, $id_destination, $id_user, $num_bordereau]);
                $id_transfert = $pdo->lastInsertId();

                // Etape C : Création du Détail
                $sqlDet = "INSERT INTO TransfertDetail (id_transfert, id_lot, quantite_transfert) VALUES (?, ?, ?)";
                $stmtDet = $pdo->prepare($sqlDet);
                $stmtDet->execute([$id_transfert, $id_lot, $quantite_demandee]);

                // Etape D : mise a jour de la quantite restante
                // ON NE TOUCHE AU STOCK PHYSIQUE QUE SI CA SORT DU MAGASIN CENTRAL (ID 1)
                if ($id_source == 1) {
                    $sqlUpdate = "UPDATE StockLot SET quantite_actuelle = quantite_actuelle - :qte
                              WHERE id_lot = :id_lot";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute([
                        ':qte'    => $quantite_demandee,
                        ':id_lot' => $id_lot
                    ]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>Transfert enregistré avec succès (Bordereau : $num_bordereau).</div>";
            } else {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot.</div>";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
        }
    }
}

// 2b. Modification d'une sortie
if ($isAdmin && isset($_POST['btn_update_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];
    $new_id_lot = (int)$_POST['id_lot'];
    $new_id_source = (int)$_POST['id_source'];
    $new_id_destination = (int)$_POST['id_destination'];
    $new_qte = (int)$_POST['quantite_sortie'];

    if ($new_id_source === $new_id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : Source et destination identiques.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Récupérer l'ancien transfert pour restaurer le stock
            $stmtOld = $pdo->prepare("SELECT td.id_lot, td.quantite_transfert, t.id_source 
                                      FROM TransfertDetail td 
                                      JOIN Transfert t ON td.id_transfert = t.id_transfert 
                                      WHERE t.id_transfert = ?");
            $stmtOld->execute([$id_transfert]);
            $old = $stmtOld->fetch();

            if (!$old) throw new Exception("Transfert introuvable.");

            // Restaurer le stock (si source = 1)
            if ($old['id_source'] == 1) {
                $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?")
                    ->execute([$old['quantite_transfert'], $old['id_lot']]);
            }

            // 2. Vérifier et déduire le nouveau stock (si source = 1)
            if ($new_id_source == 1) {
                // On vérifie le stock disponible (qui inclut maintenant la quantité restaurée)
                $stmtCheck = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                $stmtCheck->execute([$new_id_lot]);
                $stockDispo = $stmtCheck->fetchColumn();

                if ($stockDispo < $new_qte) throw new Exception("Stock insuffisant pour la modification.");

                $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?")
                    ->execute([$new_qte, $new_id_lot]);
            }

            // 3. Mettre à jour les tables Transfert et TransfertDetail
            $pdo->prepare("UPDATE Transfert SET id_source = ?, id_destination = ? WHERE id_transfert = ?")
                ->execute([$new_id_source, $new_id_destination, $id_transfert]);

            $pdo->prepare("UPDATE TransfertDetail SET id_lot = ?, quantite_transfert = ? WHERE id_transfert = ?")
                ->execute([$new_id_lot, $new_qte, $id_transfert]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie modifiée avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur modification : " . $e->getMessage() . "</div>";
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];

    // Récupérer les infos pour restaurer le stock
    $stmt = $pdo->prepare("SELECT td.id_lot, td.quantite_transfert, t.id_source 
                           FROM TransfertDetail td 
                           JOIN Transfert t ON td.id_transfert = t.id_transfert 
                           WHERE t.id_transfert = ?");
    $stmt->execute([$id_transfert]);
    $rows = $stmt->fetchAll();

    if ($rows) {
        try {
            $pdo->beginTransaction();

            foreach ($rows as $row) {
                // Restaurer le stock SI la source était le magasin central (ID 1)
                if ($row['id_source'] == 1) {
                    $upd = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
                    $upd->execute([$row['quantite_transfert'], $row['id_lot']]);
                }
            }

            // Supprimer le transfert (Cascade sur TransfertDetail grâce au SQL)
            $del = $pdo->prepare("DELETE FROM Transfert WHERE id_transfert = ?");
            $del->execute([$id_transfert]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Transfert supprimé et stock restauré.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

$groupTransfers = [];
$groupTransfersFile = __DIR__ . '/../data/group_transfers.json';
if (file_exists($groupTransfersFile)) {
    $loaded = json_decode(file_get_contents($groupTransfersFile), true);
    if (is_array($loaded)) {
        $groupTransfers = array_reverse($loaded);
    }
}

// Récupération des points de vente
$pvs = $pdo->query("SELECT * FROM PointVente ORDER BY nom_point_vente ASC")->fetchAll();

$pvNames = [];
foreach ($pvs as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

$currentUserName = $_SESSION['nom'] ?? '';
if (empty($currentUserName)) {
    $stmtUser = $pdo->prepare('SELECT nom_complet FROM Utilisateur WHERE id_user = ?');
    $stmtUser->execute([$_SESSION['user_id']]);
    $currentUserName = $stmtUser->fetchColumn() ?: '';
}

// Récupération des agents
$agents = $pdo->query("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC")->fetchAll();

// Récupération des lots disponibles
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, l.prix_achat_ttc, p.nom_medicament, p.marge_pourcentage, p.type_produit
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            WHERE l.quantite_actuelle > 0
            ORDER BY CASE WHEN p.type_produit = 'Medicament' THEN 1 ELSE 2 END, l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4">Ajouter un Transfert</h2>
    <?php if ($isAdmin): ?>

        <?php echo $message; ?>
        <?php if (isset($_SESSION['group_transfer_message'])): ?>
            <?php $groupMsg = $_SESSION['group_transfer_message']; ?>
            <div class="alert alert-<?= htmlspecialchars($groupMsg['type']) ?>">
                <?= htmlspecialchars($groupMsg['text']) ?>
            </div>
            <?php unset($_SESSION['group_transfer_message']); ?>
        <?php endif; ?>

        <!-- <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5>Enregistrer un nouveau transfert</h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Selectionner le Lot</label>
                        <select name="id_lot" id="select_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach ($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>" data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>" data-marge="<?= $l['marge_pourcentage'] ?? 70 ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Note : les lots sont tries par date d'expiration (FIFO).</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" class="form-select" required>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Destination</label>
                        <select name="id_destination" class="form-select" required>
                            <option value="">-- Choisir destination --</option>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Quantite a sortir</label>
                        <input type="number" name="quantite_sortie" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Prix Vente Est. <small class="text-muted">(Info)</small></label>
                        <input type="number" step="0.01" name="prix_vente" id="prix_vente" class="form-control" readonly>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="valider_sortie" class="btn btn-primary w-100 btn-lg">
                        Confirmer la sortie de stock
                    </button>
                </div>
            </form>
        </div>
    </div> -->

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Enregistrer un transfert</h5>
            </div>
            <div class="card-body">
                <form id="groupTransferForm" method="POST" action="save_group_transfer.php">
                    <input type="hidden" name="action" value="initiate">
                    <input type="hidden" name="items_json" id="items_json">
                    <input type="hidden" name="redirect" value="sorties.php">
                    <input type="hidden" name="id_source" id="group_source_hidden" value="<?= htmlspecialchars($pvs[0]['id_point_vente'] ?? 1) ?>">
                    <input type="hidden" name="id_destination" id="group_destination_hidden" value="">

                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Source</label>
                            <select id="group_source" class="form-select" required>
                                <?php foreach ($pvs as $pv): ?>
                                    <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Destination</label>
                            <select id="group_destination" class="form-select" required>
                                <option value="">-- Choisir destination --</option>
                                <?php foreach ($pvs as $pv): ?>
                                    <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Lot</label>
                            <input type="text" id="search_lot_input" class="form-control form-control-sm mb-1" placeholder="Rechercher par nom ou n° lot...">
                            <select id="group_lot" class="form-select">
                                <option value="">-- Choisir un lot --</option>
                                <optgroup label="Médicament">
                                    <?php foreach ($lotsDisponibles as $l): ?>
                                        <?php if ($l['type_produit'] === 'Medicament'): ?>
                                            <option value="<?= $l['id_lot'] ?>"
                                                data-label="<?= htmlspecialchars($l['nom_medicament'] . ' | Lot: ' . $l['num_lot']) ?>"
                                                data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>"
                                                data-marge="<?= $l['marge_pourcentage'] ?? 0 ?>">
                                                <?= htmlspecialchars($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Laboratoire">
                                    <?php foreach ($lotsDisponibles as $l): ?>
                                        <?php if ($l['type_produit'] !== 'Medicament'): ?>
                                            <option value="<?= $l['id_lot'] ?>"
                                                data-label="<?= htmlspecialchars($l['nom_medicament'] . ' | Lot: ' . $l['num_lot']) ?>"
                                                data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>"
                                                data-marge="<?= $l['marge_pourcentage'] ?? 0 ?>">
                                                <?= htmlspecialchars($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Qté</label>
                            <input type="number" id="group_qty" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="button" id="btnAddGroupItem" class="btn btn-secondary w-100">Ajouter</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="groupItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Lot</th>
                                    <th>Source</th>
                                    <th>Destination</th>
                                    <th class="text-end">PU Achat</th>
                                    <th class="text-center">Marge</th>
                                    <th class="text-end">PU Vente</th>
                                    <th class="text-center">Agent</th>
                                    <th class="text-center">Qté</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <!-- <div class="small text-muted">Une fois la destination choisie, elle restera fixe pour tous les lots ajoutés.</div> -->
                        <div><strong>Total général :</strong> <span id="group_total_general">0,00 FCFA</span></div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary btn-lg">Créer transfert</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Group Detail -->
        <!-- <form method="GET" id="searchForm" class="my-3" role="search">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
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
                    <option value="">Tous les agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>" <?= $agentFilter === (string)$agent['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form> -->

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // AJAX filtering for "Historique des sorties"
                const sourceFilter = document.getElementById('sourceFilter');
                const destinationFilter = document.getElementById('destinationFilter');
                const agentFilter = document.getElementById('agentFilter');
                const lotFilter = document.getElementById('lotFilter');
                const dateFilterStart = document.getElementById('dateFilterStart');
                const dateFilterEnd = document.getElementById('dateFilterEnd');
                const tableBody = document.getElementById('tableBody');

                function loadSortiesData() {
                    if (!tableBody) {
                        console.warn('AJAX sorties : tbody #tableBody introuvable, chargement annulé.');
                        return;
                    }

                    const params = new URLSearchParams({
                        source: sourceFilter ? sourceFilter.value : '',
                        destination: destinationFilter ? destinationFilter.value : '',
                        agent: agentFilter ? agentFilter.value : '',
                        lot: lotFilter ? lotFilter.value : '',
                        dateStart: dateFilterStart ? dateFilterStart.value : '',
                        dateEnd: dateFilterEnd ? dateFilterEnd.value : ''
                    });

                    fetch('fetch_sorties.php?' + params.toString())
                        .then(r => r.text())
                        .then(html => {
                            tableBody.innerHTML = html;
                        })
                        .catch(err => console.error('Erreur filtrage AJAX:', err));
                }

                [sourceFilter, destinationFilter, agentFilter, lotFilter, dateFilterStart, dateFilterEnd].forEach(el => {
                    if (el) el.addEventListener('change', loadSortiesData);
                });
                loadSortiesData(); // Initial load
            });



            var groupSource = document.getElementById('group_source');
            var groupDestination = document.getElementById('group_destination');
            var groupSourceHidden = document.getElementById('group_source_hidden');
            var groupDestinationHidden = document.getElementById('group_destination_hidden');
            var groupLot = document.getElementById('group_lot');
            var groupQty = document.getElementById('group_qty');
            var btnAddGroupItem = document.getElementById('btnAddGroupItem');
            var groupItemsTable = document.getElementById('groupItemsTable');
            var itemsJsonInput = document.getElementById('items_json');
            var totalGeneralDisplay = document.getElementById('group_total_general');
            var groupTransferForm = document.getElementById('groupTransferForm');
            var currentUserName = <?= json_encode($currentUserName) ?>;

            // Système de recherche/filtrage pour le menu déroulant des lots
            var searchLotInput = document.getElementById('search_lot_input');
            if (searchLotInput && groupLot) {
                searchLotInput.addEventListener('input', function() {
                    var filter = this.value.toLowerCase();
                    var allOptions = groupLot.querySelectorAll('option');
                    for (var i = 0; i < allOptions.length; i++) {
                        var opt = allOptions[i];
                        if (opt.value === "") continue;
                        var text = opt.textContent.toLowerCase();
                        opt.style.display = text.indexOf(filter) > -1 ? "" : "none";
                    }
                    var optgroups = groupLot.querySelectorAll('optgroup');
                    for (var j = 0; j < optgroups.length; j++) {
                        var group = optgroups[j];
                        var groupOptions = group.querySelectorAll('option');
                        var hasVisible = false;
                        for (var k = 0; k < groupOptions.length; k++) {
                            if (groupOptions[k].style.display !== "none") {
                                hasVisible = true;
                                break;
                            }
                        }
                        group.style.display = hasVisible ? "" : "none";
                    }
                });
            }

            var items = [];

            function formatCurrency(value) {
                return value.toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' FCFA';
            }

            function updateHiddenFields() {
                if (groupSource && groupSourceHidden) {
                    groupSourceHidden.value = groupSource.value;
                }
                if (groupDestination && groupDestinationHidden) {
                    groupDestinationHidden.value = groupDestination.value;
                }
            }

            function renderGroupItems() {
                var tbody = groupItemsTable.querySelector('tbody');
                tbody.innerHTML = '';
                var totalGeneral = 0;

                if (items.length === 0) {
                    var emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="10" class="text-center text-muted">Aucun lot ajouté.</td>';
                    tbody.appendChild(emptyRow);
                } else {
                    items.forEach(function(item, index) {
                        var totalRow = item.prix_vente * item.qty;
                        totalGeneral += totalRow;
                        var row = document.createElement('tr');
                        row.innerHTML =
                            '<td>' + item.label + '</td>' +
                            '<td>' + item.source + '</td>' +
                            '<td>' + item.destination + '</td>' +
                            '<td class="text-end">' + formatCurrency(item.prix_achat) + '</td>' +
                            '<td class="text-center">' + item.marge.toFixed(2) + '%</td>' +
                            '<td class="text-end">' + formatCurrency(item.prix_vente) + '</td>' +
                            '<td class="text-center">' + currentUserName + '</td>' +
                            '<td class="text-center">' + item.qty + '</td>' +
                            '<td class="text-end">' + formatCurrency(totalRow) + '</td>' +
                            '<td class="text-end">' +
                            '<button type="button" class="btn btn-sm btn-outline-danger btnRemoveGroupItem" data-index="' + index + '">' +
                            '<i class="bi bi-trash"></i>' +
                            '</button>' +
                            '</td>';
                        tbody.appendChild(row);
                    });
                }

                if (totalGeneralDisplay) {
                    totalGeneralDisplay.textContent = formatCurrency(totalGeneral);
                }
            }

            function updateItemsJson() {
                if (itemsJsonInput) {
                    itemsJsonInput.value = JSON.stringify(items);
                }
            }

            function setSelectsState(disabled) {
                if (groupSource) groupSource.disabled = disabled;
                if (groupDestination) groupDestination.disabled = disabled;
            }

            if (groupSource) {
                groupSource.addEventListener('change', updateHiddenFields);
            }
            if (groupDestination) {
                groupDestination.addEventListener('change', updateHiddenFields);
            }

            updateHiddenFields();

            if (btnAddGroupItem) {
                btnAddGroupItem.addEventListener('click', function() {
                    if (!groupLot || !groupDestination) {
                        alert('Veuillez sélectionner un lot et une destination.');
                        return;
                    }

                    var lotId = groupLot.value;
                    var selectedOption = groupLot.options[groupLot.selectedIndex];
                    var qty = parseInt(groupQty.value, 10);
                    var sourceId = groupSource ? groupSource.value : '';
                    var destinationId = groupDestination.value;

                    if (!lotId) {
                        alert('Veuillez choisir un lot.');
                        return;
                    }
                    if (!destinationId) {
                        alert('Veuillez choisir une destination valide.');
                        return;
                    }
                    if (sourceId === destinationId) {
                        alert('Source et destination doivent être différentes.');
                        return;
                    }
                    if (qty <= 0 || isNaN(qty)) {
                        alert('Veuillez saisir une quantité valide.');
                        return;
                    }

                    var prixAchat = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                    var marge = parseFloat(selectedOption.getAttribute('data-marge')) || 0;
                    var prixVente = prixAchat * (1 + (marge / 100));
                    var label = selectedOption.getAttribute('data-label') || selectedOption.text;

                    // Vérifier si le lot existe déjà avec la même source et destination
                    var existingItem = items.find(item => 
                        item.id_lot == lotId && 
                        item.source === groupSource.options[groupSource.selectedIndex].text &&
                        item.destination === groupDestination.options[groupDestination.selectedIndex].text
                    );

                    if (existingItem) {
                        // Si le lot existe déjà avec la même source/destination, incrémenter la quantité
                        existingItem.qty += qty;
                    } else {
                        // Sinon, ajouter un nouvel item
                        items.push({
                            id_lot: lotId,
                            label: label,
                            source: groupSource.options[groupSource.selectedIndex].text,
                            destination: groupDestination.options[groupDestination.selectedIndex].text,
                            prix_achat: prixAchat,
                            marge: marge,
                            prix_vente: prixVente,
                            qty: qty
                        });
                    }

                    updateHiddenFields();
                    setSelectsState(true);
                    renderGroupItems();
                    updateItemsJson();

                    groupLot.selectedIndex = 0;
                    groupQty.value = 1;
                });
            }

            if (groupItemsTable) {
                groupItemsTable.addEventListener('click', function(event) {
                    if (event.target.closest('.btnRemoveGroupItem')) {
                        var button = event.target.closest('.btnRemoveGroupItem');
                        var index = parseInt(button.dataset.index, 10);
                        if (!isNaN(index)) {
                            items.splice(index, 1);
                            updateItemsJson();
                            renderGroupItems();
                            if (items.length === 0) {
                                setSelectsState(false);
                            }
                        }
                    }
                });
            }

            if (groupTransferForm) {
                groupTransferForm.addEventListener('submit', function(event) {
                    if (items.length === 0) {
                        alert('Ajoutez au moins un lot au transfert avant de l\'envoyer.');
                        event.preventDefault();
                        return false;
                    }
                    updateHiddenFields();
                    updateItemsJson();
                });
            }



            document.addEventListener('DOMContentLoaded', function() {
                // Calcul automatique du prix de vente avec marge dynamique
                var selectLot = document.getElementById('select_lot');
                var prixVenteInput = document.getElementById('prix_vente');

                if (selectLot && prixVenteInput) {
                    selectLot.addEventListener('change', function() {
                        var option = selectLot.options[selectLot.selectedIndex];
                        var prixAchat = parseFloat(option.getAttribute('data-prix')) || 0;
                        var marge = parseFloat(option.getAttribute('data-marge')) || 70;
                        var prixVente = prixAchat * (1 + (marge / 100));
                        prixVenteInput.value = prixVente.toFixed(2);
                    });
                }

                // Remplissage du modal d'édition
                var modalEdit = document.getElementById('modalEditTransfert');
                if (modalEdit) {
                    modalEdit.addEventListener('show.bs.modal', function(event) {
                        var btn = event.relatedTarget;
                        document.getElementById('edit_id_transfert').value = btn.getAttribute('data-id');
                        document.getElementById('edit_id_lot').value = btn.getAttribute('data-lot');
                        document.getElementById('edit_id_source').value = btn.getAttribute('data-src');
                        document.getElementById('edit_id_destination').value = btn.getAttribute('data-dest');
                        document.getElementById('edit_quantite').value = btn.getAttribute('data-qte');
                    });
                }
            });
        </script>
    <?php endif; ?>

    <?php include('../includes/footer.php'); ?>
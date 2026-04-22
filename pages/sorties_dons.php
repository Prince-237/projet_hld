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
    $id_user = $_SESSION['user_id'];

    if ($id_source === $id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : La source et la destination ne peuvent pas être identiques.</div>";
    } else {

        try {
            $pdo->beginTransaction();

            $checkSql = "SELECT l.quantite_actuelle FROM StockLot l WHERE l.id_lot = :id_lot FOR UPDATE";
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute([':id_lot' => $id_lot]);
            $lot = $stmtCheck->fetch();

            $stock_suffisant = ($id_source !== 1) || ($lot && $lot['quantite_actuelle'] >= $quantite_demandee);

            if ($stock_suffisant) {

                $num_bordereau = 'TR-' . date('Ymd') . '-' . rand(100, 999);

                $sqlTrans = "INSERT INTO Transfert (id_source, id_destination, id_user, num_bordereau) VALUES (?, ?, ?, ?)";
                $stmtTrans = $pdo->prepare($sqlTrans);
                $stmtTrans->execute([$id_source, $id_destination, $id_user, $num_bordereau]);
                $id_transfert = $pdo->lastInsertId();

                $sqlDet = "INSERT INTO TransfertDetail (id_transfert, id_lot, quantite_transfert) VALUES (?, ?, ?)";
                $stmtDet = $pdo->prepare($sqlDet);
                $stmtDet->execute([$id_transfert, $id_lot, $quantite_demandee]);

                if ($id_source == 1) {
                    $sqlUpdate = "UPDATE StockLot SET quantite_actuelle = quantite_actuelle - :qte WHERE id_lot = :id_lot";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->execute([':qte' => $quantite_demandee, ':id_lot' => $id_lot]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>Transfert de don enregistré avec succès (Bordereau : $num_bordereau).</div>";
            } else {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot de don.</div>";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_transfert'])) {
    $id_transfert = (int)$_POST['id_transfert'];

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
                if ($row['id_source'] == 1) {
                    $upd = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
                    $upd->execute([$row['quantite_transfert'], $row['id_lot']]);
                }
            }

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

$currentUserName = $_SESSION['nom'] ?? '';
if (empty($currentUserName)) {
    $stmtUser = $pdo->prepare('SELECT nom_complet FROM Utilisateur WHERE id_user = ?');
    $stmtUser->execute([$_SESSION['user_id']]);
    $currentUserName = $stmtUser->fetchColumn() ?: '';
}
?>

<?php
// 3. Recuperation des lots disponibles (uniquement ceux issus de DONS)
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, p.nom_medicament
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE l.quantite_actuelle > 0 AND part.type = 'Don'
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Récupération des points de vente
$pvs = $pdo->query("SELECT * FROM PointVente ORDER BY nom_point_vente ASC")->fetchAll();

$pvNames = [];
foreach ($pvs as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

// Récupération des agents
$agents = $pdo->query("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC")->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4">Transferts de Dons</h2>
    <?php if ($isAdmin): ?>

        <?php echo $message; ?>
        <?php if (isset($_SESSION['group_transfer_message'])): ?>
            <?php $groupMsg = $_SESSION['group_transfer_message']; ?>
            <div class="alert alert-<?= htmlspecialchars($groupMsg['type']) ?>">
                <?= htmlspecialchars($groupMsg['text']) ?>
            </div>
            <?php unset($_SESSION['group_transfer_message']); ?>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Préparer un transfert</h5>
            </div>
            <div class="card-body">
                <form id="groupTransferForm" method="POST" action="save_group_transfer.php">
                    <input type="hidden" name="action" value="initiate">
                    <input type="hidden" name="items_json" id="items_json">
                    <input type="hidden" name="redirect" value="sorties_dons.php">
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
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Lot</label>
                            <select id="group_lot" class="form-select">
                                <option value="">-- Choisir un lot --</option>
                                <?php foreach ($lotsDisponibles as $l): ?>
                                    <option value="<?= $l['id_lot'] ?>"
                                        data-label="<?= htmlspecialchars($l['nom_medicament'] . ' | Lot: ' . $l['num_lot']) ?>">
                                        <?= htmlspecialchars($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
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
                                    <th class="text-center">Qté</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <!-- <div class="small text-muted">Une fois la destination choisie, elle restera fixe pour tous les lots ajoutés.</div> -->
                        <div><strong>Total articles :</strong> <span id="group_total_items">0</span></div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary btn-lg">Créer transfert</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <!-- <div class="card-header bg-info text-dark">
                <h5>Transferts en attente</h5>
            </div> -->
            <!-- <div class="card-body">
            <form id="filterForm" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Source</label>
                        <select id="sourceFilter" class="form-select">
                            <option value="">Tous</option>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Destination</label>
                        <select id="destFilter" class="form-select">
                            <option value="">Tous</option>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Agent</label>
                        <select id="agentFilterWaiting" class="form-select">
                            <option value="">Tous</option>
                            <?php
                            $agentOptions = [];
                            foreach ($groupTransfers as $group) {
                                if (!empty($group['created_by']) && !isset($agentOptions[$group['created_by']])) {
                                    $agentOptions[$group['created_by']] = $group['agent_name'] ?? 'Agent #' . $group['created_by'];
                                }
                            }
                            foreach ($agentOptions as $agentId => $agentName): ?>
                                <option value="<?= htmlspecialchars($agentId) ?>"><?= htmlspecialchars($agentName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Lot</label>
                        <select id="lotFilterWaiting" class="form-select">
                            <option value="">Tous</option>
                            <?php
                            // Collect all unique lot IDs and their labels from waiting groups
                            $lotOptions = [];
                            $waitingGroups = array_filter($groupTransfers, function ($group) {
                                return isset($group['status']) && $group['status'] === 'Envoyé';
                            });
                            foreach ($waitingGroups as $group) {
                                foreach ($group['items'] as $item) {
                                    $lotId = $item['id_lot'] ?? '';
                                    if ($lotId && !isset($lotOptions[$lotId])) {
                                        $lotOptions[$lotId] = $item['label'] ?? ('Lot #' . $lotId);
                                    }
                                }
                            }
                            foreach ($lotOptions as $lotId => $lotLabel): ?>
                                <option value="<?= htmlspecialchars($lotId) ?>"><?= htmlspecialchars($lotLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div> -->
        </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var sourceFilter = document.getElementById('sourceFilter');
                var destFilter = document.getElementById('destFilter');
                var agentFilterWaiting = document.getElementById('agentFilterWaiting');
                var lotFilterWaiting = document.getElementById('lotFilterWaiting');
                var tableBody = document.querySelector('.card-body .table tbody');

                function applyFilters() {
                    var sourceVal = sourceFilter.value;
                    var destVal = destFilter.value;
                    var agentVal = agentFilterWaiting.value;
                    var lotVal = lotFilterWaiting.value;

                    var rows = tableBody.querySelectorAll('tr');
                    var visibleCount = 0;

                    rows.forEach(function(row) {
                        var cells = row.querySelectorAll('td');
                        if (cells.length === 0) return; // Skip header rows

                        var rowSource = row.getAttribute('data-source') || '';
                        var rowDest = row.getAttribute('data-destination') || '';
                        var rowAgent = row.getAttribute('data-agent') || '';
                        var sourceName = cells[1]?.textContent.trim() || '';
                        var destName = cells[2]?.textContent.trim() || '';
                        var agentName = cells[3]?.textContent.trim() || '';
                        var lotNames = cells[4]?.textContent.trim() || ''; // Assuming lot names are in the 5th column (index 4)

                        var sourceMatch = !sourceVal || rowSource === sourceVal || sourceName.includes(sourceVal);
                        var destMatch = !destVal || rowDest === destVal || destName.includes(destVal);
                        var agentMatch = !agentVal || rowAgent === agentVal || agentName.includes(agentVal);
                        var lotMatch = !lotVal || (row.getAttribute('data-lots') && row.getAttribute('data-lots').split(',').includes(lotVal));

                        if (sourceMatch && destMatch && agentMatch && lotMatch) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // Show no results message if no rows visible
                    var noResultsRow = tableBody.querySelector('tr[data-no-results]');
                    if (!noResultsRow && visibleCount === 0) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-no-results', 'true');
                        row.innerHTML = '<td colspan="7" class="text-center text-muted">Aucun transfert correspondant aux critères.</td>';
                        tableBody.appendChild(row);
                    } else if (noResultsRow && visibleCount > 0) {
                        noResultsRow.remove();
                    }
                }

                [sourceFilter, destFilter, agentFilterWaiting, lotFilterWaiting].forEach(function(el) {
                    if (el) {
                        el.addEventListener('change', applyFilters);
                    }
                });
            });
        </script>
</div>

<?php include '../includes/footer.php'; ?>
            <!-- <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3"><
                        <label class="form-label fw-bold">Selectionner le Lot (Don)</label>
                        <select name="id_lot" id="select_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach ($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" class="form-select" required>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
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
                </div>

                <div class="mt-4">
                    <button type="submit" name="valider_sortie" class="btn btn-primary w-100 btn-lg">
                        Confirmer la sortie du don
                    </button>
                </div>
            </form>
        </div> -->
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var groupSource = document.getElementById('group_source');
        var groupDestination = document.getElementById('group_destination');
        var groupLot = document.getElementById('group_lot');
        var groupLotOptions = Array.from(groupLot.options); // Get all options once
        var groupQty = document.getElementById('group_qty');
        var btnAddGroupItem = document.getElementById('btnAddGroupItem');
        var groupItemsTable = document.getElementById('groupItemsTable').querySelector('tbody');
        var groupTotalItems = document.getElementById('group_total_items');
        var groupForm = document.getElementById('groupTransferForm');
        var itemsJson = document.getElementById('items_json');
        var groupSourceHidden = document.getElementById('group_source_hidden');
        var groupDestinationHidden = document.getElementById('group_destination_hidden');

        var items = [];

        // Initialize selects as enabled
        groupSource.disabled = false;
        groupDestination.disabled = false;

        function renderItems() {
            groupItemsTable.innerHTML = '';
            items.forEach(function(item, index) {
                var row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.label}</td>
                    <td>${item.sourceName}</td>
                    <td>${item.destName}</td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeGroupItem(${index})">&times;</button>
                    </td>
                `;
                groupItemsTable.appendChild(row);
            });
            groupTotalItems.textContent = items.length;
            itemsJson.value = JSON.stringify(items);
            groupForm.style.display = items.length > 0 ? 'block' : 'none';

            // Lock source and destination selects after first item
            groupSource.disabled = items.length > 0;
            groupDestination.disabled = items.length > 0;
        }

        window.removeGroupItem = function(index) {
            items.splice(index, 1);
            renderItems();
        };

        groupSource.addEventListener('change', function() {
            groupSourceHidden.value = this.value;
        });

        groupDestination.addEventListener('change', function() {
            groupDestinationHidden.value = this.value;
        });

        btnAddGroupItem.addEventListener('click', function() {
            var lotId = groupLot.value;
            var qty = parseInt(groupQty.value, 10);
            var sourceId = groupSource.value;
            var destId = groupDestination.value;

            if (!lotId || !qty || qty < 1 || !sourceId || !destId) {
                alert('Veuillez sélectionner un lot, une quantité valide, une source et une destination.');
                return;
            }

            if (sourceId === destId) {
                alert('La source et la destination doivent être différentes.');
                return;
            }

            var lotOption = groupLotOptions.find(opt => opt.value === lotId);
            var sourceOption = groupSource.options[groupSource.selectedIndex];
            var destOption = groupDestination.options[groupDestination.selectedIndex];

            // Vérifier si le lot existe déjà avec la même source et destination
            var existingItem = items.find(item =>
                item.id_lot === lotId &&
                item.sourceName === sourceOption.text &&
                item.destName === destOption.text
            );

            if (existingItem) {
                // Si le lot existe avec la même source/destination, incrémenter la quantité
                existingItem.qty += qty;
            } else {
                // Sinon, ajouter un nouvel item
                items.push({
                    id_lot: lotId,
                    qty: qty,
                    label: lotOption.getAttribute('data-label') || lotOption.text,
                    sourceName: sourceOption.text,
                    destName: destOption.text
                });
            }

            groupLot.value = '';
            groupQty.value = 1;

            renderItems();
        });
    });
</script>
</div>

<!-- <?php include('../includes/footer.php'); ?> -->
<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

function loadGroups(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $groups = json_decode($json, true);
    return is_array($groups) ? $groups : [];
}

function getLotDetails(PDO $pdo, int $id_lot): array
{
    $stmt = $pdo->prepare("SELECT l.id_lot, l.num_lot, l.prix_achat_ttc, p.nom_medicament, p.marge_pourcentage
                           FROM StockLot l
                           JOIN Produit p ON l.id_produit = p.id_produit
                           WHERE l.id_lot = ?");
    $stmt->execute([$id_lot]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : ['num_lot' => 'N/A', 'prix_achat_ttc' => 0, 'nom_medicament' => 'Inconnu', 'marge_pourcentage' => 0];
}

$groups = loadGroups(__DIR__ . '/../data/group_transfers.json');

$pvs = $pdo->query("SELECT id_point_vente, nom_point_vente FROM PointVente")->fetchAll(PDO::FETCH_ASSOC);
$pvNames = [];
foreach ($pvs as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

$displayGroups = array_reverse($groups);

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Liste des transferts</h2>

    <?php if (isset($_SESSION['group_transfer_message'])): ?>
        <?php $flash = $_SESSION['group_transfer_message']; ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
        <?php unset($_SESSION['group_transfer_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="searchForm" class="m-3">
                <div class="row g-2">
                    <!-- <div class="col-md-4">
                        <label class="form-label">Recherche</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Référence, agent, source, destination...">
                    </div> -->
                    <div class="col-md-2">
                        <label class="form-label">Source</label>
                        <select id="sourceFilter" class="form-select">
                            <option value="">Toutes les sources</option>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Destination</label>
                        <select id="destinationFilter" class="form-select">
                            <option value="">Toutes les destinations</option>
                            <?php foreach ($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= htmlspecialchars($pv['nom_point_vente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Statut</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">Tous</option>
                            <option value="Envoyé">Envoyé</option>
                            <option value="Reçu">Reçu</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Agent</label>
                        <select id="agentFilter" class="form-select">
                            <option value="">Tous les agents</option>
                            <?php
                            $agentOptions = [];
                            foreach ($groups as $group) {
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
                        <label class="form-label">Date (du)</label>
                        <input type="date" id="dateFilterStart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date (au)</label>
                        <input type="date" id="dateFilterEnd" class="form-control">
                    </div>
                </div>
            </form>

            <div class="table-responsive p-2">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Référence</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Agent</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th class="text-end">Total général</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (empty($displayGroups)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun transfert reçu.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayGroups as $group): ?>
                                <?php
                                    $totalGeneral = 0;
                                    if (isset($group['items'])) {
                                        foreach ($group['items'] as $item) {
                                            $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : (isset($item['prix_achat']) ? floatval($item['prix_achat']) : 0);
                                            $totalGeneral += $prixVente * intval($item['qty']);
                                        }
                                    }
                                    $statusClass = (isset($group['status']) && $group['status'] === 'Reçu') ? 'badge bg-success' : 'badge bg-warning text-dark';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($group['id']) ?></td>
                                    <td><?= htmlspecialchars($pvNames[$group['id_source']] ?? $group['id_source']) ?></td>
                                    <td><?= htmlspecialchars($pvNames[$group['id_destination']] ?? $group['id_destination']) ?></td>
                                    <td><?= htmlspecialchars($group['agent_name'] ?? 'N/A') ?></td>
                                    <td><span class="<?= $statusClass ?>"><?= htmlspecialchars($group['status']) ?></span></td>
                                    <td><?= htmlspecialchars($group['created_at']) ?></td>
                                    <td class="text-end"><?= number_format($totalGeneral, 0, '.', ' ') ?> FCFA</td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li><a class="dropdown-item" href="view_group_transfer.php?id=<?= urlencode($group['id']) ?>&redirect=liste_transferts.php"><i class="bi bi-eye me-2"></i>Voir</a></li>
                                                <li><a class="dropdown-item" href="print_transfert.php?id=<?= urlencode($group['id']) ?>" target="_blank"><i class="bi bi-printer me-2"></i>Imprimer</a></li>
                                                <?php if (isset($group['status']) && $group['status'] === 'Envoyé'): ?>
                                                    <li>
                                                        <form method="POST" action="save_group_transfer.php">
                                                            <input type="hidden" name="action" value="receive">
                                                            <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
                                                            <input type="hidden" name="redirect" value="liste_transferts.php">
                                                            <button type="submit" class="dropdown-item text-primary"><i class="bi bi-check2-circle me-2"></i>Marquer reçu</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item" href="edit_group_transfer.php?id=<?= urlencode($group['id']) ?>&redirect=liste_transferts.php"><i class="bi bi-pencil me-2"></i>Modifier</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="save_group_transfer.php" onsubmit="return confirm('Supprimer ce transfert ?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
                                                        <input type="hidden" name="redirect" value="liste_transferts.php">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Supprimer</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- <?php if (!empty($receivedGroups)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">Détails du dernier transfert</h6>
            </div>
            <div class="card-body">
                <?php $lastGroup = end($receivedGroups); ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Lot</th>
                                <th>Produit</th>
                                <th class="text-end">PU Achat</th>
                                <th class="text-center">Marge</th>
                                <th class="text-end">PU Vente</th>
                                <th class="text-center">Qté</th>
                                <th class="text-end">Total ligne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lastGroup['items'] as $item): ?>
                                <?php
                                    $prixAchat = isset($item['prix_achat']) ? floatval($item['prix_achat']) : 0;
                                    $marge = isset($item['marge']) ? floatval($item['marge']) : 0;
                                    $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : ($prixAchat * (1 + ($marge / 100)));
                                    $totalLigne = $prixVente * intval($item['qty']);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['label'] ?? ('Lot #' . $item['id_lot'])) ?></td>
                                    <td><?= htmlspecialchars($item['label'] ?? 'Lot') ?></td>
                                    <td class="text-end"><?= number_format($prixAchat, 2, '.', ' ') ?> FCFA</td>
                                    <td class="text-center"><?= number_format($marge, 2) ?>%</td>
                                    <td class="text-end"><?= number_format($prixVente, 2, '.', ' ') ?> FCFA</td>
                                    <td class="text-center"><?= intval($item['qty']) ?></td>
                                    <td class="text-end"><?= number_format($totalLigne, 2, '.', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?> -->

<script src="../assets/js/liste_transferts.js" defer></script>
<?php include '../includes/footer.php'; ?>

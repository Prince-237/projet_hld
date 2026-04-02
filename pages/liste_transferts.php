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

$receivedGroups = array_filter($groups, function($group) {
    return isset($group['status']) && $group['status'] === 'Reçu';
});

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-list-check"></i> Liste des transferts groupés</h2>

    <?php if (isset($_SESSION['group_transfer_message'])): ?>
        <?php $flash = $_SESSION['group_transfer_message']; ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
        <?php unset($_SESSION['group_transfer_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Transferts enregistrés</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
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
                    <tbody>
                        <?php if (empty($receivedGroups)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun transfert groupé reçu.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receivedGroups as $group): ?>
                                <?php
                                    $totalGeneral = 0;
                                    foreach ($group['items'] as $item) {
                                        $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : (isset($item['prix_achat']) ? floatval($item['prix_achat']) : 0);
                                        $totalGeneral += $prixVente * intval($item['qty']);
                                    }
                                    $statusClass = $group['status'] === 'Reçu' ? 'badge bg-success' : 'badge bg-info text-dark';
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
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btnViewGroup" data-group-id="<?= htmlspecialchars($group['id']) ?>" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btnEditGroup" data-group-id="<?= htmlspecialchars($group['id']) ?>" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="save_group_transfer.php" class="d-inline" onsubmit="return confirm('Supprimer ce transfert groupé ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
                                            <input type="hidden" name="redirect" value="liste_transferts.php">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<!-- Modal Group Detail -->
<div class="modal fade" id="groupDetailModal" tabindex="-1" aria-labelledby="groupDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupDetailModalLabel">Détails du transfert groupé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="groupDetailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var groupDetailModalEl = document.getElementById('groupDetailModal');
        if (!groupDetailModalEl) {
            return;
        }

        var groupDetailModal = new bootstrap.Modal(groupDetailModalEl);
        var groupDetailModalTitle = document.getElementById('groupDetailModalLabel');
        var groupDetailModalBody = document.getElementById('groupDetailModalBody');
        var receivedGroupsData = <?= json_encode(array_values($receivedGroups), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var pvNames = <?= json_encode($pvNames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function renderGroupDetail(group, mode) {
            var html = '<div class="mb-3"><strong>Référence :</strong> ' + group.id + '</div>' +
                '<div class="mb-3"><strong>Source :</strong> ' + (pvNames[group.id_source] || group.id_source) + '</div>' +
                '<div class="mb-3"><strong>Destination :</strong> ' + (pvNames[group.id_destination] || group.id_destination) + '</div>' +
                '<div class="mb-3"><strong>Agent :</strong> ' + (group.agent_name || 'N/A') + '</div>' +
                '<div class="mb-3"><strong>Statut :</strong> ' + (group.status || 'N/A') + '</div>' +
                '<div class="mb-3"><strong>Créé le :</strong> ' + (group.created_at || 'N/A') + '</div>';

            if (group.items && group.items.length) {
                html += '<div class="table-responsive"><table class="table table-sm table-bordered">' +
                    '<thead class="table-light"><tr><th>Lot</th><th>PU Achat</th><th>Marge</th><th>PU Vente</th><th>Qté</th><th>Total ligne</th></tr></thead><tbody>';
                var totalGeneral = 0;
                group.items.forEach(function(item) {
                    var prixAchat = parseFloat(item.prix_achat) || 0;
                    var marge = parseFloat(item.marge) || 0;
                    var prixVente = parseFloat(item.prix_vente) || prixAchat * (1 + (marge / 100));
                    var totalLigne = prixVente * parseInt(item.qty, 10);
                    totalGeneral += totalLigne;
                    html += '<tr>' +
                        '<td>' + (item.label || 'Lot #' + item.id_lot) + '</td>' +
                        '<td class="text-end">' + prixAchat.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                        '<td class="text-center">' + marge.toFixed(2) + '%</td>' +
                        '<td class="text-end">' + prixVente.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                        '<td class="text-center">' + parseInt(item.qty, 10) + '</td>' +
                        '<td class="text-end">' + totalLigne.toFixed(2).replace('.', ',') + ' FCFA</td>' +
                        '</tr>';
                });
                html += '</tbody></table></div>';
                html += '<div class="fw-bold text-end">Total général : ' + totalGeneral.toFixed(2).replace('.', ',') + ' FCFA</div>';
            } else {
                html += '<p class="text-muted">Aucun article enregistré.</p>';
            }

            groupDetailModalTitle.textContent = mode === 'edit' ? 'Modifier le transfert groupé' : 'Détails du transfert groupé';
            groupDetailModalBody.innerHTML = html;
            groupDetailModal.show();
        }

        function findGroupById(groupId) {
            return receivedGroupsData.find(function(group) {
                return group.id === groupId;
            });
        }

        document.querySelectorAll('.btnViewGroup, .btnEditGroup').forEach(function(button) {
            button.addEventListener('click', function() {
                var groupId = button.getAttribute('data-group-id');
                var group = findGroupById(groupId);
                if (!group) {
                    alert('Transfert non trouvé.');
                    return;
                }
                renderGroupDetail(group, button.classList.contains('btnEditGroup') ? 'edit' : 'view');
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>

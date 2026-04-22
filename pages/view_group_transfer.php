<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("Transfert invalide");
}

$groupId = $_GET['id'];
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'liste_transferts.php';
$groupTransfersFile = __DIR__ . '/../data/group_transfers.json';
$group = null;

if (file_exists($groupTransfersFile)) {
    $groups = json_decode(file_get_contents($groupTransfersFile), true);
    if (is_array($groups)) {
        foreach ($groups as $g) {
            if ($g['id'] === $groupId) {
                $group = $g;
                break;
            }
        }
    }
}

if (!$group) {
    die("Transfert introuvable.");
}

$pvs = $pdo->query("SELECT id_point_vente, nom_point_vente FROM PointVente")->fetchAll(PDO::FETCH_ASSOC);
$pvNames = [];
foreach ($pvs as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails du transfert</h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Référence :</strong><br>
                    <?= htmlspecialchars($group['id']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Source :</strong><br>
                    <?= htmlspecialchars($pvNames[$group['id_source']] ?? $group['id_source']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Destination :</strong><br>
                    <?= htmlspecialchars($pvNames[$group['id_destination']] ?? $group['id_destination']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Agent :</strong><br>
                    <?= htmlspecialchars($group['agent_name'] ?? 'N/A') ?>
                </div>
                <div class="col-md-3">
                    <strong>Statut / Date :</strong><br>
                    <span class="badge bg-<?= $group['status'] === 'Reçu' ? 'success' : 'info text-dark' ?>">
                        <?= htmlspecialchars($group['status']) ?>
                    </span> - <?= htmlspecialchars($group['created_at']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Lots transférés</strong>
        </div>
        <div class="table-responsive p-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Lot</th>
                        <th class="text-end">PU Achat</th>
                        <th class="text-center">Marge</th>
                        <th class="text-end">PU Vente</th>
                        <th class="text-center">Qté</th>
                        <th class="text-end">Total ligne</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalGeneral = 0; ?>
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                            $prixAchat = floatval($item['prix_achat'] ?? 0);
                            $marge = floatval($item['marge'] ?? 0);
                            $prixVente = floatval($item['prix_vente'] ?? ($prixAchat * (1 + ($marge / 100))));
                            $totalLigne = $prixVente * intval($item['qty']);
                            $totalGeneral += $totalLigne;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['label'] ?? 'Lot #' . $item['id_lot']) ?></td>
                            <td class="text-end"><?= number_format($prixAchat, 2, '.', ' ') ?> F</td>
                            <td class="text-center"><?= number_format($marge, 2) ?>%</td>
                            <td class="text-end"><?= number_format($prixVente, 2, '.', ' ') ?> F</td>
                            <td class="text-center"><?= intval($item['qty']) ?></td>
                            <td class="text-end fw-bold"><?= number_format($totalLigne, 2, '.', ' ') ?> F</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">TOTAL GÉNÉRAL</th>
                        <th class="text-end"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
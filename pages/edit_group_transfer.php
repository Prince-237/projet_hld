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
$groups = [];
$groupIndex = -1;

if (file_exists($groupTransfersFile)) {
    $groups = json_decode(file_get_contents($groupTransfersFile), true);
    if (is_array($groups)) {
        foreach ($groups as $idx => $g) {
            if ($g['id'] === $groupId) {
                $group = $g;
                $groupIndex = $idx;
                break;
            }
        }
    }
}

if (!$group) {
    die("Transfert introuvable.");
}

// if ($group['status'] !== 'Envoyé') {
//     die("Impossible de modifier un transfert déjà reçu.");
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update_group'])) {
    $new_qtes = $_POST['qty'] ?? [];
    
    foreach ($group['items'] as $idx => &$item) {
        if (isset($new_qtes[$idx])) {
            $item['qty'] = max(1, intval($new_qtes[$idx]));
        }
    }
    
    $groups[$groupIndex] = $group;
    if (file_put_contents($groupTransfersFile, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        $_SESSION['group_transfer_message'] = ['text' => "Transfert mis à jour.", 'type' => 'success'];
        header('Location: ' . $redirect);
        exit();
    } else {
        $error = "Erreur lors de la sauvegarde du fichier.";
    }
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
        <h2>Modifier le transfert</h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Référence :</strong><br>
                    <?= htmlspecialchars($group['id']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Source :</strong><br>
                    <?= htmlspecialchars($pvNames[$group['id_source']] ?? $group['id_source']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Destination :</strong><br>
                    <?= htmlspecialchars($pvNames[$group['id_destination']] ?? $group['id_destination']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Agent :</strong><br>
                    <?= htmlspecialchars($group['agent_name'] ?? 'N/A') ?>
                </div>
            </div>
        </div>
    </div>

    <form method="POST">
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
                    <?php foreach ($group['items'] as $idx => $item): ?>
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
                            <td>
                                <input type="number" name="qty[<?= $idx ?>]" class="form-control text-center input-qty" 
                                       value="<?= intval($item['qty']) ?>" min="1" data-pv="<?= $prixVente ?>">
                            </td>
                            <td class="text-end fw-bold total-ligne"><?= number_format($totalLigne, 2, '.', ' ') ?> F</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">TOTAL GÉNÉRAL</th>
                        <th class="text-end" id="total-general"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</th>
                    </tr>
                </tfoot>
            </table>
        </div>
            <div class="card-footer text-end">
                <button type="submit" name="btn_update_group" class="btn btn-info">Enregistrer les modifications</button>
            </div>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.input-qty').forEach(input => {
    input.addEventListener('input', () => {
        let totalGeneral = 0;
        document.querySelectorAll('.input-qty').forEach(rowInput => {
            const qty = parseInt(rowInput.value) || 0;
            const pv = parseFloat(rowInput.dataset.pv);
            const lineTotal = qty * pv;
            rowInput.closest('tr').querySelector('.total-ligne').textContent = lineTotal.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' F';
            totalGeneral += lineTotal;
        });
        document.getElementById('total-general').textContent = totalGeneral.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' F';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
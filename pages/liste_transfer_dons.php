<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');

$message = "";
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Medicament','Laboratoire']) ? $_GET['type'] : 'Medicament';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Récupération des lots disponibles (uniquement ceux issus de DONS)
$sqlLots = "SELECT l.id_lot, l.num_lot, p.nom_medicament
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE part.type = 'Don' ORDER BY p.nom_medicament ASC, l.num_lot ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();
if ($isAdmin && isset($_POST['btn_delete_transfert'])) {
    if (!empty($_POST['group_id'])) {
        $groupId = $_POST['group_id'];
        $dataFile = __DIR__ . '/../data/group_transfers.json';
        $groups = [];
        if (file_exists($dataFile)) {
            $groups = json_decode(file_get_contents($dataFile), true) ?: [];
        }

        $newGroups = array_filter($groups, fn($group) => ($group['id'] ?? '') !== $groupId);
        if (file_put_contents($dataFile, json_encode(array_values($newGroups), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
            $message = "<div class='alert alert-success'>Transfert supprimé.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Erreur : impossible de supprimer le transfert.</div>";
        }
    } else {
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

                foreach($rows as $row) {
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
}

$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC");
$stmtAgents->execute();
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Historique des transferts de dons</h2>

    <?php if($message): echo $message; endif; ?>

    <form id="searchForm" class="my-3">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fournisseur</label>
                <select id="fournisseurFilter" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($fournisseurs as $fournisseur): ?>
                        <option value="<?= $fournisseur['id_partenaire'] ?>" <?= $fournisseurFilter === (string)$fournisseur['id_partenaire'] ? 'selected' : '' ?>><?= htmlspecialchars($fournisseur['nom_entite']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (du)</label>
                <input type="date" id="dateFilterStart" value="<?= htmlspecialchars($dateFilterStart) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (au)</label>
                <input type="date" id="dateFilterEnd" value="<?= htmlspecialchars($dateFilterEnd) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Agent</label>
                <select id="agentFilter" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>" <?= $agentFilter === (string)$agent['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select id="statusFilter" class="form-select">
                    <option value="">Tous</option>
                    <option value="En attente" <?= $statusFilter === 'En attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="Valide" <?= $statusFilter === 'Valide' ? 'selected' : '' ?>>Valide</option>
                </select>
            </div>
            <!-- <div class="col-md-2">
                <label class="form-label">Lot</label>
                <select id="lotFilter" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($lotsDisponibles as $lot): ?>
                        <option value="<?= $lot['id_lot'] ?>"><?= htmlspecialchars($lot['nom_medicament']) ?> (<?= htmlspecialchars($lot['num_lot']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div> -->
        </div>
    </form>

    <script>
    const typeFilter = document.getElementById('typeFilter');
    const fournisseurFilter = document.getElementById('fournisseurFilter');
    const agentFilter = document.getElementById('agentFilter');
    const statusFilter = document.getElementById('statusFilter');
    const dateStart = document.getElementById('dateFilterStart');
    const dateEnd = document.getElementById('dateFilterEnd');
    const lotFilter = document.getElementById('lotFilter');
    let tableBody;

    function loadData() {
        if (!tableBody) {
            return;
        }

        const params = new URLSearchParams({
            type: typeFilter ? typeFilter.value : '',
            fournisseur: fournisseurFilter ? fournisseurFilter.value : '',
            agent: agentFilter ? agentFilter.value : '',
            status: statusFilter ? statusFilter.value : '',
            dateStart: dateStart ? dateStart.value : '',
            dateEnd: dateEnd ? dateEnd.value : '',
            lot: lotFilter ? lotFilter.value : ''
        });

        fetch('fetch_liste_transfer_dons.php?' + params.toString())
            .then((res) => res.text())
            .then((data) => {
                tableBody.innerHTML = data;
            })
            .catch((err) => {
                console.error('Erreur fetch_liste_transfer_dons:', err);
                tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Erreur de chargement des données.</td></tr>';
            });
    }

    [typeFilter, fournisseurFilter, agentFilter, statusFilter, dateStart, dateEnd, lotFilter].forEach((el) => {
        if (!el) {
            return;
        }
        el.addEventListener('input', loadData);
        el.addEventListener('change', loadData);
    });

    window.addEventListener('DOMContentLoaded', function() {
        tableBody = document.getElementById('tableBody');
        loadData();
    });
    </script>

    <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Historique des sorties</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bordereau</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Statut</th>
                            <th>Lots</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="7" class="text-center text-muted">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../config/db.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Medicament','Laboratoire']) ? $_GET['type'] : 'Medicament';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';
$lotFilter = $_GET['lot'] ?? '';
$statusFilter = trim($_GET['status'] ?? '');

$groupsFile = __DIR__ . '/../data/group_transfers.json';
$groups = [];
if (file_exists($groupsFile)) {
    $groups = json_decode(file_get_contents($groupsFile), true) ?: [];
}

$lotIds = [];
$pvIds = [];
foreach ($groups as $group) {
    if (!empty($group['id_source'])) {
        $pvIds[] = intval($group['id_source']);
    }
    if (!empty($group['id_destination'])) {
        $pvIds[] = intval($group['id_destination']);
    }
    foreach ($group['items'] as $item) {
        if (!empty($item['id_lot'])) {
            $lotIds[] = intval($item['id_lot']);
        }
    }
}
$lotIds = array_values(array_unique($lotIds));
$pvIds = array_values(array_unique($pvIds));

$lotDetails = [];
if (!empty($lotIds)) {
    $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
    $sql = "SELECT l.id_lot, l.num_lot, p.nom_medicament, p.type_produit, part.id_partenaire, part.nom_entite AS fournisseur
            FROM StockLot l
            JOIN Produit p ON l.id_produit = p.id_produit
            JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE l.id_lot IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($lotIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lotDetails[intval($row['id_lot'])] = $row;
    }
}

$pvNames = [];
if (!empty($pvIds)) {
    $placeholders = implode(',', array_fill(0, count($pvIds), '?'));
    $stmt = $pdo->prepare("SELECT id_point_vente, nom_point_vente FROM PointVente WHERE id_point_vente IN ($placeholders)");
    $stmt->execute($pvIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pv) {
        $pvNames[intval($pv['id_point_vente'])] = $pv['nom_point_vente'];
    }
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$outputRows = '';

$groupSummaries = [];
foreach ($groups as $group) {
    $groupId = $group['id'] ?? null;
    if (!$groupId) {
        continue;
    }

    $groupSummaries[$groupId] = [
        'group' => $group,
        'lot_ids' => [],
        'matching' => false,
    ];
}

foreach ($groupSummaries as &$summary) {
    $group = $summary['group'];
    $groupStatus = $group['status'] ?? 'Envoyé';
    $displayStatus = ($groupStatus === 'Reçu') ? 'Valide' : 'En attente';
    $summary['displayStatus'] = $displayStatus;
    $summary['statusClass'] = ($groupStatus === 'Reçu') ? 'badge bg-success' : 'badge bg-warning text-dark';
    $summary['groupAgentId'] = $group['created_by'] ?? null;
    $summary['groupAgentName'] = $group['agent_name'] ?? '-';
    $summary['groupCreatedAt'] = $group['created_at'] ?? '';
    $summary['groupStatus'] = $groupStatus;
    $summary['groupSource'] = $group['id_source'] ?? null;
    $summary['groupDest'] = $group['id_destination'] ?? null;
    $summary['matching'] = false;
    $summary['lotCount'] = 0;
}
unset($summary);

foreach ($groupSummaries as &$summary) {
    $group = $summary['group'];
    $typeMatch = false;
    $fournisseurMatch = false;
    $lotMatch = false;

    foreach ($group['items'] as $item) {
        $lotId = intval($item['id_lot'] ?? 0);
        if ($lotId === 0 || !isset($lotDetails[$lotId])) {
            continue;
        }
        $lotInfo = $lotDetails[$lotId];

        if ($typeProduit !== $lotInfo['type_produit']) {
            continue;
        }

        if (empty($fournisseurFilter) || intval($lotInfo['id_partenaire']) === intval($fournisseurFilter)) {
            $fournisseurMatch = true;
        }

        if (empty($lotFilter) || intval($lotFilter) === $lotId) {
            $lotMatch = true;
        }

        $typeMatch = true;
        $summary['lot_ids'][$lotId] = true;
    }

    if (!$typeMatch || !$fournisseurMatch || !$lotMatch) {
        $summary['matching'] = false;
        continue;
    }

    if (!empty($agentFilter) && $summary['groupAgentId'] !== null && $summary['groupAgentId'] != $agentFilter) {
        continue;
    }
    if (!empty($dateFilterStart) && $summary['groupCreatedAt'] !== '' && date('Y-m-d', strtotime($summary['groupCreatedAt'])) < $dateFilterStart) {
        continue;
    }
    if (!empty($dateFilterEnd) && $summary['groupCreatedAt'] !== '' && date('Y-m-d', strtotime($summary['groupCreatedAt'])) > $dateFilterEnd) {
        continue;
    }
    if (!empty($statusFilter) && strcasecmp($statusFilter, $summary['displayStatus']) !== 0) {
        continue;
    }

    $summary['matching'] = true;
    $summary['lotCount'] = count($summary['lot_ids']);
}
unset($summary);

foreach ($groupSummaries as $summary) {
    if (!$summary['matching']) {
        continue;
    }

    $group = $summary['group'];
    $groupId = $group['id'];
    $groupSource = $summary['groupSource'];
    $groupDest = $summary['groupDest'];
    $displayStatus = $summary['displayStatus'];
    $statusClass = $summary['statusClass'];
    $groupAgentName = $summary['groupAgentName'];
    $lotCount = $summary['lotCount'];

    $sourceName = htmlspecialchars($pvNames[intval($groupSource)] ?? ($groupSource ?? 'N/A'));
    $destName = htmlspecialchars($pvNames[intval($groupDest)] ?? ($groupDest ?? 'N/A'));
    $borderau = htmlspecialchars($groupId);

    $outputRows .= '<tr>';
    $outputRows .= '<td class="small">' . $borderau . '</td>';
    $outputRows .= '<td><span class="badge bg-secondary">' . $sourceName . '</span></td>';
    $outputRows .= '<td><span class="badge bg-success">' . $destName . '</span></td>';
    $outputRows .= '<td><span class="badge ' . $statusClass . '">' . $displayStatus . '</span></td>';
    $outputRows .= '<td class="text-center"><span class="">' . $lotCount . '</span></td>';
    $outputRows .= '<td>' . htmlspecialchars($groupAgentName) . '</td>';
    $outputRows .= '<td class="text-end">'
        . ' <div class="dropdown">'
        . ' <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">'
        . ' <i class="bi bi-chevron-down"></i>'
        . ' </button>'
        . ' <ul class="dropdown-menu dropdown-menu-end shadow">'
            . ' <li><a class="dropdown-item" href="view_group_transfer.php?id=' . urlencode($groupId) . '&redirect=liste_transfer_dons.php"><i class="bi bi-eye me-2"></i>Voir</a></li>'
            . ' <li><a class="dropdown-item" href="print_transfert.php?id=' . urlencode($groupId) . '&origin=dons" target="_blank"><i class="bi bi-printer me-2"></i>Imprimer</a></li>';
    if ($summary['groupStatus'] === 'Envoyé') {
        $outputRows .= '<li><form method="POST" action="save_group_transfer.php">'
            . ' <input type="hidden" name="action" value="receive">'
            . ' <input type="hidden" name="group_id" value="' . htmlspecialchars($groupId) . '">'
            . ' <input type="hidden" name="redirect" value="liste_transfer_dons.php">'
            . ' <button type="submit" class="dropdown-item text-primary"><i class="bi bi-check2-circle me-2"></i>Marquer reçu</button>'
            . ' </form></li>';
    }
    if ($isAdmin) {
        $outputRows .= '<li><hr class="dropdown-divider"></li>'
            . ' <li><form method="POST" action="liste_transfer_dons.php" onsubmit="return confirm(\'Supprimer ce transfert ?\');">'
            . ' <input type="hidden" name="group_id" value="' . htmlspecialchars($groupId) . '">'
            . ' <button type="submit" name="btn_delete_transfert" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Supprimer</button>'
            . ' </form></li>';
    }
    $outputRows .= '</ul>'
        . ' </div>'
        . '</td>';
    $outputRows .= '</tr>';
}

if ($outputRows === '') {
    echo '<tr><td colspan="7" class="text-center text-muted">Aucun transfert de don trouvé.</td></tr>';
    return;
}

echo $outputRows;

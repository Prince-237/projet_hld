<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

$groupTransfersFile = __DIR__ . '/../data/group_transfers.json';
$groups = [];
if (file_exists($groupTransfersFile)) {
    $loaded = json_decode(file_get_contents($groupTransfersFile), true);
    if (is_array($loaded)) {
        $groups = $loaded;
    }
}

$pointVentes = $pdo->query("SELECT id_point_vente, nom_point_vente FROM PointVente")->fetchAll(PDO::FETCH_ASSOC);
$pvNames = [];
foreach ($pointVentes as $pv) {
    $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
}

$search = trim($_GET['search'] ?? '');
$sourceFilter = trim($_GET['source'] ?? '');
$destinationFilter = trim($_GET['destination'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$agentFilter = trim($_GET['agent'] ?? '');
$dateStart = trim($_GET['dateStart'] ?? '');
$dateEnd = trim($_GET['dateEnd'] ?? '');

$filtered = array_filter($groups, function ($group) use ($search, $sourceFilter, $destinationFilter, $statusFilter, $agentFilter, $dateStart, $dateEnd, $pvNames) {
    if ($sourceFilter !== '' && strval($group['id_source']) !== strval($sourceFilter)) {
        return false;
    }
    if ($destinationFilter !== '' && strval($group['id_destination']) !== strval($destinationFilter)) {
        return false;
    }
    if ($statusFilter !== '' && isset($group['status']) && strval($group['status']) !== $statusFilter) {
        return false;
    }
    if ($agentFilter !== '' && (!isset($group['created_by']) || strval($group['created_by']) !== strval($agentFilter))) {
        return false;
    }
    if ($dateStart !== '' || $dateEnd !== '') {
        $createdAt = isset($group['created_at']) ? strtotime($group['created_at']) : null;
        if ($createdAt === false || $createdAt === null) {
            return false;
        }
        if ($dateStart !== '' && $createdAt < strtotime($dateStart . ' 00:00:00')) {
            return false;
        }
        if ($dateEnd !== '' && $createdAt > strtotime($dateEnd . ' 23:59:59')) {
            return false;
        }
    }
    if ($search !== '') {
        $needle = mb_strtolower($search, 'UTF-8');
        $haystack = [];
        $haystack[] = $group['id'] ?? '';
        $haystack[] = $group['agent_name'] ?? '';
        $haystack[] = $group['status'] ?? '';
        $haystack[] = $pvNames[$group['id_source']] ?? '';
        $haystack[] = $pvNames[$group['id_destination']] ?? '';
        if (isset($group['items']) && is_array($group['items'])) {
            foreach ($group['items'] as $item) {
                $haystack[] = $item['label'] ?? '';
            }
        }
        $haystackString = mb_strtolower(implode(' ', $haystack), 'UTF-8');
        if (mb_strpos($haystackString, $needle) === false) {
            return false;
        }
    }

    return true;
});

usort($filtered, function ($a, $b) {
    $dateA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $dateB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $dateB <=> $dateA;
});

if (empty($filtered)) {
    echo '<tr><td colspan="8" class="text-center text-muted">Aucun transfert reçu.</td></tr>';
    return;
}

foreach ($filtered as $group) {
    $totalGeneral = 0;
    if (isset($group['items']) && is_array($group['items'])) {
        foreach ($group['items'] as $item) {
            $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : (isset($item['prix_achat']) ? floatval($item['prix_achat']) : 0);
            $totalGeneral += $prixVente * intval($item['qty']);
        }
    }
    $statusClass = (isset($group['status']) && $group['status'] === 'Reçu') ? 'badge bg-success' : 'badge bg-info text-dark';
    $sourceName = htmlspecialchars($pvNames[$group['id_source']] ?? $group['id_source']);
    $destinationName = htmlspecialchars($pvNames[$group['id_destination']] ?? $group['id_destination']);
    $agentName = htmlspecialchars($group['agent_name'] ?? 'N/A');
    $createdAt = htmlspecialchars($group['created_at'] ?? 'N/A');
    $groupId = htmlspecialchars($group['id'] ?? '');

    echo '<tr>';
    echo '<td>' . $groupId . '</td>';
    echo '<td>' . $sourceName . '</td>';
    echo '<td>' . $destinationName . '</td>';
    echo '<td>' . $agentName . '</td>';
    echo '<td><span class="' . $statusClass . '">' . htmlspecialchars($group['status'] ?? '') . '</span></td>';
    echo '<td>' . $createdAt . '</td>';
    echo '<td class="text-end">' . number_format($totalGeneral, 0, '.', ' ') . ' FCFA</td>';
    echo '<td class="text-end">';
    echo '<div class="dropdown">';
    echo '<button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">';
    echo '<i class="bi bi-chevron-down"></i>';
    echo '</button>';
    echo '<ul class="dropdown-menu dropdown-menu-end shadow">';
    echo '<li><a class="dropdown-item" href="view_group_transfer.php?id=' . urlencode($group['id'] ?? '') . '&redirect=liste_transferts.php"><i class="bi bi-eye me-2"></i>Voir</a></li>';
    echo '<li><a class="dropdown-item" href="print_transfert.php?id=' . urlencode($group['id'] ?? '') . '&origin=transferts" target="_blank"><i class="bi bi-printer me-2"></i>Imprimer</a></li>';
    if (isset($group['status']) && $group['status'] === 'Envoyé') {
        echo '<li>';
        echo '<form method="POST" action="save_group_transfer.php" class="m-0">';
        echo '<input type="hidden" name="action" value="receive">';
        echo '<input type="hidden" name="group_id" value="' . htmlspecialchars($group['id'] ?? '') . '">';
        echo '<input type="hidden" name="redirect" value="liste_transferts.php">';
        echo '<button type="submit" class="dropdown-item text-primary"><i class="bi bi-check2-circle me-2"></i>Marquer reçu</button>';
        echo '</form>';
        echo '</li>';
    }
    echo '<li><a class="dropdown-item" href="edit_group_transfer.php?id=' . urlencode($group['id'] ?? '') . '&redirect=liste_transferts.php"><i class="bi bi-pencil me-2"></i>Modifier</a></li>';
    echo '<li><hr class="dropdown-divider"></li>';
    echo '<li><form method="POST" action="save_group_transfer.php" class="m-0" onsubmit="return confirm(\'Supprimer ce transfert ?\');">';
    echo '<input type="hidden" name="action" value="delete">';
    echo '<input type="hidden" name="group_id" value="' . htmlspecialchars($group['id'] ?? '') . '">';
    echo '<input type="hidden" name="redirect" value="liste_transferts.php">';
    echo '<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Supprimer</button>';
    echo '</form></li>';
    echo '</ul>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

$sourceFilter = $_GET['source'] ?? '';
$destinationFilter = $_GET['destination'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$lotFilter = $_GET['lot'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';

// Historique des sorties (uniquement Achats)
$sqlSorties = "SELECT t.id_transfert, t.num_bordereau,
                      td.quantite_transfert,
                      l.num_lot,
                      p.nom_medicament, p.type_produit,
                      u.nom_complet AS utilisateur,
                      pv_src.nom_point_vente as source_nom,
                      pv_dest.nom_point_vente as dest_nom
               FROM Transfert t
               JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
               JOIN StockLot l ON td.id_lot = l.id_lot
               JOIN Produit p ON l.id_produit = p.id_produit
               LEFT JOIN PointVente pv_src ON t.id_source = pv_src.id_point_vente
               LEFT JOIN PointVente pv_dest ON t.id_destination = pv_dest.id_point_vente
               LEFT JOIN Utilisateur u ON t.id_user = u.id_user
               LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
               LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
               LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
               WHERE part.type = 'Fournisseur'";

$params = [];

if (!empty($sourceFilter)) {
    $sqlSorties .= " AND t.id_source = ?";
    $params[] = $sourceFilter;
}
if (!empty($destinationFilter)) {
    $sqlSorties .= " AND t.id_destination = ?";
    $params[] = $destinationFilter;
}
if (!empty($agentFilter)) {
    $sqlSorties .= " AND u.id_user = ?";
    $params[] = $agentFilter;
}
if (!empty($lotFilter)) {
    $sqlSorties .= " AND l.id_lot = ?";
    $params[] = $lotFilter;
}
if (!empty($dateFilterStart)) {
    $sqlSorties .= " AND DATE(STR_TO_DATE(SUBSTRING(t.num_bordereau, 4, 14), '%Y%m%d%H%i%s')) >= ?";
    $params[] = $dateFilterStart;
}
if (!empty($dateFilterEnd)) {
    $sqlSorties .= " AND DATE(STR_TO_DATE(SUBSTRING(t.num_bordereau, 4, 14), '%Y%m%d%H%i%s')) <= ?";
    $params[] = $dateFilterEnd;
}
$sqlSorties .= " ORDER BY t.id_transfert DESC";

$stmtSorties = $pdo->prepare($sqlSorties);
$stmtSorties->execute($params);
$sorties_achats = $stmtSorties->fetchAll();

if (empty($sorties_achats)) {
    echo '<tr><td colspan="9" class="text-center text-muted">Aucune sortie enregistrée.</td></tr>';
    return;
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

foreach ($sorties_achats as $s): ?>
    <tr>
        <td class="small"><?= htmlspecialchars($s['num_bordereau']) ?></td>
        <td><?= htmlspecialchars($s['nom_medicament']) ?></td>
        <td><?= htmlspecialchars($s['type_produit']) ?></td>
        <td><?= htmlspecialchars($s['num_lot']) ?></td>
        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['source_nom'] ?? '?') ?></span></td>
        <td><span class="badge bg-success"><?= htmlspecialchars($s['dest_nom'] ?? '?') ?></span></td>
        <td><?= htmlspecialchars($s['quantite_transfert']) ?></td>
        <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? htmlspecialchars($s['utilisateur']) : '-' ?></td>
        <td class="text-nowrap">
            <?php if($isAdmin): ?>
                <form method="POST" action="sorties.php" class="d-inline" onsubmit="return confirm('Supprimer cette sortie ?');">
                    <input type="hidden" name="id_transfert" value="<?= htmlspecialchars($s['id_transfert']) ?>">
                    <button type="submit" name="btn_delete_transfert" class="btn btn-sm btn-outline-danger" title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
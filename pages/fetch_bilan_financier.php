<?php
require_once '../config/db.php';
header('Content-Type: text/html; charset=utf-8');

$year = intval($_GET['y'] ?? date('Y'));
$month = intval($_GET['m'] ?? date('n'));
$pointId = isset($_GET['pv']) && $_GET['pv'] !== '' ? intval($_GET['pv']) : null;

$sql = "
SELECT 
    pv.id_point_vente, 
    pv.nom_point_vente,
    COALESCE(stats.total_ventes, 0) as chiffre_affaires,
    COALESCE(stats.total_cout, 0) as cout_achat,
    (COALESCE(stats.total_ventes, 0) - COALESCE(stats.total_cout, 0)) as benefice
FROM PointVente pv
LEFT JOIN (
    SELECT 
        t.id_source,
        SUM(td.quantite_transfert * (IF(l.prix_achat_ttc > 0, l.prix_achat_ttc, COALESCE(p.prix_unitaire, 0)) * (1 + (COALESCE(p.marge_pourcentage, 0) / 100)))) as total_ventes,
        SUM(td.quantite_transfert * COALESCE(l.prix_achat_ttc, 0)) as total_cout
    FROM Transfert t
    JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
    JOIN StockLot l ON td.id_lot = l.id_lot
    JOIN Produit p ON l.id_produit = p.id_produit
    WHERE t.num_bordereau LIKE CONCAT('TR-', :year, LPAD(:month, 2, '0'), '%')
    GROUP BY t.id_source
) stats ON pv.id_point_vente = stats.id_source
ORDER BY pv.nom_point_vente ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':year' => $year, ':month' => $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_global = 0.0;
$filtered = [];
foreach ($rows as $r) {
    $total_global += floatval($r['benefice']);
    if ($pointId === null || $pointId == $r['id_point_vente']) $filtered[] = $r;
}

// Requête pour les statistiques par produit
$sql_prods = "
    SELECT p.nom_medicament, p.type_produit, 
           SUM(td.quantite_transfert) as total_qte, 
           SUM(td.quantite_transfert * (IF(l.prix_achat_ttc > 0, l.prix_achat_ttc, COALESCE(p.prix_unitaire, 0)) * (1 + (COALESCE(p.marge_pourcentage, 0) / 100)))) as total_valeur
    FROM Transfert t
    JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
    JOIN StockLot l ON td.id_lot = l.id_lot
    JOIN Produit p ON l.id_produit = p.id_produit
    WHERE t.num_bordereau LIKE CONCAT('TR-', :year, LPAD(:month, 2, '0'), '%')
";
$params_prods = [':year' => $year, ':month' => $month];
if ($pointId !== null) {
    $sql_prods .= " AND t.id_source = :pv";
    $params_prods[':pv'] = $pointId;
}
$sql_prods .= " GROUP BY p.id_produit ORDER BY total_qte DESC";
$stmt_prods = $pdo->prepare($sql_prods);
$stmt_prods->execute($params_prods);
$stats_produits = $stmt_prods->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Point de vente</th>
                            <th class="text-end">Chiffre d'Affaires (Ventes)</th>
                            <th class="text-end">Prix d'Achat</th>
                            <th class="text-end fw-bold">Bénéfice Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nom_point_vente']) ?></td>
                                <td class="text-end"><?= number_format($r['chiffre_affaires'], 0, ',', ' ') ?> FCFA</td>
                                <td class="text-end text-muted"><?= number_format($r['cout_achat'], 0, ',', ' ') ?> FCFA</td>
                                <td class="text-end fw-bold text-success"><?= number_format($r['benefice'], 0, ',', ' ') ?> FCFA</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-dark">
                            <th colspan="3">Total Bénéfice Global (Période sélectionnée)</th>
                            <th class="text-end"><?= number_format($total_global, 0, ',', ' ') ?> FCFA</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Classement des produits les plus sortis</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Type</th>
                            <th class="text-center">Quantité Sortie</th>
                            <th class="text-end">Montant Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_produits as $sp): ?>
                            <tr>
                                <td><?= htmlspecialchars($sp['nom_medicament']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($sp['type_produit']) ?></span></td>
                                <td class="text-center fw-bold"><?= $sp['total_qte'] ?></td>
                                <td class="text-end"><?= number_format($sp['total_valeur'], 0, ',', ' ') ?> FCFA</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats_produits)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Aucune sortie enregistrée pour cette période.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
// pages/bilan_financier.php
// Bilan financier mensuel par point de vente

require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }

$year = intval($_GET['y'] ?? date('Y'));
$month = intval($_GET['m'] ?? date('n'));
$pointId = isset($_GET['pv']) && $_GET['pv'] !== '' ? intval($_GET['pv']) : null;

// Génère liste des 24 mois précédents
$months = [];
for ($i = 0; $i < 24; $i++) {
    $ts = strtotime(sprintf('-%d months', $i));
    $months[] = ['year' => (int)date('Y', $ts), 'month' => (int)date('n', $ts), 'label' => date('F Y', $ts)];
}

// Requête principale — Utilisation de Transfert, TransfertDetail, StockLot, Produit
// On calcule le Chiffre d'Affaires théorique basé sur (Prix Achat * (1 + Marge))
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
        -- Si prix_achat_ttc est 0 (Don), on utilise le prix_unitaire du produit comme base
        SUM(td.quantite_transfert * (IF(l.prix_achat_ttc > 0, l.prix_achat_ttc, COALESCE(p.prix_unitaire, 0)) * (1 + (COALESCE(p.marge_pourcentage, 0) / 100)))) as total_ventes,
        SUM(td.quantite_transfert * COALESCE(l.prix_achat_ttc, 0)) as total_cout
    FROM Transfert t
    JOIN TransfertDetail td ON t.id_transfert = td.id_transfert
    JOIN StockLot l ON td.id_lot = l.id_lot
    JOIN Produit p ON l.id_produit = p.id_produit
    -- On utilise num_bordereau pour extraire la date si pas de colonne date_transfert, 
    -- ou on suppose que l'ID est sequentiel pour l'instant.
    -- FIX : Pour faire propre, filtrer sur les IDs si on a pas de date, ou supposer date création via logs.
    -- NEW SQL: Transfert n'a pas de date... Utilisons une astuce ou ajoutons la colonne.
    -- Astuce : On suppose que num_bordereau commence par TR-YYYYMM...
    WHERE t.num_bordereau LIKE CONCAT('TR-', :year, LPAD(:month, 2, '0'), '%')
    GROUP BY t.id_source
) stats ON pv.id_point_vente = stats.id_source
ORDER BY pv.nom_point_vente ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':year' => $year, ':month' => $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour les statistiques par produit (Top sorties)
$sql_prods = "
    SELECT p.nom_medicament, p.type_produit, 
           SUM(td.quantite_transfert) as total_qte, 
           -- Même logique : fallback sur prix_unitaire si prix_achat_ttc est 0
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

$total_global = 0.0;
$filtered = [];
foreach ($rows as $r) {
    $total_global += floatval($r['benefice']);
    if ($pointId === null || $pointId == $r['id_point_vente']) $filtered[] = $r;
}

$pvs = $pdo->query("SELECT id_point_vente, nom_point_vente FROM PointVente ORDER BY nom_point_vente ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/sidebar.php'; ?>
<div class="container mt-4">
  <h2 class="mb-4">Bilan Financier — <?= htmlspecialchars(date('F', mktime(0,0,0,$month,1,$year)))." ". $year ?></h2>

  <form method="get" class="row g-3 mb-4 bg-white p-3 shadow-sm rounded">
    <div class="col-md-3">
      <label class="form-label">Mois</label>
      <select name="m" class="form-select">
        <?php foreach ($months as $m): ?>
          <option value="<?= $m['month'] ?>" <?= ($m['month']==$month && $m['year']==$year) ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Année</label>
      <select name="y" class="form-select">
        <?php for ($y = date('Y'); $y >= date('Y')-2; $y--): ?>
          <option value="<?= $y ?>" <?= ($y==$year) ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Point de vente</label>
      <select name="pv" class="form-select">
        <option value="">Tous</option>
        <?php foreach ($pvs as $pv): ?>
          <option value="<?= $pv['id_point_vente'] ?>" <?= ($pointId == $pv['id_point_vente']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($pv['nom_point_vente']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Voir</button>
    </div>
  </form>

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

  <div class="card shadow-sm mt-4">
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
<?php include '../includes/footer.php'; ?>

<?php
require_once '../config/db.php';

if (!isset($_GET['id'])) exit("ID manquant");
$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT cd.quantite_voulue, p.nom_medicament, p.prix_unitaire 
                       FROM CommandeDetail cd 
                       JOIN Produit p ON cd.id_produit = p.id_produit 
                       WHERE cd.id_commande = ?");
$stmt->execute([$id]);
$details = $stmt->fetchAll();

if (!$details) { echo "Aucun produit trouvé."; exit; }

echo '<table class="table table-sm table-bordered">';
echo '<thead class="table-light"><tr><th>Produit</th><th class="text-center">Qté</th><th class="text-end">P.U</th><th class="text-end">Total</th></tr></thead>';
echo '<tbody>';
$totalGeneral = 0;
foreach ($details as $d) {
    $total = $d['quantite_voulue'] * $d['prix_unitaire'];
    $totalGeneral += $total;
    echo "<tr>
            <td>" . htmlspecialchars($d['nom_medicament']) . "</td>
            <td class='text-center'>{$d['quantite_voulue']}</td>
            <td class='text-end'>" . number_format($d['prix_unitaire'], 0, '.', ' ') . "</td>
            <td class='text-end'>" . number_format($total, 0, '.', ' ') . "</td>
          </tr>";
}
echo '</tbody>';
echo '<tfoot class="fw-bold"><tr class="table-info"><td colspan="3" class="text-end">TOTAL ESTIMÉ</td>';
echo '<td class="text-end">' . number_format($totalGeneral, 0, '.', ' ') . ' FCFA</td></tr></tfoot>';
echo '</table>';
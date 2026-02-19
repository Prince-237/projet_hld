<?php
// 1. Inclusion des fichiers de configuration
require_once('config/db.php');
include('includes/header.php');

/**
 * REQUÊTE COMPLEXE :
 * - On récupère les informations du produit.
 * - On calcule le stock actuel total (somme de tous les lots).
 * - On calcule la consommation des 90 derniers jours (trimestre).
 * - On définit le seuil dynamique (Consommation trimestrielle / 3).
 */
$sql = "SELECT 
            p.id_produit, 
            p.nom_medicament, 
            p.forme, 
            p.dosage,
            IFNULL(SUM(l.quantite_actuelle), 0) as stock_total,
            (SELECT IFNULL(SUM(s.quantite_sortie), 0) 
             FROM sorties s 
             INNER JOIN stock_lots sl ON s.id_lot = sl.id_lot
             WHERE sl.id_produit = p.id_produit 
             AND s.date_sortie >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ) as conso_trimestre
        FROM produits p
        LEFT JOIN stock_lots l ON p.id_produit = l.id_produit
        GROUP BY p.id_produit";

$stmt = $pdo->query($sql);
$inventaire = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2 class="mb-4 text-primary"><i class="fas fa-boxes"></i> État du Stock et Seuils Dynamiques</h2>
    
    <div class="alert alert-info">
        <strong>Note :</strong> Le seuil d'alerte est calculé automatiquement (1/3 de la consommation des 90 derniers jours).
    </div>

    <div class="table-responsive">
        <table class="table table-hover bg-white shadow-sm rounded">
            <thead class="table-dark">
                <tr>
                    <th>Médicament</th>
                    <th>Forme & Dosage</th>
                    <th>Stock Actuel</th>
                    <th>Conso. (90j)</th>
                    <th>Seuil Calculé</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventaire as $item): 
                    // Calcul du seuil : 1/3 de la consommation trimestrielle
                    $seuil_dynamique = ceil($item['conso_trimestre'] / 3);
                    
                    // Si le produit n'a jamais été vendu, on peut mettre un seuil minimum de sécurité (ex: 5)
                    if($seuil_dynamique == 0) $seuil_dynamique = 5;

                    // Détermination de l'alerte
                    $classe_stock = "";
                    $badge = "";

                    if ($item['stock_total'] <= 0) {
                        $classe_stock = "table-danger";
                        $badge = "<span class='badge bg-danger text-white'>Rupture de stock</span>";
                    } elseif ($item['stock_total'] <= $seuil_dynamique) {
                        $classe_stock = "table-warning";
                        $badge = "<span class='badge bg-warning text-dark'>Alerte : Seuil atteint</span>";
                    } else {
                        $badge = "<span class='badge bg-success'>Stock Correct</span>";
                    }
                ?>
                    <tr class="<?= $classe_stock ?>">
                        <td><strong><?= htmlspecialchars($item['nom_medicament']) ?></strong></td>
                        <td><?= htmlspecialchars($item['forme']) ?> (<?= htmlspecialchars($item['dosage']) ?>)</td>
                        <td><?= $item['stock_total'] ?></td>
                        <td><?= $item['conso_trimestre'] ?></td>
                        <td><?= $seuil_dynamique ?></td>
                        <td><?= $badge ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('includes/footer.php'); ?>
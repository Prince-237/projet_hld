<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

if ($isAdmin && isset($_POST['btn_seuil'])) {
    // Calcul : Somme des sorties des 90 derniers jours / 3
    $sql = "UPDATE produits p SET seuil_alerte = (
        SELECT IFNULL(SUM(s.quantite_sortie)/3, 0)
        FROM sorties s 
        JOIN stock_lots l ON s.id_lot = l.id_lot
        WHERE l.id_produit = p.id_produit 
        AND s.date_sortie >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    )";
    $pdo->query($sql);
    $msg = "Calcul des seuils terminé.";
}

$inventaire = $pdo->query("SELECT * FROM produits ORDER BY stock_total ASC")->fetchAll();
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between mb-4">
        <h2>État des Stocks & Seuils</h2>
        <?php if($isAdmin): ?>
            <form method="POST"><button name="btn_seuil" class="btn btn-warning shadow-sm">Recalculer les Seuils (Conso/3)</button></form>
        <?php endif; ?>
    </div>

    <table class="table table-bordered">
        <thead><tr class="table-secondary"><th>Produit</th><th>Stock Total</th><th>Seuil Alerte (Dynamique)</th><th>Statut</th></tr></thead>
        <tbody>
            <?php foreach($inventaire as $i): ?>
                <tr>
                    <td><?= $i['nom_medicament'] ?></td>
                    <td><?= $i['stock_total'] ?></td>
                    <td><?= round($i['seuil_alerte'], 1) ?></td>
                    <td>
                        <?php if($i['stock_total'] <= $i['seuil_alerte']): ?>
                            <span class="badge bg-danger">A commander</span>
                        <?php else: ?>
                            <span class="badge bg-success">Correct</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
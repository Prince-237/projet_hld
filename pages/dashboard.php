<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: /index.php"); exit(); }

// 1. Calcul des statistiques
// A. Nombre de produits total
$nb_produits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();

// B. Alertes de stock (Stock Total <= Seuil Alerte)
$sql_alerte = "SELECT COUNT(*) FROM produits WHERE stock_total <= seuil_alerte AND stock_total > 0";
$nb_alerte = $pdo->query($sql_alerte)->fetchColumn();

// C. Ruptures de stock (Stock Total = 0)
$nb_rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_total = 0")->fetchColumn();

// D. Produits périmés (On compare la date d'aujourd'hui avec date_expiration dans stock_lots)
$nb_perime = $pdo->query("SELECT COUNT(*) FROM stock_lots WHERE date_expiration <= CURRENT_DATE AND quantite_actuelle > 0")->fetchColumn();
?>

<?php include '../includes/header.php'; ?>

<div class="container mt-4">
    <h2 class="mb-4">Tableau de Bord - Hôpital Laquintinie</h2>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <h6>Total Produits</h6>
                    <h2><?= $nb_produits ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark shadow">
                <div class="card-body">
                    <h6>Stocks Critiques</h6>
                    <h2><?= $nb_alerte ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white shadow">
                <div class="card-body">
                    <h6>Ruptures de stock</h6>
                    <h2><?= $nb_rupture ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-dark text-white shadow">
                <div class="card-body">
                    <h6>Produits Périmés</h6>
                    <h2><?= $nb_perime ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <h4>Alertes à traiter d'urgence</h4>
            <table class="table table-hover bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th>Médicament</th>
                        <th>Stock Actuel</th>
                        <th>Seuil Dynamique</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $list_alerte = $pdo->query("SELECT * FROM produits WHERE stock_total <= seuil_alerte ORDER BY stock_total ASC LIMIT 10");
                    while($row = $list_alerte->fetch()) {
                        $status = ($row['stock_total'] == 0) ? '<span class="badge bg-danger">Rupture</span>' : '<span class="badge bg-warning">Critique</span>';
                        echo "<tr>
                                <td>{$row['nom_medicament']}</td>
                                <td>{$row['stock_total']}</td>
                                <td>{$row['seuil_alerte']}</td>
                                <td>$status</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
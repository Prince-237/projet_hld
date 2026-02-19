<?php 
require_once('config/db.php'); 
include('includes/header.php'); 

// On récupère les dates depuis le formulaire, sinon par défaut les 30 derniers jours
$date_debut = $_POST['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
$date_fin = $_POST['date_fin'] ?? date('Y-m-d');

// Requête pour le graphique des sorties par jour sur la période
$sql = "SELECT DATE(date_sortie) as jour, SUM(total_prix) as total 
        FROM sorties 
        WHERE date_sortie BETWEEN :debut AND :fin
        GROUP BY DATE(date_sortie)";
$stmt = $pdo->prepare($sql);
$stmt->execute([':debut' => $date_debut, ':fin' => $date_fin]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparation des données pour JavaScript
$jours = json_encode(array_column($data, 'jour'));
$totaux = json_encode(array_column($data, 'total'));
?>

<div class="row">
    <div class="col-md-12">
        <h3>Rapports Statistiques - Direction</h3>
        <form method="POST" class="row g-3 bg-white p-3 shadow-sm rounded mb-4">
            <div class="col-md-4">
                <label>Date de début</label>
                <input type="date" name="date_debut" class="form-control" value="<?= $date_debut ?>">
            </div>
            <div class="col-md-4">
                <label>Date de fin</label>
                <input type="date" name="date_fin" class="form-control" value="<?= $date_fin ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Générer le rapport</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <canvas id="myChart"></canvas> </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('myChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo $jours; ?>,
        datasets: [{
            label: 'Chiffre d\'affaires par jour (FCFA)',
            data: <?php echo $totaux; ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.5)'
        }]
    }
});
</script>

<?php include('includes/footer.php'); ?>
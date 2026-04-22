<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../includes/sidebar.php';

// 🔹 MESSAGES DE SUCCÈS
$message = '';
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . htmlspecialchars($_SESSION['success_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    unset($_SESSION['success_message']);
}

// 🔹 Vérifier que la table existe
try {
    $checkTable = "SHOW TABLES LIKE 'RetourFournisseur'";
    $res = $pdo->query($checkTable);
    if ($res->rowCount() == 0) {
        die("Table RetourFournisseur non trouvée. Veuillez d'abord créer les tables de retours en exécutant le script SQL.");
    }
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// 🔹 RÉCUPÉRER LES FOURNISSEURS POUR FILTRE
$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite 
                                   FROM Partenaire 
                                   WHERE type = 'Fournisseur' 
                                   ORDER BY nom_entite");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

// 🔹 RÉCUPÉRER LES AGENTS POUR FILTRE
$stmtAgents = $pdo->prepare("SELECT DISTINCT u.id_user, u.nom_complet 
                             FROM RetourFournisseur rf 
                             LEFT JOIN Utilisateur u ON rf.id_user = u.id_user 
                             ORDER BY u.nom_complet");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

?>


<div class="container mt-4">
    <h2 class="mb-4">Liste des retours fournisseur</h2>

    <?php if($message): echo $message; endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="searchForm" class="m-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Date début</label>
                        <input type="date" id="dateFilterStart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date fin</label>
                        <input type="date" id="dateFilterEnd" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fournisseur</label>
                        <select id="fournisseurFilter" class="form-select">
                            <option value="">Tous les fournisseurs</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <option value="<?= htmlspecialchars($f['nom_entite']) ?>"><?= htmlspecialchars($f['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Agent</label>
                        <select id="agentFilter" class="form-select">
                            <option value="">Tous les agents</option>
                            <?php foreach ($agents as $a): ?>
                                <option value="<?= htmlspecialchars($a['nom_complet']) ?>"><?= htmlspecialchars($a['nom_complet']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <div class="table-responsive p-2">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Fournisseur</th>
                            <th>Agent</th>
                            <th>Lots</th>
                            <th>Commentaire</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted">Chargement...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/liste_retours_fournisseur.js" defer></script>
<?php include '../includes/footer.php'; ?>
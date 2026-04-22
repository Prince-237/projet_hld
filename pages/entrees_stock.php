<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
include '../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin');
 
// 🔹 MESSAGES DE SUCCÈS
$message = '';
if (isset($_SESSION['success_message'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . htmlspecialchars($_SESSION['success_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    unset($_SESSION['success_message']);
}
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// 🔹 récupérer les agents (admins uniquement)
$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

// 🔹 récupérer les fournisseurs
$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Fournisseur' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

?>

<div class="container mt-4">
    <h2 class="mb-4">Liste des entrées en stock</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="searchForm" class="m-3">
            <div class="row g-2">

                <!-- TYPE -->
                <div class="col-md-2">
                    <label class="form-label">Type</label>                     
                    <select id="typeFilter" class="form-select" name="type">
                        <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                        <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                    </select>
                </div>

                <!-- RECHERCHE -->
                <!-- <div class="col-md-2">
                    <label class="form-label">Recherche</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Fournisseur, agent...">
                </div> -->

                <!-- FOURNISSEUR -->
                <div class="col-md-3">
                    <label class="form-label">Fournisseur</label>
                    <select id="fournisseurFilter" class="form-select">
                        <option value="">Tous les fournisseurs</option>
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <option value="<?= $fournisseur['id_partenaire'] ?>"><?= htmlspecialchars($fournisseur['nom_entite']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DATE -->
                <div class="col-md-3">
                    <label class="form-label">Date (du)</label>
                    <input type="date" id="dateFilterStart" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date (au)</label>
                    <input type="date" id="dateFilterEnd" class="form-control">
                </div>

                <!-- AGENT -->

                <div class="col-md-3">
                    <label class="form-label">Agent</label>
                    <select id="agentFilter" class="form-select">

                        <option value="">Tous les agents</option>

                        <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id_user'] ?>">
                                <?= htmlspecialchars($agent['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
            </div>
        </form>

        <div class="table-responsive p-2">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date commande</th>
                        <th>Fournisseur</th>
                        <th>Nombre de produits</th>
                        <th>Total</th>
                        <th>Agent</th>
                        <th>Statut</th>
                        <th>Statut Paiement</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>

                <tbody id="tableBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted">Chargement...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>

<script src="../assets/js/entrees_stock.js" defer></script>

<?php include '../includes/footer.php'; ?>
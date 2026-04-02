<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
include '../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';

$sql = "SELECT 
            cmd.id_commande,
            cmd.date_commande,
            part.nom_entite AS fournisseur,
            part.type AS source_type,
            u.nom_complet AS utilisateur,
            COUNT(l.id_lot) AS nb_produits,
            SUM(l.prix_achat_ttc * l.quantite_actuelle) AS total_commande
        FROM StockLot l
        JOIN Produit p ON l.id_produit = p.id_produit
        LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
        LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
        LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
        LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user
        WHERE p.type_produit = ?
        AND cmd.statut = 'Reçue'
        AND cmd.deleted_at IS NULL
        GROUP BY cmd.id_commande
        ORDER BY cmd.date_commande DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$typeProduit]);
$lots = $stmt->fetchAll();



// 🔹 récupérer les agents (admins uniquement)
$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

// 🔹 récupérer les fournisseurs
$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Fournisseur' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Liste des entrées en stock</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <div class="card shadow-sm mb-4">
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
                <div class="col-md-2">
                    <label class="form-label">Recherche</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Fournisseur, agent...">
                </div>

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
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>

                <tbody id="tableBody">
                    <?php if (empty($lots)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Aucune entrée en stock.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lots as $lot): ?>
                            <tr>
                                <td><?= $lot['date_commande'] ? date('d/m/Y H:i', strtotime($lot['date_commande'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($lot['fournisseur'] ?: 'N/A') ?></td>
                                <td class="text-center"><?= $lot['nb_produits'] ?></td>
                                <td class="text-end"><?= number_format($lot['total_commande'], 2, '.', ' ') ?> F</td>
                                <td><?= htmlspecialchars($lot['utilisateur'] ?: '-') ?></td>

                                <td class="text-nowrap">

                                    <!-- Voir -->
                                    <a href="details_commande_recue.php?id=<?= $lot['id_commande'] ?>"
                                        class="btn btn-sm btn-outline-primary me-1"
                                        title="Consulter">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if ($isAdmin): ?>

                                        <!-- Modifier -->
                                        <a href="edit_commande.php?id=<?= $lot['id_commande'] ?>"
                                            class="btn btn-sm btn-outline-primary me-1"
                                            title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Supprimer -->
                                        <form method="POST" action="delete_commande.php" class="d-inline"
                                            onsubmit="return confirm('Supprimer cette commande ?');">

                                            <input type="hidden" name="id_commande" value="<?= $lot['id_commande'] ?>">

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <!-- Les données filtrées seront chargées dynamiquement via fetch_entrees_stock.php -->
            </table>
        </div>
    </div>
</div>



<script src="../assets/js/entrees_stock.js" defer></script>

<?php include '../includes/footer.php'; ?>
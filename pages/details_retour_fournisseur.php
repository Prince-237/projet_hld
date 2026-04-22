<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../includes/sidebar.php';

if (!isset($_GET['id'])) {
    die("Retour invalide");
}

$id_retour = intval($_GET['id']);
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'liste_retours_fournisseur.php';

// 🔹 RÉCUPÉRER LE RETOUR
$sqlRetour = "SELECT 
                    rf.id_retour,
                    rf.id_commande,
                    rf.date_retour,
                    rf.commentaire,
                    rf.statut,
                    rf.id_user,
                    part.nom_entite AS fournisseur,
                    u.nom_complet AS agent,
                    cmd.date_commande
                FROM RetourFournisseur rf
                JOIN Commande cmd ON rf.id_commande = cmd.id_commande
                LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                LEFT JOIN Utilisateur u ON rf.id_user = u.id_user
                WHERE rf.id_retour = ?";

$stmt = $pdo->prepare($sqlRetour);
$stmt->execute([$id_retour]);
$retour = $stmt->fetch();

if (!$retour) {
    die("Retour introuvable");
}

// 🔹 RÉCUPÉRER LES LOTS RETOURNÉS
$sqlDetails = "SELECT 
                    rfd.id_retour_detail,
                    rfd.id_lot,
                    rfd.quantite_retournee,
                    l.num_lot,
                    p.nom_medicament,
                    l.date_expiration
                FROM RetourFournisseurDetail rfd
                JOIN StockLot l ON rfd.id_lot = l.id_lot
                JOIN Produit p ON l.id_produit = p.id_produit
                WHERE rfd.id_retour = ?
                ORDER BY p.nom_medicament ASC";

$stmt = $pdo->prepare($sqlDetails);
$stmt->execute([$id_retour]);
$details = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails du retour fournisseur</h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-outline-secondary">Retour</a>
    </div>

    <!-- INFOS RETOUR -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <strong>Date :</strong><br>
                    <?= date('d/m/Y H:i', strtotime($retour['date_retour'])) ?>
                </div>
                <div class="col-md-2">
                    <strong>Commande :</strong><br>
                    #<?= $retour['id_commande'] ?>
                </div>
                <div class="col-md-3">
                    <strong>Fournisseur :</strong><br>
                    <?= htmlspecialchars($retour['fournisseur'] ?: 'N/A') ?>
                </div>
                <div class="col-md-2">
                    <strong>Agent :</strong><br>
                    <?= htmlspecialchars($retour['agent'] ?: '-') ?>
                </div>
                <!-- <div class="col-md-3">
                    <strong>Statut :</strong><br>
                    <span class="badge bg-<?= $retour['statut'] === 'accepté' ? 'success' : ($retour['statut'] === 'rejeté' ? 'danger' : 'info text-dark') ?>">
                        <?= ucfirst($retour['statut']) ?>
                    </span>
                </div> -->
            </div>
        </div>
    </div>

    <!-- COMMENTAIRE COMPLET -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-warning text-dark">
            <strong>Commentaire du retour</strong>
        </div>
        <div class="card-body">
            <p class="mb-0">
                <?= nl2br(htmlspecialchars($retour['commentaire'])) ?>
            </p>
        </div>
    </div>

    <!-- LOTS RETOURNÉS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Lots retournés</strong>
        </div>

        <div class="table-responsive p-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th>Lot</th>
                        <th>Expiration</th>
                        <th class="text-center">Quantité retournée</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($details)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                Aucun lot enregistré pour ce retour
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($details as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['nom_medicament']) ?></td>
                                <td><?= htmlspecialchars($d['num_lot']) ?></td>
                                <td><?= date('d/m/Y', strtotime($d['date_expiration'])) ?></td>
                                <td class="text-center"><?= $d['quantite_retournee'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>

        <!-- ACTION -->
        <!-- <div class="mt-3 p-2">
            <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-secondary">
                ← Retour
            </a>
        </div> -->
    </div>

</div>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../includes/sidebar.php';

if (!isset($_GET['id'])) {
    die("Commande invalide");
}

$id_commande = intval($_GET['id']);


// 🔹 INFOS COMMANDE
$sqlCommande = "SELECT 
                    cmd.id_commande,
                    cmd.date_commande,
                    part.nom_entite AS fournisseur,
                    part.type AS source_type,
                    u.nom_complet AS utilisateur
                FROM Commande cmd
                LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user
                WHERE cmd.id_commande = ?";

$stmt = $pdo->prepare($sqlCommande);
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();

if (!$commande) {
    die("Commande introuvable");
}


// 🔹 PRODUITS DE LA COMMANDE
$sqlProduits = "SELECT 
                    cd.id_cmd_det,
                    cd.quantite_voulue as quantite_actuelle,
                    p.nom_medicament,
                    p.prix_unitaire as prix_achat_ttc
                FROM CommandeDetail cd
                JOIN Produit p ON cd.id_produit = p.id_produit
                WHERE cd.id_commande = ?
                ORDER BY p.nom_medicament ASC";

$stmt = $pdo->prepare($sqlProduits);
$stmt->execute([$id_commande]);
$produits = $stmt->fetchAll();

$totalGeneral = 0;
?>

<div class="container-fluid mt-4">

    <!-- <h3 class="mb-4">Détails de la commande</h3> -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails de la commande</h2>
        <a href="entrees.php" class="btn btn-outline-secondary">Retour</a>
    </div>

    <!-- INFOS COMMANDE -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row">

                <div class="col-md-3">
                    <strong>Date :</strong><br>
                    <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                </div>

                <div class="col-md-3">
                    <strong>Source :</strong><br>
                    <?= htmlspecialchars($commande['source_type'] ?: 'N/A') ?>
                </div>

                <div class="col-md-3">
                    <strong>Fournisseur :</strong><br>
                    <?= htmlspecialchars($commande['fournisseur'] ?: 'N/A') ?>
                </div>

                <div class="col-md-3">
                    <strong>Agent :</strong><br>
                    <?= htmlspecialchars($commande['utilisateur'] ?: '-') ?>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLE PRODUITS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Produits de la commande</strong>
        </div>

        <div class="table-responsive p-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th>Lot</th>
                        <th>Quantité</th>
                        <th>Expiration</th>
                        <th>Prix Achat</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                Aucun produit trouvé
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produits as $p): ?>
                            <?php
                                $total = $p['prix_achat_ttc'] * $p['quantite_actuelle'];
                                $totalGeneral += $total;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                                <td class="text-muted italic">En attente de réception</td>
                                <td class="text-end"><?= $p['quantite_actuelle'] ?></td>
                                <td class="text-muted small">-</td>
                                <td class="text-end"><?= number_format($p['prix_achat_ttc'], 2, '.', ' ') ?> F</td>
                                <td class="text-end"><?= number_format($total, 2, '.', ' ') ?> F</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <tfoot>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">TOTAL GÉNÉRAL</th>
                        <th class="text-end"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</th>
                    </tr>
                </tfoot>

            </table>
        </div>
    </div>

    <!-- ACTIONS -->
    <!-- <div class="mt-3 d-flex justify-content-between">
        <a href="entrees_stock.php" class="btn btn-secondary">
            ← Retour
        </a>

        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Imprimer
        </button>
    </div> -->

</div>

<?php include '../includes/footer.php'; ?>
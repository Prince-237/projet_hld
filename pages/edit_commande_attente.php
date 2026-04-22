<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("Commande invalide");
}

$id_commande = intval($_GET['id']);
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'entrees_stock.php';


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
foreach ($produits as $p) {
    $totalGeneral += $p['prix_achat_ttc'] * $p['quantite_actuelle'];
}


// 🔹 UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo->beginTransaction();

        foreach ($_POST['produits'] as $id_cmd_det => $data) {

            $qte = intval($data['quantite']);
            $prix = floatval($data['prix']);

            $update = $pdo->prepare("
                UPDATE CommandeDetail 
                SET quantite_voulue = ?
                WHERE id_cmd_det = ?
            ");
            $update->execute([$qte, $id_cmd_det]);

            // Optionnel : mettre à jour le prix dans Produit si nécessaire, mais probablement pas
            // Ici, on ne modifie que la quantité, le prix reste de Produit
        }

        $pdo->commit();

        header("Location: " . $redirect);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur : " . $e->getMessage());
    }
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">

    <!-- <h3 class="mb-4">Détails de la commande</h3> -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Modifier la commande en attente</h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-outline-secondary">Retour</a>
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

        <form method="POST">
            <div class="table-responsive p-2">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Prix Achat</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($produits)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    Aucun produit trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $p): ?>
                                <?php
                                    $total = $p['prix_achat_ttc'] * $p['quantite_actuelle'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                                    <td class="text-end">
                                        <input type="number"
                                               name="produits[<?= $p['id_cmd_det'] ?>][quantite]"
                                               value="<?= $p['quantite_actuelle'] ?>"
                                               class="form-control" required min="1">
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="0.01"
                                               name="produits[<?= $p['id_cmd_det'] ?>][prix]"
                                               value="<?= $p['prix_achat_ttc'] ?>"
                                               class="form-control" required min="0" readonly>
                                    </td>
                                    <td class="text-end">
                                        <span id="total-<?= $p['id_cmd_det'] ?>">
                                            <?= number_format($total, 2, '.', ' ') ?> F
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">TOTAL GÉNÉRAL</th>
                            <th class="text-end" id="total-general">
                                <?= number_format($totalGeneral, 2, '.', ' ') ?> F
                            </th>
                        </tr>
                    </tfoot>

                </table>
            </div>

            <!-- ACTIONS -->
            <div class="mt-3 d-flex justify-content-between p-2">
                <!-- <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-secondary">
                    ← Retour
                </a> -->

                <button type="submit" class="btn btn-info">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[name*="quantite"], input[name*="prix"]');
    inputs.forEach(input => {
        input.addEventListener('input', updateTotals);
    });

    function updateTotals() {
        let totalGeneral = 0;
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const quantiteInput = row.querySelector('input[name*="quantite"]');
            const prixInput = row.querySelector('input[name*="prix"]');
            const totalSpan = row.querySelector('span[id^="total-"]');
            if (quantiteInput && prixInput && totalSpan) {
                const quantite = parseFloat(quantiteInput.value) || 0;
                const prix = parseFloat(prixInput.value) || 0;
                const total = quantite * prix;
                totalSpan.textContent = total.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' F';
                totalGeneral += total;
            }
        });
        const totalGeneralSpan = document.getElementById('total-general');
        if (totalGeneralSpan) {
            totalGeneralSpan.textContent = totalGeneral.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' F';
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
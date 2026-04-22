<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    die('Commande invalide');
}

$id_commande = intval($_GET['id']);

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
    die('Commande introuvable');
}

$sqlProduits = "SELECT
                    l.id_lot,
                    l.num_lot,
                    l.quantite_actuelle,
                    l.date_expiration,
                    l.prix_achat_ttc,
                    p.nom_medicament
                FROM StockLot l
                JOIN Produit p ON l.id_produit = p.id_produit
                LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
                WHERE cd.id_commande = ?
                ORDER BY p.nom_medicament ASC";
$stmt = $pdo->prepare($sqlProduits);
$stmt->execute([$id_commande]);
$produits = $stmt->fetchAll();

$totalGeneral = 0;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Impression commande #<?= htmlspecialchars($commande['id_commande']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        .no-print { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f5f5f5; text-align: left; }
        .text-right { text-align: right; }
        .top-row div { margin-bottom: 6px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimer</button>
        <a href="entrees_stock.php">Retour</a>
    </div>

    <h1>Commande n°<?= htmlspecialchars($commande['id_commande']) ?></h1>
    <div class="top-row">
        <div><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></div>
        <div><strong>Source :</strong> <?= htmlspecialchars($commande['source_type'] ?: 'N/A') ?></div>
        <div><strong>Fournisseur :</strong> <?= htmlspecialchars($commande['fournisseur'] ?: 'N/A') ?></div>
        <div><strong>Agent :</strong> <?= htmlspecialchars($commande['utilisateur'] ?: '-') ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Lot</th>
                <th>Quantité</th>
                <th>Expiration</th>
                <th>Prix achat</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($produits)): ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun produit trouvé</td>
                </tr>
            <?php else: ?>
                <?php foreach ($produits as $p): ?>
                    <?php $total = $p['prix_achat_ttc'] * $p['quantite_actuelle']; ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                        <td><?= htmlspecialchars($p['num_lot']) ?></td>
                        <td class="text-right"><?= $p['quantite_actuelle'] ?></td>
                        <td><?= date('d/m/Y', strtotime($p['date_expiration'])) ?></td>
                        <td class="text-right"><?= number_format($p['prix_achat_ttc'], 2, '.', ' ') ?> F</td>
                        <td class="text-right"><?= number_format($total, 2, '.', ' ') ?> F</td>
                    </tr>
                    <?php $totalGeneral += $total; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" class="text-right">TOTAL GÉNÉRAL</th>
                <th class="text-right"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>

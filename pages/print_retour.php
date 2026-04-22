<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    die('Retour invalide');
}

$id_retour = intval($_GET['id']);

$sqlRetour = "SELECT
                    rf.id_retour,
                    rf.id_commande,
                    rf.date_retour,
                    rf.commentaire,
                    rf.statut,
                    rf.id_user,
                    part.nom_entite AS fournisseur,
                    u.nom_complet AS agent
                FROM RetourFournisseur rf
                JOIN Commande cmd ON rf.id_commande = cmd.id_commande
                LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                LEFT JOIN Utilisateur u ON rf.id_user = u.id_user
                WHERE rf.id_retour = ?";
$stmt = $pdo->prepare($sqlRetour);
$stmt->execute([$id_retour]);
$retour = $stmt->fetch();

if (!$retour) {
    die('Retour introuvable');
}

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
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Impression retour fournisseur #<?= htmlspecialchars($retour['id_retour']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        .no-print { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f5f5f5; text-align: left; }
        .text-right { text-align: right; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimer</button>
        <a href="liste_retours_fournisseur.php">Retour</a>
    </div>

    <h1>Retour fournisseur n°<?= htmlspecialchars($retour['id_retour']) ?></h1>
    <div>
        <div><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($retour['date_retour'])) ?></div>
        <div><strong>Commande :</strong> #<?= htmlspecialchars($retour['id_commande']) ?></div>
        <div><strong>Fournisseur :</strong> <?= htmlspecialchars($retour['fournisseur'] ?: 'N/A') ?></div>
        <div><strong>Agent :</strong> <?= htmlspecialchars($retour['agent'] ?: '-') ?></div>
    </div>

    <div style="margin-top: 16px;">
        <strong>Commentaire :</strong>
        <div><?= nl2br(htmlspecialchars($retour['commentaire'])) ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Lot</th>
                <th>Expiration</th>
                <th class="text-right">Quantité retournée</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($details)): ?>
                <tr>
                    <td colspan="4" class="text-center">Aucun lot enregistré pour ce retour</td>
                </tr>
            <?php else: ?>
                <?php foreach ($details as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nom_medicament']) ?></td>
                        <td><?= htmlspecialchars($d['num_lot']) ?></td>
                        <td><?= date('d/m/Y', strtotime($d['date_expiration'])) ?></td>
                        <td class="text-right"><?= $d['quantite_retournee'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

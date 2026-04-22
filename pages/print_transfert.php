<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    die('Transfert invalide');
}

$groupId = $_GET['id'];
$origin = $_GET['origin'] ?? 'transferts';

// Determine the type of document
if (strpos($groupId, 'grp_') === 0) {
    $docType = 'transfert';
} else {
    // Check if it's a return
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM RetourFournisseur WHERE id_retour = ?");
    $stmt->execute([$groupId]);
    if ($stmt->fetchColumn() > 0) {
        $docType = 'retour';
    } else {
        $docType = 'commande';
    }
}

$transfert = null;
$details = [];

if ($docType === 'transfert') {
    // Existing logic for transfers
    $groupTransfersFile = __DIR__ . '/../data/group_transfers.json';
    if (file_exists($groupTransfersFile)) {
        $groups = json_decode(file_get_contents($groupTransfersFile), true);
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (isset($group['id']) && $group['id'] === $groupId) {
                    // Récupérer les noms des points de vente
                    $pvs = $pdo->query("SELECT id_point_vente, nom_point_vente FROM PointVente")->fetchAll(PDO::FETCH_ASSOC);
                    $pvNames = [];
                    foreach ($pvs as $pv) {
                        $pvNames[$pv['id_point_vente']] = $pv['nom_point_vente'];
                    }

                    $transfert = [
                        'id_transfert' => $group['id'],
                        'date_transfert' => $group['created_at'] ?? date('Y-m-d H:i:s'),
                        'statut' => $group['status'] ?? 'Envoyé',
                        'source' => $pvNames[$group['id_source']] ?? 'Source inconnue',
                        'destination' => $pvNames[$group['id_destination']] ?? 'Destination inconnue',
                        'agent' => $group['agent_name'] ?? 'Agent inconnue',
                        'type' => 'transfert'
                    ];

                    // Construire les détails à partir des items du groupe
                    if (isset($group['items']) && is_array($group['items'])) {
                        foreach ($group['items'] as $item) {
                            // Récupérer les informations du lot depuis la base de données
                            $id_lot = intval($item['id_lot']);
                            $stmt = $pdo->prepare("SELECT l.num_lot, l.date_expiration, l.prix_achat_ttc, p.nom_medicament 
                                                  FROM StockLot l 
                                                  JOIN Produit p ON l.id_produit = p.id_produit 
                                                  WHERE l.id_lot = ?");
                            $stmt->execute([$id_lot]);
                            $lotInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                            $prix = $lotInfo['prix_achat_ttc'] ?? 0;
                            $quantite = $item['qty'] ?? 0;
                            $total = $prix * $quantite;

                            $details[] = [
                                'nom_medicament' => $lotInfo['nom_medicament'] ?? $item['label'] ?? 'Produit inconnue',
                                'quantite' => $quantite,
                                'prix' => $prix,
                                'total' => $total,
                                'id_lot' => $id_lot,
                                'num_lot' => $lotInfo['num_lot'] ?? '-',
                                'date_expiration' => $lotInfo['date_expiration'] ?? null
                            ];
                        }
                    }
                    break;
                }
            }
        }
    }
} elseif ($docType === 'retour') {
    // Logic for returns
    $id_retour = intval($groupId);
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

    if ($retour) {
        $transfert = [
            'id_transfert' => 'R' . $retour['id_retour'],
            'date_transfert' => $retour['date_retour'],
            'statut' => $retour['statut'],
            'source' => 'Retour fournisseur',
            'destination' => $retour['fournisseur'] ?: 'Fournisseur inconnu',
            'agent' => $retour['agent'] ?: 'Agent inconnu',
            'type' => 'retour',
            'commentaire' => $retour['commentaire'],
            'id_commande' => $retour['id_commande']
        ];

        $sqlDetails = "SELECT
                            rfd.id_retour_detail,
                            rfd.id_lot,
                            rfd.quantite_retournee,
                            l.num_lot,
                            p.nom_medicament,
                            l.date_expiration,
                            l.prix_achat_ttc
                        FROM RetourFournisseurDetail rfd
                        JOIN StockLot l ON rfd.id_lot = l.id_lot
                        JOIN Produit p ON l.id_produit = p.id_produit
                        WHERE rfd.id_retour = ?
                        ORDER BY p.nom_medicament ASC";
        $stmt = $pdo->prepare($sqlDetails);
        $stmt->execute([$id_retour]);
        $retourDetails = $stmt->fetchAll();

        foreach ($retourDetails as $d) {
            $prix = $d['prix_achat_ttc'] ?? 0;
            $quantite = $d['quantite_retournee'];
            $total = $prix * $quantite;

            $details[] = [
                'nom_medicament' => $d['nom_medicament'],
                'quantite' => $quantite,
                'prix' => $prix,
                'total' => $total,
                'id_lot' => $d['id_lot'],
                'num_lot' => $d['num_lot'],
                'date_expiration' => $d['date_expiration']
            ];
        }
    }
} elseif ($docType === 'commande') {
    // Logic for commands
    $id_commande = intval($groupId);
    $sqlCommande = "SELECT
                        cmd.id_commande,
                        cmd.date_commande,
                        cmd.statut,
                        cmd.statut_paiement,
                        part.nom_entite AS fournisseur,
                        u.nom_complet AS agent
                    FROM Commande cmd
                    LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                    LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user
                    WHERE cmd.id_commande = ?";
    $stmt = $pdo->prepare($sqlCommande);
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch();

    if ($commande) {
        $transfert = [
            'id_transfert' => 'C' . $commande['id_commande'],
            'date_transfert' => $commande['date_commande'],
            'statut' => $commande['statut'],
            'source' => 'Commande fournisseur',
            'destination' => $commande['fournisseur'] ?: 'Fournisseur inconnu',
            'agent' => $commande['agent'] ?: 'Agent inconnu',
            'type' => 'commande',
            'statut_paiement' => $commande['statut_paiement']
        ];

        $sqlDetails = "SELECT
                            cd.quantite_voulue,
                            l.num_lot,
                            p.nom_medicament,
                            l.date_expiration,
                            COALESCE(l.prix_achat_ttc, p.prix_unitaire) AS prix
                        FROM CommandeDetail cd
                        JOIN Produit p ON cd.id_produit = p.id_produit
                        LEFT JOIN StockLot l ON cd.id_cmd_det = l.id_cmd_det
                        WHERE cd.id_commande = ?
                        ORDER BY p.nom_medicament ASC";
        $stmt = $pdo->prepare($sqlDetails);
        $stmt->execute([$id_commande]);
        $commandeDetails = $stmt->fetchAll();

        foreach ($commandeDetails as $d) {
            $prix = $d['prix'] ?? 0;
            $quantite = $d['quantite_voulue'];
            $total = $prix * $quantite;

            $details[] = [
                'nom_medicament' => $d['nom_medicament'],
                'quantite' => $quantite,
                'prix' => $prix,
                'total' => $total,
                'id_lot' => null,
                'num_lot' => $d['num_lot'] ?: '-',
                'date_expiration' => $d['date_expiration']
            ];
        }
    }
}

if (!$transfert) {
    die('Document introuvable');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impression <?= $docType === 'transfert' ? 'transfert' : ($docType === 'retour' ? 'retour' : 'commande') ?> #<?= htmlspecialchars($transfert['id_transfert']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding: 24px;
            line-height: 1.6;
        }

        .no-print {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            print-display: none;
        }

        .no-print button,
        .no-print a {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .no-print button {
            background-color: #0d6efd;
            color: white;
        }

        .no-print button:hover {
            background-color: #0b5ed7;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* .no-print a {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
        } */

        /* .no-print a:hover {
            background-color: #5c636a;
            text-decoration: none;
        } */

        .container-doc {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* En-tête du document */
        .doc-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 24px;
            margin-bottom: 30px;
            text-align: center;
        }

        .doc-header h1 {
            font-size: 28px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 8px;
        }

        .doc-header p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        /* Section infos transfert */
        .transfert-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0d6efd;
        }

        .transfert-info .info-group {
            display: flex;
            flex-direction: column;
        }

        .transfert-info label {
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .transfert-info p {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin: 0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.sent {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.received {
            background-color: #d4edda;
            color: #155724;
        }
    
        .blue{
            color: #0d6efd;
        }

        /* Tableau des produits */
        .products-section {
            margin-top: 32px;
        }

        .products-section h3 {
            font-size: 14px;
            text-transform: uppercase;
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0d6efd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        table thead {
            background-color: #0d6efd;
            color: white;
        }

        table th {
            padding: 14px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        table tbody tr {
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.2s ease;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        table tbody tr:last-child {
            border-bottom: none;
        }

        table td {
            padding: 14px 12px;
            font-size: 14px;
            color: #333;
        }

        table td.text-right {
            text-align: right;
            font-weight: 500;
        }

        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-style: italic;
        }

        /* Footer */
        .doc-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: right;
            font-size: 12px;
            color: #999;
        }

        /* Impression */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: white;
                padding: 0;
            }

            .container-doc {
                box-shadow: none;
                padding: 20px;
            }

            .transfert-info {
                page-break-inside: avoid;
            }

            table {
                page-break-inside: auto;
            }

            table tr {
                page-break-inside: avoid;
            }

            .doc-footer {
                display: block;
            }
        }

        @page {
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="no-print d-flex justify-content-between align-items-center">
        <button onclick="window.print()">Imprimer</button>
        <a href="<?= $docType === 'retour' ? 'liste_retours_fournisseur.php' : ($docType === 'commande' ? 'entrees_stock.php' : ($docType === 'transfert' && $origin === 'dons' ? 'liste_transfer_dons.php' : 'liste_transferts.php')) ?>" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="container mt-4">
        

        <div class="card shadow-sm">
            <div class="card-body">
                <!-- En-tête -->
                <div class="doc-header">
                    <h2 class="mb-4 blue">Bon de <?= $docType === 'transfert' ? 'Transfert' : ($docType === 'retour' ? 'Retour Fournisseur' : 'Commande') ?></h2>
                    <p>Référence: #<?= htmlspecialchars($transfert['id_transfert']) ?></p>
                </div>

                <!-- Informations du transfert -->
                <div class="transfert-info">
                    <div class="info-group">
                        <label>Date du <?= $docType === 'transfert' ? 'transfert' : ($docType === 'retour' ? 'retour' : 'commande') ?></label>
                        <p><?= date('d/m/Y à H:i', strtotime($transfert['date_transfert'])) ?></p>
                    </div>
                    <div class="info-group">
                        <label>Statut</label>
                        <p>
                            <span class="status-badge <?= ($transfert['statut'] === 'Reçu' || $transfert['statut'] === 'Reçue') ? 'received' : 'sent' ?>">
                                <?= htmlspecialchars($transfert['statut']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="info-group">
                        <label>Source</label>
                        <p><?= htmlspecialchars($transfert['source'] ?: 'N/A') ?></p>
                    </div>
                    <div class="info-group">
                        <label>Destination</label>
                        <p><?= htmlspecialchars($transfert['destination'] ?: 'N/A') ?></p>
                    </div>
                    <div class="info-group">
                        <label>Agent responsable</label>
                        <p><?= htmlspecialchars($transfert['agent'] ?: '-') ?></p>
                    </div>
                    <div class="info-group">
                        <label>Total d'articles</label>
                        <p><?= array_sum(array_column($details, 'quantite')) ?> unités</p>
                    </div>
                    <?php if ($docType === 'retour'): ?>
                    <div class="info-group">
                        <label>Commande associée</label>
                        <p>#<?= htmlspecialchars($transfert['id_commande']) ?></p>
                    </div>
                    <div class="info-group">
                        <label>Commentaire</label>
                        <p><?= nl2br(htmlspecialchars($transfert['commentaire'] ?: '-')) ?></p>
                    </div>
                    <?php elseif ($docType === 'commande'): ?>
                    <div class="info-group">
                        <label>Statut de paiement</label>
                        <p>
                            <span class="status-badge <?= ($transfert['statut_paiement'] === 'payé' || $transfert['statut_paiement'] === 'soldé') ? 'received' : 'sent' ?>">
                                <?= htmlspecialchars(ucfirst($transfert['statut_paiement'] ?: 'du')) ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section produits -->
                <div class="products-section">
                    <h3>Produits <?= $docType === 'transfert' ? 'transférés' : ($docType === 'retour' ? 'retournés' : 'commandés') ?></h3>
                    <div class="table-responsive p-2">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Produit</th>
                                    <th style="width: 15%;">Lot</th>
                                    <th style="width: 12%;">Expiration</th>
                                    <th style="width: 10%; text-align: right;">Quantité</th>
                                    <th style="width: 12%; text-align: right;">Prix</th>
                                    <th style="width: 11%; text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($details)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Aucun produit enregistré pour ce <?= $docType === 'transfert' ? 'transfert' : ($docType === 'retour' ? 'retour' : 'commande') ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($details as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['nom_medicament']) ?></td>
                                            <td><?= htmlspecialchars($d['num_lot'] ?: '-') ?></td>
                                            <td><?= $d['date_expiration'] ? date('d/m/Y', strtotime($d['date_expiration'])) : '-' ?></td>
                                            <td class="text-right"><?= intval($d['quantite']) ?></td>
                                            <td class="text-right"><?= number_format($d['prix'], 2, '.', ' ') ?> F</td>
                                            <td class="text-right"><?= number_format($d['total'], 2, '.', ' ') ?> F</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php 
                                    $totalGeneral = array_sum(array_column($details, 'total'));
                                    ?>
                                    <tr class="table-secondary">
                                        <td colspan="5" class="text-right font-weight-bold">Total général</td>
                                        <td class="text-right font-weight-bold"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="doc-footer">
                    <p>Généré le <?= date('d/m/Y à H:i:s') ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

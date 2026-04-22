<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: produits.php');
    exit();
}

$id_produit = intval($_GET['id']);

// Récupérer les infos du produit
$sqlProduit = "SELECT p.*, c.nom_categorie, c.forme, c.dosage
               FROM Produit p
               JOIN ProductCategory c ON p.id_categorie = c.id_categorie
               WHERE p.id_produit = ?";
$stmtProduit = $pdo->prepare($sqlProduit);
$stmtProduit->execute([$id_produit]);
$produit = $stmtProduit->fetch();

if (!$produit) {
    die("Produit non trouvé");
}

// Récupérer tous les lots associés à ce produit
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, l.prix_achat_ttc,
                   part.nom_entite AS fournisseur,
                   cmd.date_commande,
                   cd.quantite_voulue,
                   part.type AS source_type
            FROM StockLot l
            LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            WHERE l.id_produit = ?
            ORDER BY l.date_expiration ASC";
$stmtLots = $pdo->prepare($sqlLots);
$stmtLots->execute([$id_produit]);
$lots = $stmtLots->fetchAll();

// Gestion de la suppression d'un lot (destockage)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lot'])) {
    $id_lot = intval($_POST['id_lot']);
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer la quantité actuelle avant suppression
        $stmtQty = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
        $stmtQty->execute([$id_lot]);
        $current_qty = $stmtQty->fetchColumn();
        
        if ($current_qty > 0) {
            // Insérer dans mouvement pour tracer la sortie
            $stmtMouv = $pdo->prepare("INSERT INTO Mouvement (id_lot, id_user, id_point_vente, type_mouvement, quantite, date_mouvement) 
                                      VALUES (?, ?, 1, 'perime', ?, NOW())"); // id_point_vente=1 par défaut
            $stmtMouv->execute([$id_lot, $_SESSION['user_id'], $current_qty]);
            
            // Mettre la quantité à 0
            $stmtUpdate = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = 0 WHERE id_lot = ?");
            $stmtUpdate->execute([$id_lot]);
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Lot destocké avec succès.";
        header("Location: details_produit.php?id=$id_produit");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur lors du destockage : " . $e->getMessage() . "</div>";
    }
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails du Produit</h2>
        <a href="produits.php" class="btn btn-outline-secondary">Retour</a>
    </div>

    <?php if (isset($message)) echo $message; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <!-- INFOS PRODUIT -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <strong>Informations Produit</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Nom :</strong><br>
                    <?= htmlspecialchars($produit['nom_medicament']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Type :</strong><br>
                    <?= htmlspecialchars($produit['type_produit']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Catégorie :</strong><br>
                    <?= htmlspecialchars($produit['nom_categorie']) ?> - <?= htmlspecialchars($produit['forme']) ?> <?= htmlspecialchars($produit['dosage']) ?>
                </div>
                <div class="col-md-2">
                    <strong>Prix Unitaire :</strong><br>
                    <?= number_format($produit['prix_unitaire'] ?? 0, 2) ?> FCFA
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-3">
                    <strong>Marge :</strong><br>
                    <?= $produit['marge_pourcentage'] ?>%
                </div>
                <div class="col-md-3">
                    <strong>Seuil Alerte :</strong><br>
                    <?= $produit['seuil_alerte'] ?>
                </div>
                <div class="col-md-6">
                    <strong>Stock Total :</strong><br>
                    <?php 
                    $totalStock = 0;
                    foreach ($lots as $lot) {
                        $totalStock += $lot['quantite_actuelle'];
                    }
                    echo $totalStock;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLEAU LOTS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Lots disponibles</strong>
        </div>

        <?php if (empty($lots)): ?>
            <div class="card-body text-center text-muted">
                Aucun lot trouvé pour ce produit.
            </div>
        <?php else: ?>
            <div class="table-responsive p-2">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Numéro Lot</th>
                            <th class="text-center">Quantité</th>
                            <th>Date Expiration</th>
                            <th class="text-end">Prix Achat</th>
                            <th>Fournisseur / Donateur</th>
                            <th>Source</th>
                            <th>Date Commande</th>
                            <th class="text-center">État</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lots as $lot):
                            $exp_ts = strtotime($lot['date_expiration']);
                            $today = strtotime(date('Y-m-d'));
                            
                            if ($lot['quantite_actuelle'] == 0) {
                                $status = '<span class="badge bg-danger">Rupture</span>';
                                $rowClass = 'table-danger text-white';
                            } elseif ($exp_ts < $today) {
                                $status = '<span class="badge bg-dark">Périmé</span>';
                                $rowClass = 'table-dark text-white';
                            } elseif ($exp_ts < strtotime('+14 days')) {
                                $status = '<span class="badge bg-warning text-dark">Critique</span>';
                                $rowClass = 'table-warning';
                            } else {
                                $status = '<span class="badge bg-success">Valide</span>';
                                $rowClass = '';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= htmlspecialchars($lot['num_lot']) ?></td>
                            <td class="text-center"><?= $lot['quantite_actuelle'] ?></td>
                            <td><?= date('d/m/Y', strtotime($lot['date_expiration'])) ?></td>
                            <td class="text-end"><?= number_format($lot['prix_achat_ttc'] ?? 0, 2, '.', ' ') ?> F</td>
                            <td><?= htmlspecialchars($lot['fournisseur'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($lot['source_type'] ?? 'N/A') ?></td>
                            <td><?= isset($lot['date_commande']) ? date('d/m/Y', strtotime($lot['date_commande'])) : 'N/A' ?></td>
                            <td class="text-center"><?= $status ?></td>
                            <td class="text-center">
                                <?php if ($lot['quantite_actuelle'] > 0): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir destocker ce lot ? Cette action est irréversible.');">
                                        <input type="hidden" name="id_lot" value="<?= $lot['id_lot'] ?>">
                                        <button type="submit" name="delete_lot" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Destocker
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Déjà destocké</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

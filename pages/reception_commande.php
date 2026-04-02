<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit();
}

$id_commande = (int)($_GET['id_commande'] ?? 0);

// Récupérer les infos de la commande
$stmt = $pdo->prepare("SELECT cmd.*, p.nom_entite 
                       FROM Commande cmd 
                       JOIN Partenaire p ON cmd.id_partenaire = p.id_partenaire 
                       WHERE cmd.id_commande = ?");
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();

if (!$commande) { die("Commande introuvable."); }

// Récupérer les détails
$stmt = $pdo->prepare("SELECT cd.*, p.nom_medicament, p.type_produit, p.marge_pourcentage, p.prix_unitaire 
                       FROM CommandeDetail cd 
                       JOIN Produit p ON cd.id_produit = p.id_produit 
                       WHERE cd.id_commande = ?");
$stmt->execute([$id_commande]);
$details = $stmt->fetchAll();

// Traitement de la réception groupée
if (isset($_POST['btn_valider_reception_finale'])) {
    try {
        $pdo->beginTransaction();
        
        $ids_det = $_POST['id_cmd_det'];
        $ids_prod = $_POST['id_produit'];
        $nums_lot = $_POST['num_lot'];
        $dates_exp = $_POST['date_exp'];
        $qtes_recues = $_POST['qte_recue'];
        $prix_achats = $_POST['prix_achat'];

        for ($i = 0; $i < count($ids_det); $i++) {
            if (!empty($nums_lot[$i]) && !empty($dates_exp[$i])) {
                // Insertion Lot
                $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtLot->execute([$ids_prod[$i], $ids_det[$i], $nums_lot[$i], $qtes_recues[$i], $dates_exp[$i], $prix_achats[$i]]);
            }
        }

        // Clôturer la commande
        $pdo->prepare("UPDATE Commande SET statut = 'Reçue' WHERE id_commande = ?")->execute([$id_commande]);

        $pdo->commit();
        header("Location: entrees.php?success=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Réception de Commande</h2>
        <a href="entrees.php" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Détails de la réception</h5>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produit</th>
                                <th style="width: 100px;">Qte Comm.</th>
                                <th style="width: 150px;">Numéro Lot *</th>
                                <th style="width: 160px;">Expiration *</th>
                                <th style="width: 110px;">Qte Reçue</th>
                                <th style="width: 130px;">Prix Achat (U)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $d): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($d['nom_medicament']) ?></strong>
                                    <input type="hidden" name="id_cmd_det[]" value="<?= $d['id_cmd_det'] ?>">
                                    <input type="hidden" name="id_produit[]" value="<?= $d['id_produit'] ?>">
                                </td>
                                <td class="text-center fw-bold"><?= $d['quantite_voulue'] ?></td>
                                <td>
                                    <input type="text" name="num_lot[]" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="date" name="date_exp[]" class="form-control form-control-sm" required min="<?= date('Y-m-d') ?>">
                                </td>
                                <td>
                                    <input type="number" name="qte_recue[]" class="form-control form-control-sm" value="<?= $d['quantite_voulue'] ?>" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="prix_achat[]" class="form-control form-control-sm" value="<?= htmlspecialchars($d['prix_unitaire']) ?>" required>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- <div class="alert alert-info py-2 mt-3 small">
                    <i class="bi bi-info-circle"></i> Assurez-vous de vérifier les quantités physiques avant de valider. Les lots vides ne seront pas créés.
                </div> -->

                <div class="text-end mt-4">
                    <button type="submit" name="btn_valider_reception_finale" class="btn btn-primary btn-lg px-5" 
                            onclick="return confirm('Confirmer la réception de tous ces produits ?');">
                        <i class="bi bi-check-all"></i> Valider l'entrée en stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
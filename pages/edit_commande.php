<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Accès refusé");
}

if (!isset($_GET['id'])) {
    die("Commande invalide");
}

$id_commande = intval($_GET['id']);


// 🔹 Récupérer produits
$sql = "SELECT 
            l.id_lot,
            l.quantite_actuelle,
            l.prix_achat_ttc,
            p.nom_medicament
        FROM StockLot l
        JOIN Produit p ON l.id_produit = p.id_produit
        LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
        WHERE cd.id_commande = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_commande]);
$produits = $stmt->fetchAll();


// 🔹 UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo->beginTransaction();

        foreach ($_POST['produits'] as $id_lot => $data) {

            $qte = intval($data['quantite']);
            $prix = floatval($data['prix']);

            $update = $pdo->prepare("
                UPDATE StockLot 
                SET quantite_actuelle = ?, prix_achat_ttc = ?
                WHERE id_lot = ?
            ");
            $update->execute([$qte, $prix, $id_lot]);
        }

        $pdo->commit();

        header("Location: entrees_stock.php?msg=updated");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur : " . $e->getMessage());
    }
}
?>

<div class="container mt-4">
    <h3>Modifier la commande #<?= $id_commande ?></h3>

    <form method="POST">
        <table class="table table-bordered">
            <tr>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix Achat</th>
            </tr>

            <?php foreach ($produits as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nom_medicament']) ?></td>

                    <td>
                        <input type="number"
                               name="produits[<?= $p['id_lot'] ?>][quantite]"
                               value="<?= $p['quantite_actuelle'] ?>"
                               class="form-control" required>
                    </td>

                    <td>
                        <input type="number" step="0.01"
                               name="produits[<?= $p['id_lot'] ?>][prix]"
                               value="<?= $p['prix_achat_ttc'] ?>"
                               class="form-control" required>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="d-flex justify-content-between">
            <a href="entrees_stock.php" class="btn btn-secondary">← Retour</a>
            <button class="btn btn-success">💾 Enregistrer</button>
        </div>
    </form>
</div>
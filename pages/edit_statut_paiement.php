<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id_commande = (int)($_GET['id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['statut_paiement'])) {
    $statut_paiement = $_POST['statut_paiement'];
    if (in_array($statut_paiement, ['du', 'partielle', 'payé', 'soldé'])) {
        try {
            $stmt = $pdo->prepare("UPDATE Commande SET statut_paiement = ? WHERE id_commande = ?");
            $stmt->execute([$statut_paiement, $id_commande]);
            $message = "<div class='alert alert-success'>Statut de paiement mis à jour.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Erreur: " . $e->getMessage() . "</div>";
        }
    }
}

// Récupérer la commande
$stmt = $pdo->prepare("SELECT cmd.*, p.nom_entite FROM Commande cmd JOIN Partenaire p ON cmd.id_partenaire = p.id_partenaire WHERE cmd.id_commande = ?");
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();

if (!$commande) {
    die("Commande introuvable.");
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2>Modifier Statut de Paiement</h2>
    <?= $message ?>
    <div class="card">
        <div class="card-body">
            <p><strong>Commande</strong> - Fournisseur: <?= htmlspecialchars($commande['nom_entite']) ?></p>
            <form method="POST">
                <div class="mb-3">
                    <label for="statut_paiement" class="form-label">Statut de paiement</label>
                    <select name="statut_paiement" id="statut_paiement" class="form-select">
                        <option value="du" <?= $commande['statut_paiement'] === 'du' ? 'selected' : '' ?>>Du</option>
                        <option value="partielle" <?= $commande['statut_paiement'] === 'partielle' ? 'selected' : '' ?>>Partielle</option>
                        <option value="payé" <?= $commande['statut_paiement'] === 'payé' ? 'selected' : '' ?>>Payé</option>
                        <option value="soldé" <?= $commande['statut_paiement'] === 'soldé' ? 'selected' : '' ?>>Soldé</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                <a href="entrees_stock.php" class="btn btn-secondary">Retour</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
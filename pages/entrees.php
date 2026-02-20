<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

if ($isAdmin && isset($_POST['btn_lot'])) {
    $id_p = $_POST['id_p'];
    $id_f = $_POST['id_f'];
    $qte = $_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];

    $pdo->beginTransaction();
    // Insertion du lot
    $stmt = $pdo->prepare("INSERT INTO stock_lots (id_produit, id_fournisseur, num_lot, quantite_initiale, quantite_actuelle, date_expiration, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_p, $id_f, $num_lot, $qte, $qte, $exp, $_SESSION['user_id']]);
    // Mise à jour stock total
    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
    $stmt->execute([$qte, $id_p]);
    $pdo->commit();
    header("Location: entrees.php"); exit();
}

$entrees = $pdo->query("SELECT l.*, p.nom_medicament, f.nom_societe, u.nom_complet AS utilisateur FROM stock_lots l JOIN produits p ON l.id_produit = p.id_produit JOIN fournisseurs f ON l.id_fournisseur = f.id_fournisseur LEFT JOIN utilisateurs u ON l.id_user = u.id_user ORDER BY l.id_lot DESC")->fetchAll();
$prods = $pdo->query("SELECT * FROM produits")->fetchAll();
$fours = $pdo->query("SELECT * FROM fournisseurs")->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Entrées en Stock (Lots)</h2>
    <?php if($isAdmin): ?>
        <div class="card p-3 mb-4 shadow-sm border-primary">
            <h5>Enregistrer une livraison</h5>
            <form method="POST" class="row g-2">
                <div class="col-md-3"><select name="id_p" class="form-select" required><?php foreach($prods as $p) echo "<option value='{$p['id_produit']}'>{$p['nom_medicament']}</option>"; ?></select></div>
                <div class="col-md-3"><select name="id_f" class="form-select" required><?php foreach($fours as $f) echo "<option value='{$f['id_fournisseur']}'>{$f['nom_societe']}</option>"; ?></select></div>
                <div class="col-md-2"><input type="number" name="qte" class="form-control" placeholder="Qté" required></div>
                <div class="col-md-2"><input type="text" name="num_lot" class="form-control" placeholder="N° Lot" required></div>
                <div class="col-md-2"><input type="date" name="exp" class="form-control" required></div>
                <div class="col-12"><button type="submit" name="btn_lot" class="btn btn-primary w-100">Valider l'entrée</button></div>
            </form>
        </div>
    <?php endif; ?>

    <table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>Médicament</th><th>Lot</th><th>Fournisseur</th><th>Qté</th><th>Expiration</th><th>Utilisateur</th></tr></thead>
        <tbody>
            <?php foreach($entrees as $e): ?>
                <tr>
                    <td><?= $e['date_enregistrement'] ?></td>
                    <td><?= $e['nom_medicament'] ?></td>
                    <td><?= $e['num_lot'] ?></td>
                    <td><?= $e['nom_societe'] ?></td>
                    <td><?= $e['quantite_initiale'] ?></td>
                    <td><?= $e['date_expiration'] ?></td>
                    <td><?= isset($e['utilisateur']) && $e['utilisateur'] ? $e['utilisateur'] : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
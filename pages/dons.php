<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

// --- ACTION : ENREGISTRER UN DON (DIRECT) ---
if ($isAdmin && isset($_POST['btn_save_don'])) {
    $id_p = $_POST['id_p'];
    $id_donateur = $_POST['id_donateur'];
    $qte = (int)$_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];

    try {
        $pdo->beginTransaction();

        // 1. Création implicite de la commande (Statut 'Reçue' direct)
        // Cela permet de garder une trace administrative homogène
        $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'Reçue')");
        $stmtCmd->execute([$id_donateur, $_SESSION['user_id']]);
        $id_commande = $pdo->lastInsertId();

        // 2. Création du détail commande
        $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
        $stmtDet->execute([$id_commande, $id_p, $qte]);
        $id_cmd_det = $pdo->lastInsertId();

        // 3. Création du StockLot (Prix Achat = 0 pour les dons)
        $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, 0)");
        $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte, $exp]);

        $pdo->commit();
        $message = "<div class='alert alert-success'>Don enregistré et stock mis à jour avec succès !</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// --- RÉCUPÉRATION HISTORIQUE DONS ---
// On récupère uniquement les entrées liées à des partenaires de type 'Don'
$sqlDons = "SELECT l.*, p.nom_medicament, p.type_produit, 
                   part.nom_entite, u.nom_complet AS utilisateur,
                   cmd.date_commande AS date_enregistrement,
                   cd.quantite_voulue AS quantite_initiale
            FROM StockLot l 
            JOIN Produit p ON l.id_produit = p.id_produit 
            LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user 
            WHERE part.type = 'Don'
            ORDER BY l.id_lot DESC LIMIT 50";
$dons = $pdo->query($sqlDons)->fetchAll();

// Listes pour le formulaire
$prods = $pdo->query("SELECT * FROM Produit ORDER BY nom_medicament ASC")->fetchAll();
$donateurs = $pdo->query("SELECT * FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC")->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4 text-dark">Réception des Dons</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Enregistrer un nouveau don</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Donateur</label>
                        <select name="id_donateur" class="form-select" required>
                            <option value="">-- Choisir le donateur --</option>
                            <?php foreach ($donateurs as $d): ?>
                                <option value="<?= $d['id_partenaire'] ?>"><?= htmlspecialchars($d['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Produit Reçu</label>
                        <select name="id_p" class="form-select" required>
                            <option value="">-- Choisir le produit --</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id_produit'] ?>">
                                    <?= htmlspecialchars($p['nom_medicament']) ?> (<?= $p['type_produit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Quantité Reçue</label>
                        <input type="number" name="qte" class="form-control" min="1" required>
                    </div>

                    <div class="col-md-2">
                        <!-- Espaceur -->
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Numéro de Lot</label>
                        <input type="text" name="num_lot" class="form-control" placeholder="Sur la boîte" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date Expiration</label>
                        <input type="date" name="exp" class="form-control" required>
                    </div>

                    <div class="col-md-12 d-flex mt-4 align-items-end">
                        <button type="submit" name="btn_save_don" class="btn btn-primary w-100 btn-lg">Valider la Réception de Don</button>
                    </div>
                    
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- HISTORIQUE DONS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Historique des dons reçus</strong></div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date Réception</th>
                        <th>État</th>
                        <th>Produit</th>
                        <th>Lot</th>
                        <th>Donateur</th>
                        <th>Qté</th>
                        <th>Expiration</th>
                        <th>Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dons)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Aucun don enregistré récemment.</td>
                        </tr>
                        <?php else: foreach ($dons as $e): ?>
                            <?php
                            $today = strtotime(date('Y-m-d'));
                            $exp_ts = strtotime($e['date_expiration']);
                            $isExpired = $exp_ts < $today;
                            $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);

                            if ($isExpired) {
                                $statusBadge = '<span class="badge bg-danger">Périmé</span>';
                                $rowClass = 'table-secondary'; // Grisé
                            } elseif ($isCritical) {
                                $statusBadge = '<span class="badge bg-warning text-dark">Critique</span>';
                                $rowClass = '';
                            } else {
                                $statusBadge = '<span class="badge bg-success">Valide</span>';
                                $rowClass = '';
                            }
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                                <td><?= $statusBadge ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($e['nom_medicament']) ?></td>
                                <td><code><?= htmlspecialchars($e['num_lot']) ?></code></td>
                                <td><?= htmlspecialchars($e['nom_entite']) ?></td>
                                <td class="fw-bold text-success">+ <?= $e['quantite_initiale'] ?></td>
                                <td class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('d/m/Y', strtotime($e['date_expiration'])) ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($e['utilisateur']) ?></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
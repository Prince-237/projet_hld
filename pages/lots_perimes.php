<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

$message = '';

function destockLot(PDO $pdo, int $id_lot, int $user_id): bool
{
    $stmtQty = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
    $stmtQty->execute([$id_lot]);
    $quantity = (int)$stmtQty->fetchColumn();

    if ($quantity <= 0) {
        return false;
    }

    $stmtMouv = $pdo->prepare("INSERT INTO Mouvement (id_lot, id_user, id_point_vente, type_mouvement, quantite, date_mouvement) VALUES (?, ?, 1, 'perime', ?, NOW())");
    $stmtMouv->execute([$id_lot, $user_id, $quantity]);

    $stmtUpdate = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = 0 WHERE id_lot = ?");
    $stmtUpdate->execute([$id_lot]);

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_expired_lot'])) {
            $id_lot = intval($_POST['id_lot']);
            $pdo->beginTransaction();
            if (destockLot($pdo, $id_lot, $_SESSION['user_id'])) {
                $message = "<div class='alert alert-success'>Lot périmé destocké avec succès.</div>";
            } else {
                $message = "<div class='alert alert-warning'>Ce lot est déjà vide ou introuvable.</div>";
            }
            $pdo->commit();
        }

        if (isset($_POST['delete_all_expired'])) {
            $expiredLots = $pdo->query("SELECT id_lot FROM StockLot WHERE date_expiration < CURRENT_DATE AND quantite_actuelle > 0")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($expiredLots)) {
                $message = "<div class='alert alert-info'>Aucun lot périmé à supprimer.</div>";
            } else {
                $pdo->beginTransaction();
                foreach ($expiredLots as $id_lot) {
                    destockLot($pdo, (int)$id_lot, $_SESSION['user_id']);
                }
                $pdo->commit();
                $message = "<div class='alert alert-success'>Tous les lots périmés ont été destockés.</div>";
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$expired_lots = $pdo->query("SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, p.nom_medicament, p.type_produit, part.nom_entite AS fournisseur, cmd.date_commande
                             FROM StockLot l
                             JOIN Produit p ON l.id_produit = p.id_produit
                             LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
                             LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
                             LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                             WHERE l.date_expiration < CURRENT_DATE
                             AND l.quantite_actuelle > 0
                             ORDER BY l.date_expiration ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Lots périmés</h2>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary">Retour</a>
        </div>
    </div>
    <form method="POST" onsubmit="return confirm('Supprimer tous les lots périmés et les destocker ?');">
        <button type="submit" name="delete_all_expired" class="btn btn-danger mb-3">
            Supprimer tous les lots périmés
        </button>
    </form>

    <?= $message ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Type</th>
                            <th>Lot</th>
                            <th class="text-center">Quantité</th>
                            <th>Expiration</th>
                            <th>Fournisseur / Donateur</th>
                            <th>Date entrée</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expired_lots)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun lot périmé disponible.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expired_lots as $lot): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lot['nom_medicament']) ?></td>
                                    <td><?= htmlspecialchars($lot['type_produit']) ?></td>
                                    <td><?= htmlspecialchars($lot['num_lot']) ?></td>
                                    <td class="text-center"><?= (int)$lot['quantite_actuelle'] ?></td>
                                    <td><?= htmlspecialchars($lot['date_expiration']) ?></td>
                                    <td><?= htmlspecialchars($lot['fournisseur'] ?? '-') ?></td>
                                    <td><?= !empty($lot['date_commande']) ? date('d/m/Y', strtotime($lot['date_commande'])) : '-' ?></td>
                                    <td class="text-center">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Destocker ce lot périmé ?');">
                                            <input type="hidden" name="id_lot" value="<?= $lot['id_lot'] ?>">
                                            <button type="submit" name="delete_expired_lot" class="btn btn-sm btn-outline-danger" title="Destocker">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
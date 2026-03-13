<?php

require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');


$message = '';

// --- ACTION : RECALCUL SEUIL ---
if ($isAdmin && isset($_POST['btn_recalcul_seuil'])) {
    // Calcul : Somme des sorties des 90 derniers jours / 3
    $sql = "UPDATE produits p SET seuil_alerte = CEIL((
        SELECT IFNULL(SUM(s.quantite_sortie), 0)
        FROM sorties s 
        JOIN stock_lots l ON s.id_lot = l.id_lot
        WHERE l.id_produit = p.id_produit 
        AND s.date_sortie >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ) / 3)";
    
    try {
        $pdo->query($sql);
        $message = "<div class='alert alert-success'>Le calcul des seuils d'alerte a été mis à jour avec succès pour tous les produits.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Erreur lors du recalcul des seuils : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION : EXPORT CSV ---

// --- ACTION : EXPORT CSV ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventaire_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    // Entêtes du CSV
    fputcsv($output, ['id_produit', 'nom_medicament', 'stock_theorique', 'stock_physique'], ';');

    $produits = $pdo->query("SELECT id_produit, nom_medicament, stock_total FROM produits ORDER BY nom_medicament ASC")->fetchAll();
    foreach ($produits as $p) {
        fputcsv($output, [$p['id_produit'], $p['nom_medicament'], $p['stock_total'], ''], ';');
    }
    fclose($output);
    exit();
}

// --- ACTION : MODIFIER UN INVENTAIRE (stub) ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'edit_inventaire' && isset($_GET['id'])) {
    $id_inv = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT statut FROM inventaires WHERE id_inventaire = ?");
    $stmt->execute([$id_inv]);
    $statut = $stmt->fetchColumn();
    if ($statut && $statut !== 'traité') {
        $message = "<div class='alert alert-info'>Fonction de modification non implémentée.</div>";
    } else {
        $message = "<div class='alert alert-warning'>Impossible de modifier un inventaire traité.</div>";
    }
}

// --- ACTION : SUPPRIMER UN INVENTAIRE (statut en cours uniquement) ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'delete_inventaire' && isset($_GET['id'])) {
    $id_inv = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT statut FROM inventaires WHERE id_inventaire = ?");
    $stmt->execute([$id_inv]);
    $statut = $stmt->fetchColumn();
    if ($statut && $statut !== 'traité') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM inventaire_details WHERE id_inventaire = ?")->execute([$id_inv]);
            $pdo->prepare("DELETE FROM inventaires WHERE id_inventaire = ?")->execute([$id_inv]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>Inventaire supprimé.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur suppression inventaire : " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Impossible de supprimer un inventaire traité.</div>";
    }
}

// --- ACTION : IMPORT CSV ---
if ($isAdmin && isset($_POST['btn_import_inventaire'])) {
    if (isset($_FILES['fichier_inventaire']) && $_FILES['fichier_inventaire']['error'] == 0) {

        $fileName = $_FILES['fichier_inventaire']['tmp_name'];
        
        $pdo->beginTransaction();
        try {
            // 1. Créer l'enregistrement de l'inventaire parent avec le statut 'en cours'
            $stmt = $pdo->prepare("INSERT INTO inventaires (date_inventaire, id_user, statut) VALUES (NOW(), ?, 'en cours')");
            $stmt->execute([$_SESSION['user_id']]);
            $id_inventaire = $pdo->lastInsertId();

            $file = fopen($fileName, 'r');
            fgetcsv($file); // Ignorer la ligne d'en-tête

            while (($data = fgetcsv($file, 1000, ';')) !== FALSE) {

                if (count($data) < 4 || empty($data[0]) || !is_numeric($data[3])) continue;

                $id_produit = (int)$data[0];
                $stock_physique = (int)$data[3];

                // Récupérer le stock théorique actuel de la BDD
                $stmtProd = $pdo->prepare("SELECT stock_total FROM produits WHERE id_produit = ?");
                $stmtProd->execute([$id_produit]);
                $stock_theorique_db = $stmtProd->fetchColumn();

                if ($stock_theorique_db === false) continue; // Produit non trouvé, on ignore

                $ecart = $stock_physique - $stock_theorique_db;

                // 2. Enregistrer le détail de la ligne d'inventaire

                $stmtDetail = $pdo->prepare("INSERT INTO inventaire_details (id_inventaire, id_produit, stock_theorique, stock_physique, ecart) VALUES (?, ?, ?, ?, ?)");
                $stmtDetail->execute([$id_inventaire, $id_produit, $stock_theorique_db, $stock_physique, $ecart]);

                // 3. Si un écart existe, mettre à jour les stocks
                if ($ecart != 0) {
                    // Mettre à jour le stock total du produit
                    $stmtUpdateProd = $pdo->prepare("UPDATE produits SET stock_total = ? WHERE id_produit = ?");
                    $stmtUpdateProd->execute([$stock_physique, $id_produit]);

                    // Logique d'ajustement des lots
                    if ($ecart > 0) { // Surplus de stock
                        // On ajoute la quantité au lot qui expire le plus tard (ou on crée un lot d'ajustement)
                        // Pour simplifier, on ajoute au lot le plus récent.
                        $stmtLot = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle + ? WHERE id_produit = ? ORDER BY date_expiration DESC LIMIT 1");
                        $stmtLot->execute([$ecart, $id_produit]);
                    } else { // Manque de stock
                        $a_retirer = abs($ecart);
                        // On retire du stock en commençant par les lots qui expirent le plus tôt (FIFO)
                        $lots = $pdo->prepare("SELECT id_lot, quantite_actuelle FROM stock_lots WHERE id_produit = ? AND quantite_actuelle > 0 ORDER BY date_expiration ASC");
                        $lots->execute([$id_produit]);
                        while (($lot = $lots->fetch()) && $a_retirer > 0) {
                            $retrait_sur_lot = min($a_retirer, $lot['quantite_actuelle']);
                            $stmtLot = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?");
                            $stmtLot->execute([$retrait_sur_lot, $lot['id_lot']]);
                            $a_retirer -= $retrait_sur_lot;
                        }
                    }
                }
            }
            fclose($file);

            // mettre le statut à 'traité' maintenant que le fichier a été parcouru
            $stmtStat = $pdo->prepare("UPDATE inventaires SET statut = 'traité' WHERE id_inventaire = ?");
            $stmtStat->execute([$id_inventaire]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Inventaire importé et traité avec succès. Un rapport a été enregistré.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors du traitement de l'inventaire : " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Erreur lors de l'upload du fichier ou aucun fichier sélectionné.</div>";
    }
}

// Récupération du dernier inventaire pour affichage
$dernier_inventaire = $pdo->query("
    SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet 
    FROM inventaires i
    JOIN utilisateurs u ON i.id_user = u.id_user
    WHERE i.statut = 'traité'
    ORDER BY i.id_inventaire DESC LIMIT 1
")->fetch();

// Historique complet (pour la pop‑up)
$inventaires_history = $pdo->query("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet
                                  FROM inventaires i
                                  JOIN utilisateurs u ON i.id_user = u.id_user
                                  ORDER BY i.date_inventaire DESC")->fetchAll();

$details_inventaire = [];
if ($dernier_inventaire) {
    $stmt = $pdo->prepare(
        "SELECT d.*, p.nom_medicament, p.seuil_alerte 
        FROM inventaire_details d
        JOIN produits p ON d.id_produit = p.id_produit
        WHERE d.id_inventaire = ?
        ORDER BY p.nom_medicament ASC"
    );
    $stmt->execute([$dernier_inventaire['id_inventaire']]);
    $details_inventaire = $stmt->fetchAll();
}

include '../includes/sidebar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <h2>Gestion de l'Inventaire</h2>
        <?php if ($isAdmin): ?>
            <form method="POST" onsubmit="return confirm('Cette action va remplacer les seuils d\'alerte de tous les produits. Voulez-vous continuer ?');">
                <button type="submit" name="btn_recalcul_seuil" class="btn btn-warning">Recalculer les Seuils</button>
            </form>
        <?php endif; ?>
    </div>
    <p>Exportez le modèle, complétez-le hors ligne puis importez‑le pour enregistrer un inventaire. Vous pouvez aussi consulter l'historique des sessions.</p>

    <?php if ($message) echo $message; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Gestion de l'inventaire</h5>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHistory">
                    Consulter l'historique
                </button>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-auto">
                        <a href="?action=export" class="btn btn-primary">Exporter</a>
                    </div>
                    <div class="col-auto">
                        <form method="POST" enctype="multipart/form-data" class="d-flex">
                            <input type="file" name="fichier_inventaire" class="form-control" required accept=".csv">
                            <button type="submit" name="btn_import_inventaire" class="btn btn-success ms-2">Importer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Stock Théorique</th>
                            <th class="text-center">Stock Physique</th>
                            <th class="text-center">Écart</th>
                            <th class="text-center">Seuil</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        $produits = $pdo->query("SELECT id_produit, nom_medicament, stock_total, seuil_alerte FROM produits ORDER BY nom_medicament ASC")->fetchAll();
                        foreach ($produits as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                                <td class="text-center"><?= $p['stock_total'] ?></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"><?= $p['seuil_alerte'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    

<?php if ($isAdmin): ?>
<!-- Modal historique -->
<div class="modal fade" id="modalHistory" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="card shadow-sm">
        <div class="modal-header">
            <h5>Historique des inventaires</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Stock Théorique</th>
                            <th class="text-center">Stock Physique</th>
                            <th class="text-center">Écart</th>
                            <th class="text-center">Seuil</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Utilisateur</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($details_inventaire)): ?>
                            <?php foreach ($details_inventaire as $detail): ?>
                                <tr class="<?= ($detail['ecart'] != 0) ? 'table-warning' : '' ?>">
                                    <td><?= htmlspecialchars($detail['nom_medicament']) ?></td>
                                    <td class="text-center"><?= $detail['stock_theorique'] ?></td>
                                    <td class="text-center fw-bold"><?= $detail['stock_physique'] ?></td>
                                    <td class="text-center fw-bold <?= ($detail['ecart'] > 0) ? 'text-success' : (($detail['ecart'] < 0) ? 'text-danger' : '') ?>">
                                        <?= $detail['ecart'] > 0 ? '+' : '' ?><?= $detail['ecart'] ?>
                                    </td>
                                    <td class="text-center"><?= $detail['seuil_alerte'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($dernier_inventaire['date_inventaire'])) ?></td>
                                    <td><?= htmlspecialchars($dernier_inventaire['statut']) ?></td>
                                    <td><?= htmlspecialchars($dernier_inventaire['nom_complet']) ?></td>
                                    <td class="text-end">
                                        <?php if ($dernier_inventaire['statut'] !== 'traité'): ?>
                                            <a href="?action=edit_inventaire&id=<?= $dernier_inventaire['id_inventaire'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Modifier"><i class="bi bi-pencil"></i></a>
                                            <a href="?action=delete_inventaire&id=<?= $dernier_inventaire['id_inventaire'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cet inventaire ?');" title="Supprimer"><i class="bi bi-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aucun détail d'inventaire à afficher.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
  </div>
</div>
<?php endif; ?>


<?php include '../includes/footer.php'; ?>
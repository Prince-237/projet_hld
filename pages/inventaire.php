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

// --- ACTION : TRAITER UN INVENTAIRE EN COURS ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'process_inventory' && isset($_GET['id'])) {
    $id_inventaire = (int)$_GET['id'];

    // Vérifier que l'inventaire est bien 'en cours'
    $stmtCheck = $pdo->prepare("SELECT statut, date_inventaire FROM inventaires WHERE id_inventaire = ?");
    $stmtCheck->execute([$id_inventaire]);
    $inventory_to_process = $stmtCheck->fetch();

    if ($inventory_to_process && $inventory_to_process['statut'] === 'en cours') {
        $pdo->beginTransaction();
        try {
            // Récupérer tous les détails de cet inventaire
            $details = $pdo->prepare("SELECT * FROM inventaire_details WHERE id_inventaire = ?");
            $details->execute([$id_inventaire]);

            while ($detail = $details->fetch()) {
                $ecart = (int)$detail['ecart'];
                $id_produit = (int)$detail['id_produit'];
                $stock_physique = (int)$detail['stock_physique'];

                if ($ecart != 0) {
                    // Mettre à jour le stock total du produit
                    $pdo->prepare("UPDATE produits SET stock_total = ? WHERE id_produit = ?")->execute([$stock_physique, $id_produit]);
                    // Logique d'ajustement des lots
                    if ($ecart > 0) { // Surplus
                        $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle + ? WHERE id_produit = ? ORDER BY date_expiration DESC LIMIT 1")->execute([$ecart, $id_produit]);
                    } else { // Manque
                        $a_retirer = abs($ecart);
                        $lots = $pdo->prepare("SELECT id_lot, quantite_actuelle FROM stock_lots WHERE id_produit = ? AND quantite_actuelle > 0 ORDER BY date_expiration ASC");
                        $lots->execute([$id_produit]);
                        while (($lot = $lots->fetch()) && $a_retirer > 0) {
                            $retrait_sur_lot = min($a_retirer, $lot['quantite_actuelle']);
                            $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?")->execute([$retrait_sur_lot, $lot['id_lot']]);
                            $a_retirer -= $retrait_sur_lot;
                        }
                    }
                }
            }
            // Passer le statut de l'inventaire à 'traité'
            $pdo->prepare("UPDATE inventaires SET statut = 'traité' WHERE id_inventaire = ?")->execute([$id_inventaire]);

            // NOUVEAU: Supprimer les autres brouillons pour la même période pour nettoyer l'historique
            $inv_date = $inventory_to_process['date_inventaire'];
            $inv_year = date('Y', strtotime($inv_date));
            $inv_month = date('n', strtotime($inv_date));

            // 1. Trouver les IDs des autres brouillons à supprimer
            $stmt_to_delete = $pdo->prepare(
                "SELECT id_inventaire FROM inventaires 
                 WHERE YEAR(date_inventaire) = ? 
                 AND MONTH(date_inventaire) = ? 
                 AND statut = 'en cours' 
                 AND id_inventaire != ?"
            );
            $stmt_to_delete->execute([$inv_year, $inv_month, $id_inventaire]);
            $ids_to_delete = $stmt_to_delete->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids_to_delete)) {
                $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                $pdo->prepare("DELETE FROM inventaire_details WHERE id_inventaire IN ($placeholders)")->execute($ids_to_delete);
                $pdo->prepare("DELETE FROM inventaires WHERE id_inventaire IN ($placeholders)")->execute($ids_to_delete);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Inventaire traité et validé. Les stocks ont été mis à jour.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors du traitement : " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Cet inventaire n'est pas 'en cours' ou n'existe pas.</div>";
    }
}


// --- ACTION : EXPORT CSV ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventaire_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    // Entêtes du CSV
    fputcsv($output, ['id_produit', 'nom_medicament', 'stock_theorique', 'stock_physique', 'ecart'], ';');

    $produits = $pdo->query("SELECT id_produit, nom_medicament, stock_total FROM produits ORDER BY nom_medicament ASC")->fetchAll();
    $rowIndex = 2; // Excel starts at row 1 for header
    foreach ($produits as $p) {
        // La colonne E (5ème colonne) sera l'écart automatique calculé dans Excel
        $formula = sprintf('=D%d-C%d', $rowIndex, $rowIndex);
        fputcsv($output, [$p['id_produit'], $p['nom_medicament'], $p['stock_total'], '', $formula], ';');
        $rowIndex++;
    }
    fclose($output);
    exit();
}

// --- ACTION : MODIFIER UN INVENTAIRE (stub) ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'edit_inventaire' && isset($_GET['id'])) {
    $id_inv = (int)$_GET['id'];
    // La vérification du statut 'traité' est retirée pour permettre la correction.
    // La fonctionnalité de modification n'est pas entièrement implémentée.
    $message = "<div class='alert alert-info'>La fonction de modification d'un inventaire existant n'est pas encore implémentée. Pour corriger une erreur, veuillez supprimer l'inventaire et l'importer à nouveau.</div>";
}

// --- ACTION : SUPPRIMER UN INVENTAIRE ---
if ($isAdmin && isset($_GET['action']) && $_GET['action'] === 'delete_inventaire' && isset($_GET['id'])) {
    $id_inv = (int)$_GET['id'];
    
    // On vérifie le statut avant de supprimer
    $stmtCheck = $pdo->prepare("SELECT statut FROM inventaires WHERE id_inventaire = ?");
    $stmtCheck->execute([$id_inv]);
    $statut = $stmtCheck->fetchColumn();
    
    if ($statut === 'traité' || $statut === 'en cours') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM inventaire_details WHERE id_inventaire = ?")->execute([$id_inv]);
            $pdo->prepare("DELETE FROM inventaires WHERE id_inventaire = ?")->execute([$id_inv]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>L'inventaire a été supprimé avec succès.</div>";
        } catch (Exception $e) {
           $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression : " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>L'inventaire à supprimer n'a pas été trouvé.</div>";
    }
}

// --- ACTION : IMPORT CSV ---
if ($isAdmin && isset($_POST['btn_import_inventaire'])) {
    if (isset($_FILES['fichier_inventaire']) && $_FILES['fichier_inventaire']['error'] == 0) {

        // VERIFICATION : Un inventaire pour le mois en cours existe-t-il déjà ?
        $currentMonth = date('n');
        $currentYear = date('Y');
        $stmtCheck = $pdo->prepare("SELECT id_inventaire, statut FROM inventaires WHERE YEAR(date_inventaire) = ? AND MONTH(date_inventaire) = ?");
        $stmtCheck->execute([$currentYear, $currentMonth]);
        $existing_inv = $stmtCheck->fetch();
        
        if ($existing_inv && $existing_inv['statut'] === 'traité') {
            $message = "<div class='alert alert-danger'>Un inventaire pour ce mois-ci est déjà traité et verrouillé. Vous ne pouvez pas l'écraser.</div>";
        } else {

        $fileName = $_FILES['fichier_inventaire']['tmp_name'];
        
        $pdo->beginTransaction();
        try {
            // Si un inventaire 'en cours' existe, on le supprime pour le remplacer
            if ($existing_inv && $existing_inv['statut'] === 'en cours') {
                $id_to_delete = $existing_inv['id_inventaire'];
                $pdo->prepare("DELETE FROM inventaire_details WHERE id_inventaire = ?")->execute([$id_to_delete]);
                $pdo->prepare("DELETE FROM inventaires WHERE id_inventaire = ?")->execute([$id_to_delete]);
            }

            // 1. Créer l'enregistrement de l'inventaire parent avec le statut 'en cours'
            $stmt = $pdo->prepare("INSERT INTO inventaires (date_inventaire, id_user, statut) VALUES (NOW(), ?, 'en cours')");
            $stmt->execute([$_SESSION['user_id']]);
            $id_inventaire = $pdo->lastInsertId();

            $file = fopen($fileName, 'r');
            fgetcsv($file); // Ignorer la ligne d'en-tête

            $ligne = 1; // Compteur pour indiquer la ligne en erreur

            while (($data = fgetcsv($file, 1000, ';')) !== FALSE) {
                $ligne++;
                
                // Ignorer les lignes vides
                if ($data === [null] || empty($data)) continue;

                // Vérification du nombre de colonnes et du type (Entiers uniquement)
                if (count($data) < 4) throw new Exception("Erreur ligne $ligne : Format invalide (colonnes manquantes).");
                
                $csv_id = trim($data[0]);
                $csv_qte = trim($data[3]);

                if (!ctype_digit($csv_id)) throw new Exception("Erreur ligne $ligne : L'ID '$csv_id' n'est pas valide. Uniquement des chiffres entiers sont acceptés.");
                if (!ctype_digit($csv_qte)) throw new Exception("Erreur ligne $ligne : La quantité '$csv_qte' n'est pas valide. Uniquement des entiers positifs sont acceptés.");

                $id_produit = (int)$csv_id;
                $stock_physique = (int)$csv_qte;

                // Récupérer le stock théorique actuel de la BDD
                $stmtProd = $pdo->prepare("SELECT stock_total FROM produits WHERE id_produit = ?");
                $stmtProd->execute([$id_produit]);
                $stock_theorique_db = $stmtProd->fetchColumn();

                // Blocage si le produit n'existe pas (Tentative d'ajout manuel par l'utilisateur)
                if ($stock_theorique_db === false) {
                    throw new Exception("Erreur ligne $ligne : Le produit ID $id_produit est inconnu dans la base. Vous ne pouvez pas ajouter de nouveaux produits via ce fichier.");
                }

                $ecart = $stock_physique - $stock_theorique_db;

                // 2. Enregistrer le détail de la ligne d'inventaire

                $stmtDetail = $pdo->prepare("INSERT INTO inventaire_details (id_inventaire, id_produit, stock_theorique, stock_physique, ecart) VALUES (?, ?, ?, ?, ?)");
                $stmtDetail->execute([$id_inventaire, $id_produit, $stock_theorique_db, $stock_physique, $ecart]);

            }
            fclose($file);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Inventaire importé en tant que brouillon. Vous devez le valider pour mettre à jour les stocks.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors du traitement de l'inventaire : " . $e->getMessage() . "</div>";
        }
        }
    } else {
        $message = "<div class='alert alert-danger'>Erreur lors de l'upload du fichier ou aucun fichier sélectionné.</div>";
    }
}

// --- ACTION : SAISIE EN LIGNE (MANUELLE) ---
if ($isAdmin && isset($_POST['btn_save_manual_inventaire'])) {
    // 1. Vérification mois en cours (même logique que l'import)
    $currentMonth = date('n');
    $currentYear = date('Y');
    $stmtCheck = $pdo->prepare("SELECT id_inventaire, statut FROM inventaires WHERE YEAR(date_inventaire) = ? AND MONTH(date_inventaire) = ?");
    $stmtCheck->execute([$currentYear, $currentMonth]);
    $existing_inv = $stmtCheck->fetch();
    
    if ($existing_inv && $existing_inv['statut'] === 'traité') {
        $message = "<div class='alert alert-danger'>Un inventaire pour ce mois-ci est déjà traité et verrouillé. Vous ne pouvez pas l'écraser.</div>";
    } else {
        $pdo->beginTransaction();
        try {
            // Si un inventaire 'en cours' existe, on le supprime pour le remplacer
            if ($existing_inv && $existing_inv['statut'] === 'en cours') {
                $id_to_delete = $existing_inv['id_inventaire'];
                $pdo->prepare("DELETE FROM inventaire_details WHERE id_inventaire = ?")->execute([$id_to_delete]);
                $pdo->prepare("DELETE FROM inventaires WHERE id_inventaire = ?")->execute([$id_to_delete]);
            }

            // Création de l'entête avec statut 'en cours'
            $stmt = $pdo->prepare("INSERT INTO inventaires (date_inventaire, id_user, statut) VALUES (NOW(), ?, 'en cours')");
            $stmt->execute([$_SESSION['user_id']]);
            $id_inventaire = $pdo->lastInsertId();

            // Traitement des lignes (On attend un tableau stocks[id_produit] = quantite)
            if (isset($_POST['stocks']) && is_array($_POST['stocks'])) {
                foreach ($_POST['stocks'] as $id_prod => $qty_physique) {
                    $id_prod = (int)$id_prod;
                    $qty_physique = (int)$qty_physique;

                    // Récupérer stock théorique
                    $stmtProd = $pdo->prepare("SELECT stock_total FROM produits WHERE id_produit = ?");
                    $stmtProd->execute([$id_prod]);
                    $stock_theo = $stmtProd->fetchColumn();
                    if ($stock_theo === false) continue;

                    $ecart = $qty_physique - $stock_theo;

                    // Enregistrement détail
                    $pdo->prepare("INSERT INTO inventaire_details (id_inventaire, id_produit, stock_theorique, stock_physique, ecart) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$id_inventaire, $id_prod, $stock_theo, $qty_physique, $ecart]);

                }
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Brouillon d'inventaire enregistré. Vous devez le valider pour mettre à jour les stocks.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de l'enregistrement : " . $e->getMessage() . "</div>";
        }
    }
}

// --- AFFICHAGE ---
// Priorité 1: Y a-t-il un inventaire 'en cours' pour ce mois-ci ?
$currentMonth = date('n');
$currentYear = date('Y');
$stmt_active = $pdo->prepare("
    SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet 
    FROM inventaires i
    JOIN utilisateurs u ON i.id_user = u.id_user
    WHERE YEAR(i.date_inventaire) = ? AND MONTH(i.date_inventaire) = ? AND i.statut = 'en cours'
    ORDER BY i.id_inventaire DESC LIMIT 1
");
$stmt_active->execute([$currentYear, $currentMonth]);
$inventaire_a_afficher = $stmt_active->fetch();

// Priorité 2: Sinon, on prend le dernier inventaire 'traité'
if (!$inventaire_a_afficher) {
    $inventaire_a_afficher = $pdo->query("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet FROM inventaires i JOIN utilisateurs u ON i.id_user = u.id_user WHERE i.statut = 'traité' ORDER BY i.id_inventaire DESC LIMIT 1")->fetch();
}


// Historique complet (pour la pop‑up)
$inventaires_history = $pdo->query("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet
                                  FROM inventaires i
                                  JOIN utilisateurs u ON i.id_user = u.id_user
                                  ORDER BY i.date_inventaire DESC")->fetchAll();

// Récupération des produits pour la vue principale de l'état des stocks
$produits_main_view = $pdo->query("
    SELECT nom_medicament, type_produit, stock_total, seuil_alerte 
    FROM produits 
    ORDER BY (type_produit='Laboratoire'), nom_medicament ASC
")->fetchAll();

// Pour l'affichage des mois en français dans le modal
$moisFrancais = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

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
    <p>Exportez le modèle, complétez-le hors ligne dans votre editeur Excel puis importez‑le pour enregistrer un inventaire.</p>

    <?php if ($message) echo $message; ?>

    <?php if ($inventaire_a_afficher && $inventaire_a_afficher['statut'] === 'en cours'): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm">
        <div>
            <h5 class="alert-heading">Un inventaire est en cours !</h5>
            Ce brouillon a été créé le <?= date('d/m/Y à H:i', strtotime($inventaire_a_afficher['date_inventaire'])) ?>. Les stocks ne seront mis à jour qu'après validation.
        </div>
        <a href="?action=process_inventory&id=<?= $inventaire_a_afficher['id_inventaire'] ?>" class="btn btn-success btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir valider cet inventaire ? Cette action est irréversible et mettra à jour tous les stocks.');">
            <i class="bi bi-check-circle-fill"></i> Valider et Traiter l'Inventaire
        </a>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">En ligne / Import-Export</h5>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHistory">
                    Consulter l'historique
                </button>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-auto">
                        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#onlineInventory" aria-expanded="false">
                            <i class="bi bi-pencil-square"></i> Saisie en ligne
                        </button>
                    </div>
                    <div class="col-auto border-end border-2 mx-2"></div>
                    <div class="col-auto">
                        <a href="?action=export" class="btn btn-outline-success">Exporter un Modèle Excel(.csv)</a>
                    </div>
                    <div class="col-auto">
                        <form method="POST" enctype="multipart/form-data" class="d-flex">
                            <input type="file" name="fichier_inventaire" class="form-control" required accept=".csv">
                            <button type="submit" name="btn_import_inventaire" class="btn btn-outline-success ms-2">Importer CSV</button>
                        </form>
                    </div>
                </div>
                
                <!-- Interface de saisie en ligne (Masquée par défaut) -->
                <div class="collapse mt-3" id="onlineInventory">
                    <div class="card card-body bg-light border-primary">
                        <h6 class="card-title text-primary"><i class="bi bi-info-circle"></i> Saisie directe de l'inventaire</h6>
                        <p class="small text-muted">Renseignez la quantité physique constatée pour chaque produit.</p>
                        
                        <form method="POST" onsubmit="return confirm('Attention : Cette action va enregistrer un brouillon d\'inventaire. Si un brouillon existe déjà pour ce mois, il sera écrasé. Continuer ?');">
                            <div style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-bordered bg-white">
                                    <thead class="table-light sticky-top" style="z-index: 1;">
                                        <tr>
                                            <th>Produit</th>
                                            <th>Type</th>
                                            <th style="width: 150px;" class="text-center">Stock Théorique</th>
                                            <th style="width: 150px;" class="text-center">Stock Physique</th>
                                            <th style="width: 100px;" class="text-center">Écart</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Pré-remplir avec les données du brouillon s'il existe
                                        $draft_details = [];
                                        if ($inventaire_a_afficher && $inventaire_a_afficher['statut'] === 'en cours') {
                                            $stmt_draft = $pdo->prepare("SELECT id_produit, stock_physique FROM inventaire_details WHERE id_inventaire = ?");
                                            $stmt_draft->execute([$inventaire_a_afficher['id_inventaire']]);
                                            foreach ($stmt_draft->fetchAll() as $row) {
                                                $draft_details[$row['id_produit']] = $row['stock_physique'];
                                            }
                                        }

                                        $list_form = $pdo->query("SELECT id_produit, nom_medicament, type_produit, stock_total FROM produits ORDER BY (type_produit='Laboratoire'), nom_medicament ASC")->fetchAll();
                                        $current_type = null;
                                        foreach ($list_form as $prod):
                                            if ($prod['type_produit'] !== $current_type) {
                                                $current_type = $prod['type_produit'];
                                                $display_type = ($current_type === 'Medicament') ? 'Pharmacie' : $current_type;
                                                echo '<tr><td colspan="5" class="table-group-divider fw-bold bg-light-subtle">' . htmlspecialchars($display_type) . '</td></tr>';
                                            }
                                            // Quantité physique du brouillon, ou stock théorique par défaut
                                            $phys_qty = isset($draft_details[$prod['id_produit']]) ? $draft_details[$prod['id_produit']] : $prod['stock_total'];
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($prod['nom_medicament']) ?></td>
                                                <td><?= htmlspecialchars($prod['type_produit']) ?></td>
                                                <td class="text-center bg-light text-muted"><?= $prod['stock_total'] ?></td>
                                                <td>
                                                    <input type="number" name="stocks[<?= $prod['id_produit'] ?>]" class="form-control form-control-sm text-center fw-bold" value="<?= $phys_qty ?>" min="0" required data-theo="<?= $prod['stock_total'] ?>" oninput="updateEcart(this)">
                                                </td>
                                                <td class="text-center align-middle">
                                                    <span class="badge bg-secondary ecart-badge">0</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#onlineInventory">Annuler</button>
                                <button type="submit" name="btn_save_manual_inventaire" class="btn btn-primary px-4">Enregistrer le Brouillon</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">État Actuel des Stocks</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Type</th>
                            <th class="text-center">Stock Actuel</th>
                            <th class="text-center">Seuil d'Alerte</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($produits_main_view)): ?>
                        <?php
                            $current_type_main = null;
                            foreach ($produits_main_view as $produit):
                                if ($produit['type_produit'] !== $current_type_main) {
                                    $current_type_main = $produit['type_produit'];
                                    $display_type = ($current_type_main === 'Medicament') ? 'Pharmacie' : $current_type_main;
                                    echo '<tr><td colspan="4" class="table-group-divider fw-bold bg-light-subtle">' . htmlspecialchars($display_type) . '</td></tr>';
                                }
                                $stock = (int)$produit['stock_total'];
                                $seuil = (int)$produit['seuil_alerte'];
                                $rowClass = '';
                                if ($stock <= 0) {
                                    $rowClass = 'table-danger';
                                } elseif ($stock > 0 && $stock <= $seuil) {
                                    $rowClass = 'table-warning';
                                }
                            ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><?= htmlspecialchars($produit['nom_medicament']) ?></td>
                                        <td><?= htmlspecialchars($produit['type_produit']) ?></td>
                                        <td class="text-center fw-bold"><?= $produit['stock_total'] ?></td>
                                        <td class="text-center"><?= $produit['seuil_alerte'] ?></td>
                                    </tr>
                            <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucun produit à afficher.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    

<?php if ($isAdmin): ?>
<!-- Modal historique -->
<div class="modal fade" id="modalHistory" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="card shadow-sm">
        <div class="modal-header">
            <h5>Historique des inventaires</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Mois</label>
                    <select id="history_m" class="form-select">
                        <?php foreach ($moisFrancais as $num => $nom): ?>
                            <option value="<?= $num ?>" <?= ($num == date('n')) ? 'selected' : '' ?>><?= $nom ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Année</label>
                    <select id="history_y" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= ($y == date('Y')) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="btnLoadHistory" class="btn btn-primary w-100">Consulter</button>
                </div>
            </div>

            <h6>Historique complet des sessions</h6>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Utilisateur</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventaires_history)): ?>
                            <?php foreach ($inventaires_history as $inv): ?>
                                <tr>
                                    <td>#<?= $inv['id_inventaire'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($inv['date_inventaire'])) ?></td>
                                    <td>
                                        <?php 
                                        $badgeClass = $inv['statut'] === 'traité' ? 'bg-success' : ($inv['statut'] === 'en cours' ? 'bg-warning' : 'bg-secondary');
                                        echo "<span class='badge {$badgeClass}'>" . htmlspecialchars($inv['statut']) . "</span>";
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($inv['nom_complet']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info view-history-btn" data-year="<?= date('Y', strtotime($inv['date_inventaire'])) ?>" data-month="<?= date('n', strtotime($inv['date_inventaire'])) ?>" title="Consulter cet inventaire"><i class="bi bi-eye"></i></button>
                                        <!-- <a href="?action=edit_inventaire&id=<?= $inv['id_inventaire'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a> -->
                                        <a href="?action=delete_inventaire&id=<?= $inv['id_inventaire'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer l\'inventaire #<?= $inv['id_inventaire'] ?> ? Cette action est irréversible.');" title="Supprimer"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Aucun historique d'inventaire trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <hr class="my-4">

            <div id="historyResultContainer" style="display:none;">
                <p class="fw-semibold" id="historyMetaInfo"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Produit</th>
                                <th>Type</th>
                                <th class="text-center">Stock théorique</th>
                                <th class="text-center">Stock physique</th>
                                <th class="text-center">Écart</th>
                                <th class="text-center">Seuil</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- Chargé via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="historyNoData" class="alert alert-info mt-3" style="display:none;">
                Aucun inventaire traité pour cette période.
            </div>

    </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btnLoadHistory').addEventListener('click', function() {
    const m = document.getElementById('history_m').value;
    const y = document.getElementById('history_y').value;
    const btn = this;
    
    btn.disabled = true;
    btn.textContent = 'Chargement...';

    fetch(`get_inventaire_history.php?year=${y}&month=${m}`)    
    .then(response => {
        if (!response.ok) {
            // Si le statut est 404, 500, etc., on lève une erreur pour aller dans le .catch
            throw new Error(`Erreur HTTP ${response.status} : ${response.statusText}`);
        }
        // On essaie de lire la réponse comme du JSON
        return response.json();
    })
    .then(data => {
        const container = document.getElementById('historyResultContainer');
        const noData = document.getElementById('historyNoData');
        const tbody = document.getElementById('historyTableBody');
        const meta = document.getElementById('historyMetaInfo');
        
        // On s'assure que le message d'erreur est caché s'il était affiché
        noData.style.display = 'none';

        tbody.innerHTML = '';

        if (data.found) {
            noData.style.display = 'none';
            container.style.display = 'block';
            
            meta.innerHTML = `Inventaire traité le ${new Date(data.header.date_inventaire).toLocaleDateString('fr-FR')} par ${data.header.nom_complet}`;

            data.details.forEach(item => {
                const ecartVal = parseInt(item.ecart);
                const ecartStr = ecartVal > 0 ? '+' + ecartVal : ecartVal;
                const rowClass = ecartVal !== 0 ? 'table-warning' : '';
                
                const row = `
                    <tr class="${rowClass}">
                        <td>${item.nom_medicament}</td>
                        <td>${item.type_produit || ''}</td>
                        <td class="text-center">${item.stock_theorique}</td>
                        <td class="text-center">${item.stock_physique}</td>
                        <td class="text-center">${ecartStr}</td>
                        <td class="text-center">${item.seuil_alerte}</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });

        } else {
            container.style.display = 'none';
            noData.textContent = data.message || 'Aucun inventaire traité trouvé pour cette période.';
            noData.style.display = 'block';
        }
    })
    .catch(err => {
        console.error("Erreur détaillée lors du chargement de l'historique :", err);
        alert("Une erreur est survenue. Ouvrez la console (F12) pour voir les détails techniques.");
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Consulter';
    });
});

document.querySelectorAll('.view-history-btn').forEach(button => {
    button.addEventListener('click', function() {
        const year = this.dataset.year;
        const month = this.dataset.month;

        // Set the values in the dropdowns
        document.getElementById('history_y').value = year;
        document.getElementById('history_m').value = month;

        // Trigger the click on the main "Consulter" button
        document.getElementById('btnLoadHistory').click();
    });
});

function updateEcart(input) {
    const theo = parseInt(input.getAttribute('data-theo')) || 0;
    const phys = parseInt(input.value) || 0;
    const ecart = phys - theo;
    const badge = input.closest('tr').querySelector('.ecart-badge');
    
    badge.textContent = (ecart > 0 ? '+' : '') + ecart;
    if (ecart === 0) {
        badge.className = 'badge bg-secondary ecart-badge';
    } else {
        badge.className = 'badge ' + (ecart > 0 ? 'bg-warning text-dark' : 'bg-danger') + ' ecart-badge';
    }
}
</script>
<?php endif; ?>


<?php include '../includes/footer.php'; ?>
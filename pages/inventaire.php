<?php

require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$hasObservationColumn = false;
try {
    $colObs = $pdo->query("SHOW COLUMNS FROM InventaireDetail LIKE 'observation'");
    $hasObservationColumn = $colObs && $colObs->rowCount() > 0;
} catch (Exception $e) {
    $hasObservationColumn = false;
}

// --- ACTION : RECALCUL SEUIL ---
if ($isAdmin && isset($_POST['btn_recalcul_seuil'])) {
    // Calcul : Somme des sorties des 90 derniers jours / 3
    // On utilise le num_bordereau (TR-YYYYMMDD...) pour filtrer la date car Transfert n'a pas de colonne date indexée simple
    $sql = "UPDATE Produit p SET seuil_alerte = CEIL((
        SELECT IFNULL(SUM(td.quantite_transfert), 0)
        FROM TransfertDetail td
        JOIN Transfert t ON td.id_transfert = t.id_transfert
        JOIN StockLot l ON td.id_lot = l.id_lot
        WHERE l.id_produit = p.id_produit
        -- On filtre grossièrement sur le bordereau qui commence par TR-YYYYMMDD
        AND t.num_bordereau >= CONCAT('TR-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y%m%d'), '000000')
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
    $stmtCheck = $pdo->prepare("SELECT statut, date_inventaire FROM Inventaire WHERE id_inventaire = ?");
    $stmtCheck->execute([$id_inventaire]);
    $inventory_to_process = $stmtCheck->fetch();

    if ($inventory_to_process && $inventory_to_process['statut'] === 'en cours') {
        $pdo->beginTransaction();
        try {
            // Récupérer tous les détails de cet inventaire
            $details = $pdo->prepare("SELECT * FROM InventaireDetail WHERE id_inventaire = ?");
            $details->execute([$id_inventaire]);

            while ($detail = $details->fetch()) {
                $ecart = (int)$detail['ecart'];
                $id_lot = (int)$detail['id_lot'];
                // Ici on suppose que InventaireDetail pointe vers StockLot comme défini dans newSql.sql

                if ($ecart != 0) {
                    // Mise à jour directe du lot concerné
                    // Si écart positif (Surplus) : On ajoute au lot
                    // Si écart négatif (Manque) : On retire du lot
                    
                    // Vérification stock négatif impossible
                    if ($ecart < 0) {
                        // On vérifie qu'on ne descend pas en dessous de 0
                        $stmtLot = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                        $stmtLot->execute([$id_lot]);
                        $qteActuelle = $stmtLot->fetchColumn();
                        if ($qteActuelle + $ecart < 0) {
                             throw new Exception("Impossible de valider : le stock du lot #$id_lot deviendrait négatif.");
                        }
                    }

                    $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?")->execute([$ecart, $id_lot]);
                }
            }
            // Passer le statut de l'inventaire à 'traité'
            $pdo->prepare("UPDATE Inventaire SET statut = 'traité' WHERE id_inventaire = ?")->execute([$id_inventaire]);

            // Supprimer les autres brouillons pour la même période pour nettoyer l'historique
            $inv_date = $inventory_to_process['date_inventaire'];
            $inv_year = date('Y', strtotime($inv_date));
            $inv_month = date('n', strtotime($inv_date));

            // Trouver les IDs des autres brouillons à supprimer
            $stmt_to_delete = $pdo->prepare(
                "SELECT id_inventaire FROM Inventaire 
                 WHERE YEAR(date_inventaire) = ? 
                 AND MONTH(date_inventaire) = ? 
                 AND statut = 'en cours' 
                 AND id_inventaire != ?"
            );
            $stmt_to_delete->execute([$inv_year, $inv_month, $id_inventaire]);
            $ids_to_delete = $stmt_to_delete->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids_to_delete)) {
                $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                $pdo->prepare("DELETE FROM InventaireDetail WHERE id_inventaire IN ($placeholders)")->execute($ids_to_delete);
                $pdo->prepare("DELETE FROM Inventaire WHERE id_inventaire IN ($placeholders)")->execute($ids_to_delete);
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
    // En-têtes du CSV - Export par LOT pour plus de précision
    fputcsv($output, ['id_lot', 'nom_medicament', 'num_lot', 'stock_theorique', 'stock_physique', 'ecart', 'observation'], ';');

    // Récupération des lots ayant un stock positif
    $sqlExp = "SELECT l.id_lot, p.nom_medicament, l.num_lot, l.quantite_actuelle 
               FROM StockLot l JOIN Produit p ON l.id_produit = p.id_produit 
               WHERE l.quantite_actuelle > 0 ORDER BY p.nom_medicament ASC";
    $lots = $pdo->query($sqlExp)->fetchAll();
    $rowIndex = 2; // Excel commence à la ligne 1 pour l'en-tête
    foreach ($lots as $l) {
        $formula = sprintf('=E%d-D%d', $rowIndex, $rowIndex);
        fputcsv($output, [$l['id_lot'], $l['nom_medicament'], $l['num_lot'], $l['quantite_actuelle'], '', $formula, ''], ';');
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
    
    // Vérification du statut avant suppression
    $stmtCheck = $pdo->prepare("SELECT statut FROM Inventaire WHERE id_inventaire = ?");
    $stmtCheck->execute([$id_inv]);
    $statut = $stmtCheck->fetchColumn();
    
    if ($statut === 'traité' || $statut === 'en cours') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM InventaireDetail WHERE id_inventaire = ?")->execute([$id_inv]);
            $pdo->prepare("DELETE FROM Inventaire WHERE id_inventaire = ?")->execute([$id_inv]);
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

        // VÉRIFICATION : Un inventaire pour le mois en cours existe-t-il déjà ?
        $currentMonth = date('n');
        $currentYear = date('Y');
        $stmtCheck = $pdo->prepare("SELECT id_inventaire, statut FROM Inventaire WHERE YEAR(date_inventaire) = ? AND MONTH(date_inventaire) = ?");
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
                $pdo->prepare("DELETE FROM InventaireDetail WHERE id_inventaire = ?")->execute([$id_to_delete]);
                $pdo->prepare("DELETE FROM Inventaire WHERE id_inventaire = ?")->execute([$id_to_delete]);
            }

            // Création de l'enregistrement de l'inventaire parent avec le statut 'en cours'
            $stmt = $pdo->prepare("INSERT INTO Inventaire (date_inventaire, id_user, statut) VALUES (NOW(), ?, 'en cours')");
            $stmt->execute([$_SESSION['user_id']]);
            $id_inventaire = $pdo->lastInsertId();

            $file = fopen($fileName, 'r');
            $headers = fgetcsv($file, 1000, ';');
            if ($headers === FALSE || count($headers) < 7) {
                throw new Exception('Format CSV invalide : en-tête manquante ou colonne observation absente.');
            }
            $headerNames = array_map('trim', $headers);
            if (strtolower($headerNames[6]) !== 'observation') {
                throw new Exception('Format CSV invalide : la 7e colonne doit être "observation".');
            }

            $ligne = 1; // Compteur pour indiquer la ligne en erreur
            $stmtDetailObs = $hasObservationColumn
                ? $pdo->prepare("INSERT INTO InventaireDetail (id_inventaire, id_lot, stock_theorique, stock_physique, observation) VALUES (?, ?, ?, ?, ?)")
                : $pdo->prepare("INSERT INTO InventaireDetail (id_inventaire, id_lot, stock_theorique, stock_physique) VALUES (?, ?, ?, ?)");

            while (($data = fgetcsv($file, 1000, ';')) !== FALSE) {
                $ligne++;
                
                // Ignorer les lignes vides
                if ($data === [null] || empty($data)) continue;
                
                // Format attendu CSV : id_lot; nom; num_lot; stock_theorique; stock_physique; ecart; observation
                if (count($data) < 7) throw new Exception("Erreur ligne $ligne : Format invalide (colonnes manquantes).");
                
                $csv_id_lot = trim($data[0]);
                $csv_qte = trim($data[4]); // stock_physique is at index 4
                $csv_observation = isset($data[6]) ? trim($data[6]) : '';
                
                if (!ctype_digit($csv_id_lot)) throw new Exception("Erreur ligne $ligne : L'ID Lot '$csv_id_lot' n'est pas valide.");
                if (!ctype_digit($csv_qte)) throw new Exception("Erreur ligne $ligne : La quantité '$csv_qte' n'est pas valide. Uniquement des entiers positifs sont acceptés.");
                
                $id_lot = (int)$csv_id_lot;
                $stock_physique = (int)$csv_qte;
                
                // Récupérer le stock théorique actuel de la BDD (table StockLot)
                $stmtLot = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                $stmtLot->execute([$id_lot]);
                $stock_theorique_db = $stmtLot->fetchColumn();
                
                if ($stock_theorique_db === false) {
                    throw new Exception("Erreur ligne $ligne : Le lot ID $id_lot est inconnu.");
                }
                
                $ecart_calcule = $stock_physique - (int)$stock_theorique_db;
                if ($ecart_calcule !== 0 && $csv_observation === '') {
                    throw new Exception("Erreur ligne $ligne : Observation obligatoire lorsque l'écart n'est pas nul.");
                }
                
                // 2. Enregistrer le détail de la ligne d'inventaire
                if ($hasObservationColumn) {
                    $stmtDetailObs->execute([$id_inventaire, $id_lot, $stock_theorique_db, $stock_physique, $csv_observation]);
                } else {
                    $stmtDetailObs->execute([$id_inventaire, $id_lot, $stock_theorique_db, $stock_physique]);
                }
                
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
    // Vérification du mois en cours (même logique que l'import)
    $currentMonth = date('n');
    $currentYear = date('Y');
    $stmtCheck = $pdo->prepare("SELECT id_inventaire, statut FROM Inventaire WHERE YEAR(date_inventaire) = ? AND MONTH(date_inventaire) = ?");
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
                $pdo->prepare("DELETE FROM InventaireDetail WHERE id_inventaire = ?")->execute([$id_to_delete]);
                $pdo->prepare("DELETE FROM Inventaire WHERE id_inventaire = ?")->execute([$id_to_delete]);
            }

            // Création de l'en-tête avec statut 'en cours'
            $stmt = $pdo->prepare("INSERT INTO Inventaire (date_inventaire, id_user, statut) VALUES (NOW(), ?, 'en cours')");
            $stmt->execute([$_SESSION['user_id']]);
            $id_inventaire = $pdo->lastInsertId();

            $observation = trim($_POST['observation'] ?? '');
            $requiresObservation = false;
            $stmtInsertDetail = $hasObservationColumn
                ? $pdo->prepare("INSERT INTO InventaireDetail (id_inventaire, id_lot, stock_theorique, stock_physique, observation) VALUES (?, ?, ?, ?, ?)")
                : $pdo->prepare("INSERT INTO InventaireDetail (id_inventaire, id_lot, stock_theorique, stock_physique) VALUES (?, ?, ?, ?)");

            // Vérification des écarts avant insertion
            if (isset($_POST['stocks']) && is_array($_POST['stocks'])) {
                foreach ($_POST['stocks'] as $id_lot => $qty_physique) {
                    $id_lot = (int)$id_lot;
                    $qty_physique = (int)$qty_physique;

                    $stmtLot = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                    $stmtLot->execute([$id_lot]);
                    $stock_theo = $stmtLot->fetchColumn();
                    if ($stock_theo === false) {
                        continue;
                    }

                    if ($qty_physique !== (int)$stock_theo) {
                        $requiresObservation = true;
                        break;
                    }
                }
            }

            if ($requiresObservation && $observation === '') {
                throw new Exception('Observation obligatoire lorsque l\'écart est différent de zéro.');
            }

            // Insertion des lignes
            if (isset($_POST['stocks']) && is_array($_POST['stocks'])) {
                foreach ($_POST['stocks'] as $id_lot => $qty_physique) {
                    $id_lot = (int)$id_lot;
                    $qty_physique = (int)$qty_physique;

                    $stmtLot = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ?");
                    $stmtLot->execute([$id_lot]);
                    $stock_theo = $stmtLot->fetchColumn();
                    if ($stock_theo === false) {
                        continue;
                    }

                    if ($hasObservationColumn) {
                        $stmtInsertDetail->execute([$id_inventaire, $id_lot, $stock_theo, $qty_physique, $observation]);
                    } else {
                        $stmtInsertDetail->execute([$id_inventaire, $id_lot, $stock_theo, $qty_physique]);
                    }
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
    FROM Inventaire i
    JOIN Utilisateur u ON i.id_user = u.id_user
    WHERE YEAR(i.date_inventaire) = ? AND MONTH(i.date_inventaire) = ? AND i.statut = 'en cours'
    ORDER BY i.id_inventaire DESC LIMIT 1
");
$stmt_active->execute([$currentYear, $currentMonth]);
$inventaire_a_afficher = $stmt_active->fetch();

// Priorité 2: Sinon, on prend le dernier inventaire 'traité'
if (!$inventaire_a_afficher) {
    $inventaire_a_afficher = $pdo->query("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet FROM Inventaire i JOIN Utilisateur u ON i.id_user = u.id_user WHERE i.statut = 'traité' ORDER BY i.id_inventaire DESC LIMIT 1")->fetch();
}


// Historique complet
$inventaires_history = $pdo->query("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet
                                  FROM Inventaire i
                                  JOIN Utilisateur u ON i.id_user = u.id_user
                                  ORDER BY i.date_inventaire DESC")->fetchAll();

// Récupération des produits pour la vue principale de l'état des stocks
$produits_main_view = $pdo->query("
    SELECT p.nom_medicament, p.type_produit, p.seuil_alerte,
           COALESCE(SUM(l.quantite_actuelle), 0) as stock_total
    FROM Produit p
    LEFT JOIN StockLot l ON p.id_produit = l.id_produit
    GROUP BY p.id_produit
    ORDER BY (p.type_produit='Laboratoire'), p.nom_medicament ASC
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
    <p>Exportez le modèle, complétez-le hors ligne dans votre éditeur Excel, puis importez-le pour enregistrer un inventaire.</p>

    <?php if ($message) echo $message; ?>

    <?php if ($inventaire_a_afficher && $inventaire_a_afficher['statut'] === 'en cours'): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm">
        <div>
            <h5 class="alert-heading">Un inventaire est en cours !</h5>
            Ce brouillon a été créé le <?= date('d/m/Y à H:i', strtotime($inventaire_a_afficher['date_inventaire'])) ?>. Les stocks ne seront mis à jour qu'après validation finale.
        </div>
        <a href="?action=process_inventory&id=<?= $inventaire_a_afficher['id_inventaire'] ?>" class="btn btn-success btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir valider cet inventaire ? Cette action est irréversible et mettra à jour les stocks physiques.');">
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
                        <a href="?action=export" class="btn btn-outline-success">Exporter un Modèle Excel (.csv)</a>
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
                        <form method="POST" onsubmit="return confirm('Attention : Cette action va enregistrer un brouillon d\'inventaire. Si un brouillon existe déjà pour ce mois, il sera remplacé. Continuer ?');">
                            <div class="mb-3">
                                <label for="inventoryObservation" class="form-label fw-bold">Observations</label>
                                <textarea id="inventoryObservation" name="observation" class="form-control" rows="3" placeholder="Décrivez les écarts constatés si un ou plusieurs lots diffèrent du stock théorique."></textarea>
                                <div class="form-text">Obligatoire si au moins un écart existe entre stock physique et stock théorique.</div>
                            </div>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-bordered bg-white">
                                    <thead class="table-light sticky-top" style="z-index: 1;">
                                        <tr>
                                            <th>Produit</th>
                                            <th>Type</th>
                                            <th>Lot</th>
                                            <th style="width: 140px;" class="text-center">Stock Théorique</th>
                                            <th style="width: 140px;" class="text-center">Stock Physique</th>
                                            <th style="width: 100px;" class="text-center">Écart</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Pré-remplissage avec les données du brouillon s'il existe
                                        $draft_details = [];
                                        if ($inventaire_a_afficher && $inventaire_a_afficher['statut'] === 'en cours') {
                                            $stmt_draft = $pdo->prepare("SELECT id_lot, stock_physique FROM InventaireDetail WHERE id_inventaire = ?");
                                            $stmt_draft->execute([$inventaire_a_afficher['id_inventaire']]);
                                            foreach ($stmt_draft->fetchAll() as $row) {
                                                $draft_details[$row['id_lot']] = $row['stock_physique'];
                                            }
                                        }

                                        // Liste des lots actifs
                                        $list_form = $pdo->query("SELECT l.id_lot, l.num_lot, l.quantite_actuelle, p.nom_medicament, p.type_produit 
                                                                  FROM StockLot l JOIN Produit p ON l.id_produit = p.id_produit 
                                                                  WHERE l.quantite_actuelle > 0 
                                                                  ORDER BY (p.type_produit='Laboratoire'), p.nom_medicament ASC")->fetchAll();

                                        $current_type = null;
                                        foreach ($list_form as $prod):
                                            if ($prod['type_produit'] !== $current_type) {
                                                $current_type = $prod['type_produit'];
                                                $display_type = ($current_type === 'Medicament') ? 'Pharmacie' : $current_type;
                                                echo '<tr><td colspan="6" class="table-group-divider fw-bold bg-light-subtle">' . htmlspecialchars($display_type) . '</td></tr>';
                                            }
                                            // Quantité physique du brouillon, ou stock théorique par défaut
                                            $phys_qty = isset($draft_details[$prod['id_lot']]) ? $draft_details[$prod['id_lot']] : $prod['quantite_actuelle'];
                                            $ecart_init = (int)$phys_qty - (int)$prod['quantite_actuelle'];
                                            $ecart_label = ($ecart_init > 0 ? '+' : '') . $ecart_init;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($prod['nom_medicament']) ?></td>
                                                <td><?= htmlspecialchars($prod['type_produit']) ?></td>
                                                <td><small><?= htmlspecialchars($prod['num_lot']) ?></small></td>
                                                <td class="text-center bg-light text-muted"><?= $prod['quantite_actuelle'] ?></td>
                                                <td>
                                                    <input type="number"
                                                           name="stocks[<?= $prod['id_lot'] ?>]"
                                                           class="form-control form-control-sm text-center fw-bold"
                                                           value="<?= $phys_qty ?>"
                                                           min="0"
                                                           data-theo="<?= $prod['quantite_actuelle'] ?>"
                                                           oninput="updateEcart(this)"
                                                           required>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $ecart_init === 0 ? 'bg-secondary' : ($ecart_init > 0 ? 'bg-warning text-dark' : 'bg-danger') ?> ecart-badge">
                                                        <?= $ecart_label ?>
                                                    </span>
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
                            <!-- <th>ID</th> -->
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
                                    <!-- <td>#<?= $inv['id_inventaire'] ?></td> -->
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
                        <tbody id="historyTableBody"></tbody>
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
            // Si le statut est 404, 500, etc., on lève une erreur
            throw new Error(`Erreur HTTP ${response.status} : ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        const container = document.getElementById('historyResultContainer');
        const noData = document.getElementById('historyNoData');
        const tbody = document.getElementById('historyTableBody');
        const meta = document.getElementById('historyMetaInfo');
        
        // On s'assure que le message d'erreur est caché
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

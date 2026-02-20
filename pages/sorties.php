<?php
// 1. Inclusion des dépendances

require_once('../config/db.php');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
include('../includes/header.php');

$message = "";

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $point_vente = $_POST['point_vente'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    $prix_vente = $_POST['prix_vente'];
    $id_user = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // ÉTAPE A : Vérifier si le stock disponible dans ce lot est suffisant
        $checkSql = "SELECT quantite_actuelle, id_produit FROM stock_lots WHERE id_lot = :id_lot FOR UPDATE";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id_lot' => $id_lot]);
        $lot = $stmtCheck->fetch();

        if ($lot && $lot['quantite_actuelle'] >= $quantite_demandee) {
            
            // ÉTAPE B : Calculer le montant total de la sortie
            $total_prix = $quantite_demandee * $prix_vente;

            // ÉTAPE C : Enregistrer la sortie dans la table 'sorties'
            $sqlInsert = "INSERT INTO sorties (id_lot, nom_point_vente, quantite_sortie, prix_vente_unitaire, total_prix, id_user) 
                          VALUES (:id_lot, :pv, :qte, :prix, :total, :user)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':id_lot' => $id_lot,
                ':pv'     => $point_vente,
                ':qte'    => $quantite_demandee,
                ':prix'   => $prix_vente,
                ':total'  => $total_prix,
                ':user'   => $id_user
            ]);

            // ÉTAPE D : Mettre à jour la quantité restante dans le lot
            $sqlUpdate = "UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - :qte 
                          WHERE id_lot = :id_lot";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':qte'    => $quantite_demandee,
                ':id_lot' => $id_lot
            ]);

            // ÉTAPE E : Réduire également le stock total du produit parent
            if (!empty($lot['id_produit'])) {
                $sqlProd = "UPDATE produits SET stock_total = stock_total - :qte WHERE id_produit = :idp";
                $stmtProd = $pdo->prepare($sqlProd);
                $stmtProd->execute([':qte' => $quantite_demandee, ':idp' => $lot['id_produit']]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie enregistrée et stock mis à jour.</div>";
        } else {
            // Si la quantité en stock est insuffisante
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : Stock insuffisant dans ce lot !</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur système : " . $e->getMessage() . "</div>";
    }
}

// 3. Récupération des lots disponibles (uniquement ceux qui ne sont pas vides)
// On joint la table produits pour afficher le nom du médicament à côté du numéro de lot
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, p.nom_medicament 
            FROM stock_lots l 
            JOIN produits p ON l.id_produit = p.id_produit 
            WHERE l.quantite_actuelle > 0 
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Récupération des sorties enregistrées (pour affichage historique)
$sqlSorties = "SELECT s.*, l.num_lot, p.nom_medicament, u.nom_complet AS utilisateur
               FROM sorties s
               JOIN stock_lots l ON s.id_lot = l.id_lot
               JOIN produits p ON l.id_produit = p.id_produit
               LEFT JOIN utilisateurs u ON s.id_user = u.id_user
               ORDER BY s.id_sortie DESC";
$sorties = $pdo->query($sqlSorties)->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4 text-primary"><i class="fas fa-file-export"></i> Sortie de Médicaments</h2>
    
    <?php echo $message; ?>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label font-weight-bold">Sélectionner le Lot (Médicament - Lot - Quantité dispo)</label>
                        <select name="id_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Note : Les lots sont triés par date d'expiration (FIFO).</small>
                    </div>
                </div>
    
                    

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Point de Vente / Service Destination</label>
                        <input type="text" name="point_vente" class="form-control" placeholder="Ex: Pharmacie de Garde, Urgences..." required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Quantité à sortir</label>
                        <input type="number" name="quantite_sortie" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Prix de vente unitaire</label>
                        <input type="number" step="0.01" name="prix_vente" class="form-control" required>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="valider_sortie" class="btn btn-primary w-100 btn-lg">
                        Confirmer la sortie de stock
                    </button>
                </div>
            </form>
            
        </div>
        
    </div>
    <div class="card mt-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Historique des sorties</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Médicament</th>
                                            <th>Lot</th>
                                            <th>Point de Vente</th>
                                            <th>Qté</th>
                                            <th>Prix U.</th>
                                            <th>Total</th>
                                            <th>Utilisateur</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($sorties)): foreach($sorties as $s): ?>
                                            <tr>
                                                <td><?= $s['date_sortie'] ?></td>
                                                <td><?= $s['nom_medicament'] ?></td>
                                                <td><?= $s['num_lot'] ?></td>
                                                <td><?= $s['nom_point_vente'] ?></td>
                                                <td><?= $s['quantite_sortie'] ?></td>
                                                <td><?= $s['prix_vente_unitaire'] ?></td>
                                                <td><?= $s['total_prix'] ?></td>
                                                <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? $s['utilisateur'] : '—' ?></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="8" class="text-center text-muted">Aucune sortie enregistrée.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
</div>


<?php include('../includes/footer.php'); ?>
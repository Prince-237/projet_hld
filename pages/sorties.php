<?php
// 1. Inclusion des dépendances
require_once('../config/db.php');
include('../includes/header.php');

$message = "";

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $point_vente = $_POST['point_vente'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    $prix_vente = $_POST['prix_vente'];
    $id_user = 1; // À lier à la session utilisateur plus tard

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
</div>

<?php include('../includes/footer.php'); ?>
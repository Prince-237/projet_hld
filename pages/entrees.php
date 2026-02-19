<?php
// 1. Inclusion des fichiers de configuration et du header
require_once('../config/db.php');
include('../includes/header.php');

$message = "";

// 2. Traitement de l'insertion lorsque le formulaire est soumis
if (isset($_POST['ajouter_stock'])) {
    // Récupération des données du formulaire
    $id_produit = $_POST['id_produit'];
    $id_fournisseur = $_POST['id_fournisseur'];
    $num_lot = $_POST['num_lot'];
    $quantite = $_POST['quantite'];
    $date_expiration = $_POST['date_expiration'];
    $prix_ht = $_POST['prix_achat_ht'];
    $prix_ttc = $_POST['prix_achat_ttc'];
    $id_user = 1; // À remplacer par $_SESSION['id_user'] après mise en place du login

    try {
        // Début d'une transaction pour garantir l'intégrité des données
        $pdo->beginTransaction();

        // Requête d'insertion dans la table stock_lots
        $sql = "INSERT INTO stock_lots (id_produit, id_fournisseur, num_lot, quantite_initiale, quantite_actuelle, date_expiration, prix_achat_ht, prix_achat_ttc, id_user) 
                VALUES (:id_p, :id_f, :num, :qte_i, :qte_a, :date_e, :p_ht, :p_ttc, :user)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_p' => $id_produit,
            ':id_f' => $id_fournisseur,
            ':num'  => $num_lot,
            ':qte_i'=> $quantite,
            ':qte_a'=> $quantite, // Au départ, la quantité actuelle est égale à l'initiale
            ':date_e' => $date_expiration,
            ':p_ht' => $prix_ht,
            ':p_ttc'=> $prix_ttc,
            ':user' => $id_user
        ]);

        // Validation de la transaction
        $pdo->commit();
        $message = "<div class='alert alert-success'>Arrivage enregistré avec succès dans le stock.</div>";
    } catch (Exception $e) {
        // En cas d'erreur, on annule tout
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur lors de l'enregistrement : " . $e->getMessage() . "</div>";
    }
}

// 3. Récupération des listes pour les menus déroulants (Produits et Fournisseurs)
$produits = $pdo->query("SELECT id_produit, nom_medicament, dosage FROM produits ORDER BY nom_medicament")->fetchAll();
$fournisseurs = $pdo->query("SELECT id_fournisseur, nom_societe FROM fournisseurs ORDER BY nom_societe")->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4">Gestion des Entrées en Stock (Arrivages)</h2>
    
    <?php echo $message; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Médicament / Produit</label>
                        <select name="id_produit" class="form-select" required>
                            <option value="">Sélectionner le produit...</option>
                            <?php foreach($produits as $p): ?>
                                <option value="<?= $p['id_produit'] ?>"><?= $p['nom_medicament'] ?> (<?= $p['dosage'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fournisseur</label>
                        <select name="id_fournisseur" class="form-select" required>
                            <option value="">Sélectionner le fournisseur...</option>
                            <?php foreach($fournisseurs as $f): ?>
                                <option value="<?= $f['id_fournisseur'] ?>"><?= $f['nom_societe'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Numéro de Lot</label>
                        <input type="text" name="num_lot" class="form-control" placeholder="Ex: LOT-2024-001" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantité Reçue</label>
                        <input type="number" name="quantite" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date d'expiration</label>
                        <input type="date" name="date_expiration" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Coût Unitaire (Avant Impôt / HT)</label>
                        <input type="number" step="0.01" name="prix_achat_ht" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Coût Unitaire (Après Impôt / TTC)</label>
                        <input type="number" step="0.01" name="prix_achat_ttc" class="form-control" required>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" name="ajouter_stock" class="btn btn-success w-100">
                        <i class="fas fa-plus-circle"></i> Enregistrer l'entrée en stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
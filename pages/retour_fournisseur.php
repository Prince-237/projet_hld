<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../includes/sidebar.php';

if (!isset($_GET['id'])) {
    die("Commande invalide");
}

$id_commande = intval($_GET['id']);
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'entrees_stock.php';


// 🔹 INFOS COMMANDE
$sqlCommande = "SELECT 
                    cmd.id_commande,
                    cmd.date_commande,
                    cmd.statut_paiement,
                    part.nom_entite AS fournisseur,
                    part.type AS source_type,
                    u.nom_complet AS utilisateur
                FROM Commande cmd
                LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user
                WHERE cmd.id_commande = ?";

$stmt = $pdo->prepare($sqlCommande);
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();

if (!$commande) {
    die("Commande introuvable");
}


// 🔹 PRODUITS DE LA COMMANDE REÇUE (StockLot)
$sqlProduits = "SELECT 
                    l.id_lot,
                    l.num_lot,
                    l.quantite_actuelle,
                    l.date_expiration,
                    l.prix_achat_ttc,
                    p.nom_medicament
                FROM StockLot l
                JOIN Produit p ON l.id_produit = p.id_produit
                LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
                WHERE cd.id_commande = ?
                ORDER BY p.nom_medicament ASC";

$stmt = $pdo->prepare($sqlProduits);
$stmt->execute([$id_commande]);
$produits = $stmt->fetchAll();


// 🔹 ENREGISTRER LE RETOUR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentaire = $_POST['commentaire'] ?? '';
    $quantities = $_POST['quantities'] ?? [];

    // Validation
    if (empty($commentaire)) {
        $error = "Le commentaire est obligatoire.";
    } else {
        // Vérifier qu'au moins une quantité a été saisie
        $hasQuantity = false;
        foreach ($quantities as $qty) {
            if (intval($qty) > 0) {
                $hasQuantity = true;
                break;
            }
        }
        if (!$hasQuantity) {
            $error = "Veuillez saisir au moins une quantité à retourner.";
        }
    }

    if (!isset($error)) {
        // Vérifier la table RetourFournisseur existe
        try {
            $checkTable = "SHOW TABLES LIKE 'RetourFournisseur'";
            $res = $pdo->query($checkTable);
            if ($res->rowCount() == 0) {
                $error = "Table RetourFournisseur non trouvée. Vous devez d'abord créer les tables de retours dans votre BDD.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Insérer le retour
                    $insertRetour = $pdo->prepare("
                        INSERT INTO RetourFournisseur (id_commande, commentaire, id_user, date_retour)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $insertRetour->execute([$id_commande, $commentaire, $_SESSION['user_id']]);
                    $id_retour = $pdo->lastInsertId();

                    // Insérer les détails (lots retournés)
                    $insertDetail = $pdo->prepare("
                        INSERT INTO RetourFournisseurDetail (id_retour, id_lot, quantite_retournee)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($quantities as $id_lot => $qty) {
                        $qty = intval($qty);
                        if ($qty > 0) {
                            $insertDetail->execute([$id_retour, $id_lot, $qty]);
                        }
                    }

                    $pdo->commit();

                    $_SESSION['success_message'] = "Retour fournisseur enregistré avec succès.";
                    header("Location: liste_retours_fournisseur.php");
                    exit();

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Retour fournisseur - Commande</h2>
        <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-outline-secondary">Retour</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- INFOS COMMANDE -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row">

                <div class="col-md-3">
                    <strong>Date :</strong><br>
                    <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                </div>

                <div class="col-md-3">
                    <strong>Source :</strong><br>
                    <?= htmlspecialchars($commande['source_type'] ?: 'N/A') ?>
                </div>

                <div class="col-md-3">
                    <strong>Fournisseur :</strong><br>
                    <?= htmlspecialchars($commande['fournisseur'] ?: 'N/A') ?>
                </div>

                <div class="col-md-3">
                    <strong>Agent :</strong><br>
                    <?= htmlspecialchars($commande['utilisateur'] ?: '-') ?>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3">
                    <strong>Statut Paiement :</strong><br>
                    <?php 
                    $sp = $commande['statut_paiement'] ?? 'du';
                    $badgeClass = match($sp) {
                        'du' => 'bg-danger',
                        'partielle' => 'bg-warning text-dark',
                        'payé' => 'bg-info',
                        'soldé' => 'bg-success',
                        default => 'bg-secondary'
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($sp) ?></span>
                </div>

            </div>
        </div>
    </div>

    <!-- FORMULAIRE RETOUR -->
    <form method="POST" id="formRetour">

        <!-- COMMENTAIRE OBLIGATOIRE -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <strong>Commentaire du retour</strong>
            </div>
            <div class="card-body">
                <textarea 
                    name="commentaire" 
                    class="form-control" 
                    rows="4" 
                    placeholder="Expliquez la raison du retour (produit endommagé, etc.)" 
                    required><?= isset($_POST['commentaire']) ? htmlspecialchars($_POST['commentaire']) : '' ?></textarea>
                <!-- <small class="text-muted">Ce champ est obligatoire</small> -->
            </div>
        </div>

        <!-- TABLE PRODUITS -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <strong>Sélectionner les lots à retourner</strong>
            </div>

            <div class="table-responsive p-2">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Lot</th>
                            <th>Expiration</th>
                            <th class="text-center">Disponible</th>
                            <th class="text-center">Quantité à retourner</th>
                            <th class="text-end">Prix Achat</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($produits)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Aucun lot trouvé pour cette commande
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                                    <td><?= htmlspecialchars($p['num_lot']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['date_expiration'])) ?></td>
                                    <td class="text-center"><?= $p['quantite_actuelle'] ?></td>
                                    <td class="text-center">
                                        <input type="number" 
                                               name="quantities[<?= $p['id_lot'] ?>]" 
                                               class="form-control form-control-sm" 
                                               min="0" 
                                               max="<?= $p['quantite_actuelle'] ?>"
                                               value="0"
                                               placeholder="0">
                                    </td>
                                    <td class="text-end"><?= number_format($p['prix_achat_ttc'], 2, '.', ' ') ?> F</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

            <!-- ACTIONS -->
            <div class="mt-3 d-flex justify-content-between p-2">
                <!-- <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-secondary">
                    ← Retour
                </a> -->

                <button type="submit" class="btn btn-warning">
                 Enregistrer le retour
                </button>
            </div>
        </div>

    </form>

</div>

<script>
function toggleQuantityInput(checkbox) {
    const row = checkbox.closest('tr');
    const quantityInput = row.querySelector('input[name^="quantities"]');
    if (checkbox.checked) {
        quantityInput.disabled = false;
        quantityInput.focus();
        if (!quantityInput.value) {
            quantityInput.value = 1;
        }
    } else {
        quantityInput.disabled = true;
        quantityInput.value = '';
    }
}

// Validation avant envoi du formulaire
document.getElementById('formRetour').addEventListener('submit', function(e) {
    const commentaire = document.querySelector('textarea[name="commentaire"]').value.trim();
    const checkeds = document.querySelectorAll('.lot-checkbox:checked');
    
    if (!commentaire) {
        e.preventDefault();
        alert('Le commentaire est obligatoire');
        return false;
    }
    
    if (checkeds.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un lot');
        return false;
    }

    // Vérifier que les quantités sont remplies
    let valid = true;
    checkeds.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const quantityInput = row.querySelector('input[name^="quantities"]');
        if (!quantityInput.value || quantityInput.value <= 0) {
            valid = false;
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('⚠️ Remplissez les quantités pour tous les lots sélectionnés');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
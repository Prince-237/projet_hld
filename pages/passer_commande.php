<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$id_produit_filter = isset($_GET['id_produit']) ? intval($_GET['id_produit']) : 0;

// Récupération des fournisseurs (partenaires)
$fournisseurs = $pdo->query("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Fournisseur' ORDER BY nom_entite ASC")->fetchAll();

// Récupération des produits dont le stock total est inférieur au seuil
$sqlProduits = "SELECT p.id_produit, p.nom_medicament, p.type_produit, p.seuil_alerte, p.prix_unitaire, p.marge_pourcentage, 
                       COALESCE(SUM(l.quantite_actuelle), 0) as stock_total
                FROM Produit p
                LEFT JOIN StockLot l ON p.id_produit = l.id_produit
                GROUP BY p.id_produit, p.nom_medicament, p.type_produit, p.seuil_alerte, p.prix_unitaire, p.marge_pourcentage
                HAVING stock_total < p.seuil_alerte
                ORDER BY CASE WHEN p.type_produit = 'Medicament' THEN 1 ELSE 2 END, p.nom_medicament ASC";
$produits = $pdo->query($sqlProduits)->fetchAll();

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_save_commande'])) {
    $id_fournisseur = intval($_POST['id_fournisseur']);
    $produits_ids = $_POST['produit_id'] ?? [];
    $quantites = $_POST['quantite_commande'] ?? [];
    $prix_unitaires = $_POST['prix_unitaire'] ?? [];
    $marges = $_POST['marge'] ?? [];

    if (empty($id_fournisseur)) {
        $message = "<div class='alert alert-danger'>Erreur : Veuillez sélectionner un fournisseur.</div>";
    } elseif (empty($produits_ids)) {
        $message = "<div class='alert alert-danger'>Erreur : Aucun produit à commander.</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // Créer la commande
            $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'Attente')");
            $stmtCmd->execute([$id_fournisseur, $_SESSION['user_id']]);
            $id_commande = $pdo->lastInsertId();

            // Insérer les détails
            $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");

            for ($i = 0; $i < count($produits_ids); $i++) {
                $id_p = intval($produits_ids[$i]);
                $qte = intval($quantites[$i] ?? 0);

                if ($qte > 0) {
                    $stmtDet->execute([$id_commande, $id_p, $qte]);
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Commande enregistrée.";
            header("Location: entrees_stock.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Passer une Commande</h2>

    <?php echo $message; ?>

    <form method="POST" id="formCommande">
        <!-- SÉLECTION FOURNISSEUR -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <strong>Sélectionner le Fournisseur</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fournisseur </label>
                        <select name="id_fournisseur" id="id_fournisseur" class="form-select" required>
                            <option value="">-- Choisir un fournisseur --</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <option value="<?= $f['id_partenaire'] ?>"><?= htmlspecialchars($f['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU PRODUITS -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <strong>Produits à commander</strong>
            </div>
            <div class="table-responsive p-2">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Stock Actuel</th>
                            <th class="text-center">Seuil</th>
                            <th class="text-center">Qté à Commander</th>
                            <th class="text-end">Prix d'achat</th>
                            <th class="text-center">Marge %</th>
                            <th class="text-end">P.U Vente</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_produits">
                        <?php
                        $totalGeneral = 0;
                        $currentType = '';
                        foreach ($produits as $p):
                            // Afficher un séparateur pour changer de type
                            if ($p['type_produit'] !== $currentType):
                                $currentType = $p['type_produit'];
                                $typeLabel = ($currentType === 'Medicament') ? 'Médicament' : 'Laboratoire';
                                if ($currentType !== ''): ?>
                                    </tbody>
                                    <tbody>
                                <?php endif; ?>
                                <tr class="table-secondary">
                                    <td colspan="9"><strong><?= $typeLabel ?></strong></td>
                                </tr>
                                <tbody>
                            <?php endif;

                            // Calcul de la quantité à commander (pour dépasser seuil + 1)
                            $qte_a_commander = max(1, $p['seuil_alerte'] + 1 - $p['stock_total']);
                            $pu = floatval($p['prix_unitaire'] ?? 0);
                            $marge = floatval($p['marge_pourcentage'] ?? 0);
                            $pu_vente = $pu * (1 + ($marge / 100));
                            $total_ligne = $pu * $qte_a_commander;
                            $totalGeneral += $total_ligne;
                        ?>
                            <tr data-produit="<?= $p['id_produit'] ?>">
                                <td><?= htmlspecialchars($p['nom_medicament']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['type_produit']) ?></td>
                                <td class="text-center"><strong><?= $p['stock_total'] ?></strong></td>
                                <td class="text-center"><?= $p['seuil_alerte'] ?></td>
                                <td class="text-center"><strong><?= $qte_a_commander ?></strong></td>
                                <td class="text-end">
                                    <input type="hidden" name="produit_id[]" value="<?= $p['id_produit'] ?>">
                                    <input type="hidden" name="quantite_commande[]" value="<?= $qte_a_commander ?>" class="input-qte">
                                    <input type="number" name="prix_unitaire[]" class="form-control form-control-sm text-end input-pu" 
                                           value="<?= number_format($pu, 2, '.', '') ?>" step="0.01" min="0" data-produit="<?= $p['id_produit'] ?>">
                                </td>
                                <td class="text-center">
                                    <input type="number" name="marge[]" class="form-control form-control-sm text-center input-marge" 
                                           value="<?= number_format($marge, 2, '.', '') ?>" step="0.01" min="0" data-produit="<?= $p['id_produit'] ?>">
                                </td>
                                <td class="text-end">
                                    <span class="pu-vente" data-produit="<?= $p['id_produit'] ?>"><?= number_format($pu_vente, 2, '.', ' ') ?> F</span>
                                </td>
                                <td class="text-end fw-bold">
                                    <span class="total-ligne" data-produit="<?= $p['id_produit'] ?>"><?= number_format($total_ligne, 2, '.', ' ') ?> F</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOTAL GÉNÉRAL -->
            <div class="card-footer bg-light">
                <div class="row">
                    <div class="col-md-8"></div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between">
                            <strong>TOTAL GÉNÉRAL :</strong>
                            <strong id="total-general"><?= number_format($totalGeneral, 2, '.', ' ') ?> F</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIONS -->
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="btn_save_commande" class="btn btn-primary btn-lg">
                        Créer la Commande
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.input-pu, .input-marge');

    function updateCalculations(produit_id) {
        const rows = document.querySelectorAll(`tr[data-produit="${produit_id}"]`);
        if (rows.length === 0) return;

        const row = rows[0];
        const inputPu = row.querySelector('.input-pu');
        const inputMarge = row.querySelector('.input-marge');
        const inputQte = row.querySelector('.input-qte');
        const puVenteSpan = row.querySelector('.pu-vente');
        const totalLigneSpan = row.querySelector('.total-ligne');

        const pu = parseFloat(inputPu.value) || 0;
        const marge = parseFloat(inputMarge.value) || 0;
        const qte = parseFloat(inputQte.value) || 1;

        const puVente = pu * (1 + (marge / 100));
        const totalLigne = pu * qte;

        puVenteSpan.textContent = puVente.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' F';
        totalLigneSpan.textContent = totalLigne.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' F';

        updateTotalGeneral();
    }

    function updateTotalGeneral() {
        let totalGeneral = 0;
        document.querySelectorAll('.total-ligne').forEach(span => {
            const text = span.textContent.replace(' F', '').replace(/\s/g, '').replace(',', '.');
            totalGeneral += parseFloat(text) || 0;
        });

        document.getElementById('total-general').textContent = totalGeneral.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' F';
    }

    inputs.forEach(input => {
        input.addEventListener('input', () => {
            const produitId = input.dataset.produit;
            updateCalculations(produitId);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>

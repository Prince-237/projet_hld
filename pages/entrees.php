<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

// --- ACTION : ENREGISTRER UN NOUVEAU LOT ---
if ($isAdmin && isset($_POST['btn_lot'])) {
    $id_p = $_POST['id_p'];
    $id_f = $_POST['id_f'];
    $qte = $_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $source = $_POST['source_provenance'];
    
    // Sécurité serveur : Si Don, le prix est forcé à 0
    $prix_achat_ttc = ($source === 'Don') ? 0 : (isset($_POST['prix_achat_ttc']) ? floatval($_POST['prix_achat_ttc']) : 0);
    $marge = isset($_POST['marge_pourcentage']) && $_POST['marge_pourcentage'] !== '' ? floatval($_POST['marge_pourcentage']) : null;

    try {
        $pdo->beginTransaction();
        
        // Insertion du lot avec la source
        $stmt = $pdo->prepare("INSERT INTO stock_lots (id_produit, id_fournisseur, num_lot, quantite_initiale, quantite_actuelle, date_expiration, prix_achat_ttc, id_user, source_provenance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_p, $id_f, $num_lot, $qte, $qte, $exp, $prix_achat_ttc, $_SESSION['user_id'], $source]);
        
        // Mise à jour du stock total du produit
        if ($marge !== null) {
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ?, marge_pourcentage = ? WHERE id_produit = ?");
            $stmt->execute([$qte, $marge, $id_p]);
        } else {
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
            $stmt->execute([$qte, $id_p]);
        }
        
        $pdo->commit();
        header("Location: entrees.php?success=1"); exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// --- ACTION : SUPPRIMER UN LOT ---
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $stmt = $pdo->prepare("SELECT id_produit, quantite_actuelle FROM stock_lots WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    $lot = $stmt->fetch();

    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM sorties WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasSorties = $checkSorties->fetchColumn() > 0;

    if ($hasSorties) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce lot a des sorties associées.</div>";
    } else if ($lot) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
            $stmt->execute([$lot['quantite_actuelle'], $lot['id_produit']]);
            $stmt = $pdo->prepare("DELETE FROM stock_lots WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>Lot supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression.</div>";
        }
    }
}

// Récupération des données pour l'affichage
$entrees = $pdo->query("SELECT l.*, p.nom_medicament, p.marge_pourcentage, f.nom_societe, u.nom_complet AS utilisateur 
                        FROM stock_lots l 
                        JOIN produits p ON l.id_produit = p.id_produit 
                        JOIN fournisseurs f ON l.id_fournisseur = f.id_fournisseur 
                        LEFT JOIN utilisateurs u ON l.id_user = u.id_user 
                        ORDER BY l.id_lot DESC")->fetchAll();

$prods = $pdo->query("SELECT * FROM produits ORDER BY nom_medicament ASC")->fetchAll();
$fours = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom_societe ASC")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">📦 Gestion des Entrées en Stock (Lots)</h2>
    
    <?php if($message): ?><?= $message ?><?php endif; ?>

    <?php if($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h5>Enregistrer une nouvelle réception</h5></div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Source</label>
                        <select name="source_provenance" id="select_source" class="form-select border-primary" onchange="verifierDon()" required>
                            <option value="Achat">🛒 Achat Marché</option>
                            <option value="Don">🎁 Don / ONG / État</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Produit (Med/Labo)</label>
                        <select name="id_p" id="select_produit_entree" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach($prods as $p): ?>
                                <option value="<?= $p['id_produit'] ?>" data-default-prix="<?= $p['prix_unitaire'] ?>" data-marge="<?= $p['marge_pourcentage'] ?>">
                                    <?= htmlspecialchars($p['nom_medicament']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fournisseur / Donateur</label>
                        <select name="id_f" class="form-select" required>
                            <?php foreach($fours as $f) echo "<option value='{$f['id_fournisseur']}'>{$f['nom_societe']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">N° Lot</label>
                        <input type="text" name="num_lot" class="form-control" placeholder="Ex: LOT-2024" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Date Expir.</label>
                        <input type="date" name="exp" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Quantité</label>
                        <input type="number" name="qte" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Prix Achat Unit. (TTC)</label>
                        <input type="number" step="0.01" name="prix_achat_ttc" id="input_prix_achat" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Nouvelle Marge (%)</label>
                        <input type="number" step="0.01" name="marge_pourcentage" id="input_marge" class="form-control" placeholder="Ex: 20">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="btn_lot" class="btn btn-primary w-100">🚀 Valider l'Entrée</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Désignation</th>
                        <th>Lot</th>
                        <th>Source</th>
                        <th>Qte Init.</th>
                        <th>Expiration</th>
                        <th>Prix Achat</th>
                        <th>Total</th>
                        <th>Agent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($entrees as $e): ?>
                        <?php 
                            $isExpired = strtotime($e['date_expiration']) < strtotime(date('Y-m-d'));
                            $isDon = ($e['source_provenance'] === 'Don');
                        ?>
                        <tr class="<?= $isExpired ? 'table-danger' : '' ?>">
                            <td class="small"><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['nom_medicament']) ?></strong>
                                <?= $isDon ? '<span class="badge bg-success ms-1" title="Donation">🎁</span>' : '' ?>
                            </td>
                            <td><code class="text-dark"><?= htmlspecialchars($e['num_lot']) ?></code></td>
                            <td><?= $isDon ? 'Don' : 'Achat' ?></td>
                            <td><?= $e['quantite_initiale'] ?></td>
                            <td class="<?= $isExpired ? 'fw-bold text-danger' : '' ?>"><?= date('d/m/Y', strtotime($e['date_expiration'])) ?></td>
                            <td><?= $isDon ? '<span class="text-success">GRATUIT</span>' : number_format($e['prix_achat_ttc'], 0, '.', ' ') . ' F' ?></td>
                            <td><?= $isDon ? '0 F' : number_format($e['prix_achat_ttc'] * $e['quantite_initiale'], 0, '.', ' ') . ' F' ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($e['utilisateur']) ?></td>
                            <td>
                                <?php if($isAdmin): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce lot de la base ?');">
                                        <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                        <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Fonction pour griser le champ prix si c'est un DON
function verifierDon() {
    const source = document.getElementById('select_source').value;
    const inputPrix = document.getElementById('input_prix_achat');
    
    if (source === 'Don') {
        inputPrix.value = 0;
        inputPrix.readOnly = true;
        inputPrix.style.backgroundColor = "#e9ecef";
    } else {
        inputPrix.readOnly = false;
        inputPrix.style.backgroundColor = "#ffffff";
        // On remet le prix par défaut du produit sélectionné
        const selectProd = document.getElementById('select_produit_entree');
        if(selectProd.selectedIndex > 0) {
            inputPrix.value = selectProd.options[selectProd.selectedIndex].getAttribute('data-default-prix');
        }
    }
}

// Gestion des changements de sélection de produit
document.addEventListener('DOMContentLoaded', function() {
    const selectProd = document.getElementById('select_produit_entree');
    const inputPrix = document.getElementById('input_prix_achat');
    const inputMarge = document.getElementById('input_marge');
    const selectSource = document.getElementById('select_source');

    if(selectProd) {
        selectProd.addEventListener('change', function() {
            if (selectSource.value === 'Don') return; // Ne change rien si c'est un don

            const option = selectProd.options[selectProd.selectedIndex];
            if(option.value !== "") {
                inputPrix.value = option.getAttribute('data-default-prix');
                inputMarge.value = option.getAttribute('data-marge');
            }
        });
    }
    // Initialisation au chargement
    verifierDon();
});
</script>

<?php include '../includes/footer.php'; ?>
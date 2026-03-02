<?php
// 1. Inclusion des dependances
require_once('../config/db.php');
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
include '../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin');

$message = "";

// 2. Traitement de la sortie de stock
if (isset($_POST['valider_sortie'])) {
    $id_lot = $_POST['id_lot'];
    $id_source = (int)$_POST['id_source'];
    $id_destination = (int)$_POST['id_destination'];
    $quantite_demandee = intval($_POST['quantite_sortie']);
    $prix_vente = $_POST['prix_vente'];
    $id_user = $_SESSION['user_id'];

    if ($id_source === $id_destination) {
        $message = "<div class='alert alert-danger'>Erreur : La source et la destination ne peuvent pas être identiques.</div>";
    } else {

    try {
        $pdo->beginTransaction();

        // Etape A : verifier si le stock disponible dans ce lot est suffisant
        // On récupère aussi le prix d'achat TTC pour pouvoir calculer le prix de vente (+70%) côté serveur
        // MODIFICATION : On joint la table produits pour récupérer la marge dynamique
        $checkSql = "SELECT l.quantite_actuelle, l.id_produit, l.prix_achat_ttc, p.marge_pourcentage 
                     FROM stock_lots l 
                     JOIN produits p ON l.id_produit = p.id_produit 
                     WHERE l.id_lot = :id_lot FOR UPDATE";
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([':id_lot' => $id_lot]);
        $lot = $stmtCheck->fetch();

        // Récupérer le nom de la destination pour l'historique (compatibilité)
        $stmtDest = $pdo->prepare("SELECT nom_point_vente FROM points_vente WHERE id_point_vente = ?");
        $stmtDest->execute([$id_destination]);
        $nom_destination = $stmtDest->fetchColumn();

        // Si la source est le Magasin Central (ID 1), on doit vérifier le stock physique
        // Si c'est un transfert entre services externes (ex: Urgences -> Betote), on suppose que le stock est dispo là-bas (ou on ne le gère pas encore strictement)
        $stock_suffisant = ($id_source !== 1) || ($lot && $lot['quantite_actuelle'] >= $quantite_demandee);

        if ($stock_suffisant) {
            // Calculer le prix de vente à partir du prix d'achat et de la marge dynamique
            if (!empty($lot['prix_achat_ttc'])) {
                $marge = !empty($lot['marge_pourcentage']) ? floatval($lot['marge_pourcentage']) : 70;
                $prix_vente_calc = round(floatval($lot['prix_achat_ttc']) * (1 + ($marge / 100)), 2);
                $prix_vente = $prix_vente_calc; 
            }

            // Etape B : calculer le montant total de la sortie
            $total_prix = $quantite_demandee * $prix_vente;

            // Etape C : enregistrer la sortie
            $sqlInsert = "INSERT INTO sorties (id_lot, id_source, id_destination, nom_point_vente, quantite_sortie, prix_vente_unitaire, total_prix, id_user)
                          VALUES (:id_lot, :src, :dest, :pv, :qte, :prix, :total, :user)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':id_lot' => $id_lot,
                ':src'    => $id_source,
                ':dest'   => $id_destination,
                ':pv'     => $nom_destination, // On garde le nom pour l'affichage simple
                ':qte'    => $quantite_demandee,
                ':prix'   => $prix_vente,
                ':total'  => $total_prix,
                ':user'   => $id_user
            ]);

            // Etape D : mise a jour de la quantite restante
            // ON NE TOUCHE AU STOCK PHYSIQUE QUE SI CA SORT DU MAGASIN CENTRAL (ID 1)
            if ($id_source == 1) {
                $sqlUpdate = "UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - :qte
                              WHERE id_lot = :id_lot";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':qte'    => $quantite_demandee,
                    ':id_lot' => $id_lot
                ]);

                // Etape E : reduire le stock total
                if (!empty($lot['id_produit'])) {
                    $sqlProd = "UPDATE produits SET stock_total = stock_total - :qte WHERE id_produit = :idp";
                    $stmtProd = $pdo->prepare($sqlProd);
                    $stmtProd->execute([':qte' => $quantite_demandee, ':idp' => $lot['id_produit']]);
                }
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie enregistree et stock mis a jour.</div>";
        } else {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : stock insuffisant dans ce lot.</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur systeme : " . $e->getMessage() . "</div>";
    }
    }
}

if ($isAdmin && isset($_POST['btn_update_sortie'])) {
    $id_sortie = (int)$_POST['id_sortie'];
    $point_vente = htmlspecialchars($_POST['point_vente']);
    $quantite_sortie = (int)$_POST['quantite_sortie'];
    $prix_vente = (float)$_POST['prix_vente'];

    $stmt = $pdo->prepare("SELECT s.id_lot, s.quantite_sortie, l.quantite_actuelle, l.id_produit FROM sorties s JOIN stock_lots l ON s.id_lot = l.id_lot WHERE s.id_sortie = ?");
    $stmt->execute([$id_sortie]);
    $row = $stmt->fetch();

    if ($row) {
        $diff = $quantite_sortie - $row['quantite_sortie'];
        if ($diff > 0 && $row['quantite_actuelle'] < $diff) {
            $message = "<div class='alert alert-danger'>Stock insuffisant pour augmenter cette sortie.</div>";
        } else {
            try {
                $pdo->beginTransaction();
                $total_prix = $quantite_sortie * $prix_vente;

                $stmt = $pdo->prepare("UPDATE sorties SET nom_point_vente = ?, quantite_sortie = ?, prix_vente_unitaire = ?, total_prix = ? WHERE id_sortie = ?");
                $stmt->execute([$point_vente, $quantite_sortie, $prix_vente, $total_prix, $id_sortie]);

                if ($diff !== 0) {
                    $stmt = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?");
                    $stmt->execute([$diff, $row['id_lot']]);

                    $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total - ? WHERE id_produit = ?");
                    $stmt->execute([$diff, $row['id_produit']]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>Sortie mise a jour.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}

if ($isAdmin && isset($_POST['btn_delete_sortie'])) {
    $id_sortie = (int)$_POST['id_sortie'];

    $stmt = $pdo->prepare("SELECT s.id_lot, s.quantite_sortie, l.id_produit FROM sorties s JOIN stock_lots l ON s.id_lot = l.id_lot WHERE s.id_sortie = ?");
    $stmt->execute([$id_sortie]);
    $row = $stmt->fetch();

    if ($row) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM sorties WHERE id_sortie = ?");
            $stmt->execute([$id_sortie]);

            $stmt = $pdo->prepare("UPDATE stock_lots SET quantite_actuelle = quantite_actuelle + ? WHERE id_lot = ?");
            $stmt->execute([$row['quantite_sortie'], $row['id_lot']]);

            $stmt = $pdo->prepare("UPDATE produits SET stock_total = stock_total + ? WHERE id_produit = ?");
            $stmt->execute([$row['quantite_sortie'], $row['id_produit']]);

            $pdo->commit();
            $message = "<div class='alert alert-success'>Sortie supprimee.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// 3. Recuperation des lots disponibles
$sqlLots = "SELECT l.id_lot, l.num_lot, l.quantite_actuelle, l.date_expiration, l.prix_achat_ttc, p.nom_medicament, p.marge_pourcentage
            FROM stock_lots l
            JOIN produits p ON l.id_produit = p.id_produit
            WHERE l.quantite_actuelle > 0
            ORDER BY l.date_expiration ASC";
$lotsDisponibles = $pdo->query($sqlLots)->fetchAll();

// Récupération des points de vente
$pvs = $pdo->query("SELECT * FROM points_vente ORDER BY nom_point_vente ASC")->fetchAll();

// Historique des sorties
$sqlSorties = "SELECT s.*, l.num_lot, l.source_provenance, p.nom_medicament, u.nom_complet AS utilisateur, pv_src.nom_point_vente as source_nom, pv_dest.nom_point_vente as dest_nom
               FROM sorties s
               JOIN stock_lots l ON s.id_lot = l.id_lot
               JOIN produits p ON l.id_produit = p.id_produit
               LEFT JOIN points_vente pv_src ON s.id_source = pv_src.id_point_vente
               LEFT JOIN points_vente pv_dest ON s.id_destination = pv_dest.id_point_vente
               LEFT JOIN utilisateurs u ON s.id_user = u.id_user
               ORDER BY s.id_sortie DESC";
$sorties = $pdo->query($sqlSorties)->fetchAll();
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-file-export"></i> Sortie de Medicaments</h2>
    <?php if($isAdmin): ?>

    <?php echo $message; ?>

    <div class="card shadow border-0">
        <div class="card-body">
            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Selectionner le Lot (Medicament - Lot - Quantite dispo)</label>
                        <select name="id_lot" id="select_lot" class="form-select" required>
                            <option value="">-- Choisir un lot --</option>
                            <?php foreach($lotsDisponibles as $l): ?>
                                <option value="<?= $l['id_lot'] ?>" data-prix="<?= $l['prix_achat_ttc'] ?? 0 ?>" data-marge="<?= $l['marge_pourcentage'] ?? 70 ?>">
                                    <?= strtoupper($l['nom_medicament']) ?> | Lot: <?= $l['num_lot'] ?> | Dispo: <?= $l['quantite_actuelle'] ?> (Exp: <?= $l['date_expiration'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- <small class="text-muted">Note : les lots sont tries par date d'expiration (FIFO).</small> -->
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="id_source" class="form-select" required>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>" <?= ($pv['id_point_vente'] == 1) ? 'selected' : '' ?>><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Destination</label>
                        <select name="id_destination" class="form-select" required>
                            <option value="">-- Choisir destination --</option>
                            <?php foreach($pvs as $pv): ?>
                                <option value="<?= $pv['id_point_vente'] ?>"><?= $pv['nom_point_vente'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Quantite a sortir</label>
                        <input type="number" name="quantite_sortie" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Prix de vente unitaire <small class="text-muted">(Auto)</small></label>
                        <input type="number" step="0.01" name="prix_vente" id="prix_vente" class="form-control" readonly>
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
    <?php endif; ?>

    <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Historique des sorties</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Med.</th>
                            <th>Lot</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Qte</th>
                            <th>P. U. Vente</th>
                            <th>Total</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($sorties)): foreach($sorties as $s): ?>
                            <tr>
                                <td><?= $s['date_sortie'] ?></td>
                                <td><?= $s['nom_medicament'] ?></td>
                                <td><?= $s['num_lot'] ?></td>
                                <td><span class="badge bg-secondary"><?= $s['source_nom'] ?? '?' ?></span></td>
                                <td><span class="badge bg-success"><?= $s['dest_nom'] ?? $s['nom_point_vente'] ?></span></td>
                                <td><?= $s['quantite_sortie'] ?></td>
                                <td><?= ($s['source_provenance'] === 'Don') ? '<span class="text-success fw-bold">GRATUIT</span>' : number_format($s['prix_vente_unitaire'], 0, '.', ' ') . ' F' ?></td>
                                <td><?= $s['total_prix'] ?></td>
                                <td><?= isset($s['utilisateur']) && $s['utilisateur'] ? $s['utilisateur'] : '-' ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditSortie"
                                            data-id="<?= $s['id_sortie'] ?>"
                                            data-point="<?= htmlspecialchars($s['nom_point_vente']) ?>"
                                            data-qte="<?= $s['quantite_sortie'] ?>"
                                            data-prix="<?= $s['prix_vente_unitaire'] ?>"
                                            title="Modifier"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette sortie ?');">
                                            <input type="hidden" name="id_sortie" value="<?= $s['id_sortie'] ?>">
                                            <button type="submit" name="btn_delete_sortie" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="10" class="text-center text-muted">Aucune sortie enregistree.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalEditSortie" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Modifier une sortie</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_sortie" id="edit_id_sortie">
          <input type="text" name="point_vente" id="edit_point_vente" class="form-control mb-2" placeholder="Point de vente" required>
          <input type="number" name="quantite_sortie" id="edit_qte_sortie" class="form-control mb-2" placeholder="Qte" required>
          <!-- <input type="number" step="0.01" name="prix_vente" id="edit_prix_vente" class="form-control" placeholder="Prix unitaire" required> -->
      </div>
      <div class="modal-footer"><button type="submit" name="btn_update_sortie" class="btn btn-success">Mettre a jour</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calcul automatique du prix de vente avec marge dynamique
    var selectLot = document.getElementById('select_lot');
    var prixVenteInput = document.getElementById('prix_vente');
    
    if (selectLot && prixVenteInput) {
        selectLot.addEventListener('change', function() {
            var option = selectLot.options[selectLot.selectedIndex];
            var prixAchat = parseFloat(option.getAttribute('data-prix')) || 0;
            var marge = parseFloat(option.getAttribute('data-marge')) || 70;
            var prixVente = prixAchat * (1 + (marge / 100)); 
            prixVenteInput.value = prixVente.toFixed(2);
        });
    }

    // Gestion du modal d'edition
    var modal = document.getElementById('modalEditSortie');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_sortie').value = button.getAttribute('data-id');
        document.getElementById('edit_point_vente').value = button.getAttribute('data-point');
        document.getElementById('edit_qte_sortie').value = button.getAttribute('data-qte');
        document.getElementById('edit_prix_vente').value = button.getAttribute('data-prix');
    });
});
</script>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
                           </table>
                            </div>
                        </div>
                    </div>
</div>

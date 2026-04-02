<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';
$typeFilter = isset($_GET['type']) && in_array($_GET['type'], ['Pharmacie', 'Laboratoire']) ? $_GET['type'] : 'Pharmacie';
$typeProduit = ($typeFilter === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';

$stmtFournisseurs = $pdo->prepare("SELECT id_partenaire, nom_entite FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC");
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll();

$stmtAgents = $pdo->prepare("SELECT id_user, nom_complet FROM Utilisateur WHERE role = 'admin' ORDER BY nom_complet ASC");
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll();

// --- ACTION : ENREGISTRER UN DON (DIRECT) ---
if ($isAdmin && isset($_POST['btn_save_don'])) {
    $id_p = $_POST['id_p'];
    $id_donateur = $_POST['id_donateur'];
    $qte = (int)$_POST['qte'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];

    if ($exp <= date('Y-m-d')) {
        $message = "<div class='alert alert-danger'>Erreur : Impossible d'enregistrer un don de produit déjà périmé ou expirant aujourd'hui.</div>";
    } else {
    try {
        $pdo->beginTransaction();

        // 1. Création implicite de la commande (Statut 'Reçue' direct)
        // Cela permet de garder une trace administrative homogène
        $stmtCmd = $pdo->prepare("INSERT INTO Commande (id_partenaire, id_user, date_commande, statut) VALUES (?, ?, NOW(), 'Reçue')");
        $stmtCmd->execute([$id_donateur, $_SESSION['user_id']]);
        $id_commande = $pdo->lastInsertId();

        // 2. Création du détail commande
        $stmtDet = $pdo->prepare("INSERT INTO CommandeDetail (id_commande, id_produit, quantite_voulue) VALUES (?, ?, ?)");
        $stmtDet->execute([$id_commande, $id_p, $qte]);
        $id_cmd_det = $pdo->lastInsertId();

        // 3. Création du StockLot (Prix Achat = 0 pour les dons)
        $stmtLot = $pdo->prepare("INSERT INTO StockLot (id_produit, id_cmd_det, num_lot, quantite_actuelle, date_expiration, prix_achat_ttc) VALUES (?, ?, ?, ?, ?, 0)");
        $stmtLot->execute([$id_p, $id_cmd_det, $num_lot, $qte, $exp]);

        $pdo->commit();
        $message = "<div class='alert alert-success'>Don enregistré et stock mis à jour avec succès !</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
    }
}

// --- ACTION : SUPPRIMER UN LOT DE DON ---
if ($isAdmin && isset($_POST['btn_delete_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    
    // Vérifier s'il y a eu des mouvements (sorties) sur ce lot
    $checkSorties = $pdo->prepare("SELECT COUNT(*) FROM TransfertDetail WHERE id_lot = ?");
    $checkSorties->execute([$id_lot]);
    $hasMvt = $checkSorties->fetchColumn() > 0;

    // Vérifier également si le lot apparaît dans un inventaire
    $checkInv = $pdo->prepare("SELECT COUNT(*) FROM InventaireDetail WHERE id_lot = ?");
    $checkInv->execute([$id_lot]);
    $hasInv = $checkInv->fetchColumn() > 0;

    if ($hasMvt || $hasInv) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce don est lié à des mouvements de stock ou à un inventaire.</div>";
    } else {
        try {
            $pdo->beginTransaction();
            // Récupérer le id_cmd_det lié pour nettoyer la trace administrative
            $stmt = $pdo->prepare("SELECT id_cmd_det FROM StockLot WHERE id_lot = ?");
            $stmt->execute([$id_lot]);
            $id_cmd_det = $stmt->fetchColumn();

            // Supprimer le lot
            $pdo->prepare("DELETE FROM StockLot WHERE id_lot = ?")->execute([$id_lot]);

            // Nettoyer le détail de commande lié au don
            if ($id_cmd_det) {
                $pdo->prepare("DELETE FROM CommandeDetail WHERE id_cmd_det = ?")->execute([$id_cmd_det]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Don supprimé avec succès.</div>";
        } catch (Exception $e) {
            $pdo->rollBack(); // Annule toutes les opérations de la transaction en cas d'erreur
            $message = "<div class='alert alert-danger'>Erreur lors de la suppression du don : " . $e->getMessage() . "</div>";
        }
    }
}

// --- ACTION : MODIFIER UN LOT DE DON ---
if ($isAdmin && isset($_POST['btn_update_lot'])) {
    $id_lot = (int)$_POST['id_lot'];
    $num_lot = $_POST['num_lot'];
    $exp = $_POST['exp'];
    $qte_new = (int)$_POST['qte'];

    $stmt = $pdo->prepare("SELECT id_lot FROM StockLot WHERE id_lot = ?");
    $stmt->execute([$id_lot]);
    
    if ($stmt->fetch()) {
        if ($exp <= date('Y-m-d')) {
            $message = "<div class='alert alert-danger'>Erreur : La nouvelle date d'expiration est invalide.</div>";
        } else {
            try {
                // On met à jour le lot (Prix Achat reste 0 car c'est un don)
                $sql = "UPDATE StockLot SET num_lot = ?, date_expiration = ?, quantite_actuelle = ? WHERE id_lot = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$num_lot, $exp, $qte_new, $id_lot]);
                $message = "<div class='alert alert-success'>Informations mise a jouravec succès.</div>";
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
            }
        }
    }
}


// --- RÉCUPÉRATION HISTORIQUE DONS ---
// On récupère uniquement les entrées liées à des partenaires de type 'Don'
$sqlDons = "SELECT l.*, p.nom_medicament, p.type_produit, 
                   part.nom_entite, u.nom_complet AS utilisateur,
                   cmd.date_commande AS date_enregistrement,
                   cd.quantite_voulue AS quantite_initiale
            FROM StockLot l 
            JOIN Produit p ON l.id_produit = p.id_produit 
            LEFT JOIN CommandeDetail cd ON l.id_cmd_det = cd.id_cmd_det
            LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande
            LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
            LEFT JOIN Utilisateur u ON cmd.id_user = u.id_user 
            WHERE part.type = 'Don' AND p.type_produit = ?";
$params = [$typeProduit];

if (!empty($fournisseurFilter)) {
    $sqlDons .= " AND part.id_partenaire = ?";
    $params[] = $fournisseurFilter;
}
if (!empty($agentFilter)) {
    $sqlDons .= " AND u.id_user = ?";
    $params[] = $agentFilter;
}
if (!empty($dateFilterStart)) {
    $sqlDons .= " AND DATE(cmd.date_commande) >= ?";
    $params[] = $dateFilterStart;
}
if (!empty($dateFilterEnd)) {
    $sqlDons .= " AND DATE(cmd.date_commande) <= ?";
    $params[] = $dateFilterEnd;
}

$sqlDons .= " ORDER BY l.id_lot DESC LIMIT 50";
$stmtDons = $pdo->prepare($sqlDons);
$stmtDons->execute($params);
$dons = $stmtDons->fetchAll();

// Listes pour le formulaire
$prods = $pdo->query("SELECT * FROM Produit ORDER BY nom_medicament ASC")->fetchAll();
$donateurs = $pdo->query("SELECT * FROM Partenaire WHERE type = 'Don' ORDER BY nom_entite ASC")->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4 text-dark">Réception des Dons</h2>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <?php if ($isAdmin): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Enregistrer un nouveau don</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Donateur</label>
                        <select name="id_donateur" class="form-select" required>
                            <option value="">-- Choisir le donateur --</option>
                            <?php foreach ($donateurs as $d): ?>
                                <option value="<?= $d['id_partenaire'] ?>"><?= htmlspecialchars($d['nom_entite']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Produit Reçu</label>
                        <select name="id_p" class="form-select" required>
                            <option value="">-- Choisir le produit --</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id_produit'] ?>" <?= (isset($_GET['id_p']) && $_GET['id_p'] == $p['id_produit']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nom_medicament']) ?> (<?= $p['type_produit'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Quantité Reçue</label>
                        <input type="number" name="qte" class="form-control" min="1" required>
                    </div>

                    <div class="col-md-2">
                        <!-- Espaceur -->
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Numéro de Lot</label>
                        <input type="text" name="num_lot" class="form-control" placeholder="Sur la boîte" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date Expiration</label>
                        <input type="date" name="exp" class="form-control" required>
                    </div>

                    <div class="col-md-12 d-flex mt-4 align-items-end">
                        <button type="submit" name="btn_save_don" class="btn btn-primary w-100 btn-lg">Valider la Réception de Don</button>
                    </div>
                    
                </form>
            </div>
        </div>
    <?php endif; ?>

    <form method="GET" id="searchForm" class="my-3" role="search">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select id="typeFilter" name="type" class="form-select">
                    <option value="Medicament" <?= $typeFilter === 'Medicament' ? 'selected' : '' ?>>Pharmacie</option>
                    <option value="Laboratoire" <?= $typeFilter === 'Laboratoire' ? 'selected' : '' ?>>Laboratoire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fournisseur</label>
                <select id="fournisseurFilter" name="fournisseur" class="form-select">
                    <option value="">Tous les fournisseurs</option>
                    <?php foreach ($fournisseurs as $fournisseur): ?>
                        <option value="<?= $fournisseur['id_partenaire'] ?>" <?= $fournisseurFilter === (string)$fournisseur['id_partenaire'] ? 'selected' : '' ?>><?= htmlspecialchars($fournisseur['nom_entite']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (du)</label>
                <input type="date" id="dateFilterStart" name="dateStart" value="<?= htmlspecialchars($dateFilterStart) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (au)</label>
                <input type="date" id="dateFilterEnd" name="dateEnd" value="<?= htmlspecialchars($dateFilterEnd) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Agent</label>
                <select id="agentFilter" name="agent" class="form-select">
                    <option value="">Tous les agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id_user'] ?>" <?= $agentFilter === (string)$agent['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($agent['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchForm = document.getElementById('searchForm');
        if (!searchForm) return;
        var fields = searchForm.querySelectorAll('select, input[type="date"], input[type="text"], input[type="search"]');
        fields.forEach(function(el) {
            var eventName = (el.tagName.toLowerCase() === 'input' && (el.type === 'text' || el.type === 'search')) ? 'input' : 'change';
            el.addEventListener(eventName, function() {
                searchForm.submit();
            });
        });
    });
    </script>

    <!-- HISTORIQUE DONS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Historique des dons reçus</strong></div>
        <div class="table-responsive p-2">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date Réception</th>
                        <th>État</th>
                        <th>Produit</th>
                        <th>Lot</th>
                        <th>Donateur</th>
                        <th>Qté</th>
                        <th>Expiration</th>
                        <th>Agent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dons)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Aucun don enregistré récemment.</td>
                        </tr>
                        <?php else: foreach ($dons as $e): ?>
                            <?php
                            $today = strtotime(date('Y-m-d'));
                            $exp_ts = strtotime($e['date_expiration']);
                            $isExpired = $exp_ts < $today;
                            $isCritical = !$isExpired && $exp_ts <= strtotime('+14 days', $today);

                            if ($isExpired) {
                                $statusBadge = '<span class="badge bg-danger">Périmé</span>';
                                $rowClass = 'table-secondary'; // Grisé
                            } elseif ($isCritical) {
                                $statusBadge = '<span class="badge bg-warning text-dark">Critique</span>';
                                $rowClass = '';
                            } else {
                                $statusBadge = '<span class="badge bg-success">Valide</span>';
                                $rowClass = '';
                            }
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= date('d/m/Y H:i', strtotime($e['date_enregistrement'])) ?></td>
                                <td><?= $statusBadge ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($e['nom_medicament']) ?></td>
                                <td><code><?= htmlspecialchars($e['num_lot']) ?></code></td>
                                <td><?= htmlspecialchars($e['nom_entite']) ?></td>
                                <td class="fw-bold text-success">+ <?= $e['quantite_initiale'] ?></td>
                                <td class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('d/m/Y', strtotime($e['date_expiration'])) ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($e['utilisateur']) ?></td>
                                <td class="text-nowrap">
                                    <?php if ($isAdmin): ?>
                                        <button class="btn btn-sm btn-outline-primary me-1 border-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditLot"
                                            data-id="<?= $e['id_lot'] ?>"
                                            data-num="<?= htmlspecialchars($e['num_lot']) ?>"
                                            data-exp="<?= $e['date_expiration'] ?>"
                                            data-qte="<?= $e['quantite_actuelle'] ?>"
                                            title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer ce don ?');">
                                            <input type="hidden" name="id_lot" value="<?= $e['id_lot'] ?>">
                                            <button type="submit" name="btn_delete_lot" class="btn btn-sm btn-outline-danger border-0">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- MODAL MODIFICATION DON -->
    <div class="modal fade" id="modalEditLot" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Don</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_lot" id="edit_id_lot">

                    <label class="form-label fw-bold">Numéro de Lot</label>
                    <input type="text" name="num_lot" id="edit_num_lot" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Date d'Expiration</label>
                    <input type="date" name="exp" id="edit_exp" class="form-control mb-2" required>

                    <label class="form-label fw-bold">Quantité (Correction)</label>
                    <input type="number" name="qte" id="edit_qte" class="form-control mb-2" min="1" required>
                    
                    <!-- <div class="alert alert-info small mt-2">
                        <i class="bi bi-info-circle"></i> Le prix d'achat reste fixé à 0 pour les dons.
                    </div> -->
                </div>
                <div class="modal-footer">
                    <button type="submit" name="btn_update_lot" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var modalEdit = document.getElementById('modalEditLot');
        if (modalEdit) {
            modalEdit.addEventListener('show.bs.modal', function(event) {
                var btn = event.relatedTarget;
                document.getElementById('edit_id_lot').value = btn.getAttribute('data-id');
                document.getElementById('edit_num_lot').value = btn.getAttribute('data-num');
                document.getElementById('edit_exp').value = btn.getAttribute('data-exp');
                document.getElementById('edit_qte').value = btn.getAttribute('data-qte');
            });
        }
    });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
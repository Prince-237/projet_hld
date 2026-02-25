<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

// Ajout Point de Vente
if ($isAdmin && isset($_POST['btn_ajouter_pv'])) {
    $nom = htmlspecialchars($_POST['nom_point_vente']);
    try {
        $stmt = $pdo->prepare("INSERT INTO points_vente (nom_point_vente) VALUES (?)");
        $stmt->execute([$nom]);
        $message = "<div class='alert alert-success'>Point de vente ajouté.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// Suppression
if ($isAdmin && isset($_POST['btn_delete_pv'])) {
    $id = (int)$_POST['id_point_vente'];
    // On protège le Magasin Central (ID 1)
    if ($id === 1) {
        $message = "<div class='alert alert-warning'>Impossible de supprimer le Magasin Central.</div>";
    } else {
        // Vérifier s'il y a des mouvements
        $check = $pdo->prepare("SELECT COUNT(*) FROM sorties WHERE id_source = ? OR id_destination = ?");
        $check->execute([$id, $id]);
        if ($check->fetchColumn() > 0) {
            $message = "<div class='alert alert-danger'>Suppression impossible : des mouvements existent pour ce point.</div>";
        } else {
            $pdo->prepare("DELETE FROM points_vente WHERE id_point_vente = ?")->execute([$id]);
            $message = "<div class='alert alert-success'>Point de vente supprimé.</div>";
        }
    }
}

// Récupération de la liste avec les statistiques (Envoyé / Reçu)
$sql = "SELECT pv.*, 
        (SELECT IFNULL(SUM(quantite_sortie), 0) FROM sorties WHERE id_source = pv.id_point_vente) as total_envoye,
        (SELECT IFNULL(SUM(quantite_sortie), 0) FROM sorties WHERE id_destination = pv.id_point_vente) as total_recu
        FROM points_vente pv 
        ORDER BY pv.id_point_vente ASC";
$points_vente = $pdo->query($sql)->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestion des Points de Vente</h2>
        <?php if($isAdmin): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddPV">
            + Nouveau Point de Vente
        </button>
        <?php endif; ?>
    </div>

    <?php if($message): ?><?= $message ?><?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nom du Point de Vente</th>
                        <th class="text-center text-success">Total Reçu (Entrant)</th>
                        <th class="text-center text-danger">Total Envoyé (Sortant)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($points_vente as $pv): ?>
                    <tr>
                        <td><?= $pv['id_point_vente'] ?></td>
                        <td><strong><?= htmlspecialchars($pv['nom_point_vente']) ?></strong></td>
                        <td class="text-center fw-bold"><?= $pv['total_recu'] ?></td>
                        <td class="text-center fw-bold"><?= $pv['total_envoye'] ?></td>
                        <td>
                            <?php if($isAdmin && $pv['id_point_vente'] != 1): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce point de vente ?');">
                                    <input type="hidden" name="id_point_vente" value="<?= $pv['id_point_vente'] ?>">
                                    <button type="submit" name="btn_delete_pv" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php elseif($pv['id_point_vente'] == 1): ?>
                                <span class="badge bg-secondary">Système</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajout -->
<?php if($isAdmin): ?>
<div class="modal fade" id="modalAddPV" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ajouter un Point de Vente</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nom du Point de Vente *</label>
            <input type="text" name="nom_point_vente" class="form-control" placeholder="Ex: Pharmacie Bloc Opératoire" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" name="btn_ajouter_pv" class="btn btn-success">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
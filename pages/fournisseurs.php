<?php
require_once '../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

// Ajout fournisseur
if ($isAdmin && isset($_POST['btn_ajouter_fournisseur'])) {
    $nom = htmlspecialchars($_POST['nom_societe']);
    $contact = htmlspecialchars($_POST['contact_nom']);
    $tel = htmlspecialchars($_POST['telephone']);
    $email = htmlspecialchars($_POST['email']);

    try {
        $sql = "INSERT INTO fournisseurs (nom_societe, contact_nom, telephone, email) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $contact, $tel, $email]);
        $message = "<div class='alert alert-success'>Fournisseur enregistre avec succes.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// Update fournisseur
if ($isAdmin && isset($_POST['btn_update_fournisseur'])) {
    $id = (int)$_POST['id_fournisseur'];
    $nom = htmlspecialchars($_POST['nom_societe']);
    $contact = htmlspecialchars($_POST['contact_nom']);
    $tel = htmlspecialchars($_POST['telephone']);
    $email = htmlspecialchars($_POST['email']);

    try {
        $stmt = $pdo->prepare("UPDATE fournisseurs SET nom_societe = ?, contact_nom = ?, telephone = ?, email = ? WHERE id_fournisseur = ?");
        $stmt->execute([$nom, $contact, $tel, $email, $id]);
        $message = "<div class='alert alert-success'>Fournisseur modifie.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// Delete fournisseur
if ($isAdmin && isset($_POST['btn_delete_fournisseur'])) {
    $id = (int)$_POST['id_fournisseur'];

    $checkLots = $pdo->prepare("SELECT COUNT(*) FROM stock_lots WHERE id_fournisseur = ?");
    $checkLots->execute([$id]);
    $hasLots = $checkLots->fetchColumn() > 0;

    if ($hasLots) {
        $message = "<div class='alert alert-danger'>Suppression impossible : des lots existent pour ce fournisseur.</div>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM fournisseurs WHERE id_fournisseur = ?");
        $stmt->execute([$id]);
        $message = "<div class='alert alert-success'>Fournisseur supprime.</div>";
    }
}

// Liste fournisseurs
$query = $pdo->query("SELECT * FROM fournisseurs ORDER BY nom_societe ASC");
$fournisseurs = $query->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Nos Fournisseurs</h2>
        <?php if($isAdmin): ?>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalFournisseur">
            + Nouveau Fournisseur
        </button>
        <?php endif; ?>
    </div>

    <?php if($message): ?><?= $message ?><?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Societe</th>
                        <th>Contact</th>
                        <th>Telephone</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fournisseurs as $f): ?>
                    <tr>
                        <td><strong><?php echo $f['nom_societe']; ?></strong></td>
                        <td><?php echo $f['contact_nom']; ?></td>
                        <td><?php echo $f['telephone']; ?></td>
                        <td><?php echo $f['email']; ?></td>
                        <td class="text-nowrap">
                            <?php if($isAdmin): ?>
                                <button
                                    class="btn btn-sm btn-outline-primary me-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditFournisseur"
                                    data-id="<?= $f['id_fournisseur'] ?>"
                                    data-nom="<?= htmlspecialchars($f['nom_societe']) ?>"
                                    data-contact="<?= htmlspecialchars($f['contact_nom']) ?>"
                                    data-tel="<?= htmlspecialchars($f['telephone']) ?>"
                                    data-email="<?= htmlspecialchars($f['email']) ?>"
                                    title="Modifier"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce fournisseur ?');">
                                    <input type="hidden" name="id_fournisseur" value="<?= $f['id_fournisseur'] ?>">
                                    <button type="submit" name="btn_delete_fournisseur" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
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

<?php if($isAdmin): ?>
<div class="modal fade" id="modalFournisseur" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Ajouter un Fournisseur</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nom de la societe *</label>
            <input type="text" name="nom_societe" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nom du contact</label>
            <input type="text" name="contact_nom" class="form-control" placeholder="Ex: Mr. Zambo">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Telephone</label>
                <input type="text" name="telephone" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
        <button type="submit" name="btn_ajouter_fournisseur" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalEditFournisseur" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5>Modifier un fournisseur</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_fournisseur" id="edit_id_fournisseur">
          <input type="text" name="nom_societe" id="edit_nom_societe" class="form-control mb-2" placeholder="Nom de la societe" required>
          <input type="text" name="contact_nom" id="edit_contact_nom" class="form-control mb-2" placeholder="Nom du contact">
          <input type="text" name="telephone" id="edit_telephone" class="form-control mb-2" placeholder="Telephone">
          <input type="email" name="email" id="edit_email" class="form-control" placeholder="Email">
      </div>
      <div class="modal-footer"><button type="submit" name="btn_update_fournisseur" class="btn btn-success">Mettre a jour</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalEditFournisseur');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_fournisseur').value = button.getAttribute('data-id');
        document.getElementById('edit_nom_societe').value = button.getAttribute('data-nom');
        document.getElementById('edit_contact_nom').value = button.getAttribute('data-contact');
        document.getElementById('edit_telephone').value = button.getAttribute('data-tel');
        document.getElementById('edit_email').value = button.getAttribute('data-email');
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

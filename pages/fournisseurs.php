<?php
require_once '../config/db.php';
session_start();

// ensure colonne pour distinguer fournisseurs vs donateurs
try {
    $pdo->exec("ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS est_donateur TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // ignore, maybe colonne already exists ou version mysql ne supporte pas IF NOT EXISTS
}

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

function getFournisseurInputErrors(): array {
    $errors = [];

    $nom = trim($_POST['nom_societe'] ?? '');
    $contact = trim($_POST['contact_nom'] ?? '');
    $tel = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($nom === '' || $contact === '' || $tel === '' || $email === '') {
        $errors[] = "Tous les champs obligatoires doivent etre remplis.";
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }

    return $errors;
}

// Ajout fournisseur
if ($isAdmin && isset($_POST['btn_ajouter_fournisseur'])) {
    $errors = getFournisseurInputErrors();
    if (!empty($errors)) {
        $message = "<div class='alert alert-danger'>" . implode("<br>", $errors) . "</div>";
    } else {
        $nom = htmlspecialchars(trim($_POST['nom_societe']));
        $contact = htmlspecialchars(trim($_POST['contact_nom']));
        $tel = htmlspecialchars(trim($_POST['telephone']));
        $email = htmlspecialchars(trim($_POST['email']));
        $isDonateur = isset($_POST['est_donateur']) ? 1 : 0;

        try {
            $sql = "INSERT INTO fournisseurs (nom_societe, contact_nom, telephone, email, est_donateur) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $contact, $tel, $email, $isDonateur]);
            $message = "<div class='alert alert-success'>Enregistrement reussi.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// Update fournisseur / donateur
if ($isAdmin && isset($_POST['btn_update_fournisseur'])) {
    $errors = getFournisseurInputErrors();
    if (!empty($errors)) {
        $message = "<div class='alert alert-danger'>" . implode("<br>", $errors) . "</div>";
    } else {
        $id = (int)$_POST['id_fournisseur'];
        $nom = htmlspecialchars(trim($_POST['nom_societe']));
        $contact = htmlspecialchars(trim($_POST['contact_nom']));
        $tel = htmlspecialchars(trim($_POST['telephone']));
        $email = htmlspecialchars(trim($_POST['email']));
        $isDonateur = isset($_POST['est_donateur']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE fournisseurs SET nom_societe = ?, contact_nom = ?, telephone = ?, email = ?, est_donateur = ? WHERE id_fournisseur = ?");
            $stmt->execute([$nom, $contact, $tel, $email, $isDonateur, $id]);
            $message = "<div class='alert alert-success'>Modification r\u00e9ussie.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
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

// Liste fournisseurs et donateurs séparés
$query = $pdo->query("SELECT * FROM fournisseurs WHERE est_donateur = 0 ORDER BY nom_societe ASC");
$fournisseurs = $query->fetchAll();
$query = $pdo->query("SELECT * FROM fournisseurs WHERE est_donateur = 1 ORDER BY nom_societe ASC");
$donateurs = $query->fetchAll();

include '../includes/sidebar.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Partenaires</h2>
        <?php if($isAdmin): ?>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalFournisseur">
            + Nouveau Fournisseur / Donateur
        </button>
        <?php endif; ?>
    </div>

    <?php if($message): ?><?= $message ?><?php endif; ?>

    <div class="column">
        <div class="col-md-12">
            <h4>Fournisseurs</h4>
            <div class="card shadow mb-4">
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
                                            data-donateur="0"
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
        <div class="col-md-12">
            <h4>Donateurs</h4>
            <div class="card shadow mb-4">
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
                            <?php foreach ($donateurs as $f): ?>
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
                                            data-donateur="1"
                                            title="Modifier"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce donateur ?');">
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
    </div>
</div>

<?php if($isAdmin): ?>
<div class="modal fade" id="modalFournisseur" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content js-required-validation">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">Ajouter un Fournisseur</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nom de la societe *</label>
            <input type="text" name="nom_societe" class="form-control" required data-required-field>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" name="est_donateur" id="add_est_donateur">
            <label class="form-check-label" for="add_est_donateur">Donateur</label>
        </div>
        <div class="mb-3">
            <label class="form-label">Nom du contact *</label>
            <input type="text" name="contact_nom" class="form-control" placeholder="Ex: Mr. Zambo" required data-required-field>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Telephone *</label>
                <input type="text" name="telephone" class="form-control" required data-required-field>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required data-required-field>
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
    <form method="POST" class="modal-content js-required-validation">
      <div class="modal-header"><h5>Modifier un fournisseur</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
          <input type="hidden" name="id_fournisseur" id="edit_id_fournisseur">
          <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="1" name="est_donateur" id="edit_est_donateur">
              <label class="form-check-label" for="edit_est_donateur">Donateur</label>
          </div>
          <input type="text" name="nom_societe" id="edit_nom_societe" class="form-control mb-2" placeholder="Nom de la societe" required data-required-field>
          <input type="text" name="contact_nom" id="edit_contact_nom" class="form-control mb-2" placeholder="Nom du contact" required data-required-field>
          <input type="text" name="telephone" id="edit_telephone" class="form-control mb-2" placeholder="Telephone" required data-required-field>
          <input type="email" name="email" id="edit_email" class="form-control" placeholder="Email" required data-required-field>
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
        // donnée donateur
        var isDon = button.getAttribute('data-donateur');
        document.getElementById('edit_est_donateur').checked = (isDon === '1');
    });

    var forms = document.querySelectorAll('.js-required-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            var requiredFields = form.querySelectorAll('[data-required-field]');
            var hasEmpty = false;

            requiredFields.forEach(function(field) {
                var value = (field.value || '').trim();
                if (value === '') {
                    hasEmpty = true;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (hasEmpty) {
                event.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
            }
        });
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

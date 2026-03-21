<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
$isAdmin = ($_SESSION['role'] === 'admin');

$message = '';

function getFournisseurInputErrors(): array {
    $errors = [];

    $nom = trim($_POST['nom_entite'] ?? '');
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
        $nom = htmlspecialchars(trim($_POST['nom_entite']));
        $contact = htmlspecialchars(trim($_POST['contact_nom']));
        $tel = htmlspecialchars(trim($_POST['telephone']));
        $email = htmlspecialchars(trim($_POST['email']));
        // Le type vient du select ou checkbox converti
        $type = isset($_POST['type']) ? $_POST['type'] : 'Fournisseur';

        try {
            $sql = "INSERT INTO Partenaire (nom_entite, contact_nom, telephone, email, type) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $contact, $tel, $email, $type]);
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
        $id = (int)$_POST['id_partenaire'];
        $nom = htmlspecialchars(trim($_POST['nom_entite']));
        $contact = htmlspecialchars(trim($_POST['contact_nom']));
        $tel = htmlspecialchars(trim($_POST['telephone']));
        $email = htmlspecialchars(trim($_POST['email']));
        $type = isset($_POST['type']) ? $_POST['type'] : 'Fournisseur';

        try {
            $stmt = $pdo->prepare("UPDATE Partenaire SET nom_entite = ?, contact_nom = ?, telephone = ?, email = ?, type = ? WHERE id_partenaire = ?");
            $stmt->execute([$nom, $contact, $tel, $email, $type, $id]);
            $message = "<div class='alert alert-success'>Modification r\u00e9ussie.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// Delete fournisseur
if ($isAdmin && isset($_POST['btn_delete_fournisseur'])) {
    $id = (int)$_POST['id_partenaire'];

    // Vérification via la table Commande (nouvelle structure)
    $checkLots = $pdo->prepare("SELECT COUNT(*) FROM Commande WHERE id_partenaire = ?");
    $checkLots->execute([$id]);
    $hasCmd = $checkLots->fetchColumn() > 0;

    if ($hasCmd) {
        $message = "<div class='alert alert-danger'>Suppression impossible : ce partenaire est lié à des commandes historiques.</div>";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM Partenaire WHERE id_partenaire = ?");
            $stmt->execute([$id]);
            $message = "<div class='alert alert-success'>Partenaire supprime.</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// Liste fournisseurs et donateurs unifiée mais triée
$query = $pdo->query("SELECT * FROM Partenaire ORDER BY type ASC, nom_entite ASC");
$partenaires = $query->fetchAll();

// Filtrage par type de partenaire
$fournisseurs = array_filter($partenaires, fn($p) => $p['type'] === 'Fournisseur');
$donateurs = array_filter($partenaires, fn($p) => $p['type'] === 'Don');

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
                                <td><strong><?php echo $f['nom_entite']; ?></strong></td>
                                <td><?php echo $f['contact_nom']; ?></td>
                                <td><?php echo $f['telephone']; ?></td>
                                <td><?php echo $f['email']; ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditFournisseur"
                                            data-id="<?= $f['id_partenaire'] ?>"
                                            data-nom="<?= htmlspecialchars($f['nom_entite']) ?>"
                                            data-contact="<?= htmlspecialchars($f['contact_nom']) ?>"
                                            data-tel="<?= htmlspecialchars($f['telephone']) ?>"
                                            data-email="<?= htmlspecialchars($f['email']) ?>"
                                            data-donateur="0"
                                            data-type="Fournisseur"
                                            title="Modifier"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce fournisseur ?');">
                                            <input type="hidden" name="id_partenaire" value="<?= $f['id_partenaire'] ?>">
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
                                <td><strong><?php echo $f['nom_entite']; ?></strong></td>
                                <td><?php echo $f['contact_nom']; ?></td>
                                <td><?php echo $f['telephone']; ?></td>
                                <td><?php echo $f['email']; ?></td>
                                <td class="text-nowrap">
                                    <?php if($isAdmin): ?>
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditFournisseur"
                                            data-id="<?= $f['id_partenaire'] ?>"
                                            data-nom="<?= htmlspecialchars($f['nom_entite']) ?>"
                                            data-contact="<?= htmlspecialchars($f['contact_nom']) ?>"
                                            data-tel="<?= htmlspecialchars($f['telephone']) ?>"
                                            data-email="<?= htmlspecialchars($f['email']) ?>"
                                            data-donateur="1"
                                            data-type="Don"
                                            title="Modifier"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce donateur ?');">
                                            <input type="hidden" name="id_partenaire" value="<?= $f['id_partenaire'] ?>">
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
            <label class="form-label fw-bold">Nom de la structure / entité *</label>
            <input type="text" name="nom_entite" class="form-control" placeholder="Ex: Laboratoires Biopharma" required data-required-field>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Type de partenaire</label>
            <select name="type" class="form-select">
                <option value="Fournisseur">Fournisseur (Achat)</option>
                <option value="Don">Donateur (Don)</option>
            </select>
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
          <input type="hidden" name="id_partenaire" id="edit_id_partenaire">
          <div class="mb-3">
            <label class="form-label fw-bold">Type</label>
            <select name="type" id="edit_type" class="form-select">
                <option value="Fournisseur">Fournisseur</option>
                <option value="Don">Donateur</option>
            </select>
          </div>
          <input type="text" name="nom_entite" id="edit_nom_entite" class="form-control mb-2" placeholder="Nom de la structure" required data-required-field>
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
        document.getElementById('edit_id_partenaire').value = button.getAttribute('data-id');
        document.getElementById('edit_nom_entite').value = button.getAttribute('data-nom');
        document.getElementById('edit_contact_nom').value = button.getAttribute('data-contact');
        document.getElementById('edit_telephone').value = button.getAttribute('data-tel');
        document.getElementById('edit_email').value = button.getAttribute('data-email');
        // donnée type
        document.getElementById('edit_type').value = button.getAttribute('data-type');
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

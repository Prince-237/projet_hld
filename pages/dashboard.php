<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: /index.php"); exit(); }

// déterminer si l'utilisateur est admin (pharmacien)
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// message pour affichage d'alertes (ex : création utilisateur)
$message = '';

// traitement de l'ajout d'un nouvel utilisateur par un admin
if ($isAdmin && isset($_POST['btn_new_user'])) {
    $nom = htmlspecialchars(trim($_POST['nom_complet']));
    $user = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $pass = $_POST['password'];
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';

    if (empty($nom) || empty($user) || empty($pass) || empty($email)) {
        $message = "<div class='alert alert-warning shadow-sm'>⚠️ Veuillez remplir tous les champs.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger shadow-sm'>⚠️ Adresse email invalide.</div>";
    } else {
        // vérification d'existence
        $stmt = $pdo->prepare("SELECT 1 FROM utilisateurs WHERE username = ? OR email = ?");
        $stmt->execute([$user, $email]);
        if ($stmt->fetch()) {
            $message = "<div class='alert alert-danger shadow-sm'>❌ Erreur : Le nom d'utilisateur ou l'email est déjà utilisé.</div>";
        } else {
            $pass_hache = password_hash($pass, PASSWORD_DEFAULT);
            $sql = "INSERT INTO utilisateurs (nom_complet, username, email, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([$nom, $user, $email, $pass_hache, $role]);
                $message = "<div class='alert alert-success shadow-sm'>✅ Utilisateur créé avec succès.</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger shadow-sm'>❌ Erreur système lors de la création de l'utilisateur.</div>";
            }
        }
    }
}


// 1. Calcul des statistiques
// A. Nombre de produits total
$nb_produits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();

// B. Alertes de stock (Stock Total <= Seuil Alerte)
$sql_alerte = "SELECT COUNT(*) FROM produits WHERE stock_total <= seuil_alerte AND stock_total > 0";
$nb_alerte = $pdo->query($sql_alerte)->fetchColumn();

// C. Ruptures de stock (Stock Total = 0)
$nb_rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_total = 0")->fetchColumn();

// D. Produits périmés ou bientôt périmés (on inclut les lots à moins de 14 jours de leur date d'expiration)
$nb_perime = $pdo->query("SELECT COUNT(*) FROM stock_lots WHERE date_expiration <= DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY) AND quantite_actuelle > 0")->fetchColumn();
// Récupère des lots périmés/critiques (détails pour affichage)
$expired_lots = $pdo->query("SELECT l.*, p.nom_medicament, f.nom_societe FROM stock_lots l JOIN produits p ON l.id_produit = p.id_produit LEFT JOIN fournisseurs f ON l.id_fournisseur = f.id_fournisseur WHERE l.date_expiration <= DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY) AND l.quantite_actuelle > 0 ORDER BY l.date_expiration ASC LIMIT 10")->fetchAll();
?>

<?php include '../includes/sidebar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Tableau de Bord - Hôpital Laquintinie</h2>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddUser">
                <i class="bi bi-person-plus"></i> Ajouter
            </button>
        <?php endif; ?>
    </div>
    <?= $message ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <h6>Total Produits</h6>
                    <h2><?= $nb_produits ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning shadow">
                <div class="card-body">
                    <h6>Critiques</h6>
                    <h2><?= $nb_alerte ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white shadow">
                <div class="card-body">
                    <h6>Ruptures</h6>
                    <h2><?= $nb_rupture ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-dark text-white shadow">
                <div class="card-body">
                    <h6>Périmés</h6>
                    <h2><?= $nb_perime ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <h4>Alertes à traiter d'urgence</h4>
            <table class="table table-hover bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th>Stock Actuel</th>
                        <th>Seuil Dynamique</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $list_alerte = $pdo->query("SELECT * FROM produits WHERE stock_total <= seuil_alerte ORDER BY stock_total ASC LIMIT 10");
                    while($row = $list_alerte->fetch()) {
                        $status = ($row['stock_total'] == 0) ? '<span class="badge bg-danger">Rupture</span>' : '<span class="badge bg-warning">Critique</span>';
                        echo "<tr>
                                <td>{$row['nom_medicament']}</td>
                                <td>{$row['stock_total']}</td>
                                <td>{$row['seuil_alerte']}</td>
                                <td>$status</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <h4>Lots périmés / Proche de l'expiration</h4>
            <table class="table table-sm table-striped bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th>Lot</th>
                        <th>Quantité restante</th>
                        <th>Expiration</th>
                        <th>Fournisseur /<br> <span>Donateur</span></th>
                        <th>Date entrée</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($expired_lots as $lot): ?>
                        <?php
                            $exp_ts = strtotime($lot['date_expiration']);
                            $today = strtotime(date('Y-m-d'));
                            if ($exp_ts < $today) {
                                $status = '<span class="badge bg-dark">Périmé</span>';
                                // $rowClass = 'table-dark text-white';
                            } else {
                                $status = '<span class="badge bg-warning">Critique</span>';
                                // $rowClass = 'table-warning';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= htmlspecialchars($lot['nom_medicament']) ?></td>
                            <td><?= htmlspecialchars($lot['num_lot']) ?></td>
                            <td><?= $lot['quantite_actuelle'] ?></td>
                            <td><?= $lot['date_expiration'] ?></td>
                            <td><?= isset($lot['nom_societe']) ? htmlspecialchars($lot['nom_societe']) : '-' ?></td>
                            <td><?= $lot['date_enregistrement'] ?></td>
                            <td><?= $status ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal pour ajout utilisateur -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter un utilisateur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <?= $message ?>
          <div class="mb-3">
              <label class="form-label fw-bold">Nom Complet</label>
              <input type="text" name="nom_complet" class="form-control" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Nom d'utilisateur</label>
              <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Email</label>
              <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Mot de passe</label>
              <div class="position-relative">
                  <input type="password" name="password" class="form-control pe-5" required>
                  <span class="toggle-password position-absolute" role="button" aria-label="Afficher le mot de passe" style="top:50%; right:0.75rem; transform:translateY(-50%);">
                      <i class="bi bi-eye"></i>
                  </span>
              </div>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Rôle</label>
              <select name="role" class="form-select" required>
                  <option value="user">Utilisateur</option>
                  <option value="admin">Administrateur</option>
              </select>
          </div>
      </div>
      <div class="modal-footer">
          <button type="submit" name="btn_new_user" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="../assets/js/password-toggle.js"></script>

<?php include '../includes/footer.php'; ?>
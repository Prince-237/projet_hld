<?php
require_once 'config/db.php';

$message = "";

if (isset($_POST['btn_inscription'])) {
    $nom = htmlspecialchars(trim($_POST['nom_complet']));
    $user = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $pass = $_POST['password'];
    
    // ON FIXE LE RÔLE ICI : personne ne peut le changer depuis le formulaire
    $role = 'user'; 

    if (empty($nom) || empty($user) || empty($pass) || empty($email)) {
        $message = "<div class='alert alert-warning shadow-sm'>⚠️ Veuillez remplir tous les champs.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger shadow-sm'>⚠️ Adresse email invalide.</div>";
    } else {
        // On ne vérifie le domaine que si une connexion internet est détectée
        $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 1);
        if ($connected) {
            fclose($connected);
            if (!checkdnsrr(substr(strrchr($email, "@"), 1), "MX")) {
                $message = "<div class='alert alert-danger shadow-sm'>⚠️ Le domaine de cet email semble invalide (vérification en ligne).</div>";
            }
        }

        // Si aucun message d'erreur n'a été défini, on continue
        if (empty($message)) {
            try {
                // On vérifie aussi que l'email ou le nom d'utilisateur n'est pas déjà pris
                $stmt = $pdo->prepare("SELECT 1 FROM utilisateurs WHERE username = ? OR email = ?");
                $stmt->execute([$user, $email]);
                if ($stmt->fetch()) {
                    $message = "<div class='alert alert-danger shadow-sm'>❌ Erreur : Le nom d'utilisateur ou l'email est déjà utilisé.</div>";
                } else {
                    $pass_hache = password_hash($pass, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO utilisateurs (nom_complet, username, email, password, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nom, $user, $email, $pass_hache, $role]);
                    $message = "<div class='alert alert-success shadow-sm'>✅ Inscription réussie ! Votre compte est en mode 'Consultation'. <a href='index.php' class='fw-bold'>Se connecter</a></div>";
                }
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger shadow-sm'>❌ Erreur système lors de l'inscription.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Pharmacie Laquintinie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card { border: none; border-radius: 15px; }
        .card-header { border-radius: 15px 15px 0 0 !important; }
        .toggle-password { cursor: pointer; color: #6c757d; }
        .toggle-password:hover { color: #343a40; }
            </style>
</head>
<body>
    <div class="container">
        <div class="card mx-auto shadow-lg" style="max-width: 450px;">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Créer un compte</h4>
                <small>Hôpital Laquintinie</small>
            </div>
            <div class="card-body p-4">
                <?= $message ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nom Complet</label>
                        <input type="text" name="nom_complet" class="form-control" placeholder="ex: My Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" placeholder="ex: My-Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="ex: moi@hopital.com" required>
                    </div>
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-bold">Mot de passe</label>
                        <input type="password" name="password" class="form-control pe-5" placeholder="Entrez un mot de passe" required>
                        <span class="toggle-password position-absolute" role="button" aria-label="Afficher le mot de passe" style="top:50%; right:0.75rem; transform:translateY(-50%);">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                    
                    <div class="alert alert-light border text-muted small">
                        ℹ️ Par défaut, votre compte sera créé avec un accès <strong>Consultation uniquement</strong>.
                    </div>

                    <button type="submit" name="btn_inscription" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        S'inscrire
                    </button>
                    <hr class="my-4">

                    <div class="text-center mt-3 mb-3">
                        <p class="mb-0 text-muted">Déjà un compte ?</p>
                        <a href="index.php" class="text-decoration-none fw-bold text-primary">Se connecter</a>
                    </div>
                    
                </form>
                <div class="card-footer text-center text-muted py-3 bg-white" style="border-radius: 0 0 15px 15px;">
                <small>Système de Gestion de la Pharmacie | Hôpital Laquintinie de Douala</small>
            </div>
            </div>
        </div>
    </div>
    <script src="assets/js/password-toggle.js"></script>
</body>
</html>
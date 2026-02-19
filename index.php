<?php
session_start();
require_once 'config/db.php';

$erreur = "";

if (isset($_POST['btn_login'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password']; 

    // 1. On cherche l'utilisateur par son login
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 2. Vérification sécurisée (Clair vs Haché en BDD)
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['nom'] = $user['nom_complet'];
        $_SESSION['role'] = $user['role'];
        
        // Attention : On redirige vers le dossier pages/
        header("Location: pages/dashboard.php");
        exit();
    } else {
        $erreur = "Identifiants incorrects ou compte inexistant.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Pharmacie Laquintinie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card { border: none; border-radius: 15px; }
        .card-header { border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background-color: #0d6efd; border: none; }
        .btn-primary:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mx-auto shadow-lg" style="max-width: 450px;">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Accès Pharmacie</h4>
                <small>Hôpital Laquintinie</small>
            </div>
            
            <div class="card-body p-4">
                <?php if($erreur): ?> 
                    <div class="alert alert-danger shadow-sm py-2 text-center"><?= $erreur ?></div> 
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" placeholder="Entrez votre login" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mot de passe</label>
                        <input type="password" name="password" class="form-control" placeholder="Entrez votre mot de passe" required>
                    </div>
                    
                    <button type="submit" name="btn_login" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        Se connecter
                    </button>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0 text-muted">Nouveau collaborateur ?</p>
                        <a href="inscription.php" class="text-decoration-none fw-bold text-primary">Créer un compte personnel</a>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center text-muted py-3 bg-white" style="border-radius: 0 0 15px 15px;">
                <small>&copy; 2026 - Direction Technique</small>
            </div>
        </div>
    </div>
</body>
</html>
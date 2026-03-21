<?php
require_once '../config/db.php';

$message = "";
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Jeton invalide.");
}

$token_hash = hash('sha256', $token);

// Vérifier le token
$stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
$stmt->execute([$token_hash]);
$user = $stmt->fetch();

if (!$user) {
    $message = "<div class='alert alert-danger'>Ce lien est invalide ou a expiré. <a href='forgot_password.php'>Ressayer</a></div>";
    $token_valid = false;
} else {
    $token_valid = true;
}

if ($token_valid && isset($_POST['btn_new_pass'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if (strlen($pass1) < 4) {
        $message = "<div class='alert alert-danger'>Le mot de passe est trop court.</div>";
    } elseif ($pass1 !== $pass2) {
        $message = "<div class='alert alert-danger'>Les mots de passe ne correspondent pas.</div>";
    } else {
        $pass_hache = password_hash($pass1, PASSWORD_DEFAULT);

        // Mise à jour du mot de passe et suppression du token
        $sql = "UPDATE Utilisateur SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id_user = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$pass_hache, $user['id_user']])) {
            $message = "<div class='alert alert-success'>Mot de passe modifié avec succès ! <a href='../index.php' class='fw-bold'>Se connecter</a></div>";
            $token_valid = false; // Pour cacher le formulaire
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
    <div class="container">
        <div class="card mx-auto shadow-lg" style="max-width: 450px;">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Nouveau mot de passe</h4>
            </div>
            <div class="card-body p-4">
                <?= $message ?>
                
                <?php if ($token_valid): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nouveau mot de passe</label>
                        <input type="password" name="pass1" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirmer le mot de passe</label>
                        <input type="password" name="pass2" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="btn_new_pass" class="btn btn-primary w-100 fw-bold">
                        Enregistrer
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

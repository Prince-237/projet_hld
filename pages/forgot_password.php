<?php
require_once '../config/db.php';

$message = "";

if (isset($_POST['btn_reset'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Vérifier si l'email existe
        $stmt = $pdo->prepare("SELECT id_user, nom_complet FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Générer un token sécurisé
            $token = bin2hex(random_bytes(16));
            $token_hash = hash('sha256', $token);
            $expiry = date("Y-m-d H:i:s", time() + 60 * 30); // Valide 30 minutes

            // Enregistrer le hash du token en base
            $sql = "UPDATE utilisateurs SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id_user = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$token_hash, $expiry, $user['id_user']])) {
                // Création du lien
                // Note: Adaptez 'localhost/projet-hld' selon votre URL réelle
                $link = "http://localhost/projet-hld/pages/reset_password.php?token=" . $token;

                // Simulation d'envoi d'email (Pour XAMPP Local)
                // Dans un vrai serveur, utilisez mail() ou PHPMailer
                $message = "<div class='alert alert-success'>
                                <strong>Simulation d'email :</strong><br>
                                Pour réinitialiser votre mot de passe, cliquez sur ce lien :<br>
                                <a href='$link'>$link</a>
                            </div>";
            } else {
                $message = "<div class='alert alert-danger'>Erreur base de données.</div>";
            }
        } else {
            // Par sécurité, on ne dit pas explicitement si l'email n'existe pas, 
            // mais ici pour le dev on peut être plus explicite ou rester vague.
            $message = "<div class='alert alert-warning'>Si cet email existe, un lien a été envoyé.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Email invalide.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié - Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
    <div class="container">
        <div class="card mx-auto shadow-lg" style="max-width: 450px;">
            <div class="card-header bg-primary text-dark text-center py-3">
                <h4 class="mb-0">Récupération</h4>
            </div>
            <div class="card-body p-4">
                <?= $message ?>
                <p class="text-muted small">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="btn_reset" class="btn btn-primary w-100 fw-bold">
                        Envoyer le lien
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="../index.php" class="text-decoration-none text-muted">Retour à la connexion</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

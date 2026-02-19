<?php
require_once 'config/db.php';

$message = "";

if (isset($_POST['btn_inscription'])) {
    // Nettoyage des données
    $nom = htmlspecialchars(trim($_POST['nom_complet']));
    $user = htmlspecialchars(trim($_POST['username']));
    $pass = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($nom) && !empty($user) && !empty($pass)) {
        // Hachage du mot de passe saisi en clair
        // C'est cette fonction qui transforme 'admin123' en une chaîne cryptée
        $pass_hache = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO utilisateurs (nom_complet, username, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nom, $user, $pass_hache, $role]);
            
            $message = "<div class='alert alert-success shadow-sm'>✅ Compte créé avec succès ! <a href='index.php' class='fw-bold'>Se connecter</a></div>";
        } catch (PDOException $e) {
            // Gestion de l'erreur si le 'username' existe déjà (contrainte UNIQUE en BDD)
            $message = "<div class='alert alert-danger shadow-sm'>❌ Erreur : Ce nom d'utilisateur est déjà utilisé.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning shadow-sm'>⚠️ Veuillez remplir tous les champs.</div>";
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
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card mx-auto shadow-lg" style="max-width: 500px;">
            <div class="card-header bg-primary text-white text-center py-3" style="border-radius: 15px 15px 0 0;">
                <h4 class="mb-0">Inscription Personnel</h4>
            </div>
            <div class="card-body p-4">
                <?= $message ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nom Complet</label>
                        <input type="text" name="nom_complet" class="form-control" placeholder="ex: Jean Dupont" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" placeholder="ex: jdupont" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mot de passe</label>
                        <input type="password" name="password" class="form-control" placeholder="Saisissez un mot de passe" required>
                        <!-- <div class="form-text">Il sera haché automatiquement en base de données.</div> -->
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rôle d'accès</label>
                        <select name="role" class="form-select border-primary">
                            <option value="user">Utilisateur (Consultation)</option>
                            <option value="admin">Administrateur (Gestion complète)</option>
                        </select>
                    </div>
                    <hr>
                    <button type="submit" name="btn_inscription" class="btn btn-primary w-100 py-2 fw-bold">Enregistrer l'utilisateur</button>
                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none text-muted">Retour à la connexion</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
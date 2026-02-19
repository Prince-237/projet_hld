<?php
// Paramètres de connexion
$host = 'localhost';
$dbname = 'laquintinie_projet'; // Nom exact de ta BDD
$user = 'root';
$pass = ''; // Vide par défaut sur XAMPP

try {
    // Création de l'instance PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    // Activation des erreurs PDO pour le développement
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mode de récupération par défaut : Tableau associatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'erreur, on arrête tout et on affiche le message
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
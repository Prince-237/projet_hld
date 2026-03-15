<?php
require_once '../config/db.php';
session_start();

// On indique au navigateur qu'on renvoie du JSON
header('Content-Type: application/json');

// Vérification de sécurité : utilisateur connecté ?
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['found' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupération et nettoyage des paramètres GET
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;

if ($year == 0 || $month == 0) {
    echo json_encode(['found' => false, 'message' => 'Date invalide']);
    exit();
}

try {
    // 1. Chercher l'entête de l'inventaire pour ce mois/année (statut traité uniquement)
    $stmt = $pdo->prepare("SELECT i.id_inventaire, i.date_inventaire, i.statut, u.nom_complet
        FROM inventaires i
        JOIN utilisateurs u ON i.id_user = u.id_user
        WHERE YEAR(i.date_inventaire) = ? 
        AND MONTH(i.date_inventaire) = ? 
        AND i.statut = 'traité'
        ORDER BY i.id_inventaire DESC LIMIT 1");
    $stmt->execute([$year, $month]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si aucun inventaire trouvé
    if (!$inventaire) {
        echo json_encode(['found' => false, 'message' => 'Aucun inventaire trouvé pour cette période.']);
        exit();
    }

    // 2. Si inventaire trouvé, récupérer les détails (produits, écarts, etc.)
    $stmtDetails = $pdo->prepare("
        SELECT d.stock_theorique, d.stock_physique, d.ecart, 
               p.nom_medicament, p.type_produit, p.seuil_alerte
        FROM inventaire_details d
        JOIN produits p ON d.id_produit = p.id_produit
        WHERE d.id_inventaire = ?
        ORDER BY p.nom_medicament ASC
    ");
    $stmtDetails->execute([$inventaire['id_inventaire']]);
    $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

    // 3. Renvoyer le tout en JSON
    echo json_encode([
        'found' => true,
        'header' => $inventaire,
        'details' => $details
    ]);

} catch (Exception $e) {
    // En cas d'erreur SQL ou PHP
    http_response_code(500);
    echo json_encode(['found' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>

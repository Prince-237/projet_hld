<?php
require_once '../config/db.php';
session_start();
// Basic access control: require logged-in user
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$q = '';
if (isset($_GET['q'])) {
    $q = trim($_GET['q']);
}

try {
    if ($q !== '') {
        $like = '%'.$q.'%';
        $stmt = $pdo->prepare("SELECT id_produit, nom_medicament, forme, dosage, prix_unitaire, marge_pourcentage, stock_total, COALESCE(seuil_alerte,0) AS seuil_alerte FROM produits WHERE nom_medicament LIKE ? OR forme LIKE ? OR dosage LIKE ? ORDER BY nom_medicament ASC LIMIT 200");
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id_produit, nom_medicament, forme, dosage, prix_unitaire, marge_pourcentage, stock_total, COALESCE(seuil_alerte,0) AS seuil_alerte FROM produits ORDER BY nom_medicament ASC LIMIT 200");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}


?>

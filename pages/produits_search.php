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
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id_produit, p.nom_medicament, c.forme, c.dosage, p.prix_unitaire, p.marge_pourcentage,
                   COALESCE(SUM(l.quantite_actuelle), 0) as stock_total,
                   COALESCE(p.seuil_alerte, 0) AS seuil_alerte
            FROM Produit p
            JOIN ProductCategory c ON p.id_categorie = c.id_categorie
            LEFT JOIN StockLot l ON p.id_produit = l.id_produit
            WHERE p.nom_medicament LIKE ? OR c.forme LIKE ? OR c.dosage LIKE ?
            GROUP BY p.id_produit
            ORDER BY p.nom_medicament ASC LIMIT 200
        ");
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id_produit, p.nom_medicament, c.forme, c.dosage, p.prix_unitaire, p.marge_pourcentage,
                   COALESCE(SUM(l.quantite_actuelle), 0) as stock_total,
                   COALESCE(p.seuil_alerte, 0) AS seuil_alerte
            FROM Produit p
            JOIN ProductCategory c ON p.id_categorie = c.id_categorie
            LEFT JOIN StockLot l ON p.id_produit = l.id_produit
            GROUP BY p.id_produit
            ORDER BY p.nom_medicament ASC LIMIT 200
        ");
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

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

$type = isset($_GET['type']) ? $_GET['type'] : 'Medicament';

// Gestion de la limite d'affichage
$limit = isset($_GET['limit']) ? $_GET['limit'] : 25;
if ($limit === 'all') {
    $limit_sql = " LIMIT 1000"; // Sécurité pour éviter de surcharger le serveur
} else {
    $limit_sql = " LIMIT " . intval($limit);
}

try {
    $sql = "SELECT p.*, c.nom_categorie, c.forme, c.dosage,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Fournisseur'), 0) AS stock_achat,
                COALESCE((SELECT SUM(sl.quantite_actuelle) FROM StockLot sl LEFT JOIN CommandeDetail cd ON sl.id_cmd_det = cd.id_cmd_det LEFT JOIN Commande cmd ON cd.id_commande = cmd.id_commande LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire WHERE sl.id_produit = p.id_produit AND part.type = 'Don'), 0) AS stock_don
             FROM Produit p 
             JOIN ProductCategory c ON p.id_categorie = c.id_categorie
             WHERE p.type_produit = ?";
             
    $params = [$type];

    if ($q !== '') {
        $sql .= " AND (p.nom_medicament LIKE ? OR c.nom_categorie LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $sql .= " ORDER BY p.nom_medicament ASC" . $limit_sql;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

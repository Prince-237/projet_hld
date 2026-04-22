<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Accès refusé");
}

if (!isset($_POST['id_commande'])) {
    die("ID invalide");
}

$id_commande = intval($_POST['id_commande']);

try {
    $pdo->beginTransaction();

    // Vérifier si la commande existe
    $check = $pdo->prepare("SELECT id_commande FROM Commande WHERE id_commande = ? AND deleted_at IS NULL");
    $check->execute([$id_commande]);

    if (!$check->fetch()) {
        throw new Exception("Commande introuvable ou déjà supprimée");
    }

    // Soft delete
    $stmt = $pdo->prepare("UPDATE Commande SET deleted_at = NOW() WHERE id_commande = ?");
    $stmt->execute([$id_commande]);

    $pdo->commit();

    header("Location: entrees_stock.php?msg=deleted");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur : " . $e->getMessage());
}
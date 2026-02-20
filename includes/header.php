<?php
// Récupère le fichier courant pour marquer le menu actif
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Gère les chemins selon que la page est dans /pages ou à la racine
$is_pages_dir = ($current_dir === 'pages');
$root_prefix = $is_pages_dir ? '../' : '';
$pages_prefix = $is_pages_dir ? '' : 'pages/';

// Fonction helper pour vérifier la page actuelle
function isActive($page) {
    global $current_page;
    return ($current_page === $page) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Laquintinie - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        .nav-link.active { border-bottom: 2px solid white; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= $root_prefix ?>dashboard.php">LAQUINTINIE</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="<?= $root_prefix ?>pages/dashboard.php">Tableau de bord</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'produits.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/produits.php">Medicaments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'entrees.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/entrees.php">Entrees/Lots</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'sorties.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/sorties.php">Sorties</a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'inventaire.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/inventaire.php">Inventaire & Seuils</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'fournisseurs.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/fournisseurs.php">Fournisseurs</a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <i class="bi bi-person-circle"></i>
                    <?= isset($_SESSION['nom']) ? strtoupper($_SESSION['nom']) : 'UTILISATEUR' ?>
                    <small class="badge bg-light text-primary ms-1"><?= $_SESSION['role'] ?? 'guest' ?></small>
                </span>
                <a href="<?= $root_prefix ?>logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
            </div>
        </div>
    </div>
</nav>

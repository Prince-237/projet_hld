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

$achats_pages = ['entrees.php', 'entrees_stock.php', 'sorties.php', 'liste_transferts.php', 'liste_retours_fournisseur.php'];
$is_achats_active = in_array($current_page, $achats_pages);

$dons_pages = ['dons.php', 'sorties_dons.php', 'liste_transfer_dons.php'];
$is_dons_active = in_array($current_page, $dons_pages);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Laquintinie - Gestion Pharmacie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $root_prefix ?>assets/css/sidebar.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        .nav-link.active { border-bottom: 2px solid white; }
    </style>
</head>
<body class="bg-light">
<div class="app-layout">
    <aside class="sidebar bg-primary text-white d-flex flex-column">
        <div class="sidebar-brand p-3">
            <a class="navbar-brand text-white sidebar-logo-link" href="<?= $root_prefix ?>pages/dashboard.php">
                <img src="<?= $root_prefix ?>assets/img/logo-hopi.jpg" alt="Logo Laquintinie" class="sidebar-logo">
            </a>
        </div>

        <!-- <nav class="flex-grow-0"> -->
            <ul class="nav flex-column px-2">
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="<?= $root_prefix ?>pages/dashboard.php">Tableau de bord</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'produits.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/produits.php">Produits</a>
                </li>
                <!-- Gestion des Achats -->
                <li class="nav-item">
                    <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#achatsSubmenu" role="button" aria-expanded="<?= $is_achats_active ? 'true' : 'false' ?>" aria-controls="achatsSubmenu">
                        <span>Gestion des Achats</span>
                        <i class="bi bi-chevron-down" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse <?= $is_achats_active ? 'show' : '' ?>" id="achatsSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'entrees.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/entrees.php">Ajouter une Commande</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'entrees_stock.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/entrees_stock.php">Liste des Entrées</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'liste_retours_fournisseur.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/liste_retours_fournisseur.php">Retour fournisseur</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'sorties.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/sorties.php">Ajouter un transfert</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'liste_transferts.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/liste_transferts.php">Liste des transferts</a>
                            </li>
                        </ul>
                    </div>
                </li>
                <!-- Gestion des Dons -->
                <li class="nav-item">
                    <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#donsSubmenu" role="button" aria-expanded="<?= $is_dons_active ? 'true' : 'false' ?>" aria-controls="donsSubmenu">
                        <span>Gestion des Dons</span>
                        <i class="bi bi-chevron-down" style="font-size: 0.8rem;"></i>
                    </a>
                    <div class="collapse <?= $is_dons_active ? 'show' : '' ?>" id="donsSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'dons.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/dons.php">Réceptionner Dons</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'sorties_dons.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/sorties_dons.php">Ajouter un transfert</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white <?= ($current_page == 'liste_transfer_dons.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/liste_transfer_dons.php">Liste des transferts</a>
                            </li>
                        </ul>
                    </div>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'inventaire.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/inventaire.php">Inventaire & Seuils</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'fournisseurs.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/fournisseurs.php">Fournisseurs / Donateurs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'points_vente.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/points_vente.php">Points de Vente</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white <?= ($current_page == 'bilan_financier.php') ? 'active' : '' ?>" href="<?= $root_prefix . $pages_prefix ?>pages/bilan_financier.php">Bilan Financier</a>
                </li>
                <?php endif; ?>
            </ul>
        <!-- </nav> -->

        <div class="sidebar-user mt-auto p-3 text-white">
            <a href="#" class="text-white text-decoration-none" data-bs-toggle="modal" data-bs-target="#userProfileModal">
                <div class="mb-2 small">
                    <i class="bi bi-person-circle fs-5 align-middle"></i>
                    <span class="align-middle"><?= isset($_SESSION['nom']) ? strtoupper($_SESSION['nom']) : 'UTILISATEUR' ?></span>
                    <small class="badge bg-light text-primary ms-1 align-middle"><?= $_SESSION['role'] ?? 'guest' ?></small>
                </div>
            </a>
            <a href="<?= $root_prefix ?>logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
        </div>
    </aside>

    <div class="content-area">
    <main class="main-content p-3" role="main">

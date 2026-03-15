-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 12:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laquintinie_projet`
--

-- --------------------------------------------------------

--
-- Table structure for table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id_fournisseur` int(11) NOT NULL,
  `nom_societe` varchar(150) NOT NULL,
  `contact_nom` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `est_donateur` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id_fournisseur`, `nom_societe`, `contact_nom`, `telephone`, `email`, `est_donateur`) VALUES
(1, 'BioPharma', 'Prince', '1234567890', 'pr@gmail.com', 0),
(2, 'Prince', 'Malif', '60744225', 'prince@gmail.com', 0),
(4, 'Try', 'try', '1234', 'gdhd@gmail.com', 1),
(5, 'SOPHARCAM', 'Mr Pierre', '651628395', 'pierre12@gmail.com', 0);

-- --------------------------------------------------------

--
-- Table structure for table `inventaires`
--

CREATE TABLE `inventaires` (
  `id_inventaire` int(11) NOT NULL,
  `date_inventaire` datetime NOT NULL,
  `id_user` int(11) NOT NULL,
  `statut` enum('en cours','traité') NOT NULL DEFAULT 'en cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventaires`
--

INSERT INTO `inventaires` (`id_inventaire`, `date_inventaire`, `id_user`, `statut`) VALUES
(1, '2026-03-12 20:26:14', 9, 'traité'),
(2, '2026-03-12 20:27:42', 6, 'traité'),
(3, '2026-03-12 21:09:36', 6, 'traité'),
(4, '2026-03-13 13:36:47', 9, 'traité');

-- --------------------------------------------------------

--
-- Table structure for table `inventaire_details`
--

CREATE TABLE `inventaire_details` (
  `id_detail` int(11) NOT NULL,
  `id_inventaire` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `stock_theorique` int(11) NOT NULL,
  `stock_physique` int(11) NOT NULL,
  `ecart` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventaire_details`
--

INSERT INTO `inventaire_details` (`id_detail`, `id_inventaire`, `id_produit`, `stock_theorique`, `stock_physique`, `ecart`) VALUES
(1, 1, 28, 30, 28, -2),
(2, 2, 28, 28, 32, 4),
(3, 3, 28, 32, 32, 0),
(4, 4, 30, 0, 0, 0),
(5, 4, 28, 32, 29, -3),
(6, 4, 33, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `points_vente`
--

CREATE TABLE `points_vente` (
  `id_point_vente` int(11) NOT NULL,
  `nom_point_vente` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `points_vente`
--

INSERT INTO `points_vente` (`id_point_vente`, `nom_point_vente`) VALUES
(1, 'Magasin Central'),
(3, 'Pharmacie Betote'),
(2, 'Pharmacie des Urgences'),
(5, 'Pharmacie du PSE'),
(4, 'Pharmacie Principal');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id_categorie` int(10) UNSIGNED NOT NULL,
  `nom_categorie` varchar(100) NOT NULL,
  `forme` varchar(50) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id_categorie`, `nom_categorie`, `forme`, `dosage`, `description`, `created_at`) VALUES
(1, 'Analgésique', 'Comprimé', '500mg', 'Médicaments contre la douleur et la fièvre', '2026-02-26 15:52:20'),
(2, 'Antibiotique', 'Sirop', '250mg/5ml', 'Traitement des infections bactériennes', '2026-02-26 15:52:20'),
(3, 'Anti-inflammatoire', 'Injectable', '100mg', 'Utilisation en urgence ou post-opératoire', '2026-02-26 15:52:20'),
(4, 'Antiinflamatoire', 'Sirop', '1000ml', NULL, '2026-02-27 04:25:47'),
(5, 'Antibiotique', 'Gélule', '500mg', 'Traitements bactériens standards', '2026-02-27 16:59:24'),
(6, 'Analgésique', 'Sirop', '125mg/5ml', 'Gestion de la douleur pédiatrique', '2026-02-27 16:59:24'),
(7, 'Hématologie', 'Flacon', '10ml', 'Réactifs pour groupage sanguin et NFS', '2026-02-27 16:59:24'),
(8, 'Biochimie', 'Coffret/Kit', '100 Tests', 'Réactifs pour Glycémie, Créatinine, Urée', '2026-02-27 16:59:24'),
(9, 'Sérologie', 'Cassette', 'Unitaires', 'Tests rapides (TDR) VIH, Paludisme, Hépatites', '2026-02-27 16:59:24'),
(10, 'Bactériologie', 'Milieu de culture', 'Boite de Pétri', 'Gélose pour examens ECBU/Pus', '2026-02-27 16:59:24'),
(11, 'Consommable Labo', 'Sachet', '100 unités', 'Cônes, tubes à essai, lancettes', '2026-02-27 16:59:24'),
(12, 'Antibiotiques', 'Comprimé', '1g', NULL, '2026-03-09 13:07:10');

-- --------------------------------------------------------

--
-- Table structure for table `produits`
--

CREATE TABLE `produits` (
  `id_produit` int(11) NOT NULL,
  `id_categorie` int(10) UNSIGNED NOT NULL,
  `nom_medicament` varchar(150) NOT NULL,
  `type_produit` enum('Medicament','Laboratoire') DEFAULT 'Medicament',
  `seuil_alerte` int(11) DEFAULT NULL,
  `stock_total` int(11) DEFAULT NULL,
  `marge_pourcentage` decimal(5,2) DEFAULT 20.00,
  `prix_unitaire` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produits`
--

INSERT INTO `produits` (`id_produit`, `id_categorie`, `nom_medicament`, `type_produit`, `seuil_alerte`, `stock_total`, `marge_pourcentage`, `prix_unitaire`) VALUES
(28, 2, 'Paracetamol', 'Medicament', 100, 29, 20.00, 100.00),
(30, 5, 'one', 'Laboratoire', 200, 0, 20.00, 700.00),
(33, 5, 'test', 'Laboratoire', 1111, 0, 20.00, 222.00);

-- --------------------------------------------------------

--
-- Table structure for table `sorties`
--

CREATE TABLE `sorties` (
  `id_sortie` int(11) NOT NULL,
  `id_lot` int(11) DEFAULT NULL,
  `id_source` int(11) DEFAULT 1,
  `id_destination` int(11) DEFAULT NULL,
  `nom_point_vente` varchar(100) NOT NULL,
  `quantite_sortie` int(11) NOT NULL,
  `prix_vente_unitaire` decimal(10,2) DEFAULT NULL,
  `total_prix` decimal(10,2) DEFAULT NULL,
  `date_sortie` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sorties`
--

INSERT INTO `sorties` (`id_sortie`, `id_lot`, `id_source`, `id_destination`, `nom_point_vente`, `quantite_sortie`, `prix_vente_unitaire`, `total_prix`, `date_sortie`, `id_user`) VALUES
(14, 33, 1, 2, 'Pharmacie des Urgences', 70, 120.00, 8400.00, '2026-03-12 15:31:38', 9);

-- --------------------------------------------------------

--
-- Table structure for table `stock_lots`
--

CREATE TABLE `stock_lots` (
  `id_lot` int(11) NOT NULL,
  `id_produit` int(11) DEFAULT NULL,
  `id_fournisseur` int(11) DEFAULT NULL,
  `source_provenance` enum('Achat','Don') DEFAULT 'Achat',
  `num_lot` varchar(50) NOT NULL,
  `quantite_initiale` int(11) NOT NULL,
  `quantite_actuelle` int(11) NOT NULL,
  `date_expiration` date NOT NULL,
  `prix_achat_ht` decimal(10,2) DEFAULT NULL,
  `prix_achat_ttc` decimal(10,2) DEFAULT NULL,
  `date_enregistrement` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) DEFAULT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `total_prix` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_lots`
--

INSERT INTO `stock_lots` (`id_lot`, `id_produit`, `id_fournisseur`, `source_provenance`, `num_lot`, `quantite_initiale`, `quantite_actuelle`, `date_expiration`, `prix_achat_ht`, `prix_achat_ttc`, `date_enregistrement`, `id_user`, `prix_unitaire`, `total_prix`) VALUES
(33, 28, 1, 'Achat', '233', 100, 29, '2026-03-31', NULL, 100.00, '2026-03-12 15:28:57', 9, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_user` int(11) NOT NULL,
  `nom_complet` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_user`, `nom_complet`, `username`, `password`, `role`, `email`, `reset_token_hash`, `reset_token_expires_at`) VALUES
(1, 'Admin', 'admin', '$2y$10$mC7G0eWzT.A.9GvG9m.pueS8PzI.p/5p6l5p.X6D6uH5p6l5p6l5p', 'admin', NULL, NULL, NULL),
(6, 'prince', 'prince', '$2y$10$/68M6VyB7m49tejYaMfVheqkdaSJsS4F5VVVgTvTyUpkT2/EWA.Ce', 'admin', NULL, NULL, NULL),
(8, 'user', 'user', '$2y$10$9RBcZ6AiztB4NlSIYUS8U.qEsl3Yerj2KeqossufY61HtUtvQS4ea', 'user', NULL, NULL, NULL),
(9, 'Derlich', 'derlich', '$2y$10$2hQO00fx0gk0PYPhRrQrF.wgUZ2I0914POjDGHHU1pQxKuEzFoVUm', 'admin', 'princetherich1@gmail.com', 'ae30ec7f19631befaec82132a19f6a4705ac8393b14854a8879ff97ecfce9817', '2026-03-02 16:17:34'),
(10, 'test', 'test', '$2y$10$nlOaLfb.sKqcTgb11yOyF.VzB00PiNc3paZo01PC9P8totwsl7oTa', 'admin', 'test@gmail.com', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id_fournisseur`),
  ADD UNIQUE KEY `nom_societe` (`nom_societe`);

--
-- Indexes for table `inventaires`
--
ALTER TABLE `inventaires`
  ADD PRIMARY KEY (`id_inventaire`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `inventaire_details`
--
ALTER TABLE `inventaire_details`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_inventaire` (`id_inventaire`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Indexes for table `points_vente`
--
ALTER TABLE `points_vente`
  ADD PRIMARY KEY (`id_point_vente`),
  ADD UNIQUE KEY `nom_point_vente` (`nom_point_vente`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id_categorie`);

--
-- Indexes for table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id_produit`),
  ADD KEY `fk_produits_categories` (`id_categorie`);

--
-- Indexes for table `sorties`
--
ALTER TABLE `sorties`
  ADD PRIMARY KEY (`id_sortie`),
  ADD KEY `id_lot` (`id_lot`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `stock_lots`
--
ALTER TABLE `stock_lots`
  ADD PRIMARY KEY (`id_lot`),
  ADD KEY `id_produit` (`id_produit`),
  ADD KEY `id_fournisseur` (`id_fournisseur`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id_fournisseur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventaires`
--
ALTER TABLE `inventaires`
  MODIFY `id_inventaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventaire_details`
--
ALTER TABLE `inventaire_details`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `points_vente`
--
ALTER TABLE `points_vente`
  MODIFY `id_point_vente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id_categorie` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `sorties`
--
ALTER TABLE `sorties`
  MODIFY `id_sortie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stock_lots`
--
ALTER TABLE `stock_lots`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventaires`
--
ALTER TABLE `inventaires`
  ADD CONSTRAINT `inventaires_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`);

--
-- Constraints for table `inventaire_details`
--
ALTER TABLE `inventaire_details`
  ADD CONSTRAINT `inventaire_details_ibfk_1` FOREIGN KEY (`id_inventaire`) REFERENCES `inventaires` (`id_inventaire`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventaire_details_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

--
-- Constraints for table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `fk_produits_categories` FOREIGN KEY (`id_categorie`) REFERENCES `product_categories` (`id_categorie`) ON UPDATE CASCADE;

--
-- Constraints for table `sorties`
--
ALTER TABLE `sorties`
  ADD CONSTRAINT `sorties_ibfk_1` FOREIGN KEY (`id_lot`) REFERENCES `stock_lots` (`id_lot`) ON DELETE CASCADE,
  ADD CONSTRAINT `sorties_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`);

--
-- Constraints for table `stock_lots`
--
ALTER TABLE `stock_lots`
  ADD CONSTRAINT `stock_lots_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_lots_ibfk_2` FOREIGN KEY (`id_fournisseur`) REFERENCES `fournisseurs` (`id_fournisseur`),
  ADD CONSTRAINT `stock_lots_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `utilisateurs` (`id_user`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

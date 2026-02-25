-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 02:11 PM
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
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id_fournisseur`, `nom_societe`, `contact_nom`, `telephone`, `email`) VALUES
(1, 'BioPharma', 'Prince', '1234567890', 'pr@gmail.com'),
(2, 'Prince', 'Malif', '60744225', 'prince@gmail.com');

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
(5, 'Pharmacie de Consultation externe'),
(2, 'Pharmacie des Urgences'),
(4, 'Pharmacie Principal');

-- --------------------------------------------------------

--
-- Table structure for table `produits`
--

CREATE TABLE `produits` (
  `id_produit` int(11) NOT NULL,
  `nom_medicament` varchar(150) NOT NULL,
  `forme` varchar(50) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `seuil_alerte` int(11) DEFAULT NULL,
  `stock_total` int(11) DEFAULT NULL,
  `marge_pourcentage` decimal(5,2) DEFAULT 20.00,
  `prix_unitaire` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produits`
--

INSERT INTO `produits` (`id_produit`, `nom_medicament`, `forme`, `dosage`, `seuil_alerte`, `stock_total`, `marge_pourcentage`, `prix_unitaire`) VALUES
(4, 'Paracetamol', 'Comprimé', '250mg', 1, 8, 20.00, NULL),
(6, 'Prince', 'Sirop', '1000ml', 5, 15, 20.00, NULL),
(7, 'try', 'try', 'try', 0, 10, 20.00, NULL),
(9, 'try', 'Comprimé', '250mg', 0, 0, 20.00, NULL),
(11, 'two', 'Sirop', '1000ml', 0, 0, 20.00, NULL),
(12, 'one', 'Comprimé', '1000ml', 0, 0, 20.00, NULL),
(13, 'Doliprane', 'Comprimé', '500mg', 0, 0, 20.00, 1000.00),
(14, 'on', 'Sirop', '250mg', 0, 21, 20.00, 100.00);

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
(13, 5, 1, 3, 'Pharmacie Betote', 2, 0.00, 0.00, '2026-02-25 09:56:36', 6),
(14, 9, 1, 4, 'Pharmacie Principal', 5, 30.00, 150.00, '2026-02-25 09:57:44', 6),
(15, 11, 4, 2, 'Pharmacie des Urgences', 5, 1700.00, 8500.00, '2026-02-25 10:00:00', 6),
(16, 11, 1, 1, 'Magasin Central', 5, 1700.00, 8500.00, '2026-02-25 10:09:14', 6),
(17, 16, 1, 5, 'Pharmacie de Consultation externe', 4, 120.00, 480.00, '2026-02-25 11:56:54', 6),
(18, 16, 1, 5, 'Pharmacie de Consultation externe', 4, 120.00, 480.00, '2026-02-25 11:57:37', 6);

-- --------------------------------------------------------

--
-- Table structure for table `stock_lots`
--

CREATE TABLE `stock_lots` (
  `id_lot` int(11) NOT NULL,
  `id_produit` int(11) DEFAULT NULL,
  `id_fournisseur` int(11) DEFAULT NULL,
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

INSERT INTO `stock_lots` (`id_lot`, `id_produit`, `id_fournisseur`, `num_lot`, `quantite_initiale`, `quantite_actuelle`, `date_expiration`, `prix_achat_ht`, `prix_achat_ttc`, `date_enregistrement`, `id_user`, `prix_unitaire`, `total_prix`) VALUES
(5, 4, 1, '123abc', 10, 8, '2026-02-24', NULL, NULL, '2026-02-21 05:03:50', 6, NULL, NULL),
(9, 6, 2, 'pr12', 20, 15, '2026-02-26', NULL, 200.00, '2026-02-21 06:03:05', 6, NULL, NULL),
(11, 6, 2, 'pr12', 5, 0, '2026-02-25', NULL, 1000.00, '2026-02-21 06:07:59', 6, NULL, NULL),
(12, 7, 1, 'try', 10, 10, '2026-02-24', NULL, 189.00, '2026-02-21 06:14:50', 6, NULL, NULL),
(14, 14, 1, '123abc', 18, 18, '2026-02-27', NULL, 100.00, '2026-02-25 11:30:02', 6, NULL, NULL),
(15, 14, 1, '123abc', 1, 1, '2026-03-01', NULL, 150.00, '2026-02-25 11:31:41', 6, NULL, NULL),
(16, 14, 2, '12ab', 10, 2, '2026-02-28', NULL, 100.00, '2026-02-25 11:41:30', 6, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_user` int(11) NOT NULL,
  `nom_complet` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_user`, `nom_complet`, `username`, `password`, `role`) VALUES
(1, 'Admin', 'admin', '$2y$10$mC7G0eWzT.A.9GvG9m.pueS8PzI.p/5p6l5p.X6D6uH5p6l5p6l5p', 'admin'),
(6, 'prince', 'prince', '$2y$10$/68M6VyB7m49tejYaMfVheqkdaSJsS4F5VVVgTvTyUpkT2/EWA.Ce', 'admin'),
(8, 'user', 'user', '$2y$10$9RBcZ6AiztB4NlSIYUS8U.qEsl3Yerj2KeqossufY61HtUtvQS4ea', 'user');

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
-- Indexes for table `points_vente`
--
ALTER TABLE `points_vente`
  ADD PRIMARY KEY (`id_point_vente`),
  ADD UNIQUE KEY `nom_point_vente` (`nom_point_vente`);

--
-- Indexes for table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id_produit`);

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
  MODIFY `id_fournisseur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `points_vente`
--
ALTER TABLE `points_vente`
  MODIFY `id_point_vente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sorties`
--
ALTER TABLE `sorties`
  MODIFY `id_sortie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stock_lots`
--
ALTER TABLE `stock_lots`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

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

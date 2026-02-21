-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 21, 2026 at 07:22 AM
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
-- Table structure for table `produits`
--

CREATE TABLE `produits` (
  `id_produit` int(11) NOT NULL,
  `nom_medicament` varchar(150) NOT NULL,
  `forme` varchar(50) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `seuil_alerte` int(11) DEFAULT NULL,
  `stock_total` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produits`
--

INSERT INTO `produits` (`id_produit`, `nom_medicament`, `forme`, `dosage`, `seuil_alerte`, `stock_total`) VALUES
(3, 'Doliprane', 'Comprimé', '500mg', 0, 15),
(4, 'Paracetamol', 'Comprimé', '250mg', 3, 1),
(5, 'Metronidazole', 'Comprimé', '500mg', 2, 4),
(6, 'Prince', 'Sirop', '1000ml', 1, 33),
(7, 'try', 'try', 'try', 2, 5);

-- --------------------------------------------------------

--
-- Table structure for table `sorties`
--

CREATE TABLE `sorties` (
  `id_sortie` int(11) NOT NULL,
  `id_lot` int(11) DEFAULT NULL,
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

INSERT INTO `sorties` (`id_sortie`, `id_lot`, `nom_point_vente`, `quantite_sortie`, `prix_vente_unitaire`, `total_prix`, `date_sortie`, `id_user`) VALUES
(6, 5, 'pharmacie de urgences', 3, NULL, 0.00, '2026-02-21 05:06:31', 6),
(7, 5, 'pharmacie de urgences', 3, NULL, 0.00, '2026-02-21 05:07:12', 6),
(8, 5, 'pharmacie de urgences', 3, 0.00, 0.00, '2026-02-21 05:07:35', 6),
(9, 8, 'pharmacie de urgences', 6, 119.00, 714.00, '2026-02-21 05:34:02', 6),
(10, 11, 'pharmacie de urgences', 2, 1700.00, 3400.00, '2026-02-21 06:08:36', 6),
(11, 12, 'pharmacie de urgences', 5, 321.30, 1606.50, '2026-02-21 06:16:53', 6);

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
(4, 3, 1, '1a', 15, 15, '2026-02-22', NULL, NULL, '2026-02-21 04:25:28', 6, NULL, NULL),
(5, 4, 1, '123abc', 10, 1, '2026-02-24', NULL, NULL, '2026-02-21 05:03:50', 6, NULL, NULL),
(8, 5, 1, '1a', 10, 4, '2026-02-22', NULL, 70.00, '2026-02-21 05:33:19', 6, NULL, NULL),
(9, 6, 2, 'pr12', 20, 20, '2026-02-26', NULL, 200.00, '2026-02-21 06:03:05', 6, NULL, NULL),
(10, 6, 2, 'p1', 10, 10, '2026-02-18', NULL, 10.00, '2026-02-21 06:06:45', 6, NULL, NULL),
(11, 6, 2, 'pr12', 5, 3, '2026-02-25', NULL, 1000.00, '2026-02-21 06:07:59', 6, NULL, NULL),
(12, 7, 1, 'try', 10, 5, '2026-02-24', NULL, 189.00, '2026-02-21 06:14:50', 6, NULL, NULL);

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
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sorties`
--
ALTER TABLE `sorties`
  MODIFY `id_sortie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `stock_lots`
--
ALTER TABLE `stock_lots`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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

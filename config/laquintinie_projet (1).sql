-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 20, 2026 at 08:26 AM
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
  `id_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  MODIFY `id_fournisseur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sorties`
--
ALTER TABLE `sorties`
  MODIFY `id_sortie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_lots`
--
ALTER TABLE `stock_lots`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT;

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

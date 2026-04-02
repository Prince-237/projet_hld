-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 30, 2026 at 05:09 PM
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
-- Database: `laquintinie_projet_1`
--

-- --------------------------------------------------------

--
-- Table structure for table `commande`
--

CREATE TABLE `commande` (
  `id_commande` int(11) NOT NULL,
  `id_partenaire` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('En attente','Reçue','Annulée') NOT NULL DEFAULT 'En attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Commandes fournisseurs';

--
-- Dumping data for table `commande`
--

INSERT INTO `commande` (`id_commande`, `id_partenaire`, `id_user`, `date_commande`, `statut`) VALUES
(2, 1, 4, '2026-03-21 14:18:11', 'Reçue'),
(5, 2, 4, '2026-03-21 14:36:18', 'Reçue'),
(6, 2, 4, '2026-03-22 09:02:32', 'Reçue'),
(7, 1, 4, '2026-03-28 04:52:15', 'Reçue'),
(8, 1, 4, '2026-03-28 04:55:27', 'Reçue');

-- --------------------------------------------------------

--
-- Table structure for table `commandedetail`
--

CREATE TABLE `commandedetail` (
  `id_cmd_det` int(11) NOT NULL,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite_voulue` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lignes de commande';

-- --------------------------------------------------------

--
-- Table structure for table `inventaire`
--

CREATE TABLE `inventaire` (
  `id_inventaire` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_inventaire` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('en cours','traité') NOT NULL DEFAULT 'en cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sessions inventaire';

-- --------------------------------------------------------

--
-- Table structure for table `inventairedetail`
--

CREATE TABLE `inventairedetail` (
  `id_inv_det` int(11) NOT NULL,
  `id_inventaire` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `stock_theorique` int(11) NOT NULL DEFAULT 0,
  `stock_physique` int(11) NOT NULL DEFAULT 0,
  `ecart` int(11) GENERATED ALWAYS AS (`stock_physique` - `stock_theorique`) STORED,
  `observations` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Détail inventaire par lot';

-- --------------------------------------------------------

--
-- Table structure for table `mouvement`
--

CREATE TABLE `mouvement` (
  `id_mouvement` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_point_vente` int(11) NOT NULL,
  `type_mouvement` enum('Entrée','Sortie','Transfert','perime') NOT NULL,
  `quantite` int(11) NOT NULL CHECK (`quantite` > 0),
  `num_document` varchar(100) DEFAULT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Entrées/sorties stock';

-- --------------------------------------------------------

--
-- Table structure for table `partenaire`
--

CREATE TABLE `partenaire` (
  `id_partenaire` int(11) NOT NULL,
  `nom_entite` varchar(150) NOT NULL,
  `type` enum('Fournisseur','Don') NOT NULL,
  `contact_nom` varchar(150) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fournisseurs et donateurs';

--
-- Dumping data for table `partenaire`
--

INSERT INTO `partenaire` (`id_partenaire`, `nom_entite`, `type`, `contact_nom`, `telephone`, `email`) VALUES
(1, 'Prince@', 'Fournisseur', 'Prince', '690744225', 'prince1@gmail.com'),
(2, 'Donateur1', 'Don', 'Don1', '123', 'don1@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `pointvente`
--

CREATE TABLE `pointvente` (
  `id_point_vente` int(11) NOT NULL,
  `nom_point_vente` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Points de distribution';

--
-- Dumping data for table `pointvente`
--

INSERT INTO `pointvente` (`id_point_vente`, `nom_point_vente`) VALUES
(1, 'Pharmacie Centrale'),
(2, 'Pédiatrie'),
(3, 'Médecine Interne'),
(4, 'Chirurgie');

-- --------------------------------------------------------

--
-- Table structure for table `productcategory`
--

CREATE TABLE `productcategory` (
  `id_categorie` int(11) NOT NULL,
  `nom_categorie` varchar(150) NOT NULL,
  `forme` varchar(100) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catégories médicaments';

--
-- Dumping data for table `productcategory`
--

INSERT INTO `productcategory` (`id_categorie`, `nom_categorie`, `forme`, `dosage`, `description`) VALUES
(1, 'Antibiotiques', 'Comprimé', '500mg', NULL),
(2, 'Antalgiques', 'Comprimé', '1g', NULL),
(3, 'Vitamines', 'Sirop', '10ml', NULL),
(4, 'Urgences', 'Injectable', '2ml', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `produit`
--

CREATE TABLE `produit` (
  `id_produit` int(11) NOT NULL,
  `id_categorie` int(11) NOT NULL,
  `nom_medicament` varchar(150) NOT NULL,
  `type_produit` varchar(100) DEFAULT NULL,
  `seuil_alerte` int(11) NOT NULL DEFAULT 0,
  `prix_unitaire` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marge_pourcentage` decimal(5,2) NOT NULL DEFAULT 20.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catalogue médicaments';

-- --------------------------------------------------------

--
-- Table structure for table `stocklot`
--

CREATE TABLE `stocklot` (
  `id_lot` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `id_cmd_det` int(11) DEFAULT NULL,
  `num_lot` varchar(100) NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `quantite_actuelle` int(11) NOT NULL DEFAULT 0,
  `prix_achat_ttc` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lots de stock';

-- --------------------------------------------------------

--
-- Table structure for table `transfert`
--

CREATE TABLE `transfert` (
  `id_transfert` int(11) NOT NULL,
  `id_source` int(11) NOT NULL,
  `id_destination` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `num_bordereau` varchar(100) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `transfertdetail`
--

CREATE TABLE `transfertdetail` (
  `id_trans_det` int(11) NOT NULL,
  `id_transfert` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite_transfert` int(11) NOT NULL CHECK (`quantite_transfert` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Détail transferts';

-- --------------------------------------------------------

--
-- Table structure for table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_user` int(11) NOT NULL,
  `nom_complet` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Utilisateurs système Hôpital Laquintinie';

--
-- Dumping data for table `utilisateur`
--

INSERT INTO `utilisateur` (`id_user`, `nom_complet`, `username`, `password`, `role`, `email`) VALUES
(1, 'Admin Pharmacie', 'admin', 'admin123', 'admin', 'admin@laquintinie.cm'),
(2, 'Dupont Jean', 'pharmacien1', '6ad14ba9986e3615423dfca256d04e3f', 'user', 'jean.pharmacie@laquintinie.cm'),
(4, 'Derlich', 'derlich', '$2y$10$egfxa2fon9/XyrlmTgkHJ.ZQneqskB6SETV9o9SvMciygybHbi0Yi', 'admin', 'derlich@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `commande`
--
ALTER TABLE `commande`
  ADD PRIMARY KEY (`id_commande`),
  ADD KEY `idx_commande_partenaire` (`id_partenaire`),
  ADD KEY `idx_commande_user` (`id_user`);

--
-- Indexes for table `commandedetail`
--
ALTER TABLE `commandedetail`
  ADD PRIMARY KEY (`id_cmd_det`),
  ADD UNIQUE KEY `uk_cmd_commande_produit` (`id_commande`,`id_produit`),
  ADD KEY `idx_cmd_detail_commande` (`id_commande`),
  ADD KEY `idx_cmd_detail_produit` (`id_produit`);

--
-- Indexes for table `inventaire`
--
ALTER TABLE `inventaire`
  ADD PRIMARY KEY (`id_inventaire`),
  ADD KEY `idx_inventaire_user` (`id_user`);

--
-- Indexes for table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  ADD PRIMARY KEY (`id_inv_det`),
  ADD UNIQUE KEY `uk_inventaire_lot` (`id_inventaire`,`id_lot`),
  ADD KEY `idx_inventaire_detail_inventaire` (`id_inventaire`),
  ADD KEY `idx_inventaire_detail_lot` (`id_lot`);

--
-- Indexes for table `mouvement`
--
ALTER TABLE `mouvement`
  ADD PRIMARY KEY (`id_mouvement`),
  ADD KEY `idx_mouvement_lot` (`id_lot`),
  ADD KEY `idx_mouvement_user` (`id_user`),
  ADD KEY `idx_mouvement_point_vente` (`id_point_vente`);

--
-- Indexes for table `partenaire`
--
ALTER TABLE `partenaire`
  ADD PRIMARY KEY (`id_partenaire`),
  ADD UNIQUE KEY `uk_partenaire_email` (`email`);

--
-- Indexes for table `pointvente`
--
ALTER TABLE `pointvente`
  ADD PRIMARY KEY (`id_point_vente`);

--
-- Indexes for table `productcategory`
--
ALTER TABLE `productcategory`
  ADD PRIMARY KEY (`id_categorie`);

--
-- Indexes for table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id_produit`),
  ADD KEY `idx_produit_categorie` (`id_categorie`);

--
-- Indexes for table `stocklot`
--
ALTER TABLE `stocklot`
  ADD PRIMARY KEY (`id_lot`),
  ADD UNIQUE KEY `uk_stocklot_num_lot` (`num_lot`),
  ADD KEY `idx_stocklot_produit` (`id_produit`),
  ADD KEY `idx_stocklot_cmd_det` (`id_cmd_det`);

--
-- Indexes for table `transfert`
--
ALTER TABLE `transfert`
  ADD PRIMARY KEY (`id_transfert`),
  ADD UNIQUE KEY `uk_transfert_num_bordereau` (`num_bordereau`),
  ADD KEY `idx_transfert_source` (`id_source`),
  ADD KEY `idx_transfert_destination` (`id_destination`),
  ADD KEY `idx_transfert_user` (`id_user`);

--
-- Indexes for table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  ADD PRIMARY KEY (`id_trans_det`),
  ADD UNIQUE KEY `uk_transfert_lot` (`id_transfert`,`id_lot`),
  ADD KEY `idx_transfert_detail_transfert` (`id_transfert`),
  ADD KEY `idx_transfert_detail_lot` (`id_lot`);

--
-- Indexes for table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uk_utilisateur_username` (`username`),
  ADD UNIQUE KEY `uk_utilisateur_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `commande`
--
ALTER TABLE `commande`
  MODIFY `id_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `commandedetail`
--
ALTER TABLE `commandedetail`
  MODIFY `id_cmd_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventaire`
--
ALTER TABLE `inventaire`
  MODIFY `id_inventaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  MODIFY `id_inv_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `mouvement`
--
ALTER TABLE `mouvement`
  MODIFY `id_mouvement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partenaire`
--
ALTER TABLE `partenaire`
  MODIFY `id_partenaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pointvente`
--
ALTER TABLE `pointvente`
  MODIFY `id_point_vente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `productcategory`
--
ALTER TABLE `productcategory`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `produit`
--
ALTER TABLE `produit`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stocklot`
--
ALTER TABLE `stocklot`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transfert`
--
ALTER TABLE `transfert`
  MODIFY `id_transfert` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  MODIFY `id_trans_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `fk_commande_partenaire` FOREIGN KEY (`id_partenaire`) REFERENCES `partenaire` (`id_partenaire`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_commande_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Constraints for table `commandedetail`
--
ALTER TABLE `commandedetail`
  ADD CONSTRAINT `fk_cmd_detail_commande` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cmd_detail_produit` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`) ON UPDATE CASCADE;

--
-- Constraints for table `inventaire`
--
ALTER TABLE `inventaire`
  ADD CONSTRAINT `fk_inventaire_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Constraints for table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  ADD CONSTRAINT `fk_inventaire_detail_inventaire` FOREIGN KEY (`id_inventaire`) REFERENCES `inventaire` (`id_inventaire`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventaire_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE;

--
-- Constraints for table `mouvement`
--
ALTER TABLE `mouvement`
  ADD CONSTRAINT `fk_mouvement_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvement_point_vente` FOREIGN KEY (`id_point_vente`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvement_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Constraints for table `produit`
--
ALTER TABLE `produit`
  ADD CONSTRAINT `fk_produit_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `productcategory` (`id_categorie`) ON UPDATE CASCADE;

--
-- Constraints for table `stocklot`
--
ALTER TABLE `stocklot`
  ADD CONSTRAINT `fk_stocklot_cmd_det` FOREIGN KEY (`id_cmd_det`) REFERENCES `commandedetail` (`id_cmd_det`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stocklot_produit` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`) ON UPDATE CASCADE;

--
-- Constraints for table `transfert`
--
ALTER TABLE `transfert`
  ADD CONSTRAINT `fk_transfert_destination` FOREIGN KEY (`id_destination`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_source` FOREIGN KEY (`id_source`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Constraints for table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  ADD CONSTRAINT `fk_transfert_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_detail_transfert` FOREIGN KEY (`id_transfert`) REFERENCES `transfert` (`id_transfert`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

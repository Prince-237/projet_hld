-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 03:52 PM
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
CREATE DATABASE IF NOT EXISTS laquintinie_projet_1
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laquintinie_projet_1;
-- --------------------------------------------------------

--
-- Table structure for table `commande`
--

CREATE TABLE `commande` (
  `id_commande` int(11) NOT NULL,
  `id_partenaire` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('En attente','Reçue','Annulée') NOT NULL DEFAULT 'En attente',
  `statut_paiement` enum('du','payé','partielle','soldé') NOT NULL DEFAULT 'du',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Commandes fournisseurs';

--
-- Dumping data for table `commande`
--

INSERT INTO `commande` (`id_commande`, `id_partenaire`, `id_user`, `date_commande`, `statut`, `statut_paiement`, `deleted_at`) VALUES
(2, 1, 4, '2026-03-21 14:18:11', 'Reçue', 'du', NULL),
(5, 2, 4, '2026-03-21 14:36:18', 'Reçue', 'du', NULL),
(6, 2, 4, '2026-03-22 09:02:32', 'Reçue', 'du', NULL),
(7, 1, 4, '2026-03-28 04:52:15', 'Reçue', 'du', NULL),
(8, 1, 4, '2026-03-28 04:55:27', 'Reçue', 'du', NULL),
(9, 1, 4, '2026-04-01 10:10:11', 'Reçue', 'du', '2026-04-02 02:51:14'),
(10, 3, 4, '2026-04-01 10:19:27', 'Reçue', 'du', NULL),
(12, 1, 4, '2026-04-01 11:37:02', 'En attente', 'du', NULL),
(13, 1, 4, '2026-04-01 11:37:14', 'Reçue', 'du', NULL),
(19, 1, 4, '2026-04-01 16:04:33', 'Reçue', 'du', NULL),
(20, 1, 4, '2026-04-02 01:20:20', 'Reçue', 'du', NULL),
(25, 2, 4, '2026-04-02 08:51:46', 'Reçue', 'du', NULL),
(37, 3, 4, '2026-04-02 14:28:22', 'Reçue', 'du', NULL),
(39, 3, 4, '2026-04-02 15:23:59', 'Reçue', 'du', NULL),
(42, 1, 4, '2026-04-03 05:10:42', 'En attente', 'du', NULL),
(43, 1, 4, '2026-04-05 06:53:36', 'En attente', 'du', '2026-04-13 15:24:21'),
(44, 2, 4, '2026-04-05 07:42:55', 'Reçue', 'du', NULL),
(45, 2, 4, '2026-04-05 08:14:02', 'Reçue', 'du', NULL),
(46, 1, 4, '2026-04-05 11:34:39', 'Reçue', 'du', NULL),
(48, 3, 4, '2026-04-08 18:02:35', 'Reçue', 'partielle', NULL),
(49, 2, 4, '2026-04-08 18:04:02', 'Reçue', 'du', NULL),
(50, 3, 4, '2026-04-09 13:50:51', 'Reçue', 'du', NULL),
(54, 1, 4, '2026-04-09 18:06:24', '', 'du', NULL),
(55, 1, 4, '2026-04-09 18:08:19', '', 'du', NULL);

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

--
-- Dumping data for table `commandedetail`
--

INSERT INTO `commandedetail` (`id_cmd_det`, `id_commande`, `id_produit`, `quantite_voulue`) VALUES
(9, 9, 12, 344),
(10, 10, 12, 234),
(12, 12, 14, 245),
(13, 13, 14, 245),
(19, 19, 12, 1),
(20, 19, 13, 12),
(21, 20, 12, 3),
(22, 20, 13, 5),
(30, 25, 12, 400),
(43, 37, 16, 350),
(44, 37, 13, 50),
(47, 39, 12, 190),
(48, 39, 13, 490),
(54, 42, 13, 112),
(55, 42, 12, 112),
(56, 42, 15, 112),
(57, 43, 15, 28),
(58, 43, 13, 28),
(59, 43, 12, 28),
(60, 44, 15, 190),
(61, 44, 13, 111),
(62, 45, 14, 19),
(63, 45, 16, 1),
(64, 46, 12, 400),
(65, 46, 15, 400),
(69, 48, 16, 16),
(70, 48, 12, 18),
(71, 49, 12, 677),
(72, 50, 17, 625),
(73, 50, 16, 625),
(74, 54, 12, 2662),
(75, 54, 15, 180),
(76, 54, 14, 98),
(77, 55, 12, 2662),
(78, 55, 15, 180),
(79, 55, 14, 98);

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
(2, 'Donateur1', 'Don', 'Don1', '123', 'don1@gmail.com'),
(3, 'qwert', 'Fournisseur', 'qwerty', '1234', 'ggffxg@gmail.com');

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

--
-- Dumping data for table `produit`
--

INSERT INTO `produit` (`id_produit`, `id_categorie`, `nom_medicament`, `type_produit`, `seuil_alerte`, `prix_unitaire`, `marge_pourcentage`) VALUES
(12, 4, 'jhych', 'Medicament', 4846, 545.00, 20.00),
(13, 1, 'test', 'Medicament', 222, 111.00, 20.00),
(14, 1, 'qqqqqq', 'Laboratoire', 341, 1333.00, 20.00),
(15, 4, 'one', 'Medicament', 742, 244.00, 20.00),
(16, 3, 'two', 'Medicament', 293, 500.00, 20.00),
(17, 4, 'thte', 'Medicament', 266, 266.00, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `retourfournisseur`
--

CREATE TABLE `retourfournisseur` (
  `id_retour` int(11) NOT NULL,
  `id_commande` int(11) NOT NULL,
  `date_retour` datetime NOT NULL DEFAULT current_timestamp(),
  `commentaire` text NOT NULL,
  `statut` enum('en attente','accepté','rejeté') DEFAULT 'en attente',
  `id_user` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Retours fournisseur - Header';

--
-- Dumping data for table `retourfournisseur`
--

INSERT INTO `retourfournisseur` (`id_retour`, `id_commande`, `date_retour`, `commentaire`, `statut`, `id_user`) VALUES
(1, 46, '2026-04-06 00:30:45', 'test', 'en attente', 4),
(2, 46, '2026-04-06 00:32:12', 'test', 'en attente', 4),
(3, 46, '2026-04-06 00:32:15', 'test', 'en attente', 4),
(4, 46, '2026-04-06 00:32:42', 'test', 'en attente', 4),
(5, 46, '2026-04-06 00:33:55', 'test', 'en attente', 4),
(6, 39, '2026-04-07 17:18:33', 'test', 'en attente', 4),
(7, 46, '2026-04-08 09:14:46', 'test', 'en attente', 4),
(8, 46, '2026-04-08 09:20:02', 'test', 'accepté', 4),
(9, 39, '2026-04-08 18:00:17', 'qwer', 'en attente', 4);

-- --------------------------------------------------------

--
-- Table structure for table `retourfournisseurdetail`
--

CREATE TABLE `retourfournisseurdetail` (
  `id_retour_detail` int(11) NOT NULL,
  `id_retour` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite_retournee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Détails des retours - Lots';

--
-- Dumping data for table `retourfournisseurdetail`
--

INSERT INTO `retourfournisseurdetail` (`id_retour_detail`, `id_retour`, `id_lot`, `quantite_retournee`) VALUES
(1, 1, 26, 20),
(2, 1, 27, 10),
(3, 2, 26, 20),
(4, 2, 27, 10),
(5, 3, 26, 20),
(6, 3, 27, 10),
(7, 4, 26, 20),
(8, 4, 27, 10),
(9, 5, 26, 20),
(10, 5, 27, 10),
(11, 6, 20, 5),
(12, 6, 21, 5),
(13, 7, 26, 18),
(14, 7, 27, 21),
(15, 8, 26, 18),
(16, 8, 27, 21),
(17, 9, 20, 20),
(18, 9, 21, 10);

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

--
-- Dumping data for table `stocklot`
--

INSERT INTO `stocklot` (`id_lot`, `id_produit`, `id_cmd_det`, `num_lot`, `date_expiration`, `quantite_actuelle`, `prix_achat_ttc`) VALUES
(8, 12, 9, '123abc', '2026-04-15', 238, 545.00),
(9, 12, 10, 'zxc', '2026-04-10', 222, 545.00),
(10, 14, 13, '12ab', '2026-04-16', 225, 1333.00),
(11, 12, 19, 'yevyu', '2026-04-30', 1, 545.00),
(12, 13, 20, 'eqkbviu', '2026-05-07', 8, 111.00),
(14, 12, 21, 'fv3', '2026-04-22', 3, 545.00),
(15, 13, 22, 'vrv', '2026-05-05', 3, 111.00),
(17, 12, 30, 'few255', '2026-05-09', 411, 0.00),
(18, 13, 44, 'vegdb', '2026-05-07', 50, 111.00),
(19, 16, 43, 'ave', '2026-04-21', 350, 500.00),
(20, 12, 47, 'jnlj', '2026-04-23', 189, 545.00),
(21, 13, 48, 'bjbuoi', '2026-05-05', 490, 111.00),
(22, 15, 60, '5cvc56', '2026-04-30', 191, 0.00),
(23, 13, 61, 'd13vsfb', '2026-04-28', 111, 0.00),
(24, 14, 62, 'aebhk68', '2026-04-29', 19, 0.00),
(25, 16, 63, '5465vgghcv', '2026-05-09', 1, 0.00),
(26, 12, 64, 'kkkkkk456', '2026-04-06', 362, 545.00),
(27, 15, 65, 'ln552', '2026-04-21', 342, 244.00),
(28, 12, 70, 'qsxsx', '2026-04-23', 18, 545.00),
(29, 16, 69, 'xsw', '2026-04-30', 16, 500.00),
(30, 12, 71, 'dwcd', '2026-04-17', 677, 0.00),
(31, 16, 73, 'wwfs', '2026-04-21', 625, 500.00),
(32, 17, 72, 'fshfs', '2026-04-20', 625, 266.00);

-- --------------------------------------------------------

--
-- Table structure for table `transfert`
--

CREATE TABLE `transfert` (
  `id_transfert` int(11) NOT NULL,
  `id_source` int(11) NOT NULL,
  `id_destination` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `num_bordereau` varchar(100) NOT NULL,
  `date_transfert` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('Recue','Envoyé') NOT NULL DEFAULT 'Envoyé'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transfert`
--

INSERT INTO `transfert` (`id_transfert`, `id_source`, `id_destination`, `id_user`, `num_bordereau`, `date_transfert`, `statut`) VALUES
(6, 1, 2, 4, 'TR-20260402151133-387', '2026-04-13 14:56:22', 'Envoyé'),
(7, 1, 4, 4, 'TR-20260402152334-847', '2026-04-13 14:56:22', 'Envoyé'),
(8, 1, 3, 4, 'TR-20260405081744-992', '2026-04-13 14:56:22', 'Envoyé'),
(9, 4, 3, 4, 'TR-20260405085509-505', '2026-04-13 14:56:22', 'Envoyé'),
(10, 1, 3, 4, 'TR-20260407173023-729', '2026-04-13 14:56:22', 'Envoyé'),
(11, 3, 2, 4, 'TR-20260408092632-516', '2026-04-13 14:56:22', 'Envoyé'),
(12, 1, 3, 4, 'TR-20260410085741-122', '2026-04-13 14:56:22', 'Envoyé'),
(13, 3, 2, 4, 'TR-20260410120338-980', '2026-04-13 14:56:22', 'Envoyé');

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

--
-- Dumping data for table `transfertdetail`
--

INSERT INTO `transfertdetail` (`id_trans_det`, `id_transfert`, `id_lot`, `quantite_transfert`) VALUES
(8, 6, 8, 10),
(9, 6, 15, 2),
(10, 7, 9, 6),
(11, 7, 8, 5),
(12, 7, 12, 4),
(13, 8, 8, 30),
(14, 8, 10, 20),
(15, 9, 17, 40),
(16, 9, 23, 16),
(17, 10, 8, 23),
(18, 10, 27, 28),
(19, 11, 26, 25),
(20, 11, 21, 32),
(21, 12, 26, 28),
(22, 12, 8, 36),
(23, 12, 27, 30),
(24, 13, 32, 1),
(25, 13, 19, 1);

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
(2, 'Dupont Jean', 'pharmacien1', '6ad14ba9986e3615423dfca256d04e3f', 'user', 'jean.pharmacie@laquintinie.cm'),
(4, 'Derlich', 'derlich', '$2y$10$egfxa2fon9/XyrlmTgkHJ.ZQneqskB6SETV9o9SvMciygybHbi0Yi', 'admin', 'derlich@gmail.com'),
(5, 'userr', 'userr', '$2y$10$F8L.7ubQMSf2odSDfznWHufT7OpvxEMufKgVRaAAqCRCGXHkh13qK', 'user', 'user12@gmail.com'),
(6, 'userrr', 'userrr', '$2y$10$a7lpjg8KIPj55muSkkg9qeraYX44yBr/0EvO82xhHflpjRU/cOe3G', 'user', 'userrr@gmail.com');

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
-- Indexes for table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  ADD PRIMARY KEY (`id_retour`),
  ADD KEY `idx_retour_commande` (`id_commande`),
  ADD KEY `idx_retour_user` (`id_user`);

--
-- Indexes for table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  ADD PRIMARY KEY (`id_retour_detail`),
  ADD KEY `idx_retour_detail_retour` (`id_retour`),
  ADD KEY `idx_retour_detail_lot` (`id_lot`);

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
  MODIFY `id_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `commandedetail`
--
ALTER TABLE `commandedetail`
  MODIFY `id_cmd_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `inventaire`
--
ALTER TABLE `inventaire`
  MODIFY `id_inventaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  MODIFY `id_inv_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `mouvement`
--
ALTER TABLE `mouvement`
  MODIFY `id_mouvement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partenaire`
--
ALTER TABLE `partenaire`
  MODIFY `id_partenaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  MODIFY `id_retour` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  MODIFY `id_retour_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stocklot`
--
ALTER TABLE `stocklot`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `transfert`
--
ALTER TABLE `transfert`
  MODIFY `id_transfert` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  MODIFY `id_trans_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- Constraints for table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  ADD CONSTRAINT `fk_retour_commande` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retour_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Constraints for table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  ADD CONSTRAINT `fk_retour_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retour_detail_retour` FOREIGN KEY (`id_retour`) REFERENCES `retourfournisseur` (`id_retour`) ON DELETE CASCADE ON UPDATE CASCADE;

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

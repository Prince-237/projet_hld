-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 06 avr. 2026 à 01:49
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `laquintinie_projet_1`
--
CREATE DATABASE IF NOT EXISTS laquintinie_projet_1
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laquintinie_projet_1;
-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

CREATE TABLE `commande` (
  `id_commande` int(11) NOT NULL,
  `id_partenaire` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('En attente','Reçue','Annulée') NOT NULL DEFAULT 'En attente',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Commandes fournisseurs';

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`id_commande`, `id_partenaire`, `id_user`, `date_commande`, `statut`, `deleted_at`) VALUES
(2, 1, 4, '2026-03-21 14:18:11', 'Reçue', NULL),
(5, 2, 4, '2026-03-21 14:36:18', 'Reçue', NULL),
(6, 2, 4, '2026-03-22 09:02:32', 'Reçue', NULL),
(7, 1, 4, '2026-03-28 04:52:15', 'Reçue', NULL),
(8, 1, 4, '2026-03-28 04:55:27', 'Reçue', NULL),
(9, 1, 4, '2026-04-01 10:10:11', 'Reçue', '2026-04-02 02:51:14'),
(10, 3, 4, '2026-04-01 10:19:27', 'Reçue', NULL),
(12, 1, 4, '2026-04-01 11:37:02', 'En attente', NULL),
(13, 1, 4, '2026-04-01 11:37:14', 'Reçue', NULL),
(19, 1, 4, '2026-04-01 16:04:33', 'Reçue', NULL),
(20, 1, 4, '2026-04-02 01:20:20', 'Reçue', NULL),
(25, 2, 4, '2026-04-02 08:51:46', 'Reçue', NULL),
(37, 3, 4, '2026-04-02 14:28:22', 'Reçue', NULL),
(39, 3, 4, '2026-04-02 15:23:59', 'Reçue', NULL),
(42, 1, 4, '2026-04-03 05:10:42', 'En attente', NULL),
(43, 1, 4, '2026-04-05 06:53:36', 'En attente', NULL),
(44, 2, 4, '2026-04-05 07:42:55', 'Reçue', NULL),
(45, 2, 4, '2026-04-05 08:14:02', 'Reçue', NULL),
(46, 1, 4, '2026-04-05 11:34:39', 'Reçue', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commandedetail`
--

CREATE TABLE `commandedetail` (
  `id_cmd_det` int(11) NOT NULL,
  `id_commande` int(11) NOT NULL,
  `id_produit` int(11) NOT NULL,
  `quantite_voulue` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lignes de commande';

--
-- Déchargement des données de la table `commandedetail`
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
(65, 46, 15, 400);

-- --------------------------------------------------------

--
-- Structure de la table `inventaire`
--

CREATE TABLE `inventaire` (
  `id_inventaire` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `date_inventaire` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` enum('en cours','traité') NOT NULL DEFAULT 'en cours'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sessions inventaire';

-- --------------------------------------------------------

--
-- Structure de la table `inventairedetail`
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
-- Structure de la table `mouvement`
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
-- Structure de la table `partenaire`
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
-- Déchargement des données de la table `partenaire`
--

INSERT INTO `partenaire` (`id_partenaire`, `nom_entite`, `type`, `contact_nom`, `telephone`, `email`) VALUES
(1, 'Prince@', 'Fournisseur', 'Prince', '690744225', 'prince1@gmail.com'),
(2, 'Donateur1', 'Don', 'Don1', '123', 'don1@gmail.com'),
(3, 'qwert', 'Fournisseur', 'qwerty', '1234', 'ggffxg@gmail.com');

-- --------------------------------------------------------

--
-- Structure de la table `pointvente`
--

CREATE TABLE `pointvente` (
  `id_point_vente` int(11) NOT NULL,
  `nom_point_vente` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Points de distribution';

--
-- Déchargement des données de la table `pointvente`
--

INSERT INTO `pointvente` (`id_point_vente`, `nom_point_vente`) VALUES
(1, 'Pharmacie Centrale'),
(2, 'Pédiatrie'),
(3, 'Médecine Interne'),
(4, 'Chirurgie');

-- --------------------------------------------------------

--
-- Structure de la table `productcategory`
--

CREATE TABLE `productcategory` (
  `id_categorie` int(11) NOT NULL,
  `nom_categorie` varchar(150) NOT NULL,
  `forme` varchar(100) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catégories médicaments';

--
-- Déchargement des données de la table `productcategory`
--

INSERT INTO `productcategory` (`id_categorie`, `nom_categorie`, `forme`, `dosage`, `description`) VALUES
(1, 'Antibiotiques', 'Comprimé', '500mg', NULL),
(2, 'Antalgiques', 'Comprimé', '1g', NULL),
(3, 'Vitamines', 'Sirop', '10ml', NULL),
(4, 'Urgences', 'Injectable', '2ml', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `produit`
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
-- Déchargement des données de la table `produit`
--

INSERT INTO `produit` (`id_produit`, `id_categorie`, `nom_medicament`, `type_produit`, `seuil_alerte`, `prix_unitaire`, `marge_pourcentage`) VALUES
(12, 4, 'jhych', 'Medicament', 4846, 545.00, 20.00),
(13, 1, 'test', 'Medicament', 222, 111.00, 20.00),
(14, 1, 'qqqqqq', 'Laboratoire', 341, 1333.00, 20.00),
(15, 4, 'one', 'Medicament', 742, 244.00, 20.00),
(16, 3, 'two', 'Medicament', 293, 500.00, 20.00);

-- --------------------------------------------------------

--
-- Structure de la table `retourfournisseur`
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
-- Déchargement des données de la table `retourfournisseur`
--

INSERT INTO `retourfournisseur` (`id_retour`, `id_commande`, `date_retour`, `commentaire`, `statut`, `id_user`) VALUES
(1, 46, '2026-04-06 00:30:45', 'test', 'en attente', 4),
(2, 46, '2026-04-06 00:32:12', 'test', 'en attente', 4),
(3, 46, '2026-04-06 00:32:15', 'test', 'en attente', 4),
(4, 46, '2026-04-06 00:32:42', 'test', 'en attente', 4),
(5, 46, '2026-04-06 00:33:55', 'test', 'en attente', 4);

-- --------------------------------------------------------

--
-- Structure de la table `retourfournisseurdetail`
--

CREATE TABLE `retourfournisseurdetail` (
  `id_retour_detail` int(11) NOT NULL,
  `id_retour` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite_retournee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Détails des retours - Lots';

--
-- Déchargement des données de la table `retourfournisseurdetail`
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
(10, 5, 27, 10);

-- --------------------------------------------------------

--
-- Structure de la table `stocklot`
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
-- Déchargement des données de la table `stocklot`
--

INSERT INTO `stocklot` (`id_lot`, `id_produit`, `id_cmd_det`, `num_lot`, `date_expiration`, `quantite_actuelle`, `prix_achat_ttc`) VALUES
(8, 12, 9, '123abc', '2026-04-15', 299, 545.00),
(9, 12, 10, 'zxc', '2026-04-10', 228, 545.00),
(10, 14, 13, '12ab', '2026-04-16', 225, 1333.00),
(11, 12, 19, 'yevyu', '2026-04-30', 1, 545.00),
(12, 13, 20, 'eqkbviu', '2026-05-07', 8, 111.00),
(14, 12, 21, 'fv3', '2026-04-22', 3, 545.00),
(15, 13, 22, 'vrv', '2026-05-05', 3, 111.00),
(17, 12, 30, 'few255', '2026-05-09', 400, 0.00),
(18, 13, 44, 'vegdb', '2026-05-07', 50, 111.00),
(19, 16, 43, 'ave', '2026-04-21', 350, 500.00),
(20, 12, 47, 'jnlj', '2026-04-23', 190, 545.00),
(21, 13, 48, 'bjbuoi', '2026-05-05', 490, 111.00),
(22, 15, 60, '5cvc56', '2026-04-30', 190, 0.00),
(23, 13, 61, 'd13vsfb', '2026-04-28', 111, 0.00),
(24, 14, 62, 'aebhk68', '2026-04-29', 19, 0.00),
(25, 16, 63, '5465vgghcv', '2026-05-09', 1, 0.00),
(26, 12, 64, 'kkkkkk456', '2026-04-06', 400, 545.00),
(27, 15, 65, 'ln552', '2026-04-21', 400, 244.00);

-- --------------------------------------------------------

--
-- Structure de la table `transfert`
--

CREATE TABLE `transfert` (
  `id_transfert` int(11) NOT NULL,
  `id_source` int(11) NOT NULL,
  `id_destination` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `num_bordereau` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transfert`
--

INSERT INTO `transfert` (`id_transfert`, `id_source`, `id_destination`, `id_user`, `num_bordereau`) VALUES
(6, 1, 2, 4, 'TR-20260402151133-387'),
(7, 1, 4, 4, 'TR-20260402152334-847'),
(8, 1, 3, 4, 'TR-20260405081744-992'),
(9, 4, 3, 4, 'TR-20260405085509-505');

-- --------------------------------------------------------

--
-- Structure de la table `transfertdetail`
--

CREATE TABLE `transfertdetail` (
  `id_trans_det` int(11) NOT NULL,
  `id_transfert` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite_transfert` int(11) NOT NULL CHECK (`quantite_transfert` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Détail transferts';

--
-- Déchargement des données de la table `transfertdetail`
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
(16, 9, 23, 16);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
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
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id_user`, `nom_complet`, `username`, `password`, `role`, `email`) VALUES
(2, 'Dupont Jean', 'pharmacien1', '6ad14ba9986e3615423dfca256d04e3f', 'user', 'jean.pharmacie@laquintinie.cm'),
(4, 'Derlich', 'derlich', '$2y$10$egfxa2fon9/XyrlmTgkHJ.ZQneqskB6SETV9o9SvMciygybHbi0Yi', 'admin', 'derlich@gmail.com');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `commande`
--
ALTER TABLE `commande`
  ADD PRIMARY KEY (`id_commande`),
  ADD KEY `idx_commande_partenaire` (`id_partenaire`),
  ADD KEY `idx_commande_user` (`id_user`);

--
-- Index pour la table `commandedetail`
--
ALTER TABLE `commandedetail`
  ADD PRIMARY KEY (`id_cmd_det`),
  ADD UNIQUE KEY `uk_cmd_commande_produit` (`id_commande`,`id_produit`),
  ADD KEY `idx_cmd_detail_commande` (`id_commande`),
  ADD KEY `idx_cmd_detail_produit` (`id_produit`);

--
-- Index pour la table `inventaire`
--
ALTER TABLE `inventaire`
  ADD PRIMARY KEY (`id_inventaire`),
  ADD KEY `idx_inventaire_user` (`id_user`);

--
-- Index pour la table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  ADD PRIMARY KEY (`id_inv_det`),
  ADD UNIQUE KEY `uk_inventaire_lot` (`id_inventaire`,`id_lot`),
  ADD KEY `idx_inventaire_detail_inventaire` (`id_inventaire`),
  ADD KEY `idx_inventaire_detail_lot` (`id_lot`);

--
-- Index pour la table `mouvement`
--
ALTER TABLE `mouvement`
  ADD PRIMARY KEY (`id_mouvement`),
  ADD KEY `idx_mouvement_lot` (`id_lot`),
  ADD KEY `idx_mouvement_user` (`id_user`),
  ADD KEY `idx_mouvement_point_vente` (`id_point_vente`);

--
-- Index pour la table `partenaire`
--
ALTER TABLE `partenaire`
  ADD PRIMARY KEY (`id_partenaire`),
  ADD UNIQUE KEY `uk_partenaire_email` (`email`);

--
-- Index pour la table `pointvente`
--
ALTER TABLE `pointvente`
  ADD PRIMARY KEY (`id_point_vente`);

--
-- Index pour la table `productcategory`
--
ALTER TABLE `productcategory`
  ADD PRIMARY KEY (`id_categorie`);

--
-- Index pour la table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id_produit`),
  ADD KEY `idx_produit_categorie` (`id_categorie`);

--
-- Index pour la table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  ADD PRIMARY KEY (`id_retour`),
  ADD KEY `idx_retour_commande` (`id_commande`),
  ADD KEY `idx_retour_user` (`id_user`);

--
-- Index pour la table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  ADD PRIMARY KEY (`id_retour_detail`),
  ADD KEY `idx_retour_detail_retour` (`id_retour`),
  ADD KEY `idx_retour_detail_lot` (`id_lot`);

--
-- Index pour la table `stocklot`
--
ALTER TABLE `stocklot`
  ADD PRIMARY KEY (`id_lot`),
  ADD UNIQUE KEY `uk_stocklot_num_lot` (`num_lot`),
  ADD KEY `idx_stocklot_produit` (`id_produit`),
  ADD KEY `idx_stocklot_cmd_det` (`id_cmd_det`);

--
-- Index pour la table `transfert`
--
ALTER TABLE `transfert`
  ADD PRIMARY KEY (`id_transfert`),
  ADD UNIQUE KEY `uk_transfert_num_bordereau` (`num_bordereau`),
  ADD KEY `idx_transfert_source` (`id_source`),
  ADD KEY `idx_transfert_destination` (`id_destination`),
  ADD KEY `idx_transfert_user` (`id_user`);

--
-- Index pour la table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  ADD PRIMARY KEY (`id_trans_det`),
  ADD UNIQUE KEY `uk_transfert_lot` (`id_transfert`,`id_lot`),
  ADD KEY `idx_transfert_detail_transfert` (`id_transfert`),
  ADD KEY `idx_transfert_detail_lot` (`id_lot`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uk_utilisateur_username` (`username`),
  ADD UNIQUE KEY `uk_utilisateur_email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `commande`
--
ALTER TABLE `commande`
  MODIFY `id_commande` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT pour la table `commandedetail`
--
ALTER TABLE `commandedetail`
  MODIFY `id_cmd_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT pour la table `inventaire`
--
ALTER TABLE `inventaire`
  MODIFY `id_inventaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  MODIFY `id_inv_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `mouvement`
--
ALTER TABLE `mouvement`
  MODIFY `id_mouvement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `partenaire`
--
ALTER TABLE `partenaire`
  MODIFY `id_partenaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `pointvente`
--
ALTER TABLE `pointvente`
  MODIFY `id_point_vente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `productcategory`
--
ALTER TABLE `productcategory`
  MODIFY `id_categorie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `produit`
--
ALTER TABLE `produit`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  MODIFY `id_retour` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  MODIFY `id_retour_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `stocklot`
--
ALTER TABLE `stocklot`
  MODIFY `id_lot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `transfert`
--
ALTER TABLE `transfert`
  MODIFY `id_transfert` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  MODIFY `id_trans_det` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `fk_commande_partenaire` FOREIGN KEY (`id_partenaire`) REFERENCES `partenaire` (`id_partenaire`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_commande_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `commandedetail`
--
ALTER TABLE `commandedetail`
  ADD CONSTRAINT `fk_cmd_detail_commande` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cmd_detail_produit` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `inventaire`
--
ALTER TABLE `inventaire`
  ADD CONSTRAINT `fk_inventaire_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `inventairedetail`
--
ALTER TABLE `inventairedetail`
  ADD CONSTRAINT `fk_inventaire_detail_inventaire` FOREIGN KEY (`id_inventaire`) REFERENCES `inventaire` (`id_inventaire`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventaire_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `mouvement`
--
ALTER TABLE `mouvement`
  ADD CONSTRAINT `fk_mouvement_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvement_point_vente` FOREIGN KEY (`id_point_vente`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvement_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `produit`
--
ALTER TABLE `produit`
  ADD CONSTRAINT `fk_produit_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `productcategory` (`id_categorie`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `retourfournisseur`
--
ALTER TABLE `retourfournisseur`
  ADD CONSTRAINT `fk_retour_commande` FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retour_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `retourfournisseurdetail`
--
ALTER TABLE `retourfournisseurdetail`
  ADD CONSTRAINT `fk_retour_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retour_detail_retour` FOREIGN KEY (`id_retour`) REFERENCES `retourfournisseur` (`id_retour`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `stocklot`
--
ALTER TABLE `stocklot`
  ADD CONSTRAINT `fk_stocklot_cmd_det` FOREIGN KEY (`id_cmd_det`) REFERENCES `commandedetail` (`id_cmd_det`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stocklot_produit` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `transfert`
--
ALTER TABLE `transfert`
  ADD CONSTRAINT `fk_transfert_destination` FOREIGN KEY (`id_destination`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_source` FOREIGN KEY (`id_source`) REFERENCES `pointvente` (`id_point_vente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_user` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id_user`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `transfertdetail`
--
ALTER TABLE `transfertdetail`
  ADD CONSTRAINT `fk_transfert_detail_lot` FOREIGN KEY (`id_lot`) REFERENCES `stocklot` (`id_lot`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfert_detail_transfert` FOREIGN KEY (`id_transfert`) REFERENCES `transfert` (`id_transfert`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

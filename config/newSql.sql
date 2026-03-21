SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- CRÉATION DE LA BASE DE DONNÉES
-- =====================================================
CREATE DATABASE IF NOT EXISTS laquintinie_projet_1
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laquintinie_projet_1;

-- Nettoyage complet des tables existantes
DROP TABLE IF EXISTS TransfertDetail;
DROP TABLE IF EXISTS Transfert;
DROP TABLE IF EXISTS Mouvement;
DROP TABLE IF EXISTS InventaireDetail;
DROP TABLE IF EXISTS Inventaire;
DROP TABLE IF EXISTS StockLot;
DROP TABLE IF EXISTS CommandeDetail;
DROP TABLE IF EXISTS Commande;
DROP TABLE IF EXISTS Produit;
DROP TABLE IF EXISTS ProductCategory;
DROP TABLE IF EXISTS PointVente;
DROP TABLE IF EXISTS Utilisateur;
DROP TABLE IF EXISTS Partenaire;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- 1) UTILISATEUR
-- =========================
CREATE TABLE Utilisateur (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nom_complet VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    email VARCHAR(255),
    reset_token_hash VARCHAR(64),
    reset_token_expires_at DATETIME,
    UNIQUE KEY uk_utilisateur_username (username),
    UNIQUE KEY uk_utilisateur_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Utilisateurs système Hôpital Laquintinie';

-- =========================
-- 2) PARTENAIRE (Fournisseur/Donateur)
-- =========================
CREATE TABLE Partenaire (
    id_partenaire INT AUTO_INCREMENT PRIMARY KEY,
    nom_entite VARCHAR(150) NOT NULL,
    type ENUM('Fournisseur', 'Don') NOT NULL,
    contact_nom VARCHAR(150),
    telephone VARCHAR(30),
    email VARCHAR(255),
    UNIQUE KEY uk_partenaire_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Fournisseurs et donateurs';

-- =========================
-- 3) CATÉGORIES PRODUITS
-- =========================
CREATE TABLE ProductCategory (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(150) NOT NULL,
    forme VARCHAR(100),
    dosage VARCHAR(100),
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catégories médicaments';

-- =========================
-- 4) POINT DE VENTE (Pharmacie, Services...)
-- =========================
CREATE TABLE PointVente (
    id_point_vente INT AUTO_INCREMENT PRIMARY KEY,
    nom_point_vente VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Points de distribution';

-- =========================
-- 5) PRODUIT
-- =========================
CREATE TABLE Produit (
    id_produit INT AUTO_INCREMENT PRIMARY KEY,
    id_categorie INT NOT NULL,
    nom_medicament VARCHAR(150) NOT NULL,
    type_produit VARCHAR(100),
    seuil_alerte INT NOT NULL DEFAULT 0,
    marge_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    prix_unitaire DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    KEY idx_produit_categorie (id_categorie),
    CONSTRAINT fk_produit_categorie
        FOREIGN KEY (id_categorie)
        REFERENCES ProductCategory(id_categorie)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catalogue médicaments';

-- =========================
-- 6) COMMANDE
-- =========================
CREATE TABLE Commande (
    id_commande INT AUTO_INCREMENT PRIMARY KEY,
    id_partenaire INT NOT NULL,
    id_user INT NOT NULL,
    date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('En attente', 'Reçue', 'Annulée') NOT NULL DEFAULT 'En attente',
    KEY idx_commande_partenaire (id_partenaire),
    KEY idx_commande_user (id_user),
    CONSTRAINT fk_commande_partenaire
        FOREIGN KEY (id_partenaire)
        REFERENCES Partenaire(id_partenaire)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_commande_user
        FOREIGN KEY (id_user)
        REFERENCES Utilisateur(id_user)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Commandes fournisseurs';

-- =========================
-- 7) COMMANDE DETAIL
-- =========================
CREATE TABLE CommandeDetail (
    id_cmd_det INT AUTO_INCREMENT PRIMARY KEY,
    id_commande INT NOT NULL,
    id_produit INT NOT NULL,
    quantite_voulue INT NOT NULL,
    KEY idx_cmd_detail_commande (id_commande),
    KEY idx_cmd_detail_produit (id_produit),
    UNIQUE KEY uk_cmd_commande_produit (id_commande, id_produit),
    CONSTRAINT fk_cmd_detail_commande
        FOREIGN KEY (id_commande)
        REFERENCES Commande(id_commande)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cmd_detail_produit
        FOREIGN KEY (id_produit)
        REFERENCES Produit(id_produit)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lignes de commande';

-- =========================
-- 8) STOCK LOT  ✅ CORRIGÉ
-- =========================
CREATE TABLE StockLot (
    id_lot INT AUTO_INCREMENT PRIMARY KEY,
    id_produit INT NOT NULL,
    id_cmd_det INT NULL,  -- ✅ Stocks initiaux/dons possibles
    num_lot VARCHAR(100) NOT NULL,
    date_expiration DATE NULL,  -- ✅ Produits sans péremption OK
    quantite_actuelle INT NOT NULL DEFAULT 0,
    prix_achat_ttc DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    KEY idx_stocklot_produit (id_produit),
    KEY idx_stocklot_cmd_det (id_cmd_det),
    UNIQUE KEY uk_stocklot_num_lot (num_lot),
    CONSTRAINT fk_stocklot_produit
        FOREIGN KEY (id_produit)
        REFERENCES Produit(id_produit)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stocklot_cmd_det
        FOREIGN KEY (id_cmd_det)
        REFERENCES CommandeDetail(id_cmd_det)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lots de stock';

-- =========================
-- 9) INVENTAIRE
-- =========================
CREATE TABLE Inventaire (
    id_inventaire INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    date_inventaire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut enum('en cours','traité') NOT NULL DEFAULT 'en cours',
    KEY idx_inventaire_user (id_user),
    CONSTRAINT fk_inventaire_user
        FOREIGN KEY (id_user)
        REFERENCES Utilisateur(id_user)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sessions inventaire';

-- =========================
-- 10) INVENTAIRE DETAIL
-- =========================
CREATE TABLE InventaireDetail (
    id_inv_det INT AUTO_INCREMENT PRIMARY KEY,
    id_inventaire INT NOT NULL,
    id_lot INT NOT NULL,
    stock_theorique INT NOT NULL DEFAULT 0,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT GENERATED ALWAYS AS (stock_physique - stock_theorique) STORED,
    KEY idx_inventaire_detail_inventaire (id_inventaire),
    KEY idx_inventaire_detail_lot (id_lot),
    UNIQUE KEY uk_inventaire_lot (id_inventaire, id_lot),
    CONSTRAINT fk_inventaire_detail_inventaire
        FOREIGN KEY (id_inventaire)
        REFERENCES Inventaire(id_inventaire)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_inventaire_detail_lot
        FOREIGN KEY (id_lot)
        REFERENCES StockLot(id_lot)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Détail inventaire par lot';

-- =========================
-- 11) MOUVEMENT
-- =========================
CREATE TABLE Mouvement (
    id_mouvement INT AUTO_INCREMENT PRIMARY KEY,
    id_lot INT NOT NULL,
    id_user INT NOT NULL,
    id_point_vente INT NOT NULL,
    type_mouvement  ENUM('Entrée', 'Sortie', 'Transfert', 'perime') NOT NULL,
    quantite INT NOT NULL,
    num_document VARCHAR(100),
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mouvement_lot (id_lot),
    KEY idx_mouvement_user (id_user),
    KEY idx_mouvement_point_vente (id_point_vente),
    CONSTRAINT fk_mouvement_lot
        FOREIGN KEY (id_lot)
        REFERENCES StockLot(id_lot)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_user
        FOREIGN KEY (id_user)
        REFERENCES Utilisateur(id_user)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_point_vente
        FOREIGN KEY (id_point_vente)
        REFERENCES PointVente(id_point_vente)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Entrées/sorties stock';

-- =========================
-- 12) TRANSFERT
-- =========================
CREATE TABLE Transfert (
    id_transfert INT AUTO_INCREMENT PRIMARY KEY,
    id_source INT NOT NULL,
    id_destination INT NOT NULL,
    id_user INT NOT NULL,
    num_bordereau VARCHAR(100) NOT NULL,
    KEY idx_transfert_source (id_source),
    KEY idx_transfert_destination (id_destination),
    KEY idx_transfert_user (id_user),
    UNIQUE KEY uk_transfert_num_bordereau (num_bordereau),
    CONSTRAINT fk_transfert_source
        FOREIGN KEY (id_source)
        REFERENCES PointVente(id_point_vente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transfert_destination
        FOREIGN KEY (id_destination)
        REFERENCES PointVente(id_point_vente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transfert_user
        FOREIGN KEY (id_user)
        REFERENCES Utilisateur(id_user)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_transfert_different
        CHECK (id_source != id_destination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Transferts inter-services';

-- =========================
-- 13) TRANSFER DETAIL
-- =========================
CREATE TABLE TransfertDetail (
    id_trans_det INT AUTO_INCREMENT PRIMARY KEY,
    id_transfert INT NOT NULL,
    id_lot INT NOT NULL,
    quantite_transfert INT NOT NULL,
    KEY idx_transfert_detail_transfert (id_transfert),
    KEY idx_transfert_detail_lot (id_lot),
    UNIQUE KEY uk_transfert_lot (id_transfert, id_lot),
    CONSTRAINT fk_transfert_detail_transfert
        FOREIGN KEY (id_transfert)
        REFERENCES Transfert(id_transfert)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_transfert_detail_lot
        FOREIGN KEY (id_lot)
        REFERENCES StockLot(id_lot)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Détail transferts';

-- =========================
-- DONNÉES DE TEST HÔPITAL LAQUINTINIE
-- =========================
INSERT INTO ProductCategory (nom_categorie, forme, dosage) VALUES
('Antibiotiques', 'Comprimé', '500mg'),
('Antalgiques', 'Comprimé', '1g'),
('Vitamines', 'Sirop', '10ml'),
('Urgences', 'Injectable', '2ml');

INSERT INTO PointVente (nom_point_vente) VALUES
('Pharmacie Centrale'),
('Pédiatrie'),
('Médecine Interne'),
('Chirurgie');

INSERT INTO Utilisateur (nom_complet, username, password, role, email) VALUES
('Admin Pharmacie', 'admin', SHA2('admin123', 256), 'admin', 'admin@laquintinie.cm'),
('Dupont Jean', 'pharmacien1', SHA2('user123', 256), 'user', 'jean.pharmacie@laquintinie.cm');
```

### Ce que ça fait :
- Tu écris le mot de passe **en clair** dans ta requête : `'admin123'`
- MySQL applique **SHA2 avec 256 bits** avant de stocker
- En base tu vois quelque chose comme :
```
8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92

-- =============================================================
-- SCRIPT TERMINÉ - PRÊT POUR DEPLOIEMENT
-- Base : laquintinie_projet_1
-- 13 tables avec relations complètes
-- =============================================================

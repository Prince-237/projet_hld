DROP DATABASE IF EXISTS laquintinie_projet;
CREATE DATABASE IF NOT EXISTS laquintinie_projet;
USE laquintinie_projet;

-- Table des Fournisseurs
CREATE TABLE IF NOT EXISTS fournisseurs (
    id_fournisseur INT PRIMARY KEY AUTO_INCREMENT,
    nom_societe VARCHAR(150) NOT NULL UNIQUE,
    contact_nom VARCHAR(100),
    telephone VARCHAR(20),
    email VARCHAR(100)
) ENGINE=InnoDB;

-- Table des Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    nom_complet VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user')
) ENGINE=InnoDB;

-- Insertion des utilisateurs (On insère le HASH, mais on tape 'admin123' ou 'user123' dans le site)
INSERT INTO utilisateurs (nom_complet, username, password, role) VALUES 
('Admin', 'admin', '$2y$10$mC7G0eWzT.A.9GvG9m.pueS8PzI.p/5p6l5p.X6D6uH5p6l5p6l5p', 'admin'),
('User', 'user', '$2y$10$8S8fG5K6.f9H5jX8Z6l4OeU8R2qT0vB5p7D.X6D6uH5p6l5p6l5p', 'user');

-- Table des Produits
CREATE TABLE IF NOT EXISTS produits (
    id_produit INT PRIMARY KEY AUTO_INCREMENT,
    nom_medicament VARCHAR(150) NOT NULL,
    forme VARCHAR(50), 
    dosage VARCHAR(50),
    prix_unitaire DECIMAL(10,2),
    seuil_alerte INT,
    stock_total INT
) ENGINE=InnoDB;

-- Table des Lots
CREATE TABLE IF NOT EXISTS stock_lots (
    id_lot INT PRIMARY KEY AUTO_INCREMENT,
    id_produit INT,
    id_fournisseur INT,
    num_lot VARCHAR(50) NOT NULL,
    quantite_initiale INT NOT NULL,
    quantite_actuelle INT NOT NULL,
    date_expiration DATE NOT NULL,
    prix_achat_ht DECIMAL(10,2),
    prix_achat_ttc DECIMAL(10,2),
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_user INT,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit) ON DELETE CASCADE,
    FOREIGN KEY (id_fournisseur) REFERENCES fournisseurs(id_fournisseur),
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
) ENGINE=InnoDB;

-- Table des Sorties
CREATE TABLE IF NOT EXISTS sorties (
    id_sortie INT PRIMARY KEY AUTO_INCREMENT,
    id_lot INT,
    nom_point_vente VARCHAR(100) NOT NULL,
    quantite_sortie INT NOT NULL,
    prix_vente_unitaire DECIMAL(10,2),
    total_prix DECIMAL(10,2),
    date_sortie TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_user INT,
    FOREIGN KEY (id_lot) REFERENCES stock_lots(id_lot) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
) ENGINE=InnoDB;

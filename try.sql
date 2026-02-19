CREATE DATABASE IF NOT EXISTS laquintinie_stock;
USE laquintinie_stock;

-- 1. Table des catégories
CREATE TABLE categories (
    id_categorie INT PRIMARY KEY AUTO_INCREMENT,
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- 2. Table des produits (Le catalogue)
CREATE TABLE produits (
    id_produit INT PRIMARY KEY AUTO_INCREMENT,
    designation VARCHAR(150) NOT NULL,
    id_categorie INT,
    forme_galenique VARCHAR(50), -- Ex: Comprimé, Injection
    dosage VARCHAR(50),          -- Ex: 500mg
    seuil_alerte INT DEFAULT 700,
    prix_unitaire DECIMAL(10, 2),
    
    FOREIGN KEY (id_categorie) REFERENCES categories(id_categorie) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Table des lots (Gestion fine de la péremption)
CREATE TABLE lots (
    id_lot INT PRIMARY KEY AUTO_INCREMENT,
    id_produit INT NOT NULL,
    num_lot VARCHAR(50) NOT NULL,
    date_peremption DATE NOT NULL,
    quantite_stock INT DEFAULT 0,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Table des utilisateurs
CREATE TABLE utilisateurs (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    nom_complet VARCHAR(100),
    identifiant VARCHAR(50) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL, -- Sera haché en PHP
    role ENUM('admin', 'gestionnaire')
) ENGINE=InnoDB;

-- 5. Table des mouvements (Traçabilité Entrées/Sorties)
CREATE TABLE mouvements (
    id_mouvement INT PRIMARY KEY AUTO_INCREMENT,
    id_produit INT NOT NULL,
    id_lot INT, -- Optionnel, pour savoir quel lot a été sorti
    id_user INT NOT NULL,
    type_mouvement ENUM('ENTREE', 'SORTIE', 'RETOUR', 'PERIME') NOT NULL,
    quantite INT NOT NULL,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motif TEXT,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit),
    FOREIGN KEY (id_lot) REFERENCES lots(id_lot),
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
) ENGINE=InnoDB;
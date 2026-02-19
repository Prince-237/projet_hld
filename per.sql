CREATE DATABASE IF NOT EXISTS pharmacie_laquintinie
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacie_laquintinie;

-- Table des utilisateurs
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','pharmacien','magasinier') NOT NULL DEFAULT 'magasinier',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des médicaments
CREATE TABLE medicaments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  nom VARCHAR(255) NOT NULL,
  forme VARCHAR(100) DEFAULT NULL,
  dosage VARCHAR(100) DEFAULT NULL,
  stock_min INT NOT NULL DEFAULT 0,
  
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des fournisseurs
CREATE TABLE fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  contact VARCHAR(255) DEFAULT NULL,
  adresse VARCHAR(255) DEFAULT NULL
);

-- Table des services (services cliniques)
CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(255) NOT NULL
);

-- Table des mouvements de stock
CREATE TABLE mouvements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicament_id INT NOT NULL,
  type ENUM('entree','sortie') NOT NULL,
  source ENUM('achat','don','retour','service','perte','autre') NOT NULL,
  quantite INT NOT NULL,
  lot VARCHAR(100) DEFAULT NULL,
  date_peremption DATE DEFAULT NULL,
  fournisseur_id INT DEFAULT NULL,
  service_id INT DEFAULT NULL,
  date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cree_par INT DEFAULT NULL,
  CONSTRAINT fk_medicament FOREIGN KEY (medicament_id) REFERENCES medicaments(id),
  CONSTRAINT fk_fournisseur FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_user FOREIGN KEY (cree_par) REFERENCES users(id)
);

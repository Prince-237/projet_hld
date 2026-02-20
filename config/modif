Maintenant je fournis le SQL complet des tables que j'ai utilisées/consultées (stock_lots, sorties, produits, utilisateurs). Copie-colle ces instructions SQL dans phpMyAdmin ou MySQL si besoin.

-- Table produits
CREATE TABLE produits (
id_produit int(11) NOT NULL,
nom_medicament varchar(150) NOT NULL,
forme varchar(50) DEFAULT NULL,
dosage varchar(50) DEFAULT NULL,
seuil_alerte int(11) DEFAULT NULL,
stock_total int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE produits
ADD PRIMARY KEY (id_produit);

ALTER TABLE produits
MODIFY id_produit int(11) NOT NULL AUTO_INCREMENT;

-- Table stock_lots
CREATE TABLE stock_lots (
id_lot int(11) NOT NULL,
id_produit int(11) DEFAULT NULL,
id_fournisseur int(11) DEFAULT NULL,
num_lot varchar(50) NOT NULL,
quantite_initiale int(11) NOT NULL,
quantite_actuelle int(11) NOT NULL,
date_expiration date NOT NULL,
prix_achat_ht decimal(10,2) DEFAULT NULL,
prix_achat_ttc decimal(10,2) DEFAULT NULL,
date_enregistrement timestamp NOT NULL DEFAULT current_timestamp(),
id_user int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE stock_lots
ADD PRIMARY KEY (id_lot),
ADD KEY id_produit (id_produit),
ADD KEY id_fournisseur (id_fournisseur),
ADD KEY id_user (id_user);

ALTER TABLE stock_lots
MODIFY id_lot int(11) NOT NULL AUTO_INCREMENT;

-- Table sorties
CREATE TABLE sorties (
id_sortie int(11) NOT NULL,
id_lot int(11) DEFAULT NULL,
nom_point_vente varchar(100) NOT NULL,
quantite_sortie int(11) NOT NULL,
prix_vente_unitaire decimal(10,2) DEFAULT NULL,
total_prix decimal(10,2) DEFAULT NULL,
date_sortie timestamp NOT NULL DEFAULT current_timestamp(),
id_user int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE sorties
ADD PRIMARY KEY (id_sortie),
ADD KEY id_lot (id_lot),
ADD KEY id_user (id_user);

ALTER TABLE sorties
MODIFY id_sortie int(11) NOT NULL AUTO_INCREMENT;

-- Table utilisateurs
CREATE TABLE utilisateurs (
id_user int(11) NOT NULL,
nom_complet varchar(100) NOT NULL,
username varchar(50) NOT NULL,
password varchar(255) NOT NULL,
role enum('admin','user') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE utilisateurs
ADD PRIMARY KEY (id_user),
ADD UNIQUE KEY username (username);

ALTER TABLE utilisateurs
MODIFY id_user int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

-- Contraintes FK (à exécuter si les tables existent et doivent être liées)
ALTER TABLE stock_lots
ADD CONSTRAINT stock_lots_ibfk_1 FOREIGN KEY (id_produit) REFERENCES produits (id_produit) ON DELETE CASCADE,
ADD CONSTRAINT stock_lots_ibfk_2 FOREIGN KEY (id_fournisseur) REFERENCES fournisseurs (id_fournisseur),
ADD CONSTRAINT stock_lots_ibfk_3 FOREIGN KEY (id_user) REFERENCES utilisateurs (id_user);

ALTER TABLE sorties
ADD CONSTRAINT sorties_ibfk_1 FOREIGN KEY (id_lot) REFERENCES stock_lots (id_lot) ON DELETE CASCADE,
ADD CONSTRAINT sorties_ibfk_2 FOREIGN KEY (id_user) REFERENCES utilisateurs (id_user);


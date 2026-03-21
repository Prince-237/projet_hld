# 🚀 GUIDE D'EXÉCUTION - MIGRATION BDD

**Ordre d'application des changements**

---

## ÉTAPE 1️⃣ : Préparation

```bash
# 1. Sauvegarder la BDD actuelle
mysqldump -u root laquintinie_projet_1 > backup_old_bdd_$(date +%Y%m%d).sql

# 2. Vérifier que git est à jour
git status
git log --oneline | head -5
```

---

## ÉTAPE 2️⃣ : Appliquer le nouveau schéma SQL

```bash
# 1. Se connecter à MySQL
mysql -u root -p

# 2. Executer le nouveau script SQL
mysql -u root laquintinie_projet_1 < config/newSql.sql
```

**⚠️ ATTENTION**: Ce script DROP + CREATE, vos données seront perdues.
Si migration depuis ancienne BDD, faire un export/import sélectif.

---

## ÉTAPE 3️⃣ : Vérifier les tables créées

```sql
-- Vérifier les tables
SHOW TABLES;

-- Vérifier structure Utilisateur (inclut colonnes token)
DESC Utilisateur;

-- Vérifier colonnes marge_pourcentage
DESC Produit;

-- Vérifier InventaireDetail (id_lot, not id_produit)
DESC InventaireDetail;
```

---

## ÉTAPE 4️⃣ : Redémarrer Apache/PHP

```bash
# Windows XAMPP
# 1. Arrêter Apache (Control Panel ou cmd)
net stop Apache2.4

# 2. Redémarrer
net start Apache2.4

# OU via interface XAMPP Control Panel
```

---

## ÉTAPE 5️⃣ : Tests de connexion

1. **Test Login**
   - Aller sur: `http://localhost/projet-hld/index.php`
   - Créer un utilisateur de test ou importer depuis ancien backup
   - Se connecter

2. **Test API Produits**
   - URL: `http://localhost/projet-hld/pages/produits_search.php?q=test`
   - Doit retourner JSON (pas erreur 500)

3. **Test Inventaire**
   - Aller sur: `http://localhost/projet-hld/pages/inventaire.php`
   - Vérifier que historique charge sans erreur

---

## ÉTAPE 6️⃣ : Corrections de données (si nécessaire)

Si migration depuis ancienne BDD vers nouvelle:

```sql
-- Exemple: Import des utilisateurs
INSERT INTO Utilisateur (nom_complet, username, email, password, role)
SELECT nom_complet, username, email, password, role
FROM old_utilisateurs;

-- Exemple: Import des partenaires
INSERT INTO Partenaire (nom_entite, type, contact_nom, telephone, email)
SELECT nom_entite, type, contact_nom, telephone, email
FROM old_partenaires;
```

---

## ÉTAPE 7️⃣ : Vérification complète

**Tests fonctionnels** (cocher comme à côté):

```
[ ] Login/Logout
[ ] Créer produit
[ ] Ajouter entrée stock
[ ] Enregistrer sortie
[ ] Consulter inventaire
[ ] Voir rapports
[ ] Modifier partenaire
[ ] Gestion points de vente
[ ] Bilan financier
[ ] Recherche produits (API)
[ ] Reset password
```

---

## ⚠️ PROBLÈMES COURANTS & SOLUTIONS

### Erreur 500 sur endpoints

**Cause**: Tables/fichiers introuvables
**Solution**: Vérifier que newSql.sql a été exécuté complètement

### erreur "Table does not exist"

**Cause**: Ancien code qui utilise noms tables en minuscules
**Solution**: Les correctifs ont été appliqués aux fichiers PHP ✅

### Colonnes manquantes (reset_token)

**Cause**: newSql.sql ancien n'avait pas ces colonnes
**Solution**: newSql.sql mis à jour ✅

### Stock = 0 partout

**Cause**: Données non migrées de l'ancienne BDD
**Solution**: Importer StockLot depuis ancien système ou créer manuellement

---

## 💾 FICHIERS À VÉRIFIER EN PROD

1. `config/newSql.sql` ✅
2. `config/db.php` (vérifier dbname correct)
3. `pages/produits.php` ✅
4. `pages/inventaire.php` ✅
5. `pages/get_inventaire_history.php` ✅
6. `pages/fournisseurs.php` ✅
7. Tous autres fichiers pages/

---

## 🎯 RÉSUMÉ RAPIDE

| Étape | Durée | Action |
|-------|-------|--------|
| Backup | 1 min | Sauvegarder ancienne BDD |
| SQL | 1 min | Exécuter newSql.sql |
| Verify | 2 min | Vérifier tables |
| Restart | 1 min | Redémarrer services |
| Test | 10 min | Tester chaque page |
| Data | 5-15 min | Importer données si besoin |
| **TOTAL** | **20-35 min** | **Migration complète** |

---

## 🔐 SÉCURITÉ

- ✅ SQL Injections: Prépared Statements utilisées
- ✅ XSS: htmlspecialchars() appliqué
- ✅ Auth: session_start() vérifié
- ✅ Passwords: password_hash() utilisé
- ✅ Tokens: reset_token_hash en SHA256

---

## 📞 SUPPORT

Si erreur après migration:

1. Vérifier les logs Apache: `C:\xampp\apache\logs\error.log`
2. Vérifier phpMyAdmin: Tables visibles?
3. Lancer test depuis script: `http://localhost/projet-hld/test.php`

---

**✅ Migration prête!**

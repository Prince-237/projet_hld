<?php
require_once '../config/db.php';

$type = $_GET['type'] ?? 'Pharmacie';
$typeProduit = ($type === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$search = $_GET['search'] ?? '';
$fournisseur = $_GET['fournisseur'] ?? '';
$agent = $_GET['agent'] ?? '';
$dateStart = $_GET['dateStart'] ?? '';
$dateEnd = $_GET['dateEnd'] ?? '';

$sql = "SELECT 
            cmd.id_commande,
            cmd.date_commande,
            part.nom_entite AS fournisseur,
            u.nom_complet AS utilisateur,
            COUNT(cd.id_cmd_det) AS nb_produits,
            SUM(cd.quantite_voulue * p.prix_unitaire) AS total_commande
        FROM Commande cmd
        JOIN CommandeDetail cd ON cmd.id_commande = cd.id_commande
        JOIN Produit p ON cd.id_produit = p.id_produit
        JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
        JOIN Utilisateur u ON cmd.id_user = u.id_user
        WHERE p.type_produit = ?
        AND cmd.statut = 'Reçue'
        AND cmd.deleted_at IS NULL
        AND part.type = 'Fournisseur'";

$params = [$typeProduit];

if (!empty($search)) {
    $sql .= " AND (part.nom_entite LIKE ? OR u.nom_complet LIKE ? OR cmd.id_commande LIKE ? OR cmd.date_commande LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($dateStart)) {
    $sql .= " AND DATE(cmd.date_commande) >= ?";
    $params[] = $dateStart;
}
if (!empty($dateEnd)) {
    $sql .= " AND DATE(cmd.date_commande) <= ?";
    $params[] = $dateEnd;
}
if (!empty($agent)) {
    $sql .= " AND u.id_user = ?";
    $params[] = $agent;
}
if (!empty($fournisseur)) {
    $sql .= " AND part.id_partenaire = ?";
    $params[] = $fournisseur;
}

$sql .= " GROUP BY cmd.id_commande ORDER BY cmd.date_commande DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lots = $stmt->fetchAll();

if (empty($lots)) {
    echo "<tr><td colspan='6' class='text-center text-muted'>Aucun résultat</td></tr>";
    exit;
}

foreach ($lots as $lot) {
    $date = date('d/m/Y H:i', strtotime($lot['date_commande']));
    $fournisseur = htmlspecialchars($lot['fournisseur']);
    $utilisateur = htmlspecialchars($lot['utilisateur']);
    $nbProduits = $lot['nb_produits'];
    $totalCommande = number_format($lot['total_commande'], 2, '.', ' ');
    $idComm = $lot['id_commande'];

    echo <<<HTML
    <tr>
        <td>{$date}</td>
        <td>{$fournisseur}</td>
        <td class='text-center'>{$nbProduits}</td>
        <td class='text-end'>{$totalCommande} F</td>
        <td>{$utilisateur}</td>
        <td class='text-center'>
            <a href='details_commande.php?id={$idComm}' class='btn btn-sm btn-outline-primary me-1' title='Voir'>
                <i class='bi bi-eye'></i>
            </a>
            <a href='edit_commande.php?id={$idComm}' class='btn btn-sm btn-outline-primary me-1' title='Modifier'>
                <i class='bi bi-pencil'></i>
            </a>
            <form method='POST' action='entrees.php' class='d-inline' onsubmit='return confirm("Supprimer cette entrée ?");'>
                <input type='hidden' name='id_commande' value="{$idComm}">
                <button type='submit' name='btn_delete_commande' class='btn btn-sm btn-outline-danger' title='Supprimer'>
                    <i class='bi bi-trash'></i>
                </button>
            </form>
        </td>
    </tr>
    HTML;
}

<?php
require_once '../config/db.php';

$typeFilter = $_GET['type'] ?? 'Medicament';
$search = $_GET['search'] ?? '';
$fournisseur = $_GET['fournisseur'] ?? '';
$agent = $_GET['agent'] ?? '';
$dateStart = $_GET['dateStart'] ?? '';
$dateEnd = $_GET['dateEnd'] ?? '';

$sql = "SELECT cmd.id_commande, cmd.date_commande,
               part.nom_entite, u.nom_complet,
               COUNT(cd.id_cmd_det) AS nb_produits,
               SUM(cd.quantite_voulue * p.prix_unitaire) AS total_prix
        FROM Commande cmd
        JOIN CommandeDetail cd ON cmd.id_commande = cd.id_commande
        JOIN Produit p ON cd.id_produit = p.id_produit
        JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
        JOIN Utilisateur u ON cmd.id_user = u.id_user
        WHERE cmd.statut = 'En attente'
        AND p.type_produit = ?";

$params = [$typeFilter];

if (!empty($search)) {
    $sql .= " AND (part.nom_entite LIKE ? OR u.nom_complet LIKE ? OR cmd.id_commande LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($fournisseur)) {
    $sql .= " AND part.id_partenaire = ?";
    $params[] = $fournisseur;
}
if (!empty($agent)) {
    $sql .= " AND u.id_user = ?";
    $params[] = $agent;
}
if (!empty($dateStart)) { $sql .= " AND DATE(cmd.date_commande) >= ?"; $params[] = $dateStart; }
if (!empty($dateEnd)) { $sql .= " AND DATE(cmd.date_commande) <= ?"; $params[] = $dateEnd; }

$sql .= " GROUP BY cmd.id_commande ORDER BY cmd.date_commande ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attentes = $stmt->fetchAll();

if (empty($attentes)) {
    echo "<tr><td colspan='6' class='text-center text-muted'>Aucune commande trouvée avec ces filtres.</td></tr>";
    exit;
}

foreach ($attentes as $att) {
    $date = date('d/m/Y H:i', strtotime($att['date_commande']));
    $total = number_format($att['total_prix'], 0, '.', ' ');
    echo "<tr>
        <td>{$date}</td>
        <td>" . htmlspecialchars($att['nom_entite']) . "</td>
        <td class='text-center'><span class='badge bg-info text-dark'>{$att['nb_produits']} produit(s)</span></td>
        <td class='fw-bold text-end'>{$total} FCFA</td>
        <td class='small text-muted'>" . htmlspecialchars($att['nom_complet']) . "</td>
        <td class='text-center'>
            <div class='btn-group'>
                <a href='details_commande_attente.php?id={$att['id_commande']}' class='btn btn-sm btn-outline-primary me-1' title='Voir'><i class='bi bi-eye'></i></a>
                <a href='reception_commande.php?id_commande={$att['id_commande']}' class='btn btn-sm btn-outline-success me-1' title='Réceptionner'>
                    <i class='bi bi-check2-circle'></i>
                </a>
                <form method='POST' class='d-inline' onsubmit='return confirm(\"Annuler cette commande ?\");'>
                    <input type='hidden' name='id_commande' value='{$att['id_commande']}'>
                    <button type='submit' name='btn_delete_commande' class='btn btn-sm btn-outline-danger' title='Annuler'>
                        <i class='bi bi-trash'></i>
                    </button>
                </form>
            </div>
        </td></tr>";
}
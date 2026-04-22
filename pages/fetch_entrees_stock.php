<?php
require_once '../config/db.php';

$type = $_GET['type'] ?? 'Pharmacie';
$typeProduit = ($type === 'Laboratoire') ? 'Laboratoire' : 'Medicament';
$search = $_GET['search'] ?? '';
$fournisseur = $_GET['fournisseur'] ?? '';
$agent = $_GET['agent'] ?? '';
$dateStart = $_GET['dateStart'] ?? '';
$dateEnd = $_GET['dateEnd'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT 
            cmd.id_commande,
            cmd.date_commande,
            cmd.statut,
            cmd.statut_paiement,
            part.nom_entite AS fournisseur,
            u.nom_complet AS utilisateur,
            COUNT(cd.id_cmd_det) AS nb_produits,
            SUM(COALESCE(l.prix_achat_ttc * l.quantite_actuelle, cd.quantite_voulue * p.prix_unitaire)) AS total_commande
        FROM Commande cmd
        JOIN CommandeDetail cd ON cmd.id_commande = cd.id_commande
        JOIN Produit p ON cd.id_produit = p.id_produit
        LEFT JOIN StockLot l ON cd.id_cmd_det = l.id_cmd_det
        LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
        JOIN Utilisateur u ON cmd.id_user = u.id_user
        WHERE p.type_produit = ?
        AND cmd.deleted_at IS NULL
        AND part.type = 'Fournisseur'
        AND cmd.statut IN ('En attente', 'Reçue')";

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
if (!empty($statusFilter)) {
    $sql .= " AND cmd.statut = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY cmd.id_commande, cmd.statut ORDER BY cmd.date_commande DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lots = $stmt->fetchAll();

if (empty($lots)) {
    echo "<tr><td colspan='8' class='text-center text-muted'>Aucun résultat</td></tr>";
    exit;
}

foreach ($lots as $lot) {
    $date = date('d/m/Y H:i', strtotime($lot['date_commande']));
    $fournisseur = htmlspecialchars($lot['fournisseur']);
    $utilisateur = htmlspecialchars($lot['utilisateur']);
    $nbProduits = $lot['nb_produits'];
    $totalCommande = number_format($lot['total_commande'], 2, '.', ' ');
    $idComm = $lot['id_commande'];
    $statut = $lot['statut'];
    $statutBadge = ($statut === 'En attente') ? '<span class="badge bg-warning text-dark">En attente</span>' : '<span class="badge bg-success">Reçue</span>';
    $sp = $lot['statut_paiement'] ?? 'du';
    $badgeClass = match($sp) {
        'du' => 'bg-danger',
        'partielle' => 'bg-warning text-dark',
        'payé' => 'bg-info',
        'soldé' => 'bg-success',
        default => 'bg-secondary'
    };
    $statutPaiementBadge = "<span class='badge {$badgeClass}'>" . ucfirst($sp) . "</span>";

    echo <<<HTML
    <tr>
        <td>{$date}</td>
        <td>{$fournisseur}</td>
        <td class='text-center'>{$nbProduits}</td>
        <td class='text-end'>{$totalCommande} F</td>
        <td>{$utilisateur}</td>
        <td>{$statutBadge}</td>
        <td>{$statutPaiementBadge}</td>
        <td class='text-center'>
            <div class='dropdown'>
                <button class='btn btn-sm btn-light' type='button' data-bs-toggle='dropdown' aria-expanded='false' title='Actions'>
                    <i class='bi bi-chevron-down'></i>
                </button>
                <ul class='dropdown-menu dropdown-menu-end shadow'>
                    <li>
                        <a class='dropdown-item' href='details_commande.php?id={$idComm}'>
                            <i class='bi bi-eye me-2'></i>Voir
                        </a>
                    </li>
                    <li>
                        <a class='dropdown-item' href='print_transfert.php?id={$idComm}' target='_blank'>
                            <i class='bi bi-printer me-2'></i>Imprimer
                        </a>
                    </li>
HTML;

    if ($statut === 'En attente') {
        echo <<<HTML
                    <li>
                        <a class='dropdown-item text-success' href='reception_commande.php?id_commande={$idComm}'>
                            <i class='bi bi-check-circle me-2'></i>Réceptionner la commande
                        </a>
                    </li>
HTML;
    } else {
        echo <<<HTML
                    <li>
                        <a class='dropdown-item text-warning' href='retour_fournisseur.php?id={$idComm}&redirect=entrees_stock.php'>
                            <i class='bi bi-arrow-counterclockwise me-2'></i>Retour fournisseur
                        </a>
                    </li>
HTML;
    }

    echo <<<HTML
                    <li>
                        <a class='dropdown-item text-info' href='edit_statut_paiement.php?id={$idComm}'>
                            <i class='bi bi-pencil me-2'></i>Modifier statut de paiement
                        </a>
                    </li>
                    <li><hr class='dropdown-divider'></li>
                    <li>
                        <form method='POST' action='delete_commande.php' onsubmit='return confirm("Supprimer cette entrée ?");'>
                            <input type='hidden' name='id_commande' value="{$idComm}">
                            <button type='submit' class='dropdown-item text-danger'>
                                <i class='bi bi-trash me-2'></i>Supprimer
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </td>
    </tr>
    HTML;
}

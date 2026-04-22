<?php
require_once '../config/db.php';
session_start();
header('Content-Type: text/html; charset=utf-8');

$dateFilterStart = $_GET['dateStart'] ?? '';
$dateFilterEnd = $_GET['dateEnd'] ?? '';
$fournisseurFilter = $_GET['fournisseur'] ?? '';
$agentFilter = $_GET['agent'] ?? '';

// 🔹 RÉCUPÉRER LES RETOURS
$sqlRetours = "SELECT 
                    rf.id_retour,
                    rf.id_commande,
                    rf.date_retour,
                    rf.commentaire,
                    rf.statut,
                    rf.id_user,
                    part.nom_entite AS fournisseur,
                    u.nom_complet AS agent,
                    cmd.date_commande,
                    COUNT(rfd.id_retour_detail) AS nb_lots
                FROM RetourFournisseur rf
                JOIN Commande cmd ON rf.id_commande = cmd.id_commande
                LEFT JOIN Partenaire part ON cmd.id_partenaire = part.id_partenaire
                LEFT JOIN Utilisateur u ON rf.id_user = u.id_user
                LEFT JOIN RetourFournisseurDetail rfd ON rf.id_retour = rfd.id_retour
                WHERE 1=1";

$params = [];

// Appliquer les filtres
if (!empty($dateFilterStart)) {
    $sqlRetours .= " AND DATE(rf.date_retour) >= ?";
    $params[] = $dateFilterStart;
}

if (!empty($dateFilterEnd)) {
    $sqlRetours .= " AND DATE(rf.date_retour) <= ?";
    $params[] = $dateFilterEnd;
}

if (!empty($fournisseurFilter)) {
    $sqlRetours .= " AND part.nom_entite = ?";
    $params[] = $fournisseurFilter;
}

if (!empty($agentFilter)) {
    $sqlRetours .= " AND u.nom_complet = ?";
    $params[] = $agentFilter;
}

$sqlRetours .= " GROUP BY rf.id_retour ORDER BY rf.date_retour DESC";

$stmt = $pdo->prepare($sqlRetours);
$stmt->execute($params);
$retours = $stmt->fetchAll();

if (empty($retours)) {
    echo '<tr><td colspan="6" class="text-center text-muted">Aucun retour fournisseur enregistré.</td></tr>';
    exit;
}

foreach ($retours as $r) {
    $commentaire = htmlspecialchars($r['commentaire']);
    $commentaireDisplay = strlen($commentaire) > 50 ? substr($commentaire, 0, 50) . '...' : $commentaire;
    ?>
    <tr>
        <td><?= date('d/m/Y H:i', strtotime($r['date_retour'])) ?></td>
        <td><?= htmlspecialchars($r['fournisseur'] ?: 'N/A') ?></td>
        <td><?= htmlspecialchars($r['agent'] ?: '-') ?></td>
        <td class="text-center"><?= $r['nb_lots'] ?></td>
        <td>
            <span title="<?= htmlspecialchars($r['commentaire']) ?>">
                <?= $commentaireDisplay ?>
            </span>
        </td>
        <td class="text-center">
            <div class="dropdown">
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                    <i class="bi bi-chevron-down"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item" href="details_retour_fournisseur.php?id=<?= $r['id_retour'] ?>&redirect=liste_retours_fournisseur.php">
                            <i class="bi bi-eye me-2"></i>Consulter
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="print_transfert.php?id=<?= $r['id_retour'] ?>" target="_blank">
                            <i class="bi bi-printer me-2"></i>Imprimer
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="edit_retour_fournisseur.php?id=<?= $r['id_retour'] ?>&redirect=liste_retours_fournisseur.php">
                            <i class="bi bi-pencil me-2"></i>Modifier
                        </a>
                    </li>
                </ul>
            </div>
        </td>
    </tr>
    <?php
}
?>

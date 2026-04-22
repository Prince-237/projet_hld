<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$dataDir = __DIR__ . '/../data';
$dataFile = $dataDir . '/group_transfers.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

function loadGroups(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $groups = json_decode($json, true);
    return is_array($groups) ? $groups : [];
}

function saveGroups(string $path, array $groups): bool
{
    return file_put_contents($path, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function flash(string $message, string $type = 'success')
{
    $_SESSION['group_transfer_message'] = ['text' => $message, 'type' => $type];
}

function getAgentName(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT nom_complet FROM Utilisateur WHERE id_user = ?');
    $stmt->execute([$userId]);
    $name = $stmt->fetchColumn();
    return $name ? $name : 'Utilisateur';
}

$redirect = isset($_POST['redirect']) && trim($_POST['redirect']) !== '' ? trim($_POST['redirect']) : 'sorties.php';
$action = $_POST['action'] ?? '';
$groups = loadGroups($dataFile);

if ($action === 'initiate') {
    $id_source = isset($_POST['id_source']) ? intval($_POST['id_source']) : 0;
    $id_destination = isset($_POST['id_destination']) ? intval($_POST['id_destination']) : 0;
    $itemsJson = $_POST['items_json'] ?? '';
    $items = json_decode($itemsJson, true);

    if ($id_source === 0 || $id_destination === 0) {
        flash('Source et destination sont requises.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    if ($id_source === $id_destination) {
        flash('La source et la destination doivent être différentes.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    if (!is_array($items) || count($items) === 0) {
        flash('Ajoutez au moins un lot au transfert.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    $agentName = $_SESSION['nom'] ?? getAgentName($pdo, $_SESSION['user_id']);
    $cleanItems = [];
    foreach ($items as $item) {
        if (empty($item['id_lot']) || empty($item['qty']) || intval($item['qty']) <= 0) {
            continue;
        }

        $prixAchat = isset($item['prix_achat']) ? floatval($item['prix_achat']) : null;
        $marge = isset($item['marge']) ? floatval($item['marge']) : null;
        $prixVente = isset($item['prix_vente']) ? floatval($item['prix_vente']) : null;

        $cleanItem = [
            'id_lot' => intval($item['id_lot']),
            'qty' => intval($item['qty']),
            'label' => trim($item['label'] ?? ''),
            'prix_achat' => $prixAchat,
            'marge' => $marge,
            'prix_vente' => $prixVente,
        ];

        $cleanItems[] = $cleanItem;
    }

    if (count($cleanItems) === 0) {
        flash('Les éléments du transfert ne sont pas valides.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    $groupId = uniqid('grp_', true);
    $groups[] = [
        'id' => $groupId,
        'id_source' => $id_source,
        'id_destination' => $id_destination,
        'items' => $cleanItems,
        'status' => 'Envoyé',
        'created_by' => $_SESSION['user_id'],
        'agent_name' => $agentName,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    if (!saveGroups($dataFile, $groups)) {
        flash('Impossible de sauvegarder le transfert.', 'danger');
    } else {
        flash('Transfert enregistré avec succès.', 'success');
    }

    header('Location: ' . $redirect);
    exit();
}

if ($action === 'receive') {
    $groupId = $_POST['group_id'] ?? '';
    if (empty($groupId)) {
        flash('Identifiant de transfert manquant.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    $foundIndex = null;
    foreach ($groups as $index => $group) {
        if ($group['id'] === $groupId) {
            $foundIndex = $index;
            break;
        }
    }

    if ($foundIndex === null) {
        flash('Transfert introuvable.', 'danger');
        header('Location: ' . $redirect);
        exit();
    }

    if ($groups[$foundIndex]['status'] !== 'Envoyé') {
        flash('Ce transfert a déjà été traité.', 'warning');
        header('Location: ' . $redirect);
        exit();
    }

    $group = $groups[$foundIndex];

    try {
        $pdo->beginTransaction();

        $num_bordereau = 'TR-' . date('YmdHis') . '-' . rand(100, 999);
        $stmtTrans = $pdo->prepare("INSERT INTO Transfert (id_source, id_destination, id_user, num_bordereau) VALUES (?, ?, ?, ?)");
        $stmtTrans->execute([$group['id_source'], $group['id_destination'], $_SESSION['user_id'], $num_bordereau]);
        $id_transfert = $pdo->lastInsertId();

        foreach ($group['items'] as $item) {
            $id_lot = intval($item['id_lot']);
            $qty = intval($item['qty']);

            if ($group['id_source'] === 1) {
                $stmtCheck = $pdo->prepare("SELECT quantite_actuelle FROM StockLot WHERE id_lot = ? FOR UPDATE");
                $stmtCheck->execute([$id_lot]);
                $lot = $stmtCheck->fetch();
                if (!$lot || $lot['quantite_actuelle'] < $qty) {
                    throw new Exception('Stock insuffisant pour le lot #' . $id_lot);
                }
                $stmtUpdate = $pdo->prepare("UPDATE StockLot SET quantite_actuelle = quantite_actuelle - ? WHERE id_lot = ?");
                $stmtUpdate->execute([$qty, $id_lot]);
            }

            $stmtDet = $pdo->prepare("INSERT INTO TransfertDetail (id_transfert, id_lot, quantite_transfert) VALUES (?, ?, ?)");
            $stmtDet->execute([$id_transfert, $id_lot, $qty]);
        }

        $pdo->commit();

        $groups[$foundIndex]['status'] = 'Reçu';
        $groups[$foundIndex]['received_at'] = date('Y-m-d H:i:s');
        $groups[$foundIndex]['transfer_id'] = $id_transfert;

        if (!saveGroups($dataFile, $groups)) {
            flash('Transfert reçu, mais impossible de mettre à jour le statut.', 'warning');
        } else {
            flash('Transfert marqué comme reçu et traité.', 'success');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('Erreur lors de la réception : ' . $e->getMessage(), 'danger');
    }

    header('Location: ' . $redirect);
    exit();
}

if ($action === 'delete') {
    $groupId = $_POST['group_id'] ?? '';
    if (!empty($groupId)) {
        $newGroups = [];
        foreach ($groups as $group) {
            if ($group['id'] !== $groupId) {
                $newGroups[] = $group;
            }
        }
        if (saveGroups($dataFile, $newGroups)) {
            flash('Transfert supprimé.', 'success');
        } else {
            flash('Impossible de supprimer le transfert.', 'danger');
        }
    }
    header('Location: ' . $redirect);
    exit();
}

header('Location: ' . $redirect);
exit();

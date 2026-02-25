<?php
// scripts/sync_upload.php
// Cliente: Subir ventas y productos pendientes al servidor
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();

// Get sync configuration
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$stmt->execute(['sync_token']);
$token = $stmt->fetchColumn();

$stmt->execute(['cloud_host']);
$cloudUrl = $stmt->fetchColumn();

if (!$token || !$cloudUrl) {
    echo json_encode(['ok' => false, 'error' => 'No sync configuration']);
    exit;
}

// Get pending items from sync_queue
$stmt = $db->prepare("SELECT * FROM sync_queue WHERE locked = 0 ORDER BY created_at ASC LIMIT 100");
$stmt->execute();
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($queue)) {
    echo json_encode(['ok' => true, 'uploaded' => 0, 'message' => 'Nothing to sync']);
    exit;
}

// Lock items
$ids = array_column($queue, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$db->prepare("UPDATE sync_queue SET locked = 1, locked_at = datetime('now') WHERE id IN ($placeholders)")->execute($ids);

// Prepare batch
$entries = [];
foreach ($queue as $item) {
    $entries[] = [
        'type' => $item['resource_type'],
        'uuid' => $item['resource_uuid'],
        'payload' => json_decode($item['payload'], true)
    ];
}

// Send to server
$ch = curl_init($cloudUrl . '/api/sync_ingest.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['entries' => $entries]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    // Unlock items on failure
    $db->prepare("UPDATE sync_queue SET locked = 0, locked_at = NULL WHERE id IN ($placeholders)")->execute($ids);
    echo json_encode(['ok' => false, 'error' => 'Server returned ' . $httpCode, 'response' => $response]);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['ok']) || !$data['ok']) {
    // Unlock items on failure
    $db->prepare("UPDATE sync_queue SET locked = 0, locked_at = NULL WHERE id IN ($placeholders)")->execute($ids);
    echo json_encode(['ok' => false, 'error' => 'Invalid server response', 'data' => $data]);
    exit;
}

// Success: delete synced items
$db->prepare("DELETE FROM sync_queue WHERE id IN ($placeholders)")->execute($ids);

echo json_encode([
    'ok' => true,
    'uploaded' => count($queue),
    'server_response' => $data
]);
?>

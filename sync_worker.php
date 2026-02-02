<?php
// sync_worker.php
// CLI worker to push queued resources to cloud via HTTP API
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Billing.php';

$db = Database::getInstance()->getConnection();
$siteUrl = Billing::getSetting('site_url');
$syncToken = Billing::getSetting('sync_api_token');
$batch = 50;

if (!$siteUrl) {
    echo "[!] site_url not configured in settings. Set it in Admin > Billing.\n";
    exit(1);
}

// Health check
$healthUrl = rtrim($siteUrl, '/') . '/health';
$ch = curl_init($healthUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$now = date('Y-m-d H:i:s');
if ($code < 200 || $code >= 400) {
    echo "[!] Health check failed for $healthUrl (HTTP $code). Will retry later.\n";
    // Log
    $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (:now, ?, 'error', ?)")
       ->execute([$now, 'Health check failed', json_encode(['url' => $healthUrl, 'http' => $code])]);
    exit(1);
}

// 1) Select a batch and lock them
$db->beginTransaction();
$driver = \Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    // SQLite: no FOR UPDATE, use PHP timestamp and numeric LIMIT
    $selectSql = "SELECT id FROM sync_queue WHERE locked = 0 AND next_attempt <= :now ORDER BY created_at LIMIT $batch";
    $selectStmt = $db->prepare($selectSql);
    $selectStmt->bindValue(':now', $now);
} else {
    // MySQL: use row locking
    $selectStmt = $db->prepare("SELECT id FROM sync_queue WHERE locked = 0 AND next_attempt <= NOW() ORDER BY created_at LIMIT :limit FOR UPDATE");
    $selectStmt->bindValue(':limit', (int)$batch, PDO::PARAM_INT);
}
$selectStmt->execute();
$ids = $selectStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($ids)) {
    $db->commit();
    echo "No hay items en cola para sincronizar.\n";
    exit(0);
}

// Lock selected rows
$in = implode(',', array_fill(0, count($ids), '?'));
$updateLock = $db->prepare("UPDATE sync_queue SET locked = 1, locked_at = ? WHERE id IN ($in)");
$params = array_merge([$now], $ids);
$updateLock->execute($params);
$db->commit();

// Fetch full rows
$stmt = $db->prepare("SELECT * FROM sync_queue WHERE id IN ($in)");
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build payload
$entries = [];
foreach ($rows as $r) {
    $payload = json_decode($r['payload'], true);
    $entries[] = ['queue_id' => $r['id'], 'resource_type' => $r['resource_type'], 'resource_uuid' => $r['resource_uuid'], 'payload' => $payload];
}

$ingestUrl = rtrim($siteUrl, '/') . '/api/sync_ingest.php';
$ch = curl_init($ingestUrl);
$headers = ['Content-Type: application/json'];
if ($syncToken) $headers[] = 'Authorization: Bearer ' . $syncToken;

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['entries' => $entries]));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($http >= 200 && $http < 300) {
    $resp = json_decode($response, true) ?: [];
    // Success, mark sales as synced and delete queue rows
    $db->beginTransaction();
    $synced = [];
    foreach ($rows as $r) {
        $qId = $r['id'];
        $payload = json_decode($r['payload'], true);
        if (is_array($payload) && !empty($payload['id'])) {
            $synced[] = $payload['id'];
        }
    }
    if (!empty($synced)) {
        $in2 = implode(',', array_fill(0, count($synced), '?'));
        $stmt = $db->prepare("UPDATE sales SET sync_status = 1, last_synced_at = ? WHERE id IN ($in2)");
        $params = array_merge([$now], $synced);
        $stmt->execute($params);
        $updatedCount = $stmt->rowCount();
    }
    // Delete queue rows
    $stmt = $db->prepare("DELETE FROM sync_queue WHERE id IN ($in)");
    $stmt->execute($ids);

    $metaArray = ['http' => $http, 'response' => $resp, 'synced_ids' => $synced, 'local_updated' => $updatedCount ?? 0];
    $meta = json_encode($metaArray);
    $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (:now, ?, 'success', ?)")
       ->execute([$now, 'Sync batch success', $meta]);
    if (empty($synced)) {
        // Edge: no payload ids found — log detail
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (:now, ?, 'error', ?)")
           ->execute([$now, 'Sync batch finished but no local ids marked as synced', json_encode(['entries' => $entries])]);
    }
    $db->commit();

    echo "[OK] Lote sincronizado correctamente.\n";
} else {
    // Failure: unlock with exponential backoff
    echo "[X] Error al enviar lote: HTTP $http - $curlErr\n";
    $db->beginTransaction();
    foreach ($rows as $r) {
        $qId = $r['id'];
        $attempts = (int)$r['attempts'] + 1;
        $delaySeconds = pow(2, min($attempts, 6)) * 30; // cap exponent
        $nextAttempt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $upd = $db->prepare("UPDATE sync_queue SET attempts = ?, locked = 0, next_attempt = ?, locked_at = NULL WHERE id = ?");
        $upd->execute([$attempts, $nextAttempt, $qId]);
    }
    $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (:now, ?, 'error', ?)")
       ->execute([$now, 'Sync batch failed', json_encode(['http' => $http, 'curl_error' => $curlErr, 'response' => $response])]);
    $db->commit();
}

?>
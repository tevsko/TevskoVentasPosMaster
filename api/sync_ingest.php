<?php
// api/sync_ingest.php
// Example cloud endpoint to receive sync batches. Requires Authorization: Bearer <token> if configured.
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Billing.php';

$db = Database::getInstance()->getConnection();

// Parse Authorization: Bearer <token>
$auth = null;
$headers = getallheaders();
if (!empty($headers['Authorization'])) $auth = trim($headers['Authorization']);
if (!$auth && !empty($headers['authorization'])) $auth = trim($headers['authorization']);

$token = null;
if ($auth && stripos($auth, 'Bearer ') === 0) {
    $token = trim(substr($auth, 7));
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: token required']);
    exit;
}

// Lookup tenant by token
$stmt = $db->prepare("SELECT id, subdomain, allowed_host FROM tenants WHERE sync_token = ? LIMIT 1");
$stmt->execute([$token]);
$tenant = $stmt->fetch();
if (!$tenant) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: invalid token']);
    exit;
}

// Optional host verification
$host = $_SERVER['HTTP_HOST'] ?? (!empty($headers['Host']) ? $headers['Host'] : (!empty($headers['host']) ? $headers['host'] : null));
if (!empty($tenant['allowed_host']) && $tenant['allowed_host'] !== $host) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden: host mismatch']);
    exit;
}

$tenantId = $tenant['id'];


$input = file_get_contents('php://input');
$body = json_decode($input, true);
$entries = $body['entries'] ?? [];
if (empty($entries)) {
    echo json_encode(['ok' => false, 'error' => 'No entries']);
    exit;
}

$inserted = 0; $duplicates = 0; $errors = [];
try {
    $db->beginTransaction();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $insertSql = "INSERT OR IGNORE INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    } else {
        $insertSql = "INSERT IGNORE INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    }
    $stmt = $db->prepare($insertSql);
    foreach ($entries as $e) {
        $p = $e['payload'] ?? null;
        if (!$p || empty($p['id'])) { $errors[] = ['entry' => $e]; continue; }
        $stmt->execute([
            $p['id'], $p['user_id'] ?? null, $p['branch_id'] ?? null, $p['machine_id'] ?? null, $p['amount'] ?? 0, $p['payment_method'] ?? 'unknown', $p['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        if ($stmt->rowCount() > 0) $inserted++; else $duplicates++;
    }
    $db->commit();
    // Audit log
    try {
        $details = "Ingest from tenant {$tenantId}: inserted={$inserted}, duplicates={$duplicates}";
        $meta = json_encode(['tenant_id' => $tenantId, 'errors' => $errors]);
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'success', ?)")->execute([$details, $meta]);
    } catch (Exception $ex) {
        // Non-fatal; don't break response
    }
    echo json_encode(['ok' => true, 'inserted' => $inserted, 'duplicates' => $duplicates, 'errors' => $errors]);
} catch (Exception $e) {
    $db->rollBack();
    try {
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")->execute(["Ingest error: " . $e->getMessage(), json_encode(['tenant_id' => $tenantId])]);
    } catch (Exception $ex) {}
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
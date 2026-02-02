<?php
// scripts/e2e_sqlite_test.php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/init_sqlite.php';
require_once __DIR__ . '/create_test_user_sqlite.php';
require_once __DIR__ . '/create_sqlite_sale_test.php';

// Give DB a small moment to settle
sleep(1);

// Run sync worker (subprocess) up to 3 attempts to tolerate timing
$php = PHP_BINARY;
$cmd = escapeshellarg($php) . " -r \"define('DB_DRIVER','sqlite'); define('DB_SQLITE_FILE', __DIR__ . '/data/test_data.sqlite'); require 'sync_worker.php';\"";
$attempts = 0; $ret = 1; $out = [];
while ($attempts < 3) {
    $attempts++;
    exec($cmd, $out, $ret);
    echo "Attempt $attempts: " . implode("\n", $out) . "\n";
    if ($ret === 0) break;
    sleep(1);
}
if ($ret !== 0) {
    echo "[FAIL] sync_worker returned non-zero exit code ($ret) after $attempts attempts\n";
    exit(1);
}

// Verify DB state (use fresh PDO to avoid cached connection in this process)
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/test_data.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$s = $pdo->query("SELECT id,sync_status FROM sales ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
if (empty($s)) {
    echo "[FAIL] No sales found after test run\n";
    exit(1);
}

$latest = $s[0];
if ((int)$latest['sync_status'] !== 1) {
    echo "[FAIL] Latest sale not marked as synced\n";
    print_r($s);
    exit(1);
}

$log = $db->query("SELECT COUNT(*) FROM sync_logs WHERE details LIKE '%Sync batch success%' AND status = 'success'")->fetchColumn();
if ((int)$log < 1) {
    echo "[FAIL] No success sync_logs found\n";
    exit(1);
}

echo "[PASS] E2E SQLite test succeeded. Latest sale synced and logs found.\n";
exit(0);

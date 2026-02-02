<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

$s = $db->prepare('SELECT id, sync_status, last_synced_at, created_at FROM sales ORDER BY created_at DESC LIMIT 5');
$s->execute();
$rows = $s->fetchAll(PDO::FETCH_ASSOC);
echo "Recent sales:\n";
print_r($rows);
$c = $db->query('SELECT COUNT(*) FROM sync_queue')->fetchColumn();
echo "Sync queue count: $c\n";
$logs = $db->query('SELECT id, last_sync, details, status FROM sync_logs ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
echo "Recent sync_logs:\n";
print_r($logs);

?>
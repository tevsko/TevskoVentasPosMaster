<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$rows = $db->query('SELECT id,last_sync,details,meta FROM sync_logs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: {$r['id']} | last_sync: {$r['last_sync']} | details: {$r['details']}\n";
    $m = $r['meta'] ? json_decode($r['meta'], true) : null;
    print_r($m);
    echo "---\n";
}

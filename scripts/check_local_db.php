<?php
// scripts/check_local_db.php
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();
$debug = [];

try {
    // 1. Check Settings
    $debug['settings']['cloud_host'] = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'cloud_host'")->fetchColumn();
    $debug['settings']['sync_token'] = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'sync_token'")->fetchColumn();

    // 2. Check Queue
    $debug['counts']['queue_total'] = $db->query("SELECT COUNT(*) FROM sync_queue")->fetchColumn();
    $debug['counts']['queue_locked'] = $db->query("SELECT COUNT(*) FROM sync_queue WHERE locked = 1")->fetchColumn();
    $debug['recent_queue'] = $db->query("SELECT resource_type, resource_uuid, created_at FROM sync_queue ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Check Local Records
    $debug['counts']['local_sales'] = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    $debug['counts']['local_machines'] = $db->query("SELECT COUNT(*) FROM machines")->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode($debug, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

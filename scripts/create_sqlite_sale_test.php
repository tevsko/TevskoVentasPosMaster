<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Uuid.php';
$db = Database::getInstance()->getConnection();

// Ensure branch and machine exist
$branchStmt = $db->prepare("SELECT id FROM branches LIMIT 1");
$branchStmt->execute();
$branch = $branchStmt->fetchColumn();
if (!$branch) {
    $branch = Uuid::generate();
    $db->prepare("INSERT OR IGNORE INTO branches (id, name, status) VALUES (?, ?, 1)")->execute([$branch, 'Test Branch']);
}

$machineStmt = $db->prepare("SELECT id FROM machines LIMIT 1");
$machineStmt->execute();
$machine = $machineStmt->fetchColumn();
if (!$machine) {
    $machine = Uuid::generate();
    $db->prepare("INSERT OR IGNORE INTO machines (id, name, price, branch_id, active) VALUES (?, ?, ?, ? ,1)")
       ->execute([$machine, 'Test Machine', 10.5, $branch]);
}

// Create sale
$saleId = Uuid::generate();
$userStmt = $db->prepare("SELECT id FROM users LIMIT 1");
$userStmt->execute();
$user = $userStmt->fetchColumn();
if (!$user) {
    $user = Uuid::generate();
    $hash = password_hash('pass123', PASSWORD_DEFAULT);
    $db->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, created_at, branch_id) VALUES (?, ?, ?, 'admin', datetime('now'), ?)")
       ->execute([$user, 'sqlite_user', $hash, $branch]);
}

$createdAt = date('Y-m-d H:i:s');
$db->prepare("INSERT INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
   ->execute([$saleId, $user, $branch, $machine, 10.50, 'cash', $createdAt]);

$payload = json_encode([
    'id' => $saleId,
    'user_id' => $user,
    'branch_id' => $branch,
    'machine_id' => $machine,
    'amount' => 10.5,
    'payment_method' => 'cash',
    'created_at' => $createdAt
]);

// Ensure next_attempt is slightly in the past to avoid timing races with worker
$db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload, attempts, next_attempt, created_at) VALUES ('sale', ?, ?, 0, datetime('now','-1 second'), datetime('now'))")
   ->execute([$saleId, $payload]);

echo "Inserted sale $saleId and queued it.\n";
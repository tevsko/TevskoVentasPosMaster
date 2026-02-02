<?php
require_once __DIR__ . '/../src/Uuid.php';
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

// Use first user as creator
$user = $db->query('SELECT id, branch_id FROM users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$branch_id = $user['branch_id'] ?? null;
if (!$branch_id) {
    // use first branch
    $branch = $db->query('SELECT id FROM branches LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $branch_id = $branch['id'] ?? null;
}
$saleId = Uuid::generate();
$machine = $db->query('SELECT id FROM machines LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$machineId = $machine['id'] ?? 'M001';
$amount = 123.45;
$method = 'cash';
$createdAt = date('Y-m-d H:i:s');

$stmt = $db->prepare("INSERT INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$saleId, $user['id'], $branch_id, $machineId, $amount, $method, $createdAt]);

$payload = json_encode(['id' => $saleId, 'user_id' => $user['id'], 'branch_id' => $branch_id, 'machine_id' => $machineId, 'amount' => $amount, 'payment_method' => $method, 'created_at' => $createdAt]);
$nextAttempt = $createdAt;
$db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload, attempts, next_attempt, created_at) VALUES ('sale', ?, ?, 0, ?, ?)")->execute([$saleId, $payload, $nextAttempt, $createdAt]);

echo "Sale created: $saleId\n";
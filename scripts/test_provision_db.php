<?php
// scripts/test_provision_db.php
require_once __DIR__ . '/../api/provision.php';
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();

// Ensure there is a plan and subscription
$planId = null;
$stmt = $db->query("SELECT id FROM plans LIMIT 1");
if ($stmt && ($r = $stmt->fetch())) {
    $planId = $r['id'];
} else {
    $db->prepare("INSERT INTO plans (code, name, price, period) VALUES ('trial', 'Trial', 0, 'monthly')")->execute();
    $planId = $db->lastInsertId();
}

// Create a subscription
$db->prepare("INSERT INTO subscriptions (tenant_id, plan_id, status, amount, period) VALUES (NULL, ?, 'pending', 0, 'monthly')")->execute([$planId]);
$subId = $db->lastInsertId();

// Provision with allowed_host
$res = provision_tenant_for_subscription($subId, 'sulocal.tevsko.com.ar');
print_r($res);

// Verify DB
$stmt = $db->prepare("SELECT id, sync_token, allowed_host FROM tenants WHERE id = ? LIMIT 1");
$stmt->execute([$res['tenant']['id']]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Tenant stored: "; print_r($t);

echo "Test complete.\n";
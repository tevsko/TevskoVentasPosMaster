<?php
// scripts/test_provision_page.php
// Simulates visiting the provisioning page by calling provisioning.php with a secret
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

// Create a dummy subscription + tenant using existing helper
$stmt = $db->prepare("INSERT INTO subscriptions (tenant_id, plan_id, status, amount, period) VALUES (NULL, 1, 'pending', 0, 'monthly')");
$stmt->execute();
$subId = $db->lastInsertId();
require_once __DIR__ . '/../api/provision.php';
$res = provision_tenant_for_subscription($subId, 'sulocal.tevsko.com.ar');
if (empty($res['provisioning_url'])) {
    echo "No provisioning URL generated\n";
    print_r($res);
    exit(1);
}
// Extract secret from URL and emulate accessing provisioning.php
$url = $res['provisioning_url'];
$parts = parse_url($url);
parse_str($parts['query'] ?? '', $qs);
$secret = $qs['secret'] ?? null;
if (!$secret) { echo "No secret in provisioning URL\n"; exit(1); }

// Emulate GET
$_GET['secret'] = $secret;
include __DIR__ . '/../provisioning.php';

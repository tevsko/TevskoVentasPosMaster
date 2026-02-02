<?php
// api/check_provision.php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

header('Content-Type: application/json');
$subId = $_GET['subscription_id'] ?? null;
if (!$subId) { echo json_encode(['ok'=>false,'error'=>'subscription_id required']); exit; }

$stmt = $db->prepare("SELECT provision_secret FROM subscriptions WHERE id = ? LIMIT 1");
$stmt->execute([$subId]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { echo json_encode(['ok'=>false,'error'=>'subscription not found']); exit; }

if (!empty($s['provision_secret'])) {
    $siteStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
    $siteStmt->execute();
    $site = $siteStmt->fetchColumn();
    $url = $site ? rtrim($site, '/') . '/provisioning.php?secret=' . $s['provision_secret'] : null;

    // If user has session pending flag for this subscription, clear it
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!empty($_SESSION['pending_subscription_id']) && $_SESSION['pending_subscription_id'] == $subId) {
        unset($_SESSION['pending_subscription_id']);
    }

    echo json_encode(['ok'=>true,'provisioned'=>true,'provisioning_url'=>$url]);
    exit;
}

echo json_encode(['ok'=>true,'provisioned'=>false]);

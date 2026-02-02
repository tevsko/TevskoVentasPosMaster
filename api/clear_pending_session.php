<?php
// api/clear_pending_session.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$subId = $_GET['subscription_id'] ?? null;
if ($subId && !empty($_SESSION['pending_subscription_id']) && $_SESSION['pending_subscription_id'] == $subId) {
    unset($_SESSION['pending_subscription_id']);
}
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);

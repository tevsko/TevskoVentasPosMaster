<?php
// api/check_device_license.php
// Check if a device license is valid and active

require_once __DIR__ . '/../src/Database.php';

header('Content-Type: application/json');

$deviceId = $_GET['device_id'] ?? $_POST['device_id'] ?? '';

if (!$deviceId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'device_id required']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Get device license
$stmt = $db->prepare("SELECT * FROM device_licenses WHERE device_id = ? LIMIT 1");
$stmt->execute([$deviceId]);
$license = $stmt->fetch();

if (!$license) {
    echo json_encode([
        'ok' => false,
        'error' => 'Device not registered',
        'status' => 'not_registered'
    ]);
    exit;
}

// Check expiration
$now = new DateTime();
$expiresAt = $license['expires_at'] ? new DateTime($license['expires_at']) : null;

$status = 'active';
$daysRemaining = null;

if ($expiresAt) {
    $interval = $now->diff($expiresAt);
    $daysRemaining = (int)$interval->format('%r%a');
    
    if ($daysRemaining < 0) {
        $status = 'expired';
    } elseif ($daysRemaining <= 7) {
        $status = 'expiring_soon';
    }
}

// Update last_seen
$stmt = $db->prepare("UPDATE device_licenses SET last_seen_at = " . Database::nowSql() . " WHERE device_id = ?");
$stmt->execute([$deviceId]);

echo json_encode([
    'ok' => true,
    'device_id' => $license['device_id'],
    'device_name' => $license['device_name'],
    'device_role' => $license['device_role'],
    'license_type' => $license['license_type'],
    'status' => $status,
    'license_status' => $license['status'],
    'expires_at' => $license['expires_at'],
    'days_remaining' => $daysRemaining,
    'monthly_fee' => $license['monthly_fee'],
    'payment_period' => $license['payment_period']
]);

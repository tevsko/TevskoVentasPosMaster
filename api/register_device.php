<?php
// api/register_device.php
// Register or validate a device license

require_once __DIR__ . '/../src/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

// Get authorization token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$syncToken = str_replace('Bearer ', '', $authHeader);

if (!$syncToken) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authorization token required']);
    exit;
}

// Get device info
$deviceId = $_POST['device_id'] ?? '';
$deviceName = $_POST['device_name'] ?? 'Unknown PC';
$deviceRole = $_POST['device_role'] ?? 'master';

if (!$deviceId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'device_id required']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Find tenant by sync token
$stmt = $db->prepare("SELECT id FROM tenants WHERE sync_token = ? LIMIT 1");
$stmt->execute([$syncToken]);
$tenant = $stmt->fetch();

if (!$tenant) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

$tenantId = $tenant['id'];

// Get plan info
$stmt = $db->prepare("
    SELECT p.pos_included, p.pos_extra_monthly_fee, p.pos_extra_annual_fee 
    FROM subscriptions s 
    JOIN plans p ON p.id = s.plan_id 
    WHERE s.tenant_id = ? AND s.status = 'active' 
    LIMIT 1
");
$stmt->execute([$tenantId]);
$plan = $stmt->fetch();

if (!$plan) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No active subscription found']);
    exit;
}

// Check if device already exists
$stmt = $db->prepare("SELECT * FROM device_licenses WHERE device_id = ? LIMIT 1");
$stmt->execute([$deviceId]);
$existingDevice = $stmt->fetch();

if ($existingDevice) {
    // Update last_seen
    $stmt = $db->prepare("UPDATE device_licenses SET last_seen_at = NOW(), ip_address = ? WHERE device_id = ?");
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $deviceId]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Device already registered',
        'device_role' => $existingDevice['device_role'],
        'license_type' => $existingDevice['license_type'],
        'status' => $existingDevice['status'],
        'expires_at' => $existingDevice['expires_at']
    ]);
    exit;
}

// Count active devices
$stmt = $db->prepare("SELECT COUNT(*) FROM device_licenses WHERE tenant_id = ? AND status = 'active'");
$stmt->execute([$tenantId]);
$activeDevices = (int)$stmt->fetchColumn();

// Determine license type
$licenseType = 'included'; // Master is always included
$monthlyFee = 0.00;
$expiresAt = null;

if ($activeDevices >= $plan['pos_included']) {
    // This is a Slave that requires payment
    echo json_encode([
        'ok' => false,
        'error' => 'POS limit exceeded',
        'requires_payment' => true,
        'pos_included' => $plan['pos_included'],
        'active_devices' => $activeDevices,
        'monthly_fee' => $plan['pos_extra_monthly_fee'],
        'annual_fee' => $plan['pos_extra_annual_fee']
    ]);
    exit;
}

// Get subscription expiry for Master
$stmt = $db->prepare("SELECT ended_at FROM subscriptions WHERE tenant_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$tenantId]);
$subscription = $stmt->fetch();
$expiresAt = $subscription['ended_at'] ?? null;

// Register new device (Master)
$stmt = $db->prepare("
    INSERT INTO device_licenses 
    (tenant_id, device_id, device_name, device_role, license_type, monthly_fee, activated_at, expires_at, ip_address, last_seen_at, status) 
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), 'active')
");

$stmt->execute([
    $tenantId,
    $deviceId,
    $deviceName,
    $deviceRole,
    $licenseType,
    $monthlyFee,
    $expiresAt,
    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
]);

echo json_encode([
    'ok' => true,
    'message' => 'Device registered successfully',
    'device_role' => $deviceRole,
    'license_type' => $licenseType,
    'active_devices' => $activeDevices + 1,
    'pos_included' => $plan['pos_included'],
    'expires_at' => $expiresAt
]);

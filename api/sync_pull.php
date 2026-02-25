<?php
// api/sync_pull.php
// Endpoint para que el cliente descargue productos del servidor
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();

// Parse Authorization: Bearer <token>
$auth = null;
$headers = (function_exists('getallheaders')) ? getallheaders() : [];

if (!empty($headers['Authorization'])) $auth = trim($headers['Authorization']);
elseif (!empty($headers['authorization'])) $auth = trim($headers['authorization']);
elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);

$token = null;
if ($auth && stripos($auth, 'Bearer ') === 0) {
    $token = trim(substr($auth, 7));
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: token required']);
    exit;
}

// Lookup tenant by token
$stmt = $db->prepare("SELECT id, subdomain FROM tenants WHERE sync_token = ? LIMIT 1");
$stmt->execute([$token]);
$tenant = $stmt->fetch();
if (!$tenant) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: invalid token']);
    exit;
}

$tenantId = $tenant['id'];

// Get branch_id for this tenant
$stmt = $db->prepare("SELECT id FROM branches WHERE id IN (SELECT branch_id FROM users WHERE tenant_id = ?) LIMIT 1");
$stmt->execute([$tenantId]);
$branch = $stmt->fetch();

if (!$branch) {
    echo json_encode(['ok' => false, 'error' => 'No branch found for tenant']);
    exit;
}

// Fetch full branch details
$stmt = $db->prepare("SELECT id, tenant_id, name, address, phone, cuit, 
    license_expiry, license_pos_expiry, license_mp_expiry, license_modo_expiry, license_cloud_expiry,
    mp_token, mp_collector_id, mp_status 
    FROM branches WHERE id = ?");
$stmt->execute([$branch['id']]);
$branchData = $stmt->fetch(PDO::FETCH_ASSOC);

$branchId = $branchData['id'];

// Fetch all active products for this branch
$stmt = $db->prepare("SELECT id, name, price, branch_id, active FROM machines WHERE (branch_id = ? OR branch_id IS NULL) AND tenant_id = ?");
$stmt->execute([$branchId, $tenantId]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for this branch (excluding admin)
$stmt = $db->prepare("SELECT id, username, emp_name, emp_email, role, active FROM users WHERE branch_id = ? AND role != 'admin'");
$stmt->execute([$branchId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent sales for reports (last 7 days)
$stmt = $db->prepare("SELECT id, tenant_id, user_id, branch_id, machine_id, amount, payment_method, created_at FROM sales WHERE branch_id = ? AND tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$branchId, $tenantId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok' => true,
    'machines' => $machines,
    'users' => $users,
    'sales' => $sales,
    'branch' => $branchData,
    'branch_id' => $branchId
]);
?>

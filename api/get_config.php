<?php
// api/get_config.php
// Endpoint para que el Cliente Offline descargue su configuración inicial usando el token.

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Database.php';

// Inicializar conexión a base de datos
try {
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['sync_token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token required']);
    exit;
}

try {
    // 1. Validar Token en Tenants
    $stmt = $pdo->prepare("SELECT id, subdomain, business_name FROM tenants WHERE sync_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid Token']);
        exit;
    }

    // 2. Obtener Sucursal (Branch) asociada
    // Asumimos que la primera sucursal creada es la principal o única por ahora.
    // Si tuviéramos tabla branches con tenant_id sería ideal.
    // Por ahora, ActivationService metió todo junto. Vamos a buscar branches vinculadas si existen, 
    // o enviar datos genéricos basados en el tenant.
    
    // NOTA: En ActivationService, creamos la branch y el user pero NO vinculamos la branch al tenant explícitamente en la tabla branches (error de diseño previo?).
    // Revisemos ActivationService... Ah, la tabla `branches` NO tiene tenant_id en el schema original de init_sqlite.php.
    // Pero `users` SÍ tiene `tenant_id`. Y `users` tiene `branch_id`.
    
    // Vamos a buscar el usuario principal de este tenant (admin o branch_manager).
    $stmtUser = $pdo->prepare("SELECT id, username, password_hash, role, branch_id FROM users WHERE tenant_id = ? AND (role = 'admin' OR role = 'branch_manager') ORDER BY role ASC LIMIT 1");
    $stmtUser->execute([$tenant['id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $branch = null;
    if ($user && $user['branch_id']) {
        $stmtBranch = $pdo->prepare("SELECT id, name, license_expiry, license_pos_expiry, license_mp_expiry, license_modo_expiry, license_cloud_expiry, pos_license_limit, pos_title, mp_status FROM branches WHERE id = ? LIMIT 1");
        $stmtBranch->execute([$user['branch_id']]);
        $branch = $stmtBranch->fetch(PDO::FETCH_ASSOC);
    }

    // Responder con los datos para que el cliente se configure
    echo json_encode([
        'status' => 'ok',
        'tenant' => $tenant,
        'user' => $user, // El cliente insertará este usuario admin
        'branch' => $branch
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

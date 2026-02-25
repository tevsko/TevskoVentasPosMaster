<?php
/**
 * API de Autenticación para Módulo Móvil
 * Endpoint: /api/mobile/auth.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/TenantManager.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Detect Tenant from Host
    TenantManager::init($db);
    $tenantId = TenantManager::getTenantId();
    
    if (!$tenantId && !TenantManager::isGlobal()) {
        http_response_code(403);
        echo json_encode(['error' => 'No se pudo identificar el Tenant. Acceda vía su subdominio (ej: cliente.spacepark.com.ar)']);
        exit;
    }

    switch ($action) {
        case 'login':
            handleLogin($db, $input, $tenantId);
            break;
            
        case 'validate_token':
            handleValidateToken($db, $input, $tenantId);
            break;
            
        case 'logout':
            handleLogout($db, $input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

/**
 * Maneja el login de empleados
 */
function handleLogin($db, $input, $tenantId = null) {
    $username = isset($input['username']) ? $input['username'] : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $location_id = isset($input['location_id']) ? $input['location_id'] : null;
    
    if ($tenantId) {
        // Verificar si el modulo esta activo para este tenant
        $stmtCfg = $db->prepare("SELECT enabled FROM mobile_module_config WHERE tenant_id = ?");
        $stmtCfg->execute([$tenantId]);
        $cfg = $stmtCfg->fetch();
        if (!$cfg || !$cfg['enabled']) {
             http_response_code(403);
             echo json_encode(['error' => 'El módulo Arcade Móvil no está activado para este cliente.']);
             return;
        }
    }
    
    // Buscar empleado
    $sql = "SELECT e.*, l.location_name, l.tenant_id 
            FROM arcade_employees e
            INNER JOIN arcade_locations l ON e.location_id = l.id
            WHERE e.username = :username 
            AND e.active = 1 
            AND l.active = 1";
    
    if ($tenantId) {
        $sql .= " AND l.tenant_id = :tenant_id";
    }
    
    if ($location_id) {
        $sql .= " AND e.location_id = :location_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':username', $username);
    if ($tenantId) {
        $stmt->bindParam(':tenant_id', $tenantId);
    }
    if ($location_id) {
        $stmt->bindParam(':location_id', $location_id);
    }
    $stmt->execute();
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
        return;
    }
    
    // Verificar contraseña
    if (!password_verify($password, $employee['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
        return;
    }
    
    // Actualizar último login
    $updateStmt = $db->prepare("UPDATE arcade_employees SET last_login = " . Database::nowSql() . " WHERE id = :id");
    $updateStmt->execute([':id' => $employee['id']]);
    
    // Generar token simple compatible con PHP 5.x
    if (function_exists('random_bytes')) {
        $token = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $token = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $token = md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true));
    }
    
    // Guardar token en sesión (simplificado, en producción usar tabla de tokens)
    session_start();
    $_SESSION['mobile_token'] = $token;
    $_SESSION['mobile_employee_id'] = $employee['id'];
    $_SESSION['mobile_location_id'] = $employee['location_id'];
    $_SESSION['mobile_tenant_id'] = $employee['tenant_id'];
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'token' => $token,
        'employee' => [
            'id' => (int)$employee['id'],
            'username' => $employee['username'],
            'name' => $employee['full_name'],
            'location_id' => (int)$employee['location_id'],
            'location_name' => $employee['location_name'],
            'daily_salary' => (float)$employee['daily_salary']
        ]
    ]);
}

/**
 * Valida un token
 */
function handleValidateToken($db, $input) {
    $token = isset($input['token']) ? $input['token'] : '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token requerido']);
        return;
    }
    
    session_start();
    
    if (!isset($_SESSION['mobile_token']) || $_SESSION['mobile_token'] !== $token) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        return;
    }
    
    // Obtener datos del empleado
    $stmt = $db->prepare("
        SELECT e.*, l.location_name, l.tenant_id 
        FROM arcade_employees e
        INNER JOIN arcade_locations l ON e.location_id = l.id
        WHERE e.id = :id AND e.active = 1
    ");
    $stmt->execute([':id' => $_SESSION['mobile_employee_id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        http_response_code(401);
        echo json_encode(['error' => 'Sesión inválida']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'employee' => [
            'id' => (int)$employee['id'],
            'username' => $employee['username'],
            'name' => $employee['full_name'],
            'location_id' => (int)$employee['location_id'],
            'location_name' => $employee['location_name'],
            'daily_salary' => (float)$employee['daily_salary']
        ]
    ]);
}

/**
 * Cierra sesión
 */
function handleLogout($db, $input) {
    session_start();
    session_destroy();
    
    echo json_encode(['success' => true]);
}

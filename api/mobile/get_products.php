<?php
/**
 * API de Productos para Módulo Móvil
 * Endpoint: /api/mobile/get_products.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/TenantManager.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener parámetros
$token = $_GET['token'] ?? '';
$location_id = $_GET['location_id'] ?? null;

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

try {
    // Validar token
    session_start();
    if (!isset($_SESSION['mobile_token']) || $_SESSION['mobile_token'] !== $token) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Detect Tenant from Host
    TenantManager::init($db);
    $tenantId = TenantManager::getTenantId();
    
    // Validar que el local pertenezca al tenant actual
    $stmtLoc = $db->prepare("SELECT tenant_id FROM arcade_locations WHERE id = ?");
    $stmtLoc->execute([$location_id]);
    $locTenant = $stmtLoc->fetchColumn();
    
    if ($locTenant != $tenantId) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso no autorizado a este local']);
        exit;
    }

    if (!$location_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Location ID requerido']);
        exit;
    }
    
    // Obtener productos activos del local
    $stmt = $db->prepare("
        SELECT 
            id,
            product_name,
            price,
            display_order
        FROM arcade_products
        WHERE location_id = :location_id
        AND active = 1
        ORDER BY display_order ASC, id ASC
    ");
    
    $stmt->execute([':location_id' => $location_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a formato adecuado
    $formattedProducts = array_map(function($product) {
        return [
            'id' => (int)$product['id'],
            'name' => $product['product_name'],
            'price' => (float)$product['price']
        ];
    }, $products);
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

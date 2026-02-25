<?php
/**
 * API de Historial de Reportes para Módulo Móvil
 * Endpoint: /api/mobile/get_reports.php
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
$token = isset($_GET['token']) ? $_GET['token'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;

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
    
    // Usar datos de sesión si no se proporcionan
    if (!$employee_id) {
        $employee_id = isset($_SESSION['mobile_employee_id']) ? $_SESSION['mobile_employee_id'] : null;
    }
    if (!$location_id) {
        $location_id = isset($_SESSION['mobile_location_id']) ? $_SESSION['mobile_location_id'] : null;
    }

    // Validar que el empleado pertenezca al tenant actual
    if ($employee_id) {
        $stmtEmp = $db->prepare("SELECT l.tenant_id FROM arcade_employees e JOIN arcade_locations l ON e.location_id = l.id WHERE e.id = ?");
        $stmtEmp->execute(array($employee_id));
        $empTenant = $stmtEmp->fetchColumn();
        if ($empTenant != $tenantId) {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso no autorizado a este empleado']);
            exit;
        }
    }
    
    // Construir query
    $sql = "
        SELECT 
            r.id,
            r.report_date,
            r.total_sales,
            r.total_payments,
            r.total_expenses,
            r.employee_salary,
            r.expected_cash,
            r.photo_url,
            r.submitted_at,
            r.is_offline_sync,
            e.full_name as employee_name,
            l.location_name
        FROM arcade_daily_reports r
        INNER JOIN arcade_employees e ON r.employee_id = e.id
        INNER JOIN arcade_locations l ON r.location_id = l.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($employee_id) {
        $sql .= " AND r.employee_id = :employee_id";
        $params[':employee_id'] = $employee_id;
    }
    
    if ($location_id) {
        $sql .= " AND r.location_id = :location_id";
        $params[':location_id'] = $location_id;
    }
    
    if ($date_from) {
        $sql .= " AND r.report_date >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if ($date_to) {
        $sql .= " AND r.report_date <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    $sql .= " ORDER BY r.report_date DESC, r.submitted_at DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT :limit";
    }
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear reportes
    $formattedReports = array_map(function($report) {
        return array(
            'id' => (int)$report['id'],
            'date' => $report['report_date'],
            'total_sales' => (float)$report['total_sales'],
            'total_payments' => (float)$report['total_payments'],
            'total_expenses' => (float)$report['total_expenses'],
            'employee_salary' => (float)$report['employee_salary'],
            'expected_cash' => (float)$report['expected_cash'],
            'status' => (float)$report['expected_cash'] >= 0 ? 'positive' : 'negative',
            'has_photo' => !empty($report['photo_url']),
            'photo_url' => $report['photo_url'],
            'submitted_at' => $report['submitted_at'],
            'is_offline_sync' => (bool)$report['is_offline_sync'],
            'employee_name' => $report['employee_name'],
            'location_name' => $report['location_name']
        );
    }, $reports);
    
    echo json_encode(array(
        'success' => true,
        'reports' => $formattedReports,
        'count' => count($formattedReports)
    ));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

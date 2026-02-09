<?php
/**
 * API de Envío de Reportes para Módulo Móvil
 * Endpoint: /api/mobile/submit_report.php
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

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos JSON inválidos']);
    exit;
}

$token = $input['token'] ?? '';
$report_date = $input['report_date'] ?? date('Y-m-d');
$products_sold = $input['products_sold'] ?? [];
$cash_received = $input['cash_received'] ?? 0;
$mercadopago_received = $input['mercadopago_received'] ?? 0;
$transfer_received = $input['transfer_received'] ?? 0;
$expenses = $input['expenses'] ?? [];
$employee_paid = $input['employee_paid'] ?? false;
$photo_base64 = $input['photo_base64'] ?? null;

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
    
    $employee_id = $_SESSION['mobile_employee_id'];
    $location_id = $_SESSION['mobile_location_id'];

    // Validar que el local pertenezca al tenant actual
    $stmtLoc = $db->prepare("SELECT tenant_id FROM arcade_locations WHERE id = ?");
    $stmtLoc->execute([$location_id]);
    $locTenant = $stmtLoc->fetchColumn();
    
    if ($locTenant != $tenantId) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso no autorizado a este local']);
        exit;
    }
    
    // Validar que no exista reporte para esta fecha
    $checkStmt = $db->prepare("
        SELECT id FROM arcade_daily_reports 
        WHERE location_id = :location_id 
        AND report_date = :report_date
    ");
    $checkStmt->execute([
        ':location_id' => $location_id,
        ':report_date' => $report_date
    ]);
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Ya existe un reporte para esta fecha']);
        exit;
    }
    
    // Obtener información del empleado
    // Si viene employee_salary en el input, usar ese (para montos parciales)
    if (isset($input['employee_salary'])) {
        $daily_salary = (float)$input['employee_salary'];
    } else {
        $empStmt = $db->prepare("SELECT daily_salary FROM arcade_employees WHERE id = :id");
        $empStmt->execute([':id' => $employee_id]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        $daily_salary = $employee_paid ? (float)$employee['daily_salary'] : 0;
    }
    
    // Calcular totales
    $total_sales = 0;
    $products_sold_formatted = [];
    
    foreach ($products_sold as $item) {
        $product_id = $item['product_id'] ?? 0;
        $quantity = $item['quantity'] ?? 0;
        
        if ($quantity <= 0) continue;
        
        // Obtener precio del producto
        $prodStmt = $db->prepare("SELECT product_name, price FROM arcade_products WHERE id = :id");
        $prodStmt->execute([':id' => $product_id]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $price = (float)$product['price'];
            $subtotal = $quantity * $price;
            $total_sales += $subtotal;
            
            $products_sold_formatted[] = [
                'product_id' => (int)$product_id,
                'product_name' => $product['product_name'],
                'quantity' => (int)$quantity,
                'price' => $price,
                'total' => $subtotal
            ];
        }
    }
    
    $total_payments = (float)$cash_received + (float)$mercadopago_received + (float)$transfer_received;
    
    $total_expenses = 0;
    $expenses_formatted = [];
    foreach ($expenses as $expense) {
        $amount = (float)($expense['amount'] ?? 0);
        if ($amount > 0) {
            $total_expenses += $amount;
            $expenses_formatted[] = [
                'description' => $expense['description'] ?? 'Sin descripción',
                'amount' => $amount
            ];
        }
    }
    
    $expected_cash = $total_payments - $total_expenses - $daily_salary;
    
    // Validaciones
    if ($total_sales < 0 || $total_payments < 0 || $total_expenses < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Los montos no pueden ser negativos']);
        exit;
    }
    
    if ($total_payments > ($total_sales * 1.1)) { // Margen de 10%
        http_response_code(400);
        echo json_encode(['error' => 'Los pagos recibidos exceden las ventas']);
        exit;
    }
    
    // Procesar foto si existe
    $photo_url = null;
    if ($photo_base64) {
        $photo_url = savePhoto($photo_base64, $location_id, $report_date);
    }
    
    // Insertar reporte
    $insertStmt = $db->prepare("
        INSERT INTO arcade_daily_reports (
            location_id,
            employee_id,
            report_date,
            products_sold,
            total_sales,
            cash_received,
            mercadopago_received,
            transfer_received,
            total_payments,
            expenses,
            total_expenses,
            employee_paid,
            employee_salary,
            expected_cash,
            photo_url,
            device_info,
            is_offline_sync
        ) VALUES (
            :location_id,
            :employee_id,
            :report_date,
            :products_sold,
            :total_sales,
            :cash_received,
            :mercadopago_received,
            :transfer_received,
            :total_payments,
            :expenses,
            :total_expenses,
            :employee_paid,
            :employee_salary,
            :expected_cash,
            :photo_url,
            :device_info,
            :is_offline_sync
        )
    ");
    
    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $is_offline_sync = $input['is_offline_sync'] ?? 0;
    
    $insertStmt->execute([
        ':location_id' => $location_id,
        ':employee_id' => $employee_id,
        ':report_date' => $report_date,
        ':products_sold' => json_encode($products_sold_formatted),
        ':total_sales' => $total_sales,
        ':cash_received' => $cash_received,
        ':mercadopago_received' => $mercadopago_received,
        ':transfer_received' => $transfer_received,
        ':total_payments' => $total_payments,
        ':expenses' => json_encode($expenses_formatted),
        ':total_expenses' => $total_expenses,
        ':employee_paid' => $employee_paid ? 1 : 0,
        ':employee_salary' => $daily_salary,
        ':expected_cash' => $expected_cash,
        ':photo_url' => $photo_url,
        ':device_info' => $device_info,
        ':is_offline_sync' => $is_offline_sync
    ]);
    
    $report_id = $db->lastInsertId();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'report_id' => (int)$report_id,
        'total_sales' => $total_sales,
        'total_payments' => $total_payments,
        'total_expenses' => $total_expenses,
        'employee_salary' => $daily_salary,
        'expected_cash' => $expected_cash,
        'status' => $expected_cash >= 0 ? 'positive' : 'negative'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

/**
 * Guarda la foto en el servidor
 */
function savePhoto($base64Data, $location_id, $report_date) {
    // Decodificar base64
    $data = preg_replace('#^data:image/\w+;base64,#i', '', $base64Data);
    $photoData = base64_decode($data);
    
    if (!$photoData) {
        return null;
    }
    
    // Crear carpeta si no existe
    $uploadDir = __DIR__ . '/../../assets/uploads/arcade/photos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generar nombre único
    $filename = 'report_' . $location_id . '_' . str_replace('-', '', $report_date) . '_' . time() . '.jpg';
    $filepath = $uploadDir . '/' . $filename;
    
    // Guardar archivo
    if (file_put_contents($filepath, $photoData)) {
        return 'assets/uploads/arcade/photos/' . $filename;
    }
    
    return null;
}

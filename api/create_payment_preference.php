<?php
// api/create_payment_preference.php
// Crear preferencia de pago en Mercado Pago para renovación de licencia

header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    $moduleCode = $input['module_code'] ?? null;
    $periodType = $input['period_type'] ?? 'monthly';
    $amount = $input['amount'] ?? 0;
    
    if (!$moduleCode || $amount <= 0) {
        throw new Exception('Datos inválidos');
    }
    
    
    $db = Database::getInstance()->getConnection();
    $driver = Database::getInstance()->getDriver();
    $auth = new Auth();
    $currentUser = $auth->getCurrentUser();
    
    if (!$currentUser) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Obtener tenant_id y branch_id según el contexto
    $tenantId = null;
    $branchId = null;
    
    if ($driver === 'sqlite') {
        // Cliente local: obtener de settings
        $stmt = $db->query("SELECT value FROM settings WHERE key = 'tenant_id'");
        $tenantId = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT value FROM settings WHERE key = 'branch_id'");
        $branchId = $stmt->fetchColumn();
    } else {
        // Servidor web: obtener del usuario
        $tenantId = $currentUser['tenant_id'] ?? null;
        $branchId = $currentUser['branch_id'] ?? null;
        
        // Si no tiene tenant_id, obtener el primero disponible (admin)
        if (!$tenantId) {
            $stmt = $db->query("SELECT id FROM tenants LIMIT 1");
            $tenantId = $stmt->fetchColumn();
        }
        
        // Si no tiene branch_id, obtener la primera sucursal del tenant
        if (!$branchId && $tenantId) {
            $stmt = $db->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $branchId = $stmt->fetchColumn();
        }
    }
    
    if (!$tenantId) {
        throw new Exception('No se pudo determinar el tenant. Contacte al administrador.');
    }
    
    // Obtener información del módulo
    $stmt = $db->prepare("SELECT * FROM module_prices WHERE module_code = ?");
    $stmt->execute([$moduleCode]);
    $module = $stmt->fetch();
    
    if (!$module) {
        throw new Exception('Módulo no encontrado');
    }
    
    // Calcular duración en meses
    $monthsDuration = match($periodType) {
        'monthly' => 1,
        'quarterly' => 3,
        'annual' => 12,
        default => 1
    };
    
    // Obtener configuración de Mercado Pago desde settings (billing.php)
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mp_access_token'");
    $stmt->execute();
    $mpAccessToken = $stmt->fetchColumn();
    
    if (!$mpAccessToken) {
        throw new Exception('Mercado Pago no configurado. Configure en Admin > Configuración > Facturación.');
    }
    
    // Crear registro de pago pendiente
    $stmt = $db->prepare("
        INSERT INTO license_payments 
        (tenant_id, branch_id, user_id, module_code, period_type, months_duration, amount, final_amount, payment_method, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'mercadopago', 'pending')
    ");
    
    $stmt->execute([
        $tenantId,
        $branchId,
        $currentUser['id'],
        $moduleCode,
        $periodType,
        $monthsDuration,
        $amount,
        $amount
    ]);
    
    $paymentId = $db->lastInsertId();
    
    // Crear preferencia en Mercado Pago
    $preferenceData = [
        'items' => [
            [
                'title' => 'Renovación ' . $module['module_name'] . ' - ' . ucfirst($periodType),
                'description' => $module['description'],
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => (float) $amount
            ]
        ],
        'payer' => [
            'name' => $currentUser['username'],
            'email' => $currentUser['email'] ?? 'noreply@spacepark.com'
        ],
        'back_urls' => [
            'success' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/payment_success.php?payment_id=' . $paymentId,
            'failure' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/payment_failure.php?payment_id=' . $paymentId,
            'pending' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/payment_pending.php?payment_id=' . $paymentId
        ],
        'auto_return' => 'approved',
        'external_reference' => 'LICENSE_' . $paymentId,
        'notification_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/mp_webhook_license.php',
        'statement_descriptor' => 'SPACEPARK LICENSE'
    ];
    
    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $mpAccessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        throw new Exception('Error al crear preferencia de pago en Mercado Pago');
    }
    
    $preference = json_decode($response, true);
    
    // Actualizar registro con preference_id
    $stmt = $db->prepare("UPDATE license_payments SET mp_preference_id = ? WHERE id = ?");
    $stmt->execute([$preference['id'], $paymentId]);
    
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentId,
        'preference_id' => $preference['id'],
        'init_point' => $preference['init_point'],
        'sandbox_init_point' => $preference['sandbox_init_point'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

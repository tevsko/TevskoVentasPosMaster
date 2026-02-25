<?php
// api/mp_webhook_license.php
// Webhook de Mercado Pago para renovación de licencias

require_once __DIR__ . '/../src/Database.php';

// Log de webhook para debugging
$logFile = __DIR__ . '/../logs/mp_webhook_license.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'body' => file_get_contents('php://input')
];

@file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

try {
    $db = Database::getInstance()->getConnection();
    
    // Mercado Pago envía notificaciones de diferentes tipos
    $topic = $_GET['topic'] ?? $_POST['topic'] ?? null;
    $id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if ($topic === 'payment' && $id) {
        // Obtener información del pago desde MP
        // Necesitamos el access token del tenant, pero no sabemos cuál es aún
        // Por ahora, buscaremos el pago en nuestra BD por external_reference
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $input['data'] ?? [];
        $paymentId = $data['id'] ?? $id;
        
        // Obtener todos los access tokens activos (temporal, mejorar después)
        $stmt = $db->query("SELECT id, mp_access_token FROM tenants WHERE mp_access_token IS NOT NULL AND mp_access_token != ''");
        $tenants = $stmt->fetchAll();
        
        $paymentInfo = null;
        $usedToken = null;
        
        // Intentar con cada token hasta encontrar el pago
        foreach ($tenants as $tenant) {
            $ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tenant['mp_access_token']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $paymentInfo = json_decode($response, true);
                $usedToken = $tenant['mp_access_token'];
                break;
            }
        }
        
        if (!$paymentInfo) {
            throw new Exception('Pago no encontrado en Mercado Pago');
        }
        
        // Extraer external_reference
        $externalRef = $paymentInfo['external_reference'] ?? null;
        
        if (!$externalRef || !str_starts_with($externalRef, 'LICENSE_')) {
            throw new Exception('External reference inválido');
        }
        
        $licensePaymentId = (int) str_replace('LICENSE_', '', $externalRef);
        
        // Obtener registro de pago
        $stmt = $db->prepare("SELECT * FROM license_payments WHERE id = ?");
        $stmt->execute([$licensePaymentId]);
        $licensePayment = $stmt->fetch();
        
        if (!$licensePayment) {
            throw new Exception('Registro de pago no encontrado');
        }
        
        // Actualizar estado del pago
        $status = $paymentInfo['status'];
        $statusDetail = $paymentInfo['status_detail'] ?? '';
        
        $stmt = $db->prepare("
            UPDATE license_payments 
            SET mp_payment_id = ?, 
                mp_status = ?, 
                mp_status_detail = ?,
                payment_status = ?,
                paid_at = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $paymentStatus = match($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending'
        };
        
        $paidAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            $paymentId,
            $status,
            $statusDetail,
            $paymentStatus,
            $paidAt,
            $licensePaymentId
        ]);
        
        // Si el pago fue aprobado, actualizar la licencia
        if ($status === 'approved') {
            activateLicense($db, $licensePayment);
        }
        
        http_response_code(200);
        echo json_encode(['success' => true]);
        
    } else {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Topic not handled']);
    }
    
} catch (Exception $e) {
    @file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Activar/renovar licencia después de pago aprobado
 */
function activateLicense($db, $licensePayment) {
    // El sistema moderno usa device_payments, no license_payments
    // Buscar el device_license_id desde el license_payment
    // Primero, necesitamos vincular el pago con el dispositivo
    
    // Obtener device_license_id desde el external_reference o desde la relación
    // Por ahora, vamos a buscar el dispositivo por tenant_id y actualizar el que corresponda
    
    $tenantId = $licensePayment['tenant_id'];
    $amount = $licensePayment['final_amount'];
    
    // Buscar device_payments pendiente que coincida con este monto y tenant
    $stmt = $db->prepare("
        SELECT dp.*, dl.id as device_id, dl.payment_period
        FROM device_payments dp
        INNER JOIN device_licenses dl ON dp.device_license_id = dl.id
        WHERE dp.tenant_id = ? 
        AND dp.amount = ?
        AND dp.status = 'pending'
        ORDER BY dp.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $amount]);
    $devicePayment = $stmt->fetch();
    
    if (!$devicePayment) {
        // Log para debugging
        $logFile = __DIR__ . '/../logs/mp_webhook_license.log';
        @file_put_contents($logFile, 
            "WARNING: No matching device_payment found for tenant {$tenantId}, amount {$amount}\n\n", 
            FILE_APPEND
        );
        return; // No throw exception, just log
    }
    
    // Actualizar el device_payment a completed
    $stmt = $db->prepare("UPDATE device_payments SET status = 'completed' WHERE id = ?");
    $stmt->execute([$devicePayment['id']]);
    
    // Calcular nueva fecha de vencimiento
    $periodDays = $devicePayment['payment_period'] === 'annual' ? 365 : 30;
    
    // Obtener fecha de expiración actual
    $stmt = $db->prepare("SELECT expires_at FROM device_licenses WHERE id = ?");
    $stmt->execute([$devicePayment['device_id']]);
    $currentExpiry = $stmt->fetchColumn();
    
    // Si ya tiene fecha de expiración y está en el futuro, extender desde esa fecha
    $baseDate = $currentExpiry && strtotime($currentExpiry) > time() 
        ? $currentExpiry 
        : date('Y-m-d H:i:s');
    
    $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " + {$periodDays} days"));
    
    // Actualizar device_license
    $stmt = $db->prepare("
        UPDATE device_licenses 
        SET expires_at = ?, 
            last_payment_date = NOW(),
            status = 'active',
            payment_status = 'paid'
        WHERE id = ?
    ");
    $stmt->execute([$newExpiry, $devicePayment['device_id']]);
    
    // Log de activación
    $logFile = __DIR__ . '/../logs/mp_webhook_license.log';
    @file_put_contents($logFile, 
        "LICENSE ACTIVATED: Device {$devicePayment['device_id']}, Tenant {$tenantId}, New expiry: {$newExpiry}\n\n", 
        FILE_APPEND
    );
}

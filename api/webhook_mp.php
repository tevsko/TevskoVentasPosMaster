<?php
// api/webhook_mp.php
// Este archivo recibe notificaciones de Mercado Pago en segundo plano

require_once __DIR__ . '/../src/ActivationService.php';

// Leer el JSON que envía MP
$input = file_get_contents('php://input');
$event = json_decode($input, true);

if (!isset($event['type'])) {
    http_response_code(400); // Bad Request
    die('No type');
}

// Solo nos interesa cuando se crea/actualiza un pago
if ($event['type'] === 'payment') {
    $paymentId = $event['data']['id'];

    // Consultar el estado del pago a la API de MP (Opcional pero recomendado para verificar)
    // Para simplificar y no requerir el Token aquí de nuevo (aunque lo ideal es tenerlo),
    // asumimos que si MP nos avisa es "payment", pero DEBERÍAMOS verificar que sea aprobado.
    // Sin embargo, en el payload del webhook básico no viene el status, hay que consultarlo.
    // O... usar "IPN" legacy. 
    // MP Actual usa Notification URL con topic/id o type/data.id.
    
    // OPCION RAPIDA: Obtener Token de BD
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'saas_mp_token'");
        $token = $stmt->fetchColumn();
        
        if ($token) {
            $ch = curl_init("https://api.mercadopago.com/v1/payments/$paymentId");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            
            $payment = json_decode($res, true);
            
            if ($payment['status'] === 'approved') {
                $ref = $payment['external_reference']; // "REG-123"
                if (strpos($ref, 'REG-') === 0) {
                    $pendingId = str_replace('REG-', '', $ref);
                    
                    // ACTIVAR CUENTA
                    ActivationService::activate($pendingId);
                    // (Si ya estaba activada, ActivationService retorna null y no pasa nada malo)
                }
            }
        }
    } catch (Exception $e) {
        // Log error (opcional)
        error_log("Webhook Error: " . $e->getMessage());
    }
}

// Responder OK siempre para que MP deje de insistir
http_response_code(200);
echo "OK";

<?php
// api/payment_success.php
require_once __DIR__ . '/../src/ActivationService.php';
require_once __DIR__ . '/../src/Database.php';

$status = isset($_GET['collection_status']) ? $_GET['collection_status'] : '';
$ref = isset($_GET['external_reference']) ? $_GET['external_reference'] : '';

// Validar que sea approved y que sea un REG- ID
if ($status === 'approved' && strpos($ref, 'REG-') === 0) {
    $pendingId = str_replace('REG-', '', $ref);
    
    try {
        // Intentar activar (o recuperar datos si ya se activó por webhook)
        $result = ActivationService::activate($pendingId);
        
        if ($result) {
            // Activación exitosa en este hilo
            $sub = $result['subdomain'];
            $usr = $result['username'];
            $tok = $result['sync_token'];
            $email = $result['email'];
            
            // Obtener información del plan contratado
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT 
                    p.name as plan_name, 
                    p.period, 
                    p.pos_limit,
                    b.modules,
                    b.license_expiry
                FROM tenants t
                JOIN branches b ON b.tenant_id = t.id
                JOIN users u ON u.tenant_id = t.id AND u.role = 'branch_manager'
                LEFT JOIN pending_signups ps ON ps.subdomain = t.subdomain
                LEFT JOIN plans p ON p.id = JSON_UNQUOTE(JSON_EXTRACT(ps.data, '$.plan_id'))
                WHERE t.subdomain = ?
                LIMIT 1
            ");
            $stmt->execute([$sub]);
            $planData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Preparar datos para success_setup.php
            $params = array(
                'domain' => $sub,
                'user' => $usr,
                'token' => $tok,
                'email' => $email
            );
            
            if ($planData) {
                $params['plan'] = isset($planData['plan_name']) ? $planData['plan_name'] : 'Plan Contratado';
                $params['period'] = isset($planData['period']) ? $planData['period'] : 'monthly';
                $params['pos_count'] = isset($planData['pos_limit']) ? $planData['pos_limit'] : 1;
                $params['expiry'] = isset($planData['license_expiry']) ? $planData['license_expiry'] : date('Y-m-d', strtotime('+1 year'));
                
                // Decodificar módulos (integraciones)
                $modules = $planData['modules'] ? json_decode($planData['modules'], true) : array();
                $params['integrations'] = implode(', ', array_map('strtoupper', $modules));
            }
            
            // Redirigir a Success Setup con todos los datos
            header("Location: ../success_setup.php?" . http_build_query($params));
            exit;

        } else {
            // Ya no existe el pending, ASUMIMOS que se activó por Webhook.
            header("Location: ../login.php?msg=activated_externally");
            exit;
        }

    } catch (Exception $e) {
        // Si hay error (ej: duplicado real), mostrarlo
        die("Error Activando Cuenta: " . $e->getMessage());
    }

} else {
     echo "<h1>Pago Pendiente o Cancelado</h1>";
     echo "<p>Si ya pagó, espere unos instantes o revise su email. Referencia: $ref</p>";
     echo "<a href='../index.php'>Volver</a>";
}

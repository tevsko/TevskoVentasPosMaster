<?php
// src/ActivationService.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class ActivationService {

    /**
     * Activa una cuenta pendiente dado su ID (external_reference)
     * Retorna array con datos de acceso si exitoso, o lanza Exception.
     */
    public static function activate($pendingId) {
        $db = Database::getInstance()->getConnection();
        
        // 1. Buscar datos pendientes
        $stmt = $db->prepare("SELECT * FROM pending_signups WHERE id = ?");
        $stmt->execute([$pendingId]);
        $pending = $stmt->fetch();
        
        if (!$pending) {
            // Verificar si ya fue activado (podríamos buscar por subdomain o email si quisiéramos ser más específicos)
            // Por ahora, asumimos que si no está en pending es porque o no existe o ya se procesó.
            // Para ser robustos, retornamos null indicando "ya no está pendiente".
            return null;
        }
        
        $data = json_decode($pending['data'], true);
        
        try {
            $db->beginTransaction();

            // 2. Subdomain Logic (Idempotency & Collision)
            $subdomain = $data['subdomain'];
            $finalSubdomain = $subdomain;
            $idx = 1;
            while(true) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM tenants WHERE subdomain = ?");
                $stmt->execute([$finalSubdomain]);
                if ($stmt->fetchColumn() == 0) break;
                $finalSubdomain = $subdomain . '-' . $idx; 
                $idx++;
            }
            
            // 3. Crear Tenant
            // Generar Token Seguro
            $syncToken = bin2hex(random_bytes(32)); // 64 chars

            $stmt = $db->prepare("INSERT INTO tenants (subdomain, business_name, status, sync_token) VALUES (?, ?, 'active', ?)");
            $stmt->execute([$finalSubdomain, $data['business_name'], $syncToken]);
            $tenantId = $db->lastInsertId();
            
            // 4. IDs para Branch y User
            if (function_exists('uuid_create')) {
                 $branchId = uuid_create();
                 $userId = uuid_create();
            } else {
                 // Fallback simple
                 $branchId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                 $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            }
            
            // 5. Crear Branch con Licencias Granulares
            // Obtener duración del plan
            $planId = $data['plan_id'] ?? null;
            $months = 12; // Default 1 year
            if ($planId) {
                $stmt = $db->prepare("SELECT period FROM plans WHERE id = ?");
                $stmt->execute([$planId]);
                $p = $stmt->fetch();
                if ($p) {
                    if ($p['period'] == 'monthly') $months = 1;
                    elseif ($p['period'] == 'quarterly') $months = 3;
                    elseif ($p['period'] == 'annual') $months = 12;
                }
            }
            
            $expiryDate = date('Y-m-d', strtotime("+$months months"));
            $posLimit = isset($data['final_pos_limit']) ? (int)$data['final_pos_limit'] : 1;
            
            // Módulos decodificados
            $purchasedModules = $data['modules'] ?? [];
            $modulesJson = json_encode($purchasedModules);
            
            // Licencias específicas
            $licPos = $expiryDate; 
            $licCloud = $expiryDate;
            $licMp = in_array('mp', $purchasedModules) ? $expiryDate : null;
            $licModo = in_array('modo', $purchasedModules) ? $expiryDate : null;
            
            $stmt = $db->prepare("INSERT INTO branches (id, tenant_id, name, status, license_expiry, license_pos_expiry, license_mp_expiry, license_modo_expiry, license_cloud_expiry, pos_license_limit, modules) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $branchId, 
                $tenantId,
                $data['business_name'], 
                $expiryDate, // Base
                $licPos, 
                $licMp, 
                $licModo, 
                $licCloud, 
                $posLimit, 
                $modulesJson
            ]);
            
            // 6. Crear User
            $stmt = $db->prepare("INSERT INTO users (id, tenant_id, username, password_hash, role, branch_id, emp_email, active) VALUES (?, ?, ?, ?, 'branch_manager', ?, ?, 1)");
            $stmt->execute([$userId, $tenantId, $data['username'], $data['password_hash'], $branchId, $data['email']]);
            
            // 7. Borrar Pending
            $stmt = $db->prepare("DELETE FROM pending_signups WHERE id = ?");
            $stmt->execute([$pendingId]);
            
            $db->commit();
            
            // 8. Enviar Email
            self::sendWelcomeEmail($data['email'], $data['username'], $finalSubdomain, $data['business_name'], $syncToken);

            return [
                'subdomain' => $finalSubdomain,
                'username' => $data['username'],
                'email' => $data['email'],
                'sync_token' => $syncToken
            ];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function sendWelcomeEmail($to, $username, $subdomain, $businessName, $syncToken) {
        $subject = "Bienvenido a SpacePark - Activación Exitosa";
        $loginUrl = "http://$subdomain.tevsko.com.ar"; // Ajustar si se usa HTTPS forzado
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #0d6efd; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .code { background: #f8f9fa; padding: 10px; border: 1px dashed #ccc; font-family: monospace; display: block; margin: 10px 0; word-break: break-all; }
                .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>¡Bienvenido a SpacePark!</h2>
                </div>
                <div class='content'>
                    <p>Hola <strong>$username</strong>,</p>
                    <p>Gracias por confiar en SpacePark. Tu cuenta para <strong>$businessName</strong> ha sido activada correctamente.</p>
                    
                    <h3>Detalles de Acceso:</h3>
                    <p><strong>URL de tu Panel:</strong> <a href='$loginUrl'>$loginUrl</a></p>
                    <p><strong>Usuario:</strong> $username</p>
                    <p><strong>Contraseña:</strong> (La que elegiste al registrarte)</p>
                    
                    <h3>🔌 Token de Sincronización</h3>
                    <p>Copia este código y pégalo en la sección 'Nube' de tu programa de escritorio:</p>
                    <span class='code'>$syncToken</span>

                    <a href='$loginUrl' class='btn'>Ingresar al Sistema</a>
                    
                    <p style='margin-top: 30px;'>
                        <strong>¿Necesitas el instalador?</strong><br>
                        Puedes descargar el software de escritorio desde tu panel de control o desde el siguiente enlace:<br>
                        <a href='http://tevsko.com.ar/out/SpaceParkInstaller-1.0.0.exe'>Descargar Instalador Windows</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Si tienes alguna duda, contáctanos a soporte@tevsko.com.ar</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=UTF-8'
        ];
        
        Mailer::sendNow($to, $subject, $body, $headers);
    }
}

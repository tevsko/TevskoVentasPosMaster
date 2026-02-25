<?php
// setup_client.php
// Pantalla de configuración inicial para el cliente offline.
session_start();
require_once 'config/db.php';
require_once 'src/Database.php';

$message = '';
$messageType = 'danger';

// Cargar actuales si existen
$pdo = Database::getInstance()->getConnection();
$currentHost = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'cloud_host'")->fetchColumn() ?: 'http://tevsko.com.ar';
$currentToken = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'sync_token'")->fetchColumn() ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cloudUrl = rtrim($_POST['cloud_url'], '/');
    $token = trim($_POST['sync_token']);

    if (!$cloudUrl || !$token) {
        $message = 'Por favor complete todos los campos.';
    } else {
        // 1. Conectar al servidor para descargar datos
        $apiUrl = $cloudUrl . '/api/get_config.php';
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sync_token' => $token]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Deshabilitar verif SSL si es localhost o http (para pruebas)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['status']) && $data['status'] === 'ok') {
                try {
                    $pdo = Database::getInstance()->getConnection();
                    $pdo->beginTransaction();

                    // 2. Guardar Configuración (Token y URL)
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->execute(['sync_token', $token]);
                    $stmt->execute(['cloud_host', $cloudUrl]);
                    $stmt->execute(['site_name', $data['tenant']['business_name']]);

                    // 3. Insertar/Actualizar Tenant Local
                    // SQLite local 'tenants' table might differ slightly, but let's try.
                    $stmtT = $pdo->prepare("INSERT OR REPLACE INTO tenants (id, subdomain, business_name, sync_token, status) VALUES (?, ?, ?, ?, 'active')");
                    // Note: ID might conflict if auto-increment differs. Ideally we use the server ID or ignore it.
                    // Let's use 1 for local tenant single-tenant mode.
                    $stmtT->execute([1, $data['tenant']['subdomain'], $data['tenant']['business_name'], $token]);

                    // 4. Insertar Sucursal con todas sus licencias y configs
                    if (!empty($data['branch'])) {
                        $stmtB = $pdo->prepare("INSERT OR REPLACE INTO branches (
                            id, name, status, 
                            license_expiry, license_pos_expiry, license_mp_expiry, license_modo_expiry, license_cloud_expiry, 
                            pos_license_limit, pos_title, mp_status
                        ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtB->execute([
                            $data['branch']['id'], 
                            $data['branch']['name'],
                            $data['branch']['license_expiry'] ?? null,
                            $data['branch']['license_pos_expiry'] ?? null,
                            $data['branch']['license_mp_expiry'] ?? null,
                            $data['branch']['license_modo_expiry'] ?? null,
                            $data['branch']['license_cloud_expiry'] ?? null,
                            $data['branch']['pos_license_limit'] ?? 1,
                            $data['branch']['pos_title'] ?? 'SpacePark POS',
                            $data['branch']['mp_status'] ?? 0
                        ]);
                    }

                    // 5. Insertar Usuario Admin
                    if (!empty($data['user'])) {
                        $stmtU = $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, branch_id, tenant_id, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                        $stmtU->execute([
                            $data['user']['id'],
                            $data['user']['username'],
                            $data['user']['password_hash'], // Hashed password from server
                            $data['user']['role'], // Usar rol real (admin o branch_manager)
                            $data['user']['branch_id'],
                            1 // Tenant ID local
                        ]);
                    }

                    $pdo->commit();
                    
                    // ===== SINCRONIZACIÓN COMPLETA AUTOMÁTICA =====
                    // Después de configurar la estructura básica, descargar TODOS los datos
                    try {
                        // Llamar al endpoint de sincronización completa
                        $syncUrl = $cloudUrl . '/api/sync_pull.php';
                        $ch = curl_init($syncUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        
                        $syncResponse = curl_exec($ch);
                        $syncHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($syncHttpCode === 200) {
                            $syncData = json_decode($syncResponse, true);
                            if ($syncData && isset($syncData['ok']) && $syncData['ok']) {
                                // Procesar máquinas
                                if (!empty($syncData['machines'])) {
                                    $stmtMachine = $pdo->prepare("INSERT OR REPLACE INTO machines (id, name, price, branch_id, active) VALUES (?, ?, ?, ?, ?)");
                                    foreach ($syncData['machines'] as $m) {
                                        $stmtMachine->execute([
                                            $m['id'],
                                            $m['name'],
                                            $m['price'],
                                            $m['branch_id'] ?? null,
                                            $m['active'] ?? 1
                                        ]);
                                    }
                                }
                                
                                
                                // Procesar empleados (solo NUEVOS - no sobrescribir existentes)
                                if (!empty($syncData['users'])) {
                                    foreach ($syncData['users'] as $usr) {
                                        // Verificar si el usuario ya existe
                                        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                                        $checkStmt->execute([$usr['id']]);
                                        $exists = $checkStmt->fetchColumn();
                                        
                                        if (!$exists) {
                                            // Solo insertar si NO existe (para no sobrescribir al admin/gerente)
                                            $stmtEmp = $pdo->prepare("INSERT INTO users (id, username, emp_name, emp_email, role, branch_id, active, password_hash, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                            $tempPass = password_hash('1234', PASSWORD_DEFAULT);
                                            $stmtEmp->execute([
                                                $usr['id'],
                                                $usr['username'],
                                                $usr['emp_name'] ?? '',
                                                $usr['emp_email'] ?? '',
                                                $usr['role'],
                                                $usr['branch_id'] ?? $data['branch']['id'],
                                                $usr['active'] ?? 1,
                                                $tempPass,
                                                1 // tenant_id local
                                            ]);
                                        }
                                    }
                                }
                                
                                // Procesar ventas (últimos 7 días)
                                if (!empty($syncData['sales'])) {
                                    $stmtSale = $pdo->prepare("INSERT OR REPLACE INTO sales (id, tenant_id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                                    foreach ($syncData['sales'] as $sale) {
                                        $stmtSale->execute([
                                            $sale['id'],
                                            $sale['tenant_id'] ?? 1,
                                            $sale['user_id'],
                                            $sale['branch_id'],
                                            $sale['machine_id'],
                                            $sale['amount'],
                                            $sale['payment_method'],
                                            $sale['created_at']
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (Exception $syncError) {
                        // Si falla la sincronización, continuar igual - ya tenemos la estructura básica
                        // Esto no debería bloquear el login
                    }
                    
                    // Éxito: Redirigir al login
                    function safeRedirect($url) {
                        if (!headers_sent()) {
                            header("Location: $url");
                        } else {
                            echo "<script>window.location.href='$url';</script>";
                            echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
                        }
                        exit;
                    }
                    safeRedirect("login.php?msg=setup_ok");

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error guardando datos locales: " . $e->getMessage();
                }
            } else {
                $message = "Error del servidor: " . ($data['error'] ?? 'Respuesta desconocida');
            }
        } else {
            $message = "No se pudo conectar al servidor ($httpCode). Verifique la URL.";
            // Debug
            // $message .= " Resp: " . htmlspecialchars(substr($response, 0, 100));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración Inicial - SpacePark</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .setup-card { max-width: 500px; width: 100%; padding: 2rem; background: white; border-radius: 10px; shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="setup-card shadow">
        <h3 class="text-center mb-4 text-primary">Bienvenido a SpacePark</h3>
        <p class="text-muted text-center mb-4">Para comenzar, ingrese el Token de Sincronización que recibió en su correo electrónico.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">URL del Servidor Nube</label>
                <input type="url" name="cloud_url" class="form-control" placeholder="https://tudominio.com" required value="<?= htmlspecialchars($currentHost) ?>">
                <div class="form-text">La dirección web donde contrató el servicio.</div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Token de Sincronización</label>
                <input type="text" name="sync_token" class="form-control form-control-lg font-monospace" placeholder="Pegue su token aquí..." required value="<?= htmlspecialchars($currentToken) ?>">
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Conectar e Importar Cuenta</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="login.php" class="small text-muted">Saltar configuración (Solo expertos)</a>
        </div>
    </div>
</body>
</html>

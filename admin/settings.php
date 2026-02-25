<?php
// admin/settings.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$message = '';

// Auto-fix: Asegurar que la tabla settings existe
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_mp') {
        // Health Check Mercado Pago
        $token = $_POST['mp_token'];
        if (!$token) {
            $message = "Error: Token vacío.";
        } else {
            $ch = curl_init("https://api.mercadopago.com/v1/payment_methods");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200) {
                $message = "¡Conexión MP Exitosa! El token es válido.";
            } else {
                $message = "Error de Conexión MP (Código $code). Verifique el token.";
            }
        }
    } elseif ($action === 'test_saas_mp') {
        // Test SaaS Token (Creating a dummy preference)
        $token = $_POST['saas_mp_token'];
        if (!$token) {
            $message = "Error: Token SaaS vacío.";
        } else {
            $url = "https://api.mercadopago.com/checkout/preferences";
            $data = [
                "items" => [[
                    "title" => "Test de Conectividad",
                    "quantity" => 1,
                    "currency_id" => "ARS",
                    "unit_price" => 1
                ]]
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . trim($token),
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 201 || $code == 200) {
                $message = "¡Conexión SaaS Exitosa! El token tiene permisos para cobrar.";
            } else {
                $json = json_decode($res, true);
                $errMsg = $json['message'] ?? 'Error desconocido';
                $message = "Error SaaS ($code): $errMsg. Revise permisos en MP Developers.";
            }
        }
    } elseif ($action === 'test_cloud') {
        // Health Check Cloud Sync (API)
        $host = rtrim($_POST['cloud_host'], '/');
        $token = $_POST['sync_token'];

        if (!$host || !$token) {
            $message = "Error: Faltan datos (URL o Token).";
        } else {
            // Test API Connection
            $url = "$host/api/sync_ingest.php"; // Endpoint
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Send empty entries list; server should reply "ok": false, "error": "No entries" but NOT 401 Unauthorized
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['entries' => []]));
            
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 || $code === 400 || ($code === 200 && strpos($res, 'No entries') !== false)) {
                // If code is 200, it means auth passed. Even if it says 'No entries', it's connected.
                // sync_ingest.php returns 200 OK + json error if no entries.
                // It returns 401 if token is invalid.
                if (strpos($res, 'Unauthorized') !== false) {
                     $message = "Error: Token Inválido o Rechazado por el servidor.";
                } else {
                     $message = "¡Conexión Nube Exitosa! Token válido.";
                }
            } else {
                $message = "Error de Conexión ($code). Verifique la URL.\nRespuesta: " . substr($res, 0, 100);
            }
        }
    } elseif ($action === 'reset_sync') {
        // Reset de Sincronización (Solo para Clientes Offline)
        try {
            $db->exec("DELETE FROM settings WHERE setting_key IN ('cloud_host', 'sync_token')");
            $message = "Configuración de sincronización eliminada. La próxima vez que abra el sistema se pedirá el Token.";
        } catch (PDOException $e) {
            $message = "Error al reiniciar: " . $e->getMessage();
        }
    } else {
        // Guardar Configuración (Acción por defecto)
        $settings = [
            'cloud_host' => $_POST['cloud_host'],
            'sync_token' => $_POST['sync_token'], // New Token
            // 'cloud_db'   => $_POST['cloud_db'],  // Removed
            // 'cloud_user' => $_POST['cloud_user'], // Removed
            // 'cloud_pass' => $_POST['cloud_pass'], // Removed
            'mp_token'   => $_POST['mp_token'],
            'saas_mp_token' => $_POST['saas_mp_token'],
            'mp_status'  => $_POST['mp_status'] ?? '0',
            // MODO
            'modo_status' => $_POST['modo_status'] ?? '0',
            'modo_client_id' => $_POST['modo_client_id'] ?? '',
            'modo_client_secret' => $_POST['modo_client_secret'] ?? '',
            'modo_store_id' => $_POST['modo_store_id'] ?? '',
            // SMTP
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_secure' => $_POST['smtp_secure'] ?? 'tls',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'mail_from' => $_POST['mail_from'] ?? '',
            'mail_from_name' => $_POST['mail_from_name'] ?? 'SpacePark'
        ];

        try {
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $key => $val) {
                $stmt->execute([$key, $val]);
            }
            $message = "Configuración guardada correctamente.";
        } catch (PDOException $e) {
            $message = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Cargar configs actuales
$current = [];
$rows = $db->query("SELECT * FROM settings")->fetchAll();
foreach ($rows as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}
$val = fn($k) => $current[$k] ?? '';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Configuraciones Generales</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="settingsForm">
    
    <!-- TABS NAVIGATION -->
    <ul class="nav nav-tabs mb-4" id="settingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cloud-tab" data-bs-toggle="tab" data-bs-target="#cloud" type="button" role="tab" aria-selected="true">
                <i class="bi bi-cloud me-2"></i> Sincronización en Nube
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mp-tab" data-bs-toggle="tab" data-bs-target="#mp" type="button" role="tab" aria-selected="false">
                <i class="bi bi-qr-code-scan me-2"></i> Mercado Pago
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="modo-tab" data-bs-toggle="tab" data-bs-target="#modo" type="button" role="tab" aria-selected="false">
                <i class="bi bi-wallet2 me-2"></i> MODO
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab" aria-selected="false">
                <i class="bi bi-envelope me-2"></i> Correo SMTP
            </button>
        </li>
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content" id="myTabContent">
        
        <!-- NUBE -->
        <div class="tab-pane fade show active" id="cloud" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-7">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">Sincronización en Nube</h5>
                            <p class="text-muted small mb-4">Ingrese el Token de Sincronización provisto en el email de bienvenida o en su panel web.</p>
                            
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">URL del Servidor (Nube)</label>
                                    <input type="text" name="cloud_host" class="form-control" value="<?= htmlspecialchars($val('cloud_host')) ?>" placeholder="Ej: http://tevsko.com.ar">
                                    <div class="form-text">La dirección web de su sistema central.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-success">Token de Sincronización</label>
                                    <input type="text" name="sync_token" class="form-control font-monospace" value="<?= htmlspecialchars($val('sync_token') ?? '') ?>" placeholder="Pegue aquí el código largo...">
                                </div>
                            </div>
        
                            <div class="mt-4 pt-3 border-top d-flex gap-2">
                                 <button type="submit" name="action" value="test_cloud" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-repeat"></i> Probar Conexión Nube
                                </button>
                                <?php if ($val('sync_token')): ?>
                                <button type="submit" name="action" value="reset_sync" class="btn btn-outline-danger" onclick="return confirm('¿Está seguro de que desea desvincular este equipo? La base de datos local se mantendrá pero el equipo dejará de sincronizar hasta que vuelva a poner el token.')">
                                    <i class="bi bi-trash"></i> Reiniciar Configuración
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php 
                $driver = \Database::getInstance()->getDriver();
                if ($driver === 'sqlite'): 
                ?>
                <div class="col-md-5">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Estado del Sincronismo</h5>
                            <div class="list-group list-group-flush small">
                                <?php
                                $queueCount = $db->query("SELECT COUNT(*) FROM sync_queue")->fetchColumn();
                                $logCount = $db->query("SELECT COUNT(*) FROM sync_logs")->fetchColumn();
                                $lastSync = $db->query("SELECT last_sync FROM sync_logs ORDER BY created_at DESC LIMIT 1")->fetchColumn();
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Pendientes de Subida
                                    <span class="badge bg-<?= $queueCount > 0 ? 'warning' : 'success' ?> rounded-pill"><?= $queueCount ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Última Sincronización
                                    <span class="text-muted"><?= $lastSync ?: 'Nunca' ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Total de Operaciones Loggeadas
                                    <span><?= $logCount ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Últimos Logs de Sincronización:</h6>
                                <div style="max-height: 200px; overflow-y: auto;" class="border rounded p-2 bg-light">
                                    <?php
                                    $logs = $db->query("SELECT status, details, created_at FROM sync_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();
                                    foreach ($logs as $l):
                                        $color = ($l['status'] == 'success') ? 'text-success' : 'text-danger';
                                    ?>
                                        <div class="mb-2 border-bottom pb-1">
                                            <div class="d-flex justify-content-between">
                                                <strong class="<?= $color ?>"><?= strtoupper($l['status']) ?></strong>
                                                <span class="x-small text-muted"><?= $l['created_at'] ?></span>
                                            </div>
                                            <div class="text-truncate" title="<?= htmlspecialchars($l['details']) ?>"><?= htmlspecialchars($l['details']) ?></div>
                                        </div>
                                    <?php endforeach; if (!$logs) echo "<p class='text-muted italic'>No hay logs aún.</p>"; ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Sincronización Manual:</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="syncPush()">
                                        <i class="bi bi-cloud-upload"></i> Subir Cambios al Servidor
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" onclick="syncPull()">
                                        <i class="bi bi-cloud-download"></i> Descargar Productos del Servidor
                                    </button>
                                </div>
                                <div id="syncResult" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MERCADO PAGO -->
        <div class="tab-pane fade" id="mp" role="tabpanel">
             <!-- ... existing MP content ... -->
             <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <!-- ... MP CONTENT ... -->
                    <h5 class="card-title text-info mb-3">Integración Cobros QR</h5>
                    <p class="text-muted small mb-4">Ingrese su Access Token de producción para integrar los cobros QR.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Estado Integración</label>
                        <select name="mp_status" class="form-select w-50">
                            <option value="1" <?= $val('mp_status') == '1' ? 'selected' : '' ?>>Activo (Mostrar en POS)</option>
                            <option value="0" <?= $val('mp_status') != '1' ? 'selected' : '' ?>>Inactivo (Ocultar)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Access Token (POS QR - Integración Puntos de Venta)</label>
                        <textarea name="mp_token" class="form-control" rows="2" placeholder="APP_USR-xxxxxxxx..."><?= htmlspecialchars($val('mp_token')) ?></textarea>
                        <div class="form-text">Este token se usa para que las sucursales cobren con QR.</div>
                    </div>

                    <hr>

                    <h5 class="card-title text-success mb-3">Cobro de Suscripciones (SaaS)</h5>
                    <p class="text-muted small">Configure aquí las credenciales para cobrar los planes a sus clientes (Dueños de Sucursales).</p>
                    
                    <div class="mb-3">
                        <label class="form-label">SaaS Access Token (Cobro de Planes)</label>
                        <textarea name="saas_mp_token" class="form-control" rows="2" placeholder="TEST-xxxxxxxx... o APP_USR-xxxxxxxx..."><?= htmlspecialchars($val('saas_mp_token')) ?></textarea>
                        <div class="form-text">Token de la cuenta de Mercado Pago donde USTED recibirá el dinero de los planes.</div>
                        <button type="submit" name="action" value="test_saas_mp" class="btn btn-sm btn-outline-success mt-2">
                            <i class="bi bi-check-circle"></i> Verificar Permisos de Cobro
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODO -->
        <div class="tab-pane fade" id="modo" role="tabpanel">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-primary mb-3">Integración MODO</h5>
                    <p class="text-muted small mb-4">Ingrese sus credenciales de MODO para permitir cobros con esta billetera.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Estado Integración</label>
                        <select name="modo_status" class="form-select w-50">
                            <option value="1" <?= $val('modo_status') == '1' ? 'selected' : '' ?>>Activo (Mostrar en POS)</option>
                            <option value="0" <?= $val('modo_status') != '1' ? 'selected' : '' ?>>Inactivo (Ocultar)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Client ID</label>
                        <input type="text" name="modo_client_id" class="form-control" value="<?= htmlspecialchars($val('modo_client_id')) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Client Secret</label>
                        <input type="password" name="modo_client_secret" class="form-control" value="<?= htmlspecialchars($val('modo_client_secret')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Store ID (Sucursal)</label>
                        <input type="text" name="modo_store_id" class="form-control" value="<?= htmlspecialchars($val('modo_store_id')) ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SMTP EMAIL -->
        <div class="tab-pane fade" id="smtp" role="tabpanel">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-primary mb-3">Configuración de Correo SMTP</h5>
                    <p class="text-muted small mb-4">Configure el servidor SMTP para enviar correos de bienvenida y notificaciones a sus clientes.</p>
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Host SMTP</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($val('smtp_host')) ?>" placeholder="mail.tevsko.com.ar">
                            <div class="form-text">Servidor de correo saliente (ej: mail.tudominio.com)</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($val('smtp_port') ?: '587') ?>" placeholder="587">
                            <div class="form-text">587 (TLS) o 465 (SSL)</div>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Seguridad</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= $val('smtp_secure') == 'tls' || !$val('smtp_secure') ? 'selected' : '' ?>>TLS (Recomendado - Puerto 587)</option>
                                <option value="ssl" <?= $val('smtp_secure') == 'ssl' ? 'selected' : '' ?>>SSL (Puerto 465)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Usuario SMTP</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($val('smtp_username')) ?>" placeholder="no-reply@tevsko.com.ar">
                            <div class="form-text">Cuenta de correo para autenticación</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Contraseña SMTP</label>
                            <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($val('smtp_password')) ?>" placeholder="••••••••">
                            <div class="form-text">Contraseña de la cuenta de correo</div>
                        </div>
                        
                        <div class="col-md-8">
                            <label class="form-label">Correo Remitente</label>
                            <input type="email" name="mail_from" class="form-control" value="<?= htmlspecialchars($val('mail_from')) ?>" placeholder="no-reply@tevsko.com.ar">
                            <div class="form-text">Dirección que aparecerá como remitente</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Nombre Remitente</label>
                            <input type="text" name="mail_from_name" class="form-control" value="<?= htmlspecialchars($val('mail_from_name') ?: 'SpacePark') ?>" placeholder="SpacePark">
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="mb-3">Probar Configuración</h6>
                        <div class="input-group">
                            <input type="email" id="testEmailAddress" class="form-control" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($val('mail_from')) ?>">
                            <button type="button" class="btn btn-outline-primary" onclick="testSMTP()">
                                <i class="bi bi-send"></i> Enviar Email de Prueba
                            </button>
                        </div>
                        <div id="smtpTestResult" class="mt-2"></div>
                        <div class="form-text">Se enviará un correo de prueba a la dirección especificada</div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Cómo configurar en cPanel</h6>
                        <ol class="mb-0 small">
                            <li>Accede a tu cPanel en CDMON</li>
                            <li>Busca "Cuentas de correo" y crea una nueva (ej: no-reply@tevsko.com.ar)</li>
                            <li>Anota el host SMTP (generalmente mail.tudominio.com)</li>
                            <li>Usa el puerto 587 con TLS o 465 con SSL</li>
                            <li>Ingresa los datos aquí y prueba la conexión</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed-bottom bg-white border-top p-3 shadow text-end" style="z-index: 100;">
        <div class="container-fluid">
            <button type="submit" name="action" value="save" class="btn btn-lg btn-success">
                <i class="bi bi-save me-2"></i> Guardar Cambios
            </button>
        </div>
    </div>
</form>

<div style="height: 80px;"></div> <!-- Spacer for fixed footer -->

<script>
function syncPush() {
    const btn = event.target;
    const resultDiv = document.getElementById('syncResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';
    
    fetch('../scripts/sync_upload.php')
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                resultDiv.innerHTML = `<div class="alert alert-success alert-sm">✓ Sincronizado: ${data.uploaded || 0} items subidos</div>`;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger alert-sm">✗ Error: ${data.error}</div>`;
            }
        })
        .catch(e => {
            resultDiv.innerHTML = `<div class="alert alert-danger alert-sm">✗ Error: ${e.message}</div>`;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-upload"></i> Subir Cambios al Servidor';
            setTimeout(() => resultDiv.innerHTML = '', 5000);
        });
}

function syncPull() {
    const btn = event.target;
    const resultDiv = document.getElementById('syncResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Descargando...';
    
    fetch('../scripts/sync_pull.php')
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const machineStats = data.machines || {};
                resultDiv.innerHTML = `<div class="alert alert-success alert-sm">✓ Productos: ${machineStats.inserted || 0} nuevos, ${machineStats.updated || 0} actualizados</div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger alert-sm">✗ Error: ${data.error}</div>`;
            }
        })
        .catch(e => {
            resultDiv.innerHTML = `<div class="alert alert-danger alert-sm">✗ Error: ${e.message}</div>`;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download"></i> Descargar Productos del Servidor';
        });
}

function testSMTP() {
    const email = document.getElementById('testEmailAddress').value;
    const resultDiv = document.getElementById('smtpTestResult');
    
    if (!email) {
        resultDiv.innerHTML = '<div class="alert alert-warning alert-sm">Por favor ingrese un correo de destino</div>';
        return;
    }
    
    resultDiv.innerHTML = '<div class="alert alert-info alert-sm"><span class="spinner-border spinner-border-sm me-2"></span>Enviando email de prueba...</div>';
    
    fetch('../api/test_smtp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({email: email})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            resultDiv.innerHTML = '<div class="alert alert-success alert-sm">✓ Email enviado correctamente a ' + email + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger alert-sm">✗ Error: ' + (data.error || 'Desconocido') + '</div>';
        }
    })
    .catch(e => {
        resultDiv.innerHTML = '<div class="alert alert-danger alert-sm">✗ Error de conexión: ' + e.message + '</div>';
    })
    .finally(() => {
        setTimeout(() => resultDiv.innerHTML = '', 8000);
    });
}
</script>

<?php require_once 'layout_foot.php'; ?>

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
    } elseif ($action === 'test_cloud') {
        // Health Check Cloud Sync
        $host = $_POST['cloud_host'];
        $dbname = $_POST['cloud_db'];
        $user = $_POST['cloud_user'];
        $pass = $_POST['cloud_pass'];

        if (!$host || !$dbname || !$user) {
            $message = "Error: Faltan datos de conexión.";
        } else {
            try {
                // Intentar conexión (3 segundos timeout)
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3
                ];
                $testPdo = new PDO($dsn, $user, $pass, $options);
                $message = "¡Conexión Nube Exitosa! Acceso correcto a la BD.";
            } catch (PDOException $e) {
                $message = "Error de Conexión Nube: " . $e->getMessage();
            }
        }
    } else {
        // Guardar Configuración (Acción por defecto)
        $settings = [
            'cloud_host' => $_POST['cloud_host'],
            'cloud_db'   => $_POST['cloud_db'],
            'cloud_user' => $_POST['cloud_user'],
            'cloud_pass' => $_POST['cloud_pass'], 
            'mp_token'   => $_POST['mp_token'],
            'mp_status'  => $_POST['mp_status'] ?? '0'
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
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content" id="myTabContent">
        
        <!-- NUBE -->
        <div class="tab-pane fade show active" id="cloud" role="tabpanel">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title text-primary mb-3">Conexión a Servidor Remoto</h5>
                    <p class="text-muted small mb-4">Configure aquí los datos de su servidor remoto. El sistema intentará sincronizar las ventas automáticamente.</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Host Remoto (IP / Dominio)</label>
                            <input type="text" name="cloud_host" class="form-control" value="<?= htmlspecialchars($val('cloud_host')) ?>" placeholder="Ej: 192.168.1.100 o midominio.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre Base de Datos</label>
                            <input type="text" name="cloud_db" class="form-control" value="<?= htmlspecialchars($val('cloud_db')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario BD</label>
                            <input type="text" name="cloud_user" class="form-control" value="<?= htmlspecialchars($val('cloud_user')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña BD</label>
                            <input type="password" name="cloud_pass" class="form-control" value="<?= htmlspecialchars($val('cloud_pass')) ?>">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                         <button type="submit" name="action" value="test_cloud" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-repeat"></i> Probar Conexión Nube
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MERCADO PAGO -->
        <div class="tab-pane fade" id="mp" role="tabpanel">
             <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
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
                        <label class="form-label">Access Token (Production)</label>
                        <textarea name="mp_token" class="form-control" rows="3" placeholder="APP_USR-xxxxxxxx-xxxx..."><?= htmlspecialchars($val('mp_token')) ?></textarea>
                    </div>

                    <div class="alert alert-warning small mt-3">
                        <i class="bi bi-exclamation-triangle"></i> Asegúrese de que el Token tenga permisos para crear órdenes de pago (QR).
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" name="action" value="test_mp" class="btn btn-outline-info">
                            <i class="bi bi-lightning-charge"></i> Probar Conexión MP
                        </button>
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

<?php require_once 'layout_foot.php'; ?>

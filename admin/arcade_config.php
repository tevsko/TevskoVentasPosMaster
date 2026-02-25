<?php
// admin/arcade_config.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$tenantId = $currentUser['tenant_id'] ?? null;

// Get config from DB
$stmt = $db->prepare("SELECT * FROM mobile_module_config WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$config = $stmt->fetch();

// Default values if not exists
if (!$config) {
    $config = [
        'enabled' => 0,
        'max_locations' => 3,
        'max_employees_per_location' => 2
    ];
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    
    $stmtCheck = $db->prepare("SELECT id FROM mobile_module_config WHERE tenant_id = ?");
    $stmtCheck->execute([$tenantId]);
    if ($stmtCheck->fetch()) {
        $stmt = $db->prepare("UPDATE mobile_module_config SET enabled = ? WHERE tenant_id = ?");
        $stmt->execute([$enabled, $tenantId]);
    } else {
        $stmt = $db->prepare("INSERT INTO mobile_module_config (tenant_id, enabled) VALUES (?, ?)");
        $stmt->execute([$tenantId, $enabled]);
    }
    header("Location: arcade_config.php?saved=1");
    exit;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-gear-fill me-2"></i>Configuración del Módulo Arcade</h1>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Configuración guardada correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">
                Control de Estado
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" id="moduleEnabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="moduleEnabled">Activar Módulo Arcade para este Tenant</label>
                    </div>
                    <p class="small text-muted">
                        Si se desactiva, los empleados no podrán iniciar sesión en la PWA y no se mostrarán las opciones en el menú lateral.
                    </p>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                Acceso a la Aplicación
            </div>
            <div class="card-body text-center">
                <?php
                // Detectar URL base correcta para el tenant
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $subdomain = null;

                if ($tenantId && $driver !== 'sqlite') {
                    $stCheck = $db->prepare("SELECT subdomain FROM tenants WHERE id = ?");
                    $stCheck->execute([$tenantId]);
                    $subdomain = $stCheck->fetchColumn();
                }

                $pwaUrl = "$protocol://$host/mobile/";
                
                if ($subdomain) {
                    // Si ya estamos en el subdominio, perfecto. 
                    // Si no, lo construimos usando el dominio base.
                    if (strpos($host, $subdomain . '.') !== 0) {
                        // Intentar detectar dominio base (ej: tevsko.com.ar de un dev.tevsko.com.ar)
                        $parts = explode('.', $host);
                        if (count($parts) >= 2) {
                            $baseDomain = implode('.', array_slice($parts, -2));
                            if ($baseDomain === 'com.ar' && count($parts) >= 3) {
                                $baseDomain = implode('.', array_slice($parts, -3));
                            }
                            $pwaUrl = "$protocol://$subdomain.$baseDomain/mobile/";
                        }
                    }
                }
                ?>
                <p class="mb-3">Los empleados pueden acceder directamente desde su celular a:</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control text-center bg-light" value="<?= $pwaUrl ?>" readonly id="pwaUrl">
                    <button class="btn btn-outline-secondary" type="button" onclick="copyUrl()">Copiar</button>
                </div>
                
                <div id="qrcode-container" class="d-flex justify-content-center mb-3">
                    <div id="qrcode" class="p-3 border rounded bg-white shadow-sm" style="width: auto; height: auto;">
                        <!-- El QR se generará aquí -->
                    </div>
                </div>
                <p class="small text-muted">Escanea este código o comparte el link para instalar la PWA.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body">
                <h5 class="fw-bold"><i class="bi bi-info-circle-fill me-2 text-primary"></i>¿Cómo funciona?</h5>
                <ol class="small mt-3">
                    <li class="mb-2"><strong>Habilitar Módulo:</strong> Asegúrate de que el estado esté "Activo".</li>
                    <li class="mb-2"><strong>Configurar Locales:</strong> Ve a <a href="arcade_locations.php">Locales</a> y registra tus puntos de venta.</li>
                    <li class="mb-2"><strong>Registrar Empleados:</strong> Crea usuarios para tus empleados móviles.</li>
                    <li class="mb-2"><strong>Instalar PWA:</strong> El empleado abre el link en su navegador móvil y selecciona "Instalar" o "Agregar a pantalla de inicio".</li>
                    <li><strong>Reportar:</strong> Cada día, el empleado carga las fichas vendidas y una foto de su control manuscrito.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Cargar librería QR -->
<script src="../assets/vendor/qrcode/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var pwaUrl = "<?= $pwaUrl ?>";
    new QRCode(document.getElementById("qrcode"), {
        text: pwaUrl,
        width: 180,
        height: 180,
        colorDark: "#1e3a8a",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
});

function copyUrl() {
    var copyText = document.getElementById("pwaUrl");
    copyText.select();
    document.execCommand("copy");
    
    // Feedback visual simple
    const btn = event.target;
    const originalText = btn.innerText;
    btn.innerText = "¡Copiado!";
    btn.classList.replace('btn-outline-secondary', 'btn-success');
    setTimeout(() => {
        btn.innerText = originalText;
        btn.classList.replace('btn-success', 'btn-outline-secondary');
    }, 2000);
}
</script>

<?php require_once 'layout_foot.php'; ?>

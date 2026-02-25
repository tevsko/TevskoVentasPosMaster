<?php
// admin/downloads.php
require_once 'layout_head.php';

// Obtener información del usuario
$username = $currentUser['username'] ?? 'Usuario';
$email = $currentUser['email'] ?? '';
$tenantId = $currentUser['tenant_id'] ?? null;

// Obtener información de licencia del tenant
$licenseKey = 'No asignada';
$planName = 'Plan Básico';
$expiryDate = date('Y-m-d', strtotime('+1 year'));

if ($tenantId && $driver !== 'sqlite') {
    try {
        $stmt = $db->prepare("SELECT license_key, plan_name, expiry_date FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $licenseKey = $tenant['license_key'] ?? $licenseKey;
            $planName = $tenant['plan_name'] ?? $planName;
            $expiryDate = $tenant['expiry_date'] ?? $expiryDate;
        }
    } catch (Exception $e) {
        // Usar valores por defecto
    }
}

$expiryFormatted = date('d/m/Y', strtotime($expiryDate));
$version = '1.0.0';
$downloadBaseUrl = 'https://tevsko.com.ar/downloads/';

// Obtener token de sincronización y URL del servidor
$syncToken = '';
$serverUrl = '';
$isClient = ($driver === 'sqlite'); // Cliente usa SQLite

try {
    if ($isClient) {
        // En cliente: el token se ingresa manualmente, mostrar el almacenado
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sync_token'");
        $stmt->execute();
        $tokenRow = $stmt->fetch();
        if ($tokenRow && $tokenRow['setting_value']) {
            $syncToken = $tokenRow['setting_value'];
        } else {
            $syncToken = '(No configurado aún - Ingrésalo en Setup al abrir SpacePark)';
        }
        
        // URL del servidor nube
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cloud_host'");
        $stmt->execute();
        $urlRow = $stmt->fetch();
        if ($urlRow && $urlRow['setting_value']) {
            $serverUrl = $urlRow['setting_value'];
        } else {
            $serverUrl = '(No configurado aún)';
        }
    } else {
        // En servidor: obtener token del tenant actual
        if ($tenantId) {
            $stmt = $db->prepare("SELECT sync_token FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $tokenRow = $stmt->fetch();
            
            if ($tokenRow && $tokenRow['sync_token']) {
                $syncToken = $tokenRow['sync_token'];
            } else {
                // Generar token nuevo para este tenant si no existe
                $syncToken = bin2hex(random_bytes(32));
                $stmt = $db->prepare("UPDATE tenants SET sync_token = ? WHERE id = ?");
                $stmt->execute([$syncToken, $tenantId]);
            }
        } else {
            $syncToken = '(No hay tenant asignado - contacta soporte)';
        }
        
        // URL del servidor (el actual)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $serverUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
} catch (Exception $e) {
    // Valores por defecto
    if ($isClient) {
        $syncToken = '(Error al leer configuración)';
        $serverUrl = '(Error al leer configuración)';
    } else {
        $syncToken = '(Error: ' . $e->getMessage() . ')';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $serverUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
}


?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="bi bi-download me-2"></i>Descargas de Software</h2>
            <p class="text-muted">Descarga los instaladores de SpacePark para Windows</p>
        </div>
    </div>

    <!-- Información de Licencia -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Tu Licencia</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Clave de Licencia:</small><br>
                            <strong class="font-monospace"><?= htmlspecialchars($licenseKey) ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Plan:</small><br>
                            <strong><?= htmlspecialchars($planName) ?></strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Válida hasta:</small><br>
                            <strong><?= $expiryFormatted ?></strong>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Activa
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuración de Sincronización -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>Configuración de Sincronización</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Usa esta información para configurar la sincronización en otros PCs clientes
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">
                                <i class="bi bi-server me-1"></i>URL del Servidor Nube
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" 
                                       id="serverUrl" readonly 
                                       value="<?= htmlspecialchars($serverUrl) ?>">
                                <button class="btn btn-outline-primary" type="button" 
                                        onclick="copyToClipboard('serverUrl', this)">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                <?php if (strpos($serverUrl, 'https://') === 0): ?>
                                    <i class="bi bi-shield-check text-success me-1"></i>Conexión segura (HTTPS)
                                <?php else: ?>
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>HTTP (funciona pero HTTPS es más seguro)
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">
                                <i class="bi bi-key me-1"></i>Token de Sincronización
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" 
                                       id="syncToken" readonly 
                                       value="<?= htmlspecialchars($syncToken) ?>">
                                <button class="btn btn-outline-primary" type="button" 
                                        onclick="copyToClipboard('syncToken', this)">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-shield-lock me-1"></i>Mantén este token privado
                            </small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-0 mt-2">
                        <strong><i class="bi bi-lightbulb me-2"></i>Consejo:</strong>
                        Haz clic en los botones <i class="bi bi-clipboard"></i> para copiar los valores fácilmente.
                        Necesitarás estos datos al configurar SpacePark en otros PCs.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instaladores Disponibles -->
    <div class="row">
        <!-- Instalador Offline -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-box-seam-fill me-2"></i>
                        Instalador Offline
                        <span class="badge bg-light text-success float-end">Recomendado</span>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Instalación completa sin necesidad de conexión a internet. Incluye todos los archivos necesarios.
                    </p>
                    
                    <ul class="list-unstyled mb-3">
                        <li><i class="bi bi-check-circle text-success me-2"></i>No requiere internet</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Instalación rápida</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Ideal para múltiples PCs</li>
                    </ul>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span><strong>Versión:</strong> <?= $version ?></span>
                        <span><strong>Tamaño:</strong> ~125 MB</span>
                    </div>

                    <a href="<?= $downloadBaseUrl ?>SpaceParkInstaller-<?= $version ?>-Offline.exe" 
                       class="btn btn-success w-100 btn-lg">
                        <i class="bi bi-download me-2"></i>
                        Descargar Offline
                    </a>
                </div>
                <div class="card-footer text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Tiempo estimado de descarga: 2-3 minutos
                </div>
            </div>
        </div>

        <!-- Instalador Online -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-cloud-download me-2"></i>
                        Instalador Online
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Descarga rápida del instalador. Los archivos se descargan durante la instalación desde la nube.
                    </p>
                    
                    <ul class="list-unstyled mb-3">
                        <li><i class="bi bi-check-circle text-info me-2"></i>Descarga rápida (~5 MB)</li>
                        <li><i class="bi bi-check-circle text-info me-2"></i>Siempre actualizado</li>
                        <li><i class="bi bi-exclamation-circle text-warning me-2"></i>Requiere internet estable</li>
                    </ul>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span><strong>Versión:</strong> <?= $version ?></span>
                        <span><strong>Tamaño:</strong> ~5 MB</span>
                    </div>

                    <a href="<?= $downloadBaseUrl ?>SpaceParkInstaller-<?= $version ?>-Online.exe" 
                       class="btn btn-info w-100 btn-lg">
                        <i class="bi bi-cloud-download me-2"></i>
                        Descargar Online
                    </a>
                </div>
                <div class="card-footer text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Descarga archivos adicionales durante instalación
                </div>
            </div>
        </div>
    </div>

    <!-- Instrucciones de Instalación -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Instrucciones de Instalación</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Pasos de Instalación:</h6>
                            <ol>
                                <li>Descarga el instalador de tu preferencia</li>
                                <li>Ejecuta el archivo descargado</li>
                                <li>Sigue las instrucciones del asistente</li>
                                <li>Ingresa tu clave de licencia cuando se solicite</li>
                                <li>¡Listo! Comienza a usar SpacePark</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Requisitos del Sistema:</h6>
                            <ul>
                                <li>Windows 10 o superior (64 bits)</li>
                                <li>4 GB de RAM mínimo</li>
                                <li>500 MB de espacio en disco</li>
                                <li>Conexión a internet (para sincronización)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Soporte -->
    <div class="row mt-4 mb-5">
        <div class="col-md-12">
            <div class="alert alert-info">
                <h6><i class="bi bi-question-circle me-2"></i>¿Necesitas ayuda?</h6>
                <p class="mb-0">
                    Si tienes problemas con la instalación, contacta a nuestro soporte:
                    <strong>Tel: 1135508224</strong> | 
                    <strong>Email: tevsko@gmail.com</strong>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId, button) {
    const input = document.getElementById(elementId);
    input.select();
    input.setSelectionRange(0, 99999); // Para móviles
    
    try {
        document.execCommand('copy');
        
        // Feedback visual
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check"></i>';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    } catch (err) {
        alert('No se pudo copiar: ' + err);
    }
}
</script>

<?php require_once 'layout_foot.php'; ?>


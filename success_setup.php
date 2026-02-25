<?php
// success_setup.php
$domain = isset($_GET['domain']) ? $_GET['domain'] : '';
$username = isset($_GET['user']) ? $_GET['user'] : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';
$plan = isset($_GET['plan']) ? $_GET['plan'] : 'Plan Contratado';
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$posCount = isset($_GET['pos_count']) ? $_GET['pos_count'] : 1;
$expiry = isset($_GET['expiry']) ? $_GET['expiry'] : date('Y-m-d', strtotime('+1 year'));
$integrations = isset($_GET['integrations']) ? $_GET['integrations'] : '';

// Formatear período
$periodsList = array(
    'monthly' => 'Mensual',
    'quarterly' => 'Trimestral',
    'annual' => 'Anual'
);
$periodText = isset($periodsList[$period]) ? $periodsList[$period] : 'Mensual';

// Formatear fecha de expiración
$expiryDate = date('d/m/Y', strtotime($expiry));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Felicitaciones! - SpacePark</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 700px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .celebration-header {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header-icon {
            font-size: 5rem;
            animation: bounce 1s ease-in-out;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .info-card {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .token-box {
            background: #fff3cd;
            border: 2px dashed #ffc107;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .badge-custom {
            font-size: 0.9rem;
            padding: 8px 15px;
        }
    </style>
</head>
<body>

<div class="success-card">
    <!-- Header de Celebración -->
    <div class="celebration-header">
        <i class="bi bi-check-circle-fill header-icon"></i>
        <h1 class="fw-bold mt-3 mb-2">¡Felicitaciones!</h1>
        <p class="lead mb-0">Tu suscripción ha sido activada exitosamente</p>
    </div>
    
    <!-- Contenido Principal -->
    <div class="p-4">
        
        <!-- Información del Plan -->
        <div class="info-card">
            <h5 class="text-primary mb-3"><i class="bi bi-box-seam me-2"></i>Plan Contratado</h5>
            <div class="row g-2">
                <div class="col-md-6">
                    <strong>Plan:</strong> <?= htmlspecialchars($plan) ?>
                </div>
                <div class="col-md-6">
                    <strong>Período:</strong> <span class="badge bg-info"><?= $periodText ?></span>
                </div>
                <div class="col-md-6">
                    <strong>POS Incluidos:</strong> <?= $posCount ?>
                </div>
                <div class="col-md-6">
                    <strong>Vigencia hasta:</strong> <?= $expiryDate ?>
                </div>
                <?php 
                $hasMobile = (strpos($integrations, 'Móvil') !== false || isset($_GET['mobile']));
                if ($integrations || $hasMobile): ?>
                <div class="col-12">
                    <strong>Módulos Activos:</strong> 
                    <?php 
                    $ints = explode(', ', $integrations);
                    if ($hasMobile && !in_array('Móvil Arcade', $ints)) $ints[] = 'Móvil Arcade';
                    foreach ($ints as $int): if(empty($int)) continue; ?>
                        <span class="badge bg-success ms-1"><?= htmlspecialchars($int) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasMobile): ?>
        <!-- Módulo Arcade -->
        <div class="info-card border-warning">
            <h5 class="text-warning mb-3"><i class="bi bi-joystick me-2"></i>Módulo Arcade Móvil Activo</h5>
            <p class="small mb-2">Has incluido el control de Arcades móviles. Acceso para empleados:</p>
            <div class="input-group mb-2">
                <input type="text" class="form-control form-control-sm bg-light" value="http://<?= htmlspecialchars($domain) ?>.tevsko.com.ar/mobile/" readonly>
                <span class="input-group-text small">PWA</span>
            </div>
            <div class="alert alert-warning py-2 mb-0" style="font-size: 0.8rem;">
                <i class="bi bi-info-circle me-1"></i> Instálalo en el celular abriendo el link y seleccionando "Añadir a pantalla de inicio".
            </div>
        </div>
        <?php endif; ?>

        <!-- Datos de Acceso -->
        <div class="info-card">
            <h5 class="text-primary mb-3"><i class="bi bi-person-badge me-2"></i>Datos de Acceso</h5>
            <div class="mb-2">
                <strong>Panel Web (Acceso Universal):</strong><br>
                <a href="login.php?tenant=<?= htmlspecialchars($domain) ?>" class="text-decoration-none fw-bold">
                    http://tevsko.com.ar/login.php?tenant=<?= htmlspecialchars($domain) ?>
                </a>
                <div class="small text-muted mt-1">Lanzamiento inmediato (No requiere configuración DNS)</div>
            </div>
            <div class="mb-2">
                <strong>Link Profesional (Subdominio):</strong><br>
                <a href="http://<?= htmlspecialchars($domain) ?>.tevsko.com.ar" target="_blank" class="text-decoration-none text-muted">
                    http://<?= htmlspecialchars($domain) ?>.tevsko.com.ar
                </a>
            </div>
            <div class="mb-2">
                <strong>Usuario:</strong> <?= htmlspecialchars($username) ?>
            </div>
            <div>
                <strong>Contraseña:</strong> <span class="text-muted fst-italic">(La que elegiste al registrarte)</span>
            </div>
        </div>

        <!-- Token de Sincronización -->
        <div class="mb-4">
            <h5 class="text-success mb-3"><i class="bi bi-cloud-check me-2"></i>Token de Sincronización</h5>
            <p class="text-muted small mb-2">Copia este código en tu cliente de escritorio para activar la sincronización en la nube:</p>
            <div class="token-box">
                <button class="btn btn-sm btn-warning copy-btn" onclick="copyToken()">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
                <div id="tokenText" class="pe-5"><?= htmlspecialchars($token) ?></div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="d-grid gap-2">
            <a href="login.php?tenant=<?= htmlspecialchars($domain) ?>" class="btn btn-primary btn-lg shadow">
                <i class="bi bi-box-arrow-in-right me-2"></i> Ir a mi Panel de Control
            </a>
        </div>
        
        <!-- Descargar Instaladores -->
        <div class="mt-4">
            <h5 class="text-primary mb-3"><i class="bi bi-download me-2"></i>Descargar Cliente Windows</h5>
            
            <!-- Instalador Offline (Recomendado) -->
            <div class="card mb-3 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1">
                                <i class="bi bi-box-seam-fill text-success me-2"></i>
                                Instalador Offline
                                <span class="badge bg-success ms-2">Recomendado</span>
                            </h6>
                            <p class="card-text small text-muted mb-2">
                                Instalación completa sin necesidad de internet. Ideal para instalaciones en sitios sin conexión o con internet lento.
                            </p>
                            <p class="small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Tamaño:</strong> ~125 MB | 
                                <strong>Tiempo:</strong> 2-3 minutos
                            </p>
                        </div>
                    </div>
                    <a href="https://tevsko.com.ar/downloads/SpaceParkInstaller-1.0.0-Offline.exe" 
                       class="btn btn-success w-100 mt-3">
                        <i class="bi bi-download me-2"></i> Descargar Offline
                    </a>
                </div>
            </div>
            
            <!-- Instalador Online (Alternativa) -->
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1">
                                <i class="bi bi-cloud-download text-info me-2"></i>
                                Instalador Online
                            </h6>
                            <p class="card-text small text-muted mb-2">
                                Descarga rápida, archivos se descargan durante la instalación. Requiere conexión a internet estable.
                            </p>
                            <p class="small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Tamaño:</strong> ~5 MB | 
                                <strong>Requiere:</strong> Internet durante instalación
                            </p>
                        </div>
                    </div>
                    <a href="https://tevsko.com.ar/downloads/SpaceParkInstaller-1.0.0-Online.exe" 
                       class="btn btn-outline-info w-100 mt-3">
                        <i class="bi bi-cloud-download me-2"></i> Descargar Online
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Próximos Pasos -->
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="fw-bold mb-2"><i class="bi bi-list-check me-2"></i>Próximos Pasos:</h6>
            <ol class="mb-0 small">
                <li>Descarga e instala el cliente de escritorio</li>
                <li>Ingresa el token de sincronización en la configuración</li>
                <li>¡Comienza a usar SpacePark!</li>
            </ol>
        </div>

        <p class="text-center text-muted small mt-4 mb-0">
            <i class="bi bi-envelope me-1"></i>
            Hemos enviado toda esta información a: <strong><?= htmlspecialchars($email) ?></strong>
        </p>
    </div>
    
    <!-- Footer -->
    <div class="bg-light p-3 text-center small text-muted border-top">
        &copy; <?= date('Y') ?> SpacePark - Todos los derechos reservados
    </div>
</div>

<script>
function copyToken() {
    const tokenText = document.getElementById('tokenText').innerText;
    navigator.clipboard.writeText(tokenText).then(() => {
        const btn = document.querySelector('.copy-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copiado!';
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-warning');
        }, 2000);
    });
}
</script>

</body>
</html>

<?php
// admin/license.php
// Página de gestión de licencia para clientes locales y branch managers

require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$driver = Database::getInstance()->getDriver();

// Obtener branch_id
$branchId = null;
if ($auth->isAdmin()) {
    // Admin puede ver cualquier sucursal, pero por defecto mostramos la primera
    $stmt = $db->query("SELECT id FROM branches LIMIT 1");
    $branchId = $stmt->fetchColumn();
} elseif ($auth->isBranchManager()) {
    $branchId = $currentUser['branch_id'];
}

// Obtener información de la sucursal
if ($branchId) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch();
} else {
    $branch = null;
}

// Funciones de verificación de licencia
function checkLic($date) {
    if (!$date) return false;
    return ($date >= date('Y-m-d'));
}

function licBadge($status, $date) {
    if ($status) return '<span class="badge bg-success">Activa</span> <small class="text-muted">Vence: '.$date.'</small>';
    return '<span class="badge bg-danger">Inactiva/Vencida</span> <small class="text-danger">'.$date.'</small>';
}

// Verificar licencias de módulos
$lic_base = false;
$lic_pos = false;
$lic_mp = false;
$lic_modo = false;
$lic_cloud = false;
$lic_arcade = false;

if ($branch) {
    $lic_base = checkLic($branch['license_expiry']);
    $lic_pos = checkLic($branch['license_pos_expiry']);
    $lic_mp = checkLic($branch['license_mp_expiry'] ?? null);
    $lic_modo = checkLic($branch['license_modo_expiry'] ?? null);
    $lic_cloud = checkLic($branch['license_cloud_expiry'] ?? null);
    $lic_arcade = checkLic($branch['license_arcade_expiry'] ?? null);
}

// Calcular días restantes para cada módulo
function getDaysRemaining($date) {
    if (!$date) return null;
    $expiry = new DateTime($date);
    $today = new DateTime();
    $diff = $today->diff($expiry)->days;
    return ($expiry < $today) ? -$diff : $diff;
}

$days_base = getDaysRemaining($branch['license_expiry'] ?? null);
$days_pos = getDaysRemaining($branch['license_pos_expiry'] ?? null);
$days_mp = getDaysRemaining($branch['license_mp_expiry'] ?? null);
$days_modo = getDaysRemaining($branch['license_modo_expiry'] ?? null);
$days_cloud = getDaysRemaining($branch['license_cloud_expiry'] ?? null);
$days_arcade = getDaysRemaining($branch['license_arcade_expiry'] ?? null);

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="bi bi-award-fill me-2"></i>Gestión de Licencias</h2>
            <p class="text-muted">Estado de tus licencias y módulos de SpacePark</p>
        </div>
    </div>

    <?php if (!$branch): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No se encontró información de sucursal. Contacte al administrador.
        </div>
    <?php else: ?>

    <!-- Licencias de Módulos -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Estado de Licencias por Módulo</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <!-- Acceso Base -->
                        <li class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-person-badge me-2 text-primary"></i>
                                    <strong>Acceso Base (Login)</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_base, $branch['license_expiry']) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_base !== null): ?>
                                        <span class="<?= $days_base < 0 ? 'text-danger' : ($days_base <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_base < 0 ? 'Vencida hace ' . abs($days_base) . ' días' : $days_base . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_base ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('base')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>

                        <!-- Módulo POS -->
                        <li class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-cart me-2 text-success"></i>
                                    <strong>Módulo POS (Ventas)</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_pos, $branch['license_pos_expiry']) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_pos !== null): ?>
                                        <span class="<?= $days_pos < 0 ? 'text-danger' : ($days_pos <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_pos < 0 ? 'Vencida hace ' . abs($days_pos) . ' días' : $days_pos . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_pos ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('pos')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>

                        <!-- Mercado Pago -->
                        <li class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-credit-card me-2 text-info"></i>
                                    <strong>Módulo Mercado Pago</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_mp, $branch['license_mp_expiry']) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_mp !== null): ?>
                                        <span class="<?= $days_mp < 0 ? 'text-danger' : ($days_mp <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_mp < 0 ? 'Vencida hace ' . abs($days_mp) . ' días' : $days_mp . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_mp ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('mercadopago')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>

                        <!-- MODO -->
                        <li class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-credit-card me-2 text-warning"></i>
                                    <strong>Módulo MODO</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_modo, $branch['license_modo_expiry']) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_modo !== null): ?>
                                        <span class="<?= $days_modo < 0 ? 'text-danger' : ($days_modo <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_modo < 0 ? 'Vencida hace ' . abs($days_modo) . ' días' : $days_modo . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_modo ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('modo')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>

                        <!-- Nube -->
                        <li class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-cloud-arrow-up me-2 text-primary"></i>
                                    <strong>Módulo Nube</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_cloud, $branch['license_cloud_expiry']) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_cloud !== null): ?>
                                        <span class="<?= $days_cloud < 0 ? 'text-danger' : ($days_cloud <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_cloud < 0 ? 'Vencida hace ' . abs($days_cloud) . ' días' : $days_cloud . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_cloud ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('nube')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <!-- Arcade PWA -->
                        <li class="list-group-item bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <i class="bi bi-phone me-2 text-warning"></i>
                                    <strong>Módulo Arcade PWA</strong>
                                </div>
                                <div class="col-md-4">
                                    <?= licBadge($lic_arcade, $branch['license_arcade_expiry'] ?? null) ?>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($days_arcade !== null): ?>
                                        <span class="<?= $days_arcade < 0 ? 'text-danger' : ($days_arcade <= 30 ? 'text-warning' : 'text-success') ?>">
                                            <?= $days_arcade < 0 ? 'Vencida hace ' . abs($days_arcade) . ' días' : $days_arcade . ' días restantes' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($driver !== 'sqlite'): ?>
                                    <button class="btn btn-sm <?= !$lic_arcade ? 'btn-danger' : 'btn-primary' ?> ms-2" onclick="openRenewalPage('arcade_pwa')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renovar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Información y Soporte -->
    <div class="row">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h5>
                </div>
                <div class="card-body">
                    <h6>¿Qué incluyen los módulos?</h6>
                    <ul class="small">
                        <li><strong>Acceso Base:</strong> Login y acceso al sistema</li>
                        <li><strong>Módulo POS:</strong> Ventas, productos, reportes</li>
                        <li><strong>Mercado Pago:</strong> Integración de pagos con MP</li>
                        <li><strong>MODO:</strong> Integración de pagos con MODO</li>
                        <li><strong>Nube:</strong> Sincronización y backups automáticos</li>
                        <li><strong>Arcade PWA:</strong> Reporte de ventas móvil para empleados</li>
                    </ul>
                    <hr>
                    <h6>Preguntas Frecuentes</h6>
                    <div class="accordion accordion-flush" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    ¿Puedo renovar módulos individualmente?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body small">
                                    Sí, cada módulo tiene su propia fecha de vencimiento y puede renovarse de forma independiente según tus necesidades.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    ¿Qué pasa si vence un módulo?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body small">
                                    Si vence un módulo, solo perderás acceso a esa funcionalidad específica. Los demás módulos activos seguirán funcionando normalmente.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-headset me-2"></i>Soporte</h5>
                </div>
                <div class="card-body">
                    <p>¿Necesitas ayuda con tus licencias? Contáctanos:</p>
                    <div class="mb-3">
                        <i class="bi bi-telephone-fill me-2 text-success"></i>
                        <strong>Teléfono:</strong> 1135508224
                    </div>
                    <div class="mb-3">
                        <i class="bi bi-envelope-fill me-2 text-success"></i>
                        <strong>Email:</strong> tevsko@gmail.com
                    </div>
                    <div class="mb-3">
                        <i class="bi bi-clock-fill me-2 text-success"></i>
                        <strong>Horario:</strong> Lun-Vie 9:00-18:00
                    </div>
                    <hr>
                    <?php if ($driver !== 'sqlite'): ?>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" onclick="window.open('mailto:tevsko@gmail.com?subject=Consulta sobre Licencias')">
                            <i class="bi bi-envelope me-2"></i>Enviar Email
                        </button>
                        <button class="btn btn-outline-success" onclick="window.open('https://wa.me/541135508224', '_blank')">
                            <i class="bi bi-whatsapp me-2"></i>WhatsApp
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
function openRenewalPage(module) {
    // Solo funciona en servidor web
    window.location.href = 'renew_license.php?module=' + module;
}

// Auto-refresh cada 5 minutos para actualizar estado
setInterval(function() {
    location.reload();
}, 5 * 60 * 1000);
</script>

<?php require_once 'layout_foot.php'; ?>

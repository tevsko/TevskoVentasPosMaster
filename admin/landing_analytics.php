<?php
/**
 * Analytics Dashboard para Landing Page CMS
 * Muestra estadísticas de visitas y contenido activo
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

// Verificar autenticación
$auth = new Auth();
try {
    $auth->requireRole(['admin']);
} catch (Exception $e) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener visitas de los últimos 30 días
$stmt = $db->query("
    SELECT visit_date, visit_count 
    FROM landing_visits 
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY visit_date DESC
");
$visits = $stmt->fetchAll();

// Calcular totales
$totalVisits = array_sum(array_column($visits, 'visit_count'));
$avgDaily = count($visits) > 0 ? round($totalVisits / count($visits), 1) : 0;

// Visitas de hoy
$todayVisits = 0;
if (!empty($visits) && $visits[0]['visit_date'] === date('Y-m-d')) {
    $todayVisits = $visits[0]['visit_count'];
}

// Estadísticas de contenido
$carouselCount = $db->query("SELECT COUNT(*) as c FROM landing_carousel WHERE active = 1")->fetch()['c'];
$carouselTotal = $db->query("SELECT COUNT(*) as c FROM landing_carousel")->fetch()['c'];

$featuresCount = $db->query("SELECT COUNT(*) as c FROM landing_features WHERE active = 1")->fetch()['c'];
$featuresTotal = $db->query("SELECT COUNT(*) as c FROM landing_features")->fetch()['c'];

$testimonialsCount = $db->query("SELECT COUNT(*) as c FROM landing_testimonials WHERE active = 1")->fetch()['c'];
$testimonialsTotal = $db->query("SELECT COUNT(*) as c FROM landing_testimonials")->fetch()['c'];

// Configuración
$whatsappEnabled = $db->query("SELECT setting_value FROM landing_settings WHERE setting_key = 'whatsapp_enabled'")->fetch()['setting_value'] ?? '0';
$popupEnabled = $db->query("SELECT setting_value FROM landing_settings WHERE setting_key = 'popup_enabled'")->fetch()['setting_value'] ?? '0';
$testimonialsEnabled = $db->query("SELECT setting_value FROM landing_settings WHERE setting_key = 'testimonials_enabled'")->fetch()['setting_value'] ?? '1';

require_once 'layout_head.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0">
            <?php require_once 'layout_sidebar.php'; ?>
        </div>
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-graph-up"></i> Analytics - Landing Page</h1>
                <a href="landing_editor.php" class="btn btn-secondary">
                    <i class="bi bi-pencil-square"></i> Ir al Editor
                </a>
            </div>

            <!-- Estadísticas Principales -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-eye-fill text-primary" style="font-size: 2rem;"></i>
                            <h2 class="mt-2 mb-0"><?= number_format($totalVisits) ?></h2>
                            <p class="text-muted mb-0">Visitas (30 días)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-calendar-day text-success" style="font-size: 2rem;"></i>
                            <h2 class="mt-2 mb-0"><?= number_format($todayVisits) ?></h2>
                            <p class="text-muted mb-0">Visitas Hoy</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-graph-up-arrow text-info" style="font-size: 2rem;"></i>
                            <h2 class="mt-2 mb-0"><?= number_format($avgDaily, 1) ?></h2>
                            <p class="text-muted mb-0">Promedio Diario</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <i class="bi bi-calendar-range text-warning" style="font-size: 2rem;"></i>
                            <h2 class="mt-2 mb-0"><?= count($visits) ?></h2>
                            <p class="text-muted mb-0">Días con Visitas</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas de Contenido -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-collection"></i> Contenido Activo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-images text-primary me-3" style="font-size: 2.5rem;"></i>
                                        <div>
                                            <h4 class="mb-0"><?= $carouselCount ?> / <?= $carouselTotal ?></h4>
                                            <p class="text-muted mb-0">Slides de Carousel</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-star-fill text-warning me-3" style="font-size: 2.5rem;"></i>
                                        <div>
                                            <h4 class="mb-0"><?= $featuresCount ?> / <?= $featuresTotal ?></h4>
                                            <p class="text-muted mb-0">Features Activas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-chat-quote-fill text-success me-3" style="font-size: 2.5rem;"></i>
                                        <div>
                                            <h4 class="mb-0"><?= $testimonialsCount ?> / <?= $testimonialsTotal ?></h4>
                                            <p class="text-muted mb-0">Testimonios Activos</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado de Configuración -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-gear-fill"></i> Estado de Configuración</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-whatsapp text-success me-3" style="font-size: 2rem;"></i>
                                        <div>
                                            <h6 class="mb-0">WhatsApp Flotante</h6>
                                            <span class="badge bg-<?= $whatsappEnabled == '1' ? 'success' : 'secondary' ?>">
                                                <?= $whatsappEnabled == '1' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-window text-primary me-3" style="font-size: 2rem;"></i>
                                        <div>
                                            <h6 class="mb-0">Popup Promocional</h6>
                                            <span class="badge bg-<?= $popupEnabled == '1' ? 'success' : 'secondary' ?>">
                                                <?= $popupEnabled == '1' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-chat-left-quote text-warning me-3" style="font-size: 2rem;"></i>
                                        <div>
                                            <h6 class="mb-0">Sección Testimonios</h6>
                                            <span class="badge bg-<?= $testimonialsEnabled == '1' ? 'success' : 'secondary' ?>">
                                                <?= $testimonialsEnabled == '1' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Visitas Diarias -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Visitas Diarias (Últimos 30 días)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($visits)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No hay datos de visitas registrados aún.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Día de la Semana</th>
                                        <th class="text-end">Visitas</th>
                                        <th>Gráfico</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxVisits = max(array_column($visits, 'visit_count'));
                                    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                                    foreach ($visits as $v): 
                                        $fecha = new DateTime($v['visit_date']);
                                        $diaSemana = $dias[$fecha->format('w')];
                                        $porcentaje = $maxVisits > 0 ? ($v['visit_count'] / $maxVisits) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= $fecha->format('d/m/Y') ?></td>
                                        <td><?= $diaSemana ?></td>
                                        <td class="text-end"><strong><?= number_format($v['visit_count']) ?></strong></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-primary" role="progressbar" 
                                                     style="width: <?= $porcentaje ?>%" 
                                                     aria-valuenow="<?= $v['visit_count'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="<?= $maxVisits ?>">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="mt-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="landing_editor.php" class="btn btn-primary">
                                <i class="bi bi-pencil-square"></i> Editar Contenido
                            </a>
                            <a href="../landing.php" class="btn btn-success" target="_blank">
                                <i class="bi bi-eye"></i> Ver Landing Page
                            </a>
                            <a href="landing_editor.php#configuracion" class="btn btn-info">
                                <i class="bi bi-gear"></i> Configuración
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>

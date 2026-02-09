<?php
// admin/arcade_report_detail.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$tenantId = $currentUser['tenant_id'] ?? null;
$reportId = $_GET['id'] ?? null;

if (!$reportId) {
    header('Location: arcade_reports.php');
    exit;
}

// Get Report Detail
$stmt = $db->prepare("SELECT r.*, l.location_name, e.full_name as employee_name 
                      FROM arcade_daily_reports r 
                      JOIN arcade_locations l ON r.location_id = l.id 
                      JOIN arcade_employees e ON r.employee_id = e.id 
                      WHERE r.id = ? AND l.tenant_id = ?");
$stmt->execute([$reportId, $tenantId]);
$report = $stmt->fetch();

if (!$report) {
    echo "<div class='alert alert-danger'>Reporte no encontrado o sin permisos.</div>";
    require_once 'layout_foot.php';
    exit;
}

// Parse JSON data
$products_sold = json_decode($report['products_sold'] ?? '[]', true);
$expenses = json_decode($report['expenses'] ?? '[]', true);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-file-earmark-text me-2"></i>Detalle de Reporte</h1>
    <div>
        <a href="arcade_reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Información General -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-info-circle me-2 text-primary"></i>Información General
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small d-block">Fecha del Reporte</label>
                    <div class="fw-bold fs-5"><?= date('d/m/Y', strtotime($report['report_date'])) ?></div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">Local</label>
                    <div class="fw-bold"><?= htmlspecialchars($report['location_name']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">Empleado</label>
                    <div class="fw-bold"><?= htmlspecialchars($report['employee_name']) ?></div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="text-muted small d-block">Fecha/Hora de Envío</label>
                    <div class="small"><?= date('d/m/Y H:i', strtotime($report['submitted_at'])) ?></div>
                </div>
                <div class="mb-1">
                    <label class="text-muted small d-block">Sincronización Offline</label>
                    <span class="badge <?= $report['is_offline_sync'] ? 'bg-warning text-dark' : 'bg-success' ?>">
                        <?= $report['is_offline_sync'] ? 'SÍ (Sincronizado luego)' : 'NO (En tiempo real)' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Foto del Comprobante -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-camera me-2 text-primary"></i>Foto del Comprobante Manuscrito</span>
                <?php if ($report['photo_url']): ?>
                    <a href="/<?= $report['photo_url'] ?>" target="_blank" class="btn btn-xs btn-outline-primary">Ver tamaño completo</a>
                <?php endif; ?>
            </div>
            <div class="card-body text-center bg-light">
                <?php if ($report['photo_url']): ?>
                    <img src="/<?= $report['photo_url'] ?>" class="img-fluid rounded shadow-sm" style="max-height: 500px; cursor: zoom-in;" onclick="window.open(this.src)">
                <?php else: ?>
                    <div class="py-5 text-muted">
                        <i class="bi bi-camera-off fs-1 d-block mb-3"></i>
                        No se cargó foto en este reporte.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desglose de Ventas -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-cart me-2 text-primary"></i>Productos Vendidos
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products_sold as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td class="text-center"><?= $p['quantity'] ?></td>
                            <td class="text-end text-muted">$<?= number_format($p['price'], 2) ?></td>
                            <td class="text-end fw-bold">$<?= number_format($p['subtotal'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="3" class="text-end">Total Ventas</td>
                            <td class="text-end fw-bold text-success">$<?= number_format($report['total_sales'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen de Pagos y Gastos -->
    <div class="col-md-6">
        <div class="row g-4">
            <!-- Pagos -->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-credit-card me-2 text-primary"></i>Pagos Recibidos
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Efectivo:</span>
                            <span class="fw-bold">$<?= number_format($report['cash_received'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Mercado Pago:</span>
                            <span class="fw-bold">$<?= number_format($report['mercadopago_received'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Transferencia:</span>
                            <span class="fw-bold">$<?= number_format($report['transfer_received'], 2) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between text-primary fw-bold">
                            <span>Total Pagos:</span>
                            <span>$<?= number_format($report['total_payments'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gastos -->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cash-stack me-2 text-primary"></i>Gastos</span>
                        <span class="badge bg-danger">-$<?= number_format($report['total_expenses'], 2) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($expenses)): ?>
                            <div class="p-3 text-center text-muted small">Sin gastos reportados.</div>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($expenses as $g): ?>
                                    <tr class="small">
                                        <td class="ps-3"><?= htmlspecialchars($g['description']) ?></td>
                                        <td class="text-end pe-3 fw-bold">$<?= number_format($g['amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado de Caja -->
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h5 class="mb-1 fw-bold">Control de Caja Final</h5>
                        <p class="text-muted small mb-0">Fórmula: (Efectivo Recibido) - (Gastos) - (Sueldo si se pagó)</p>
                        <?php if ($report['employee_paid']): ?>
                            <span class="badge bg-info mt-2">Sueldo empleado pagado en caja ($<?= number_format($report['employee_salary'], 2) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="bg-white p-3 rounded border shadow-sm d-inline-block">
                            <label class="text-muted small d-block">Efectivo que DEBE haber en caja:</label>
                            <span class="display-6 fw-bold <?= $report['expected_cash'] < 0 ? 'text-danger' : 'text-success' ?>">
                                $<?= number_format($report['expected_cash'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

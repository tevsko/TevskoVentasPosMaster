<?php
// admin/arcade_reports.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$tenantId = $currentUser['tenant_id'] ?? null;

// Get filters from GET
$filter_date_from = $_GET['date_from'] ?? date('Y-m-01');
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');
$filter_location = $_GET['location_id'] ?? '';
$filter_employee = $_GET['employee_id'] ?? '';

// Get locations for filter dropdown
$stmtL = $db->prepare("SELECT id, location_name FROM arcade_locations WHERE tenant_id = ? AND active = 1 ORDER BY location_name ASC");
$stmtL->execute([$tenantId]);
$locations = $stmtL->fetchAll();

// Get employees for filter dropdown
$stmtE = $db->prepare("SELECT e.id, e.full_name FROM arcade_employees e JOIN arcade_locations l ON e.location_id = l.id WHERE l.tenant_id = ? AND e.active = 1 ORDER BY e.full_name ASC");
$stmtE->execute([$tenantId]);
$employees = $stmtE->fetchAll();

// Build Query
$sql = "SELECT r.*, l.location_name, e.full_name as employee_name 
        FROM arcade_daily_reports r 
        JOIN arcade_locations l ON r.location_id = l.id 
        JOIN arcade_employees e ON r.employee_id = e.id 
        WHERE l.tenant_id = ? 
        AND r.report_date BETWEEN ? AND ?";
$params = [$tenantId, $filter_date_from, $filter_date_to];

if ($filter_location) {
    $sql .= " AND r.location_id = ?";
    $params[] = $filter_location;
}

if ($filter_employee) {
    $sql .= " AND r.employee_id = ?";
    $params[] = $filter_employee;
}

$sql .= " ORDER BY r.report_date DESC, r.submitted_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Stats calculation
$total_sales = 0;
$total_expenses = 0;
$total_expected = 0;

foreach ($reports as $r) {
    $total_sales += $r['total_sales'];
    $total_expenses += $r['total_expenses'];
    $total_expected += $r['expected_cash'];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes de Arcade</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Desde</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filter_date_from ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Hasta</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filter_date_to ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Local</label>
                <select name="location_id" class="form-select form-select-sm">
                    <option value="">-- Todos --</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $filter_location == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Empleado</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value="">-- Todos --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filter_employee == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm border-0">
            <div class="card-body">
                <h6 class="card-title opacity-75 small">Total Ventas</h6>
                <h3 class="mb-0">$<?= number_format($total_sales, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm border-0">
            <div class="card-body">
                <h6 class="card-title opacity-75 small">Total Gastos</h6>
                <h3 class="mb-0">$<?= number_format($total_expenses, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body">
                <h6 class="card-title opacity-75 small">Efectivo Esperado</h6>
                <h3 class="mb-0">$<?= number_format($total_expected, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Reportes -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr>
                        <th>Fecha</th>
                        <th>Local</th>
                        <th>Empleado</th>
                        <th>Ventas</th>
                        <th>Gastos</th>
                        <th>Pagos</th>
                        <th>Efectivo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No se encontraron reportes para estos filtros.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($reports as $r): 
                        $badgeClass = $r['expected_cash'] < 0 ? 'bg-danger' : 'bg-success';
                    ?>
                    <tr>
                        <td><strong><?= date('d/m/Y', strtotime($r['report_date'])) ?></strong></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['location_name']) ?></span></td>
                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                        <td class="fw-bold">$<?= number_format($r['total_sales'], 2) ?></td>
                        <td class="text-danger">-$<?= number_format($r['total_expenses'], 2) ?></td>
                        <td class="small">
                            MP: $<?= number_format($r['mercadopago_received'], 0) ?><br>
                            TR: $<?= number_format($r['transfer_received'], 0) ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?>">
                                $<?= number_format($r['expected_cash'], 2) ?>
                            </span>
                        </td>
                        <td>
                            <a href="arcade_report_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

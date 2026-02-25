<?php
// admin/dashboard.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$tenantId = $currentUser['tenant_id'] ?? null;

// --- ESTADISTICAS RAPIDAS ---

// Total Ventas Hoy (using shift logic: 9 AM to now)
$driver = \Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    // SQLite: use shift logic (9 AM start)
    $cur_h = (int)date('H');
    $shift_date = ($cur_h < 9) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $start = $shift_date . ' 09:00:00';
    $end = date('Y-m-d H:i:s');
    $stmt = $db->prepare("SELECT SUM(amount) FROM sales WHERE created_at >= :start AND created_at <= :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $salesToday = $stmt->fetchColumn() ?: 0;
} else {
    // MySQL: use shift logic
    $cur_h = (int)date('H');
    $shift_date = ($cur_h < 9) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    $start = $shift_date . ' 09:00:00';
    $end = date('Y-m-d H:i:s');
    $stmt = $db->prepare("SELECT SUM(amount) FROM sales WHERE created_at >= ? AND created_at <= ? AND tenant_id = ?");
    $stmt->execute([$start, $end, $tenantId]);
    $salesToday = $stmt->fetchColumn() ?: 0;
}

// Sucursales Activas
$stmt = $driver === 'sqlite' ? $db->query("SELECT COUNT(*) FROM branches WHERE status = 1") : $db->prepare("SELECT COUNT(*) FROM branches WHERE status = 1 AND tenant_id = ?");
if ($driver !== 'sqlite') $stmt->execute([$tenantId]);
$activeBranches = $stmt->fetchColumn();

// Empleados
$stmt = $driver === 'sqlite' ? $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee'") : $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'employee' AND tenant_id = ?");
if ($driver !== 'sqlite') $stmt->execute([$tenantId]);
$totalEmployees = $stmt->fetchColumn();

// Máquinas Activas
$stmt = $driver === 'sqlite' ? $db->query("SELECT COUNT(*) FROM machines WHERE active = 1") : $db->prepare("SELECT COUNT(*) FROM machines WHERE active = 1 AND tenant_id = ?");
if ($driver !== 'sqlite') $stmt->execute([$tenantId]);
$activeMachines = $stmt->fetchColumn();

// --- MONITOREO ---

// Usuarios en Línea (Activos en los últimos 10 minutos)
$driver = \Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    $cutoff = date('Y-m-d H:i:s', time() - 10 * 60);
    $stmt = $db->prepare("SELECT id, username, role, last_activity, branch_id FROM users WHERE last_activity >= :cutoff ORDER BY last_activity DESC");
    $stmt->execute([':cutoff' => $cutoff]);
} else {
    $stmt = $db->prepare("SELECT id, username, role, last_activity, branch_id FROM users WHERE last_activity >= NOW() - INTERVAL 10 MINUTE AND tenant_id = ? ORDER BY last_activity DESC");
    $stmt->execute([$tenantId]);
}
$onlineUsers = $stmt->fetchAll();

?>

<!-- Botón de acceso rápido al POS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body py-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="text-white">
                        <h4 class="mb-1"><i class="bi bi-shop-window me-2"></i>Punto de Venta</h4>
                        <p class="mb-0 opacity-75">Accede al sistema de ventas para realizar transacciones</p>
                    </div>
                    <a href="../pos/index.php" class="btn btn-light btn-lg px-5">
                        <i class="bi bi-arrow-right-circle me-2"></i>
                        Abrir POS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- Ventas Hoy -->
    <div class="col-md-3">
        <div class="card card-stat bg-primary text-white p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Ventas Hoy</h6>
                    <h3 class="mb-0">$<?= number_format($salesToday, 2) ?></h3>
                </div>
                <i class="bi bi-cash-coin icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Sucursales -->
    <div class="col-md-3">
        <div class="card card-stat bg-success text-white p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Sucursales</h6>
                    <h3 class="mb-0"><?= $activeBranches ?></h3>
                </div>
                <i class="bi bi-shop icon"></i>
            </div>
        </div>
    </div>

    <!-- Empleados -->
    <div class="col-md-3">
        <div class="card card-stat bg-info text-white p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Empleados</h6>
                    <h3 class="mb-0"><?= $totalEmployees ?></h3>
                </div>
                <i class="bi bi-people icon"></i>
            </div>
        </div>
    </div>

    <!-- Máquinas -->
    <div class="col-md-3">
        <div class="card card-stat bg-warning text-dark p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Máquinas</h6>
                    <h3 class="mb-0"><?= $activeMachines ?></h3>
                </div>
                <i class="bi bi-joystick icon"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- USUARIOS EN LINEA -->
    <div class="col-md-4 order-md-2 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-circle-fill text-warning me-2" style="font-size: 0.6rem; animation: blink 2s infinite;"></i> Usuarios en Línea</h5>
                <span class="badge bg-white text-success rounded-pill"><?= count($onlineUsers) ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($onlineUsers)): ?>
                    <div class="list-group-item text-muted text-center py-4">
                        <i class="bi bi-moon-stars display-6 d-block mb-2"></i>
                        No hay usuarios activos
                    </div>
                <?php else: ?>
                    <?php foreach ($onlineUsers as $u): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">
                                <?= htmlspecialchars($u['username']) ?>
                                <?php if ($u['role'] == 'admin'): ?>
                                    <span class="badge bg-danger ms-1" style="font-size: 0.6em;">ADM</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-clock"></i> <?= date('H:i', strtotime($u['last_activity'])) ?>
                            </small>
                        </div>
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="showLogs('<?= $u['id'] ?>', '<?= $u['username'] ?>')">
                            Ver Actividad
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light text-center small text-muted">
                Actualizado: <?= date('H:i:s') ?>
            </div>
        </div>
    </div>

    <!-- ULTIMAS VENTAS -->
    <div class="col-md-8 order-md-1">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ultimas Ventas (Global)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hora</th>
                                <th>Local</th>
                                <th>Máquina</th>
                                <th>Monto</th>
                                <th>Cajero</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($driver === 'sqlite') {
                                $sql = "SELECT s.created_at, b.name as branch, m.name as machine, s.amount, u.username 
                                        FROM sales s 
                                        LEFT JOIN branches b ON s.branch_id = b.id 
                                        LEFT JOIN machines m ON s.machine_id = m.id 
                                        LEFT JOIN users u ON s.user_id = u.id 
                                        ORDER BY s.created_at DESC LIMIT 8";
                                $stmt = $db->query($sql);
                                $rows = $stmt->fetchAll();
                            } else {
                                $sql = "SELECT s.created_at, b.name as branch, m.name as machine, s.amount, u.username 
                                        FROM sales s 
                                        LEFT JOIN branches b ON s.branch_id = b.id 
                                        LEFT JOIN machines m ON s.machine_id = m.id 
                                        LEFT JOIN users u ON s.user_id = u.id 
                                        WHERE s.tenant_id = ? 
                                        ORDER BY s.created_at DESC LIMIT 8";
                                $stmt = $db->prepare($sql);
                                $stmt->execute([$tenantId]);
                                $rows = $stmt->fetchAll();
                            }
                            
                            if (empty($rows)) {
                                echo "<tr><td colspan='5' class='text-center text-muted py-4'>Sin movimientos recientes</td></tr>";
                            } else {
                                foreach ($rows as $row) {
                                    echo "<tr>";
                                    echo "<td>" . date('H:i', strtotime($row['created_at'])) . "</td>";
                                    echo "<td><span class='badge bg-light text-dark border'>" . htmlspecialchars($row['branch'] ?? 'N/A') . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['machine'] ?? 'N/A') . "</td>";
                                    echo "<td class='fw-bold text-success'>$" . number_format($row['amount'], 2) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Logs -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Actividad: <span id="logUsername" class="fw-bold">...</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hora</th>
                            <th>Acción</th>
                            <th>Detalle</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody">
                        <tr><td colspan="4" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes blink { 50% { opacity: 0; } }
</style>

<script>
    var logModal;
    document.addEventListener('DOMContentLoaded', function() {
        logModal = new bootstrap.Modal(document.getElementById('logModal'));
    });

    function showLogs(userId, username) {
        document.getElementById('logUsername').innerText = username;
        logModal.show();
        
        const tbody = document.getElementById('logTableBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border text-primary"></div></td></tr>';

        fetch('../api/get_user_logs.php?user_id=' + userId)
            .then(response => response.json())
            .then(data => {
                tbody.innerHTML = '';
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">' + data.error + '</td></tr>';
                    return;
                }
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin actividad registrada reciente</td></tr>';
                    return;
                }

                data.forEach(log => {
                    const row = `<tr>
                        <td>${new Date(log.created_at).toLocaleTimeString()}</td>
                        <td><span class="badge bg-secondary">${log.action}</span></td>
                        <td>${log.details || '-'}</td>
                        <td class="small text-muted">${log.ip_address || ''}</td>
                    </tr>`;
                    tbody.innerHTML += row;
                });
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Error al cargar logs</td></tr>';
            });
    }
</script>

<?php require_once 'layout_foot.php'; ?>

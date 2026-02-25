<?php
// admin/branch_view.php
require_once 'layout_head.php';

$branchId = null;

// Determine Branch ID
if ($auth->isAdmin()) {
    $branchId = $_GET['id'] ?? null;
    if (!$branchId) {
        echo "<div class='alert alert-danger'>Error: No se especificó el ID de sucursal. <a href='branches.php'>Volver</a></div>";
        require_once 'layout_foot.php';
        exit;
    }
} elseif ($auth->isBranchManager()) {
    $branchId = $currentUser['branch_id'];
    if (!$branchId) {
        echo "<div class='alert alert-danger'>Error: No tienes una sucursal asignada. Contacte al administrador.</div>";
        require_once 'layout_foot.php';
        exit;
    }
}

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$test_msg = '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only process actions if licenses are valid (although we hide UI, backend check is good)
    // For simplicity, we rely on UI hiding for now, or basic checks.
    
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        $pos_title = $_POST['pos_title'] ?? 'SpacePark POS';
        $phone = $_POST['phone'] ?? '';
        $cuit = $_POST['cuit'] ?? '';
        $address = $_POST['address'] ?? ''; 
        $fiscal_data = $_POST['fiscal_data'] ?? '';

        try {
            $sql = "UPDATE branches SET pos_title=?, phone=?, cuit=?, address=?, fiscal_data=? WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$pos_title, $phone, $cuit, $address, $fiscal_data, $branchId]);
            $message = "Configuración General actualizada.";
        } catch (PDOException $e) { $error = "Error al guardar: " . $e->getMessage(); }
    }
    elseif ($action === 'save_mp') {
        $mp_token = $_POST['mp_token'] ?? '';
        $mp_collector_id = $_POST['mp_collector_id'] ?? '';
        $mp_status = $_POST['mp_status'] ?? 0;
        try {
            $stmt = $db->prepare("UPDATE branches SET mp_token=?, mp_collector_id=?, mp_status=? WHERE id=?");
            $stmt->execute([$mp_token, $mp_collector_id, $mp_status, $branchId]);
            $message = "Configuración Mercado Pago actualizada.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
    elseif ($action === 'save_cloud') {
        $cloud_host = $_POST['cloud_host'] ?? '';
        $cloud_db = $_POST['cloud_db'] ?? '';
        $cloud_user = $_POST['cloud_user'] ?? '';
        $cloud_pass = $_POST['cloud_pass'] ?? '';
        try {
            $stmt = $db->prepare("UPDATE branches SET cloud_host=?, cloud_db=?, cloud_user=?, cloud_pass=? WHERE id=?");
            $stmt->execute([$cloud_host, $cloud_db, $cloud_user, $cloud_pass, $branchId]);
            $message = "Configuración de Nube actualizada.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
    elseif ($action === 'test_cloud') {
        $host = $_POST['cloud_host'];
        $dbname = $_POST['cloud_db'];
        $user = $_POST['cloud_user'];
        $pass = $_POST['cloud_pass'];
        
        if ($host && $dbname) {
            try {
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                $test_msg = "<div class='alert alert-success'>Conexión Exitosa a la Nube!</div>";
            } catch (Exception $e) {
                $test_msg = "<div class='alert alert-danger'>Falló la conexión: " . $e->getMessage() . "</div>";
            }
        } else {
            $test_msg = "<div class='alert alert-warning'>Faltan datos de host/db para probar.</div>";
        }
    }
    elseif ($action === 'test_mp') {
        $token = $_POST['mp_token'];
        if (strlen($token) > 10) {
            $test_msg = "<div class='alert alert-success'>Token parece válido (Simulación).</div>";
        } else {
            $test_msg = "<div class='alert alert-danger'>Token inválido o muy corto.</div>";
        }
    }
    elseif ($action === 'save_licenses' && $auth->isAdmin()) {
        $base = $_POST['license_expiry'] ?: null;
        $pos = $_POST['license_pos_expiry'] ?: null;
        $mp = $_POST['license_mp_expiry'] ?: null;
        $modo = $_POST['license_modo_expiry'] ?: null;
        $cloud = $_POST['license_cloud_expiry'] ?: null;
        $arcade = $_POST['license_arcade_expiry'] ?: null;
        $limit = $_POST['pos_license_limit'] ?? 1;

        try {
            // Actualizar sistema antiguo (branches)
            $stmt = $db->prepare("UPDATE branches SET license_expiry=?, license_pos_expiry=?, license_mp_expiry=?, license_modo_expiry=?, license_cloud_expiry=?, license_arcade_expiry=?, pos_license_limit=? WHERE id=?");
            $stmt->execute([$base, $pos, $mp, $modo, $cloud, $arcade, $limit, $branchId]);
            
            // NUEVO: También actualizar sistema moderno (device_licenses)
            // Obtener el tenant_id de esta branch
            $stmtTenant = $db->prepare("SELECT tenant_id FROM branches WHERE id = ?");
            $stmtTenant->execute([$branchId]);
            $tenantId = $stmtTenant->fetchColumn();
            
            if ($tenantId && $pos) {
                // Actualizar todos los dispositivos de este tenant con la nueva fecha de expiración
                $stmt = $db->prepare("
                    UPDATE device_licenses 
                    SET expires_at = ?,
                        status = IF(? >= CURDATE(), 'active', 'expired'),
                        payment_status = IF(? >= CURDATE(), 'paid', 'overdue')
                    WHERE tenant_id = ?
                ");
                
                // Convertir fecha Y-m-d a Y-m-d H:i:s para device_licenses
                $expiryDatetime = $pos ? $pos . ' 23:59:59' : null;
                $stmt->execute([$expiryDatetime, $pos, $pos, $tenantId]);
            }
            
            $message = "Licencias y Límites actualizados (sistema antiguo y moderno sincronizados).";
        } catch (PDOException $e) { $error = "Error al actualizar licencias: " . $e->getMessage(); }
    }
}

// --- FETCH BRANCH INFO ---
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branchId]);
$branch = $stmt->fetch();

if (!$branch) {
    echo "<div class='alert alert-danger'>Sucursal no encontrada.</div>";
    require_once 'layout_foot.php';
    exit;
}

// Licenses Check
function checkLic($date) {
    if (!$date) return false;
    return ($date >= date('Y-m-d'));
}

$lic_base = checkLic($branch['license_expiry']);
$lic_pos = checkLic($branch['license_pos_expiry']);
$lic_mp = checkLic($branch['license_mp_expiry'] ?? null);
$lic_modo = checkLic($branch['license_modo_expiry'] ?? null);
$lic_cloud = checkLic($branch['license_cloud_expiry'] ?? null);
$lic_arcade = checkLic($branch['license_arcade_expiry'] ?? null);

// Fetch Sync Token for display
$syncToken = 'No disponible';
$driver = Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    $stmtT = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sync_token'");
    $stmtT->execute();
    $syncToken = $stmtT->fetchColumn() ?: 'No configurado';
} else {
    // On Server: Try to find tenant sync token via user bridge
    $stmtT = $db->prepare("SELECT t.sync_token FROM tenants t JOIN users u ON u.tenant_id = t.id WHERE u.branch_id = ? LIMIT 1");
    $stmtT->execute([$branchId]);
    $syncToken = $stmtT->fetchColumn() ?: 'No generado';
}

function licBadge($status, $date) {
    if ($status) return '<span class="badge bg-success">Activa</span> <small class="text-muted">Vence: '.$date.'</small>';
    return '<span class="badge bg-danger">Inactiva/Vencida</span> <small class="text-danger">'.$date.'</small>';
}

// Stats
$driver = \Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    $start = date('Y-m-d 00:00:00');
    $end = date('Y-m-d 23:59:59');
    $stmt = $db->prepare("SELECT SUM(amount) FROM sales WHERE branch_id = ? AND created_at BETWEEN :start AND :end");
    $stmt->execute([$branchId, ':start' => $start, ':end' => $end]);
    $salesToday = $stmt->fetchColumn() ?: 0;
} else {
    $stmt = $db->prepare("SELECT SUM(amount) FROM sales WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$branchId]);
    $salesToday = $stmt->fetchColumn() ?: 0;
}
$stmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE branch_id = ? AND active = 1");
$stmt->execute([$branchId]);
$activeMachines = $stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ? AND role != 'admin'");
$stmt->execute([$branchId]);
$countEmployees = $stmt->fetchColumn();
$stmt = $db->prepare("SELECT s.created_at, m.name as machine, s.amount, u.username FROM sales s JOIN machines m ON s.machine_id = m.id LEFT JOIN users u ON s.user_id = u.id WHERE s.branch_id = ? ORDER BY s.created_at DESC LIMIT 10");
$stmt->execute([$branchId]);
$recentSales = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= htmlspecialchars($branch['name']) ?></h1>
    <?php if($auth->isAdmin()): ?>
        <a href="branches.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?= $test_msg ?>

<!-- TABS -->
<ul class="nav nav-tabs mb-4" id="branchTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dashboard" type="button"><i class="bi bi-speedometer2"></i> Dashboard</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#licenses" type="button"><i class="bi bi-key"></i> Licencias</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#general" type="button"><i class="bi bi-shop"></i> General</button></li>
    <li class="nav-item">
        <button class="nav-link <?= !$lic_mp ? 'disabled text-muted' : '' ?>" data-bs-toggle="tab" data-bs-target="#mercadopago" type="button">
            <i class="bi bi-credit-card"></i> Mercado Pago <?= !$lic_mp ? '<i class="bi bi-lock-fill"></i>' : '' ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= !$lic_cloud ? 'disabled text-muted' : '' ?>" data-bs-toggle="tab" data-bs-target="#cloud" type="button">
            <i class="bi bi-cloud-arrow-up"></i> Nube <?= !$lic_cloud ? '<i class="bi bi-lock-fill"></i>' : '' ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="branchTabContent">
    
    <!-- DASHBOARD -->
    <div class="tab-pane fade show active" id="dashboard">
        <!-- ... (Same Dashboard Content) ... -->
        <div class="row g-4 mb-4">
            <div class="col-md-4"><div class="card bg-primary text-white p-3"><h6 class="mb-0">Ventas Hoy (Local)</h6><h3 class="mb-0">$<?= number_format($salesToday, 2) ?></h3></div></div>
            <div class="col-md-4"><div class="card bg-warning text-dark p-3"><h6 class="mb-0">Máquinas Activas</h6><h3 class="mb-0"><?= $activeMachines ?></h3></div></div>
            <div class="col-md-4"><div class="card bg-info text-white p-3"><h6 class="mb-0">Empleados</h6><h3 class="mb-0"><?= $countEmployees ?></h3></div></div>
        </div>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Últimas Ventas</h5></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Hora</th><th>Máquina</th><th>Monto</th><th>Cajero</th></tr></thead>
                            <tbody>
                                <?php if (empty($recentSales)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No hay movimientos</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td><?= date('H:i', strtotime($sale['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($sale['machine']) ?></td>
                                        <td class="fw-bold text-success">$<?= number_format($sale['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($sale['username'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Estado de Licencias</h5></div>
                    <div class="list-group list-group-flush small">
                        <div class="list-group-item d-flex justify-content-between align-items-center">Pos Base: <?= licBadge($lic_base, $branch['license_expiry']) ?></div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">Ventas (POS): <?= licBadge($lic_pos, $branch['license_pos_expiry'] ?? null) ?></div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">Mercado Pago: <?= licBadge($lic_mp, $branch['license_mp_expiry'] ?? null) ?></div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">MODO: <?= licBadge($lic_modo, $branch['license_modo_expiry'] ?? null) ?></div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">Nube / Sincro: <?= licBadge($lic_cloud, $branch['license_cloud_expiry'] ?? null) ?></div>
                        <div class="list-group-item d-flex justify-content-between align-items-center text-primary">Arcade PWA: <?= licBadge($lic_arcade, $branch['license_arcade_expiry'] ?? null) ?></div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Gestión Rápida</h5></div>
                    <div class="list-group list-group-flush">
                         <a href="machines.php" class="list-group-item list-group-item-action">Gestionar Máquinas</a>
                         <a href="reports.php" class="list-group-item list-group-item-action">Ver Reportes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LICENSES TAB (NEW) -->
    <div class="tab-pane fade" id="licenses">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-4">Estado de Licencias y Módulos</h5>
                
                <?php if ($auth->isAdmin()): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_licenses">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Licencia Base (Acceso Sistema)</label>
                                <input type="date" name="license_expiry" class="form-control" value="<?= $branch['license_expiry'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Licencia Módulo POS</label>
                                <input type="date" name="license_pos_expiry" class="form-control" value="<?= $branch['license_pos_expiry'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Licencia Mercado Pago</label>
                                <input type="date" name="license_mp_expiry" class="form-control" value="<?= $branch['license_mp_expiry'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Licencia MODO</label>
                                <input type="date" name="license_modo_expiry" class="form-control" value="<?= $branch['license_modo_expiry'] ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Licencia Nube</label>
                                <input type="date" name="license_cloud_expiry" class="form-control" value="<?= $branch['license_cloud_expiry'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">Licencia Arcade PWA</label>
                                <input type="date" name="license_arcade_expiry" class="form-control" value="<?= $branch['license_arcade_expiry'] ?>">
                            </div>
                            <div class="col-12"><hr></div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">Límite Puntos de Venta (POS)</label>
                                <input type="number" name="pos_license_limit" class="form-control" min="1" value="<?= $branch['pos_license_limit'] ?? 1 ?>">
                                <small class="text-muted">Cantidad de puestos/cajeros simultáneos.</small>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">Guardar Licencias</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Read Only for Managers -->
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi bi-person-badge me-2"></i> Acceso Base (Login)</div>
                            <div><?= licBadge($lic_base, $branch['license_expiry']) ?></div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi bi-cart me-2"></i> Módulo POS (Ventas)</div>
                            <div><?= licBadge($lic_pos, $branch['license_pos_expiry']) ?></div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi bi-credit-card me-2"></i> Módulo Mercado Pago</div>
                            <div><?= licBadge($lic_mp, $branch['license_mp_expiry']) ?></div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi bi-credit-card me-2"></i> Módulo MODO</div>
                            <div><?= licBadge($lic_modo, $branch['license_modo_expiry']) ?></div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi bi-cloud-arrow-up me-2"></i> Módulo Nube</div>
                            <div><?= licBadge($lic_cloud, $branch['license_cloud_expiry']) ?></div>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center text-primary">
                            <div><i class="bi bi-phone me-2"></i> Módulo Arcade PWA</div>
                            <div><?= licBadge($lic_arcade, $branch['license_arcade_expiry']) ?></div>
                        </li>
                    </ul>
                    <div class="mt-4 p-3 bg-light rounded text-center">
                        <h6 class="text-muted mb-0">Límite de Puestos POS Habilitados: <strong class="text-dark fs-5"><?= $branch['pos_license_limit'] ?? 1 ?></strong></h6>
                    </div>
                <?php endif; ?>
                
                <?php if(!$auth->isAdmin()): ?>
                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle"></i> Contacte al administrador para renovar módulos vencidos.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- GENERAL SETTINGS -->
    <div class="tab-pane fade" id="general">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-4">Información del Comercio</h5>
                <?php if (!$lic_pos): ?>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> La licencia del <strong>Módulo POS</strong> ha vencido. No se podrán realizar ventas hasta su renovación.</div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="save_general">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre Comercial (Título POS)</label>
                            <input type="text" name="pos_title" class="form-control" value="<?= htmlspecialchars($branch['pos_title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección / Ubicación</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($branch['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono de Contacto</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($branch['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CUIT / Identificación Fiscal</label>
                            <input type="text" name="cuit" class="form-control" value="<?= htmlspecialchars($branch['cuit'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Datos Fiscales Adicionales (JSON/Texto)</label>
                            <textarea name="fiscal_data" class="form-control" rows="3"><?= htmlspecialchars($branch['fiscal_data'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MERCADO PAGO (Conditional) -->
    <?php if ($lic_mp): ?>
    <div class="tab-pane fade" id="mercadopago">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-4">Integración Mercado Pago</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="save_mp">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Estado Integración</label>
                            <select name="mp_status" class="form-select">
                                <option value="1" <?= $branch['mp_status'] == 1 ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= $branch['mp_status'] == 0 ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Access Token</label>
                            <div class="input-group">
                                <input type="text" name="mp_token" class="form-control" value="<?= htmlspecialchars($branch['mp_token'] ?? '') ?>" placeholder="APP_USR-xxxxxxxx-xxxx...">
                                <button type="submit" name="action" value="test_mp" class="btn btn-outline-info">Probar</button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Collector ID (ID de Cuenta de Mercado Pago)</label>
                            <input type="text" name="mp_collector_id" class="form-control" value="<?= htmlspecialchars($branch['mp_collector_id'] ?? '') ?>" placeholder="Ej: 123456789">
                            <small class="text-muted">Se obtiene desde la URL de tu cuenta de Mercado Pago o configurando QR estático</small>
                        </div>
                    </div>
                    <div class="mt-4 text-end"><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CLOUD SYNC / TOKEN SYNC -->
    <?php if ($lic_cloud): ?>
    <div class="tab-pane fade" id="cloud">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-3">Sincronización con Nube</h5>
                
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Información de Sincronización:</strong><br>
                            Utilice el Token de Sincronización para vincular este local con el servidor en la nube.
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Token de Sincronización</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($syncToken) ?>" readonly id="tokenInput">
                        <button class="btn btn-outline-primary" type="button" onclick="copyToken()">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                    <small class="text-muted">Este es el código que debe ingresar en el Instalador o en la Configuración del cliente local.</small>
                </div>

                <?php if ($driver === 'sqlite'): ?>
                <hr class="my-4">
                <h6 class="mb-3">Sincronización Manual (Local)</h6>
                <div class="d-grid gap-2 d-md-block">
                    <button type="button" class="btn btn-primary me-md-2 mb-2" onclick="syncPush()">
                        <i class="bi bi-cloud-upload"></i> Subir Ventas y Productos
                    </button>
                    <button type="button" class="btn btn-info text-white mb-2" onclick="syncPull()">
                        <i class="bi bi-cloud-download"></i> Descargar Configuración
                    </button>
                </div>
                <div id="syncResult" class="mt-2"></div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var triggerTabList = [].slice.call(document.querySelectorAll('#branchTabs button'))
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl)
            triggerEl.addEventListener('click', function (event) { })
        })
    });

    function copyToken() {
        var copyText = document.getElementById("tokenInput");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert("Token copiado al portapapeles");
    }

    <?php if ($driver === 'sqlite'): ?>
    function syncPush() {
        const btn = event.target;
        const resultDiv = document.getElementById('syncResult');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';
        
        fetch('../scripts/sync_upload.php')
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    resultDiv.innerHTML = `<div class="alert alert-success mt-2">✓ Sincronizado: ${data.uploaded || 0} items subidos</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger mt-2">✗ Error: ${data.error}</div>`;
                }
            })
            .catch(e => {
                resultDiv.innerHTML = `<div class="alert alert-danger mt-2">✗ Error: ${e.message}</div>`;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cloud-upload"></i> Subir Ventas y Productos';
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
                    resultDiv.innerHTML = `<div class="alert alert-success mt-2">✓ Sincronización exitosa. Recargando...</div>`;
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger mt-2">✗ Error: ${data.error}</div>`;
                }
            })
            .catch(e => {
                resultDiv.innerHTML = `<div class="alert alert-danger mt-2">✗ Error: ${e.message}</div>`;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cloud-download"></i> Descargar Configuración';
            });
    }
    <?php endif; ?>
</script>

<?php require_once 'layout_foot.php'; ?>

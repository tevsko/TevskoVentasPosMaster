<?php
// pos/licenses.php
// Client dashboard for managing device licenses

require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$tenantId = $_SESSION['tenant_id'] ?? null;

if (!$tenantId) {
    die('Error: No tenant ID found');
}

// Get tenant info
$stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();

// Get subscription info
$stmt = $db->prepare("
    SELECT s.*, p.name as plan_name, p.price, p.pos_included, p.pos_extra_monthly_fee, p.pos_extra_annual_fee
    FROM subscriptions s
    JOIN plans p ON p.id = s.plan_id
    WHERE s.tenant_id = ? AND s.status = 'active'
    LIMIT 1
");
$stmt->execute([$tenantId]);
$subscription = $stmt->fetch();

// Get all devices
$stmt = $db->prepare("
    SELECT * FROM device_licenses 
    WHERE tenant_id = ? 
    ORDER BY device_role ASC, created_at ASC
");
$stmt->execute([$tenantId]);
$devices = $stmt->fetchAll();

$activeDevices = count(array_filter($devices, fn($d) => $d['status'] === 'active'));
$expiringSoon = count(array_filter($devices, function($d) {
    if (!$d['expires_at']) return false;
    $expires = new DateTime($d['expires_at']);
    $now = new DateTime();
    $diff = $now->diff($expires);
    return $diff->days <= 7 && $diff->invert == 0;
}));

require_once 'layout_head.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-pc-display-horizontal"></i> Mis Dispositivos (POS)
        </h1>
    </div>

    <!-- Plan Summary -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Plan Actual</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($subscription['plan_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-star-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">POS Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $activeDevices ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">POS Incluidos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $subscription['pos_included'] ?? 1 ?> Master</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-gift-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Por Vencer</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $expiringSoon ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Devices List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Dispositivos Registrados</h6>
        </div>
        <div class="card-body">
            <?php if (empty($devices)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay dispositivos registrados aún.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Dispositivo</th>
                                <th>Tipo</th>
                                <th>Licencia</th>
                                <th>Estado</th>
                                <th>Vencimiento</th>
                                <th>Última Conexión</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): 
                                $expires = $device['expires_at'] ? new DateTime($device['expires_at']) : null;
                                $now = new DateTime();
                                $daysRemaining = null;
                                $statusBadge = 'success';
                                $statusText = 'Activo';
                                
                                if ($expires) {
                                    $diff = $now->diff($expires);
                                    $daysRemaining = (int)$diff->format('%r%a');
                                    
                                    if ($daysRemaining < 0) {
                                        $statusBadge = 'danger';
                                        $statusText = 'Vencido';
                                    } elseif ($daysRemaining <= 7) {
                                        $statusBadge = 'warning';
                                        $statusText = 'Por vencer';
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($device['device_name']) ?></strong><br>
                                    <small class="text-muted"><?= substr($device['device_id'], 0, 16) ?>...</small>
                                </td>
                                <td>
                                    <?php if ($device['device_role'] === 'master'): ?>
                                        <span class="badge bg-primary">Master</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Slave</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($device['license_type'] === 'included'): ?>
                                        <span class="badge bg-success">Incluida</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <?= $device['payment_period'] === 'annual' ? 'Anual' : 'Mensual' ?>
                                            ($<?= number_format($device['monthly_fee'], 0) ?><?= $device['payment_period'] === 'annual' ? '/año' : '/mes' ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if ($expires): ?>
                                        <?= $expires->format('d/m/Y') ?>
                                        <?php if ($daysRemaining !== null && $daysRemaining >= 0): ?>
                                            <br><small class="text-muted">(<?= $daysRemaining ?> días)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($device['last_seen_at']): ?>
                                        <?php
                                        $lastSeen = new DateTime($device['last_seen_at']);
                                        $diff = $now->diff($lastSeen);
                                        if ($diff->days > 0) {
                                            echo "Hace " . $diff->days . " día(s)";
                                        } elseif ($diff->h > 0) {
                                            echo "Hace " . $diff->h . " hora(s)";
                                        } else {
                                            echo "Hace " . $diff->i . " min";
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($device['license_type'] === 'paid' && ($daysRemaining < 30 || $daysRemaining < 0)): ?>
                                        <a href="renew_license.php?device_id=<?= $device['id'] ?>&period=monthly" class="btn btn-sm btn-primary">
                                            <i class="bi bi-credit-card"></i> Renovar ($<?= number_format($subscription['pos_extra_monthly_fee'], 0) ?>/mes)
                                        </a>
                                        <a href="renew_license.php?device_id=<?= $device['id'] ?>&period=annual" class="btn btn-sm btn-success">
                                            <i class="bi bi-credit-card"></i> Anual ($<?= number_format($subscription['pos_extra_annual_fee'], 0) ?>)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pricing Info -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Información de Precios</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">Plan Base</div>
                        <div class="card-body">
                            <h4 class="card-title">$<?= number_format($subscription['price'], 2) ?>/mes</h4>
                            <p class="card-text">Incluye <?= $subscription['pos_included'] ?> POS Master</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info mb-3">
                        <div class="card-header bg-info text-white">POS Adicional (Mensual)</div>
                        <div class="card-body">
                            <h4 class="card-title">$<?= number_format($subscription['pos_extra_monthly_fee'], 2) ?>/mes</h4>
                            <p class="card-text">Por cada Slave adicional</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">POS Adicional (Anual)</div>
                        <div class="card-body">
                            <h4 class="card-title">$<?= number_format($subscription['pos_extra_annual_fee'], 2) ?>/año</h4>
                            <p class="card-text">
                                Ahorra $<?= number_format(($subscription['pos_extra_monthly_fee'] * 12) - $subscription['pos_extra_annual_fee'], 2) ?> al año
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

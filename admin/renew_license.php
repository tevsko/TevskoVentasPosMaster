<?php
// admin/renew_license.php
// Página de renovación de licencia con selección de período y pago

require_once 'layout_head.php';


$db = Database::getInstance()->getConnection();
$driver = Database::getInstance()->getDriver();

// Obtener módulo a renovar
$moduleCode = $_GET['module'] ?? null;

if (!$moduleCode) {
    header('Location: license.php');
    exit;
}

// Obtener información del módulo
$stmt = $db->prepare("SELECT * FROM module_prices WHERE module_code = ? AND active = 1");
$stmt->execute([$moduleCode]);
$module = $stmt->fetch();

if (!$module) {
    echo "<div class='alert alert-danger'>Módulo no encontrado</div>";
    require_once 'layout_foot.php';
    exit;
}

// Obtener información de la sucursal actual
$branchId = null;
$currentLicenseExpiry = null;

if ($auth->isAdmin()) {
    $stmt = $db->query("SELECT id FROM branches LIMIT 1");
    $branchId = $stmt->fetchColumn();
} elseif ($auth->isBranchManager()) {
    $branchId = $currentUser['branch_id'];
}

if ($branchId) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch();
    
    // Obtener fecha de vencimiento actual del módulo
    $expiryField = match($moduleCode) {
        'base' => 'license_expiry',
        'pos' => 'license_pos_expiry',
        'mercadopago' => 'license_mp_expiry',
        'modo' => 'license_modo_expiry',
        'nube' => 'license_cloud_expiry',
        default => 'license_expiry'
    };
    
    $currentLicenseExpiry = $branch[$expiryField] ?? null;
}

// Calcular descuentos
$quarterlyDiscount = 0;
$annualDiscount = 0;

if ($module['quarterly_price']) {
    $quarterlyDiscount = round((1 - ($module['quarterly_price'] / ($module['monthly_price'] * 3))) * 100);
}

if ($module['annual_price']) {
    $annualDiscount = round((1 - ($module['annual_price'] / ($module['monthly_price'] * 12))) * 100);
}

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-arrow-repeat me-2"></i>Renovar Licencia</h2>
                    <p class="text-muted">Selecciona el período de renovación para <?= htmlspecialchars($module['module_name']) ?></p>
                </div>
                <a href="license.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>

    <!-- Información del Módulo -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <?= htmlspecialchars($module['module_name']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><?= htmlspecialchars($module['description']) ?></p>
                    <?php if ($currentLicenseExpiry): ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-calendar-event me-2"></i>
                            <strong>Vencimiento actual:</strong> <?= date('d/m/Y', strtotime($currentLicenseExpiry)) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Opciones de Renovación -->
    <div class="row">
        <!-- Mensual -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-secondary">
                <div class="card-header bg-secondary text-white text-center">
                    <h5 class="mb-0">Mensual</h5>
                </div>
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        $<?= number_format($module['monthly_price'], 0, ',', '.') ?>
                    </div>
                    <p class="text-muted">por mes</p>
                    <ul class="list-unstyled mb-4">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Renovación mensual</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Sin compromiso</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Cancela cuando quieras</li>
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-primary w-100" onclick="selectPeriod('monthly', <?= $module['monthly_price'] ?>)">
                        <i class="bi bi-credit-card me-2"></i>Seleccionar
                    </button>
                </div>
            </div>
        </div>

        <!-- Trimestral -->
        <?php if ($module['quarterly_price']): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark text-center">
                    <h5 class="mb-0">
                        Trimestral
                        <span class="badge bg-danger ms-2"><?= $quarterlyDiscount ?>% OFF</span>
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-3">
                        $<?= number_format($module['quarterly_price'], 0, ',', '.') ?>
                    </div>
                    <p class="text-muted">por 3 meses</p>
                    <p class="small text-success">
                        <del class="text-muted">$<?= number_format($module['monthly_price'] * 3, 0, ',', '.') ?></del>
                        Ahorras $<?= number_format(($module['monthly_price'] * 3) - $module['quarterly_price'], 0, ',', '.') ?>
                    </p>
                    <ul class="list-unstyled mb-4">
                        <li><i class="bi bi-check-circle text-success me-2"></i>3 meses de servicio</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i><?= $quarterlyDiscount ?>% de descuento</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Mejor precio</li>
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-warning w-100" onclick="selectPeriod('quarterly', <?= $module['quarterly_price'] ?>)">
                        <i class="bi bi-credit-card me-2"></i>Seleccionar
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Anual -->
        <?php if ($module['annual_price']): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-success shadow">
                <div class="card-header bg-success text-white text-center">
                    <h5 class="mb-0">
                        Anual
                        <span class="badge bg-danger ms-2"><?= $annualDiscount ?>% OFF</span>
                    </h5>
                    <small>¡Más Popular!</small>
                </div>
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-3">
                        $<?= number_format($module['annual_price'], 0, ',', '.') ?>
                    </div>
                    <p class="text-muted">por 12 meses</p>
                    <p class="small text-success">
                        <del class="text-muted">$<?= number_format($module['monthly_price'] * 12, 0, ',', '.') ?></del>
                        Ahorras $<?= number_format(($module['monthly_price'] * 12) - $module['annual_price'], 0, ',', '.') ?>
                    </p>
                    <ul class="list-unstyled mb-4">
                        <li><i class="bi bi-check-circle text-success me-2"></i>12 meses de servicio</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i><?= $annualDiscount ?>% de descuento</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Máximo ahorro</li>
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-success w-100" onclick="selectPeriod('annual', <?= $module['annual_price'] ?>)">
                        <i class="bi bi-credit-card me-2"></i>Seleccionar
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Información Adicional -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h6><i class="bi bi-shield-check me-2"></i>Información Importante</h6>
                    <ul class="small mb-0">
                        <li>La renovación se activa inmediatamente después del pago aprobado</li>
                        <li>Recibirás un email de confirmación con los detalles de tu renovación</li>
                        <li>Puedes pagar con Mercado Pago (tarjeta, efectivo, transferencia)</li>
                        <li>Si tienes dudas, contáctanos: <strong>1135508224</strong> o <strong>tevsko@gmail.com</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Confirmar Renovación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Módulo:</strong> <span id="confirm-module"><?= htmlspecialchars($module['module_name']) ?></span></p>
                <p><strong>Período:</strong> <span id="confirm-period"></span></p>
                <p><strong>Monto:</strong> $<span id="confirm-amount"></span></p>
                <hr>
                <p class="small text-muted mb-0">Serás redirigido a Mercado Pago para completar el pago de forma segura.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="processPayment()">
                    <i class="bi bi-credit-card me-2"></i>Ir a Pagar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedPeriod = null;
let selectedAmount = null;

function selectPeriod(period, amount) {
    selectedPeriod = period;
    selectedAmount = amount;
    
    const periodNames = {
        'monthly': 'Mensual (1 mes)',
        'quarterly': 'Trimestral (3 meses)',
        'annual': 'Anual (12 meses)'
    };
    
    document.getElementById('confirm-period').textContent = periodNames[period];
    document.getElementById('confirm-amount').textContent = amount.toLocaleString('es-AR');
    
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

function processPayment() {
    if (!selectedPeriod || !selectedAmount) {
        alert('Error: Selecciona un período primero');
        return;
    }
    
    // Mostrar loading
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    
    // Crear preferencia de pago
    fetch('../api/create_payment_preference.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            module_code: '<?= $moduleCode ?>',
            period_type: selectedPeriod,
            amount: selectedAmount
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.init_point) {
            // Redirigir a Mercado Pago
            window.location.href = data.init_point;
        } else {
            alert('Error al crear el pago: ' + (data.error || 'Desconocido'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-credit-card me-2"></i>Ir a Pagar';
        }
    })
    .catch(e => {
        alert('Error: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-credit-card me-2"></i>Ir a Pagar';
    });
}
</script>

<?php require_once 'layout_foot.php'; ?>

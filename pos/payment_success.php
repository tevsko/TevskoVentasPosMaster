<?php
// pos/payment_success.php
// Handle successful payment and activate license

require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$tenantId = $_SESSION['tenant_id'] ?? null;
$paymentId = $_GET['payment_id'] ?? '';
$deviceId = $_GET['device_id'] ?? '';
$period = $_GET['period'] ?? 'monthly';

if (!$tenantId || !$paymentId || !$deviceId) {
    die('Error: Missing parameters');
}

// Get payment info
$stmt = $db->prepare("SELECT * FROM device_payments WHERE id = ? AND tenant_id = ?");
$stmt->execute([$paymentId, $tenantId]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Error: Payment not found');
}

// Update payment status
$stmt = $db->prepare("UPDATE device_payments SET status = 'completed' WHERE id = ?");
$stmt->execute([$paymentId]);

// Update device license
$periodDays = $period === 'annual' ? 365 : 30;
$newExpiry = date('Y-m-d H:i:s', strtotime("+{$periodDays} days"));

$stmt = $db->prepare("
    UPDATE device_licenses 
    SET expires_at = ?, 
        last_payment_date = " . Database::nowSql() . ",
        status = 'active',
        payment_period = ?,
        payment_status = 'paid'
    WHERE id = ? AND tenant_id = ?
");
$stmt->execute([$newExpiry, $period, $deviceId, $tenantId]);

// Get device info for confirmation
$stmt = $db->prepare("SELECT * FROM device_licenses WHERE id = ?");
$stmt->execute([$deviceId]);
$device = $stmt->fetch();

require_once 'layout_head.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-success">
                <div class="card-header bg-success text-white text-center">
                    <h4 class="mb-0"><i class="bi bi-check-circle-fill"></i> ¡Pago Exitoso!</h4>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-check-circle text-success" style="font-size: 80px;"></i>
                    </div>
                    
                    <h5>Licencia Renovada</h5>
                    <p class="text-muted">Tu licencia ha sido renovada exitosamente</p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><strong>Dispositivo:</strong> <?= htmlspecialchars($device['device_name']) ?></p>
                        <p><strong>Período:</strong> <?= $period === 'annual' ? 'Anual (365 días)' : 'Mensual (30 días)' ?></p>
                        <p><strong>Monto Pagado:</strong> <span class="text-success">$<?= number_format($payment['amount'], 2) ?></span></p>
                        <p><strong>Nueva Fecha de Vencimiento:</strong> <?= date('d/m/Y', strtotime($newExpiry)) ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="licenses.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Volver a Mis Licencias
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-house"></i> Ir al Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

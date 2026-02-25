<?php
// pos/renew_license.php
// Initiate license renewal payment process

require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$tenantId = $_SESSION['tenant_id'] ?? null;
$deviceId = $_GET['device_id'] ?? '';
$period = $_GET['period'] ?? 'monthly'; // monthly or annual

if (!$tenantId || !$deviceId) {
    die('Error: Missing parameters');
}

// Get device info
$stmt = $db->prepare("SELECT * FROM device_licenses WHERE id = ? AND tenant_id = ?");
$stmt->execute([$deviceId, $tenantId]);
$device = $stmt->fetch();

if (!$device) {
    die('Error: Device not found');
}

// Get subscription/plan info
$stmt = $db->prepare("
    SELECT p.pos_extra_monthly_fee, p.pos_extra_annual_fee
    FROM subscriptions s
    JOIN plans p ON p.id = s.plan_id
    WHERE s.tenant_id = ? AND s.status = 'active'
    LIMIT 1
");
$stmt->execute([$tenantId]);
$plan = $stmt->fetch();

if (!$plan) {
    die('Error: No active subscription found');
}

// Calculate amount
$amount = $period === 'annual' ? $plan['pos_extra_annual_fee'] : $plan['pos_extra_monthly_fee'];
$periodDays = $period === 'annual' ? 365 : 30;

// Get payment settings
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");

$stmt->execute(['mp_access_token']);
$mpToken = $stmt->fetchColumn();

$stmt->execute(['modo_api_key']);
$modoKey = $stmt->fetchColumn();

// For now, create a simple payment link
// In production, integrate with Mercado Pago/MODO API

$paymentDescription = "Renovación de licencia POS - " . htmlspecialchars($device['device_name']) . " (" . ($period === 'annual' ? 'Anual' : 'Mensual') . ")";

// Create pending payment record
$periodStart = date('Y-m-d');
$periodEnd = date('Y-m-d', strtotime("+{$periodDays} days"));

$stmt = $db->prepare("
    INSERT INTO device_payments 
    (device_license_id, tenant_id, amount, payment_method, period_start, period_end, status)
    VALUES (?, ?, ?, 'pending', ?, ?, 'pending')
");
$stmt->execute([$device['id'], $tenantId, $amount, $periodStart, $periodEnd]);
$paymentId = $db->lastInsertId();

// Redirect to payment gateway (simplified version)
// In production, use Mercado Pago or MODO checkout

require_once 'layout_head.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Renovar Licencia</h5>
                </div>
                <div class="card-body">
                    <h6>Dispositivo:</h6>
                    <p><strong><?= htmlspecialchars($device['device_name']) ?></strong></p>
                    
                    <h6>Período:</h6>
                    <p><?= $period === 'annual' ? 'Anual (12 meses)' : 'Mensual (30 días)' ?></p>
                    
                    <h6>Monto a Pagar:</h6>
                    <h3 class="text-success">$<?= number_format($amount, 2) ?></h3>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <strong>ℹ️ Integración de Pago</strong><br>
                        Esta pantalla se conectará con Mercado Pago o MODO para procesar el pago.
                    </div>
                    
                    <!-- Simulate payment buttons -->
                    <div class="d-grid gap-2">
                        <a href="payment_success.php?payment_id=<?= $paymentId ?>&device_id=<?= $device['id'] ?>&period=<?= $period ?>" 
                           class="btn btn-success btn-lg">
                            <i class="bi bi-credit-card"></i> Pagar con Mercado Pago
                        </a>
                        <a href="payment_success.php?payment_id=<?= $paymentId ?>&device_id=<?= $device['id'] ?>&period=<?= $period ?>" 
                           class="btn btn-info btn-lg">
                            <i class="bi bi-wallet2"></i> Pagar con MODO
                        </a>
                        <a href="licenses.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

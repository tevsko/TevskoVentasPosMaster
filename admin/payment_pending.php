<?php
// admin/payment_pending.php
// Página de pago pendiente

require_once 'layout_head.php';

$paymentId = $_GET['payment_id'] ?? null;
$db = Database::getInstance()->getConnection();

$payment = null;
if ($paymentId) {
    $stmt = $db->prepare("
        SELECT lp.*, mp.module_name
        FROM license_payments lp
        JOIN module_prices mp ON lp.module_code = mp.module_code
        WHERE lp.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
}

?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-warning shadow-lg mt-5">
                <div class="card-header bg-warning text-dark text-center py-4">
                    <i class="bi bi-clock-fill" style="font-size: 4rem;"></i>
                    <h2 class="mt-3 mb-0">Pago Pendiente</h2>
                </div>
                <div class="card-body p-5">
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-hourglass-split me-2"></i>Tu pago está siendo procesado</h5>
                        <p class="mb-0">La renovación de tu licencia se activará automáticamente cuando se confirme el pago.</p>
                    </div>
                    
                    <?php if ($payment): ?>
                    <h5 class="mb-3">Detalles del Pago</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Módulo:</th>
                            <td><?= htmlspecialchars($payment['module_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Período:</th>
                            <td>
                                <?php
                                $periods = [
                                    'monthly' => 'Mensual (1 mes)',
                                    'quarterly' => 'Trimestral (3 meses)',
                                    'annual' => 'Anual (12 meses)'
                                ];
                                echo $periods[$payment['period_type']] ?? $payment['period_type'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Monto:</th>
                            <td class="fw-bold">$<?= number_format($payment['final_amount'], 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
                            <td><span class="badge bg-warning">Pendiente de confirmación</span></td>
                        </tr>
                    </table>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-2"></i>¿Qué sigue?</h6>
                        <ul class="mb-0">
                            <li>Si pagaste con tarjeta, la confirmación es inmediata</li>
                            <li>Si pagaste en efectivo, puede tardar hasta 48 horas</li>
                            <li>Recibirás un email cuando se confirme el pago</li>
                            <li>Tu licencia se activará automáticamente</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-primary btn-lg" onclick="checkStatus()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Verificar Estado
                        </button>
                        <a href="license.php" class="btn btn-outline-secondary">
                            <i class="bi bi-award me-2"></i>Ver Mis Licencias
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkStatus() {
    location.reload();
}

// Auto-refresh cada 10 segundos
setInterval(() => {
    location.reload();
}, 10000);
</script>

<?php require_once 'layout_foot.php'; ?>

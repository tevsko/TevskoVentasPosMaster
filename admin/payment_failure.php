<?php
// admin/payment_failure.php
// Página de pago rechazado/fallido

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
            <div class="card border-danger shadow-lg mt-5">
                <div class="card-header bg-danger text-white text-center py-4">
                    <i class="bi bi-x-circle-fill" style="font-size: 4rem;"></i>
                    <h2 class="mt-3 mb-0">Pago No Completado</h2>
                </div>
                <div class="card-body p-5">
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>El pago no pudo ser procesado</h5>
                        <p class="mb-0">Tu renovación de licencia no se ha completado. Por favor, intenta nuevamente.</p>
                    </div>
                    
                    <?php if ($payment): ?>
                    <h5 class="mb-3">Detalles del Intento</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Módulo:</th>
                            <td><?= htmlspecialchars($payment['module_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Monto:</th>
                            <td>$<?= number_format($payment['final_amount'], 2, ',', '.') ?></td>
                        </tr>
                        <?php if ($payment['mp_status_detail']): ?>
                        <tr>
                            <th>Motivo:</th>
                            <td><?= htmlspecialchars($payment['mp_status_detail']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-lightbulb me-2"></i>¿Qué puedes hacer?</h6>
                        <ul class="mb-0">
                            <li>Verifica que tu tarjeta tenga fondos suficientes</li>
                            <li>Intenta con otro método de pago</li>
                            <li>Contacta a tu banco si el problema persiste</li>
                            <li>Contáctanos para asistencia: <strong>1135508224</strong></li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <?php if ($payment): ?>
                        <a href="renew_license.php?module=<?= urlencode($payment['module_code']) ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-repeat me-2"></i>Intentar Nuevamente
                        </a>
                        <?php endif; ?>
                        <a href="license.php" class="btn btn-outline-secondary">
                            <i class="bi bi-award me-2"></i>Ver Mis Licencias
                        </a>
                        <a href="mailto:tevsko@gmail.com?subject=Problema con pago de licencia" class="btn btn-outline-info">
                            <i class="bi bi-envelope me-2"></i>Contactar Soporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

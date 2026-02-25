<?php
// admin/payment_success.php
// Página de confirmación de pago exitoso

require_once 'layout_head.php';

$paymentId = $_GET['payment_id'] ?? null;
$db = Database::getInstance()->getConnection();

$payment = null;
if ($paymentId) {
    $stmt = $db->prepare("
        SELECT lp.*, mp.module_name, mp.description
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
            <?php if ($payment): ?>
                <!-- Pago Exitoso -->
                <div class="card border-success shadow-lg mt-5">
                    <div class="card-header bg-success text-white text-center py-4">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                        <h2 class="mt-3 mb-0">¡Pago Exitoso!</h2>
                    </div>
                    <div class="card-body p-5">
                        <div class="alert alert-success">
                            <h5><i class="bi bi-info-circle me-2"></i>Tu licencia ha sido renovada</h5>
                            <p class="mb-0">El módulo <strong><?= htmlspecialchars($payment['module_name']) ?></strong> está ahora activo.</p>
                        </div>
                        
                        <h5 class="mb-3">Detalles de la Renovación</h5>
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
                                <th>Monto Pagado:</th>
                                <td class="text-success fw-bold">$<?= number_format($payment['final_amount'], 2, ',', '.') ?></td>
                            </tr>
                            <?php if ($payment['license_end_date']): ?>
                            <tr>
                                <th>Válido hasta:</th>
                                <td><?= date('d/m/Y', strtotime($payment['license_end_date'])) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>ID de Pago:</th>
                                <td class="font-monospace"><?= htmlspecialchars($payment['mp_payment_id'] ?? $payment['id']) ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de Pago:</th>
                                <td><?= $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : 'Procesando...' ?></td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-envelope me-2"></i>
                            Recibirás un email de confirmación con los detalles de tu renovación.
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="license.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-award me-2"></i>Ver Mis Licencias
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house me-2"></i>Ir al Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Pago no encontrado -->
                <div class="card border-warning shadow-lg mt-5">
                    <div class="card-header bg-warning text-dark text-center py-4">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 4rem;"></i>
                        <h2 class="mt-3 mb-0">Información no disponible</h2>
                    </div>
                    <div class="card-body p-5 text-center">
                        <p>No se encontró información del pago.</p>
                        <a href="license.php" class="btn btn-primary">
                            <i class="bi bi-award me-2"></i>Ver Mis Licencias
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-refresh para actualizar estado si está procesando
<?php if ($payment && !$payment['paid_at']): ?>
setTimeout(() => {
    location.reload();
}, 3000);
<?php endif; ?>
</script>

<?php require_once 'layout_foot.php'; ?>

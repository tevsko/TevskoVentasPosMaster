<?php
// admin/module_prices.php
// Configuración de precios de renovación de módulos (solo admin)

require_once 'layout_head.php';

if (!$auth->isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_prices') {
    try {
        foreach ($_POST['modules'] as $moduleCode => $prices) {
            $stmt = $db->prepare("
                UPDATE module_prices 
                SET monthly_price = ?, 
                    quarterly_price = ?, 
                    annual_price = ?,
                    active = ?
                WHERE module_code = ?
            ");
            
            $stmt->execute([
                $prices['monthly'] ?? 0,
                $prices['quarterly'] ?? null,
                $prices['annual'] ?? null,
                isset($prices['active']) ? 1 : 0,
                $moduleCode
            ]);
        }
        
        $message = 'Precios actualizados correctamente';
    } catch (Exception $e) {
        $error = 'Error al actualizar precios: ' . $e->getMessage();
    }
}

// Obtener precios actuales
$stmt = $db->query("SELECT * FROM module_prices ORDER BY display_order ASC");
$modules = $stmt->fetchAll();

?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="bi bi-cash-coin me-2"></i>Precios de Renovación de Módulos</h2>
            <p class="text-muted">Configure los precios para la renovación de licencias individuales</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Información -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle me-2"></i>Información Importante</h6>
                <ul class="mb-0 small">
                    <li>Estos precios son para <strong>renovación de licencias</strong>, independientes de los planes iniciales</li>
                    <li>Los clientes pueden renovar módulos individuales con estos precios</li>
                    <li>Los descuentos trimestral y anual se calculan automáticamente</li>
                    <li>Configure Mercado Pago en: <a href="billing.php">Configuración > Facturación</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Formulario de Precios -->
    <form method="POST">
        <input type="hidden" name="action" value="save_prices">
        
        <div class="row">
            <?php foreach ($modules as $module): ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($module['module_name']) ?></h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="modules[<?= $module['module_code'] ?>][active]" 
                                       <?= $module['active'] ? 'checked' : '' ?>>
                                <label class="form-check-label text-white">Activo</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= htmlspecialchars($module['description']) ?></p>
                        
                        <div class="row g-3">
                            <!-- Mensual -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" 
                                           class="form-control" 
                                           name="modules[<?= $module['module_code'] ?>][monthly]" 
                                           value="<?= $module['monthly_price'] ?>"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Trimestral -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Trimestral
                                    <?php if ($module['quarterly_price']): ?>
                                        <?php 
                                        $discount = round((1 - ($module['quarterly_price'] / ($module['monthly_price'] * 3))) * 100);
                                        ?>
                                        <span class="badge bg-success"><?= $discount ?>% OFF</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" 
                                           class="form-control" 
                                           name="modules[<?= $module['module_code'] ?>][quarterly]" 
                                           value="<?= $module['quarterly_price'] ?>">
                                </div>
                                <small class="text-muted">Opcional (recomendado: 10% desc.)</small>
                            </div>
                            
                            <!-- Anual -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Anual
                                    <?php if ($module['annual_price']): ?>
                                        <?php 
                                        $discount = round((1 - ($module['annual_price'] / ($module['monthly_price'] * 12))) * 100);
                                        ?>
                                        <span class="badge bg-success"><?= $discount ?>% OFF</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" 
                                           class="form-control" 
                                           name="modules[<?= $module['module_code'] ?>][annual]" 
                                           value="<?= $module['annual_price'] ?>">
                                </div>
                                <small class="text-muted">Opcional (recomendado: 20% desc.)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-lg ms-2">
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once 'layout_foot.php'; ?>

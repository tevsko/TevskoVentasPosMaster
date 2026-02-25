<?php
// admin/plans_manage.php
require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
$auth->requireRole('admin'); // Solo Super Admin

$db = Database::getInstance()->getConnection();
$message = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_plan') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? 'Nuevo Plan';
        $price = (float)($_POST['price'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Integration Fields
        $mp_fee = (float)($_POST['mp_fee'] ?? 0);
        $allow_mp = isset($_POST['allow_mp']) ? 1 : 0;
        
        $modo_fee = (float)($_POST['modo_fee'] ?? 0);
        $allow_modo = isset($_POST['allow_modo']) ? 1 : 0;
        
        // Device License Fields
        $pos_included = 1; // Always 1 Master
        $pos_extra_monthly_fee = (float)($_POST['pos_extra_monthly_fee'] ?? 500);
        $pos_extra_monthly_fee = (float)($_POST['pos_extra_monthly_fee'] ?? 500);
        $pos_extra_annual_fee = (float)($_POST['pos_extra_annual_fee'] ?? 5000);
        
        $mobile_enabled = isset($_POST['mobile_enabled']) ? 1 : 0;
        
        $period = 'annual'; 
        
        // Features processing
        $rawFeatures = $_POST['features_csv'] ?? '';
        $featuresArray = array_filter(array_map('trim', explode(',', $rawFeatures)));
        $featuresJson = json_encode(array_values($featuresArray), JSON_UNESCAPED_UNICODE);

        if ($id) {
            // Update
            $stmt = $db->prepare("UPDATE plans SET name=?, price=?, mp_fee=?, allow_mp_integration=?, modo_fee=?, allow_modo_integration=?, mobile_module_enabled=?, pos_included=?, pos_extra_monthly_fee=?, pos_extra_annual_fee=?, features=?, active=? WHERE id=?");
            $stmt->execute([$name, $price, $mp_fee, $allow_mp, $modo_fee, $allow_modo, $mobile_enabled, $pos_included, $pos_extra_monthly_fee, $pos_extra_annual_fee, $featuresJson, $active, $id]);
            $message = "Plan actualizado correctamente.";
        } else {
            // Insert
            $code = strtolower(str_replace(' ', '_', $name)) . '_' . time();
            $stmt = $db->prepare("INSERT INTO plans (code, name, price, mp_fee, allow_mp_integration, modo_fee, allow_modo_integration, mobile_module_enabled, pos_included, pos_extra_monthly_fee, pos_extra_annual_fee, period, features, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $price, $mp_fee, $allow_mp, $modo_fee, $allow_modo, $mobile_enabled, $pos_included, $pos_extra_monthly_fee, $pos_extra_annual_fee, $period, $featuresJson, $active]);
            $message = "Nuevo plan creado.";
        }
    }
    elseif ($action === 'delete_plan') {
        $id = $_POST['id'] ?? '';
        try {
            $stmt = $db->prepare("DELETE FROM plans WHERE id=?");
            $stmt->execute([$id]);
            $message = "Plan eliminado.";
        } catch (Exception $e) {
            $message = "No se puede eliminar: compruebe si tiene suscripciones activas. Mejor desactívelo.";
        }
    }
}

// --- FETCH PLANS ---
$plans = $db->query("SELECT * FROM plans ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'layout_head.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Gestión de Planes de Licencia</h1>
    <button class="btn btn-primary" onclick="openModal()"><i class="bi bi-plus-circle me-1"></i> Nuevo Plan Anual</button>
</div>

<?php if($message): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Listado de Planes</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Precio Base</th>
                        <th>Opciones MP</th>
                        <th>Límites POS</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($plans as $p): 
                        $feats = json_decode($p['features'] ?? '[]', true);
                        $featsStr = implode(", ", $feats ?: []);
                        $mpFee = isset($p['mp_fee']) ? $p['mp_fee'] : 0;
                        $allowMp = isset($p['allow_mp_integration']) ? $p['allow_mp_integration'] : 1;
                        $posIncluded = isset($p['pos_included']) ? $p['pos_included'] : 1;
                        $posMonthly = isset($p['pos_extra_monthly_fee']) ? $p['pos_extra_monthly_fee'] : 500;
                        $posAnnual = isset($p['pos_extra_annual_fee']) ? $p['pos_extra_annual_fee'] : 5000;
                        
                        // MODO
                        $modoFee = isset($p['modo_fee']) ? $p['modo_fee'] : 0;
                        $allowModo = isset($p['allow_modo_integration']) ? $p['allow_modo_integration'] : 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td class="text-success fw-bold">$<?= number_format($p['price'], 2) ?></td>
                        <td class="small">
                            <?php if ($allowMp): ?>
                                <span class="badge bg-success">MP: Sí <?= $mpFee > 0 ? '(+$'.number_format($mpFee,2).')' : '' ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">MP: No</span>
                            <?php endif; ?>
                            <br>
                            <?php if ($allowModo): ?>
                                <span class="badge bg-info">MODO: Sí <?= $modoFee > 0 ? '(+$'.number_format($modoFee,2).')' : '' ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">MODO: No</span>
                            <?php endif; ?>
                            <br>
                            <?php if ($p['mobile_module_enabled']): ?>
                                <span class="badge bg-dark">Móvil: Sí</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted">Móvil: No</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <strong>Incluidos:</strong> <?= $posIncluded ?> Master<br>
                            <strong>Mensual:</strong> $<?= number_format($posMonthly, 2) ?>/mes<br>
                            <strong>Anual:</strong> $<?= number_format($posAnnual, 2) ?>/año
                        </td>
                        <td>
                            <?php if ($p['active']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button 
                                class="btn btn-sm btn-primary" 
                                onclick="editPlan(this)"
                                data-id="<?= $p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-price="<?= $p['price'] ?>"
                                data-mp_fee="<?= $mpFee ?>"
                                data-allow_mp="<?= $allowMp ?>"
                                data-modo_fee="<?= $modoFee ?>"
                                data-allow_modo="<?= $allowModo ?>"
                                data-pos_included="<?= $posIncluded ?>"
                                data-pos_monthly="<?= $posMonthly ?>"
                                data-pos_annual="<?= $posAnnual ?>"
                                data-features="<?= htmlspecialchars($featsStr) ?>"
                                data-active="<?= $p['active'] ?>"
                                data-mobile_enabled="<?= $p['mobile_module_enabled'] ?>"
                            >
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este plan?');">
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edit/Create -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg"> <!-- Modal Large -->
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nuevo Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_plan">
                    <input type="hidden" name="id" id="planId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre del Plan</label>
                        <input type="text" name="name" id="planName" class="form-control" required placeholder="Ej: Plan Corporativo">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Precio Base ($)</label>
                            <input type="number" step="0.01" name="price" id="planPrice" class="form-control" required>
                            <small class="form-text text-muted">Precio del plan (mensual o anual según corresponda)</small>
                        </div>
                    </div>

                    <div class="row mb-3 p-3 border rounded bg-light mx-1">
                        <h6 class="text-primary fw-bold mb-3"><i class="bi bi-credit-card"></i> Configuración Mercado Pago</h6>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_mp" id="planAllowMp" checked>
                                <label class="form-check-label" for="planAllowMp">Permitir Integración MP</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Costo Extra ($)</label>
                            <input type="number" step="0.01" name="mp_fee" id="planMpFee" class="form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row mb-3 p-3 border rounded bg-white mx-1 border-info">
                        <h6 class="text-info fw-bold mb-3"><i class="bi bi-wallet2"></i> Configuración MODO</h6>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_modo" id="planAllowModo">
                                <label class="form-check-label" for="planAllowModo">Permitir Integración MODO</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Costo Extra ($)</label>
                            <input type="number" step="0.01" name="modo_fee" id="planModoFee" class="form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row mb-3 p-3 border rounded bg-light mx-1">
                        <h6 class="text-primary fw-bold mb-3"><i class="bi bi-pc-display"></i> Licencias de Dispositivos (POS)</h6>
                        <div class="col-md-12 mb-2">
                            <div class="alert alert-info small mb-0">
                                <strong>ℹ️ Modelo de Licencias:</strong> Cada plan incluye 1 POS Master. Los POS adicionales (Slaves) se cobran por separado.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">POS Incluidos en el Plan</label>
                            <input type="number" name="pos_included" id="planPosIncluded" class="form-control" value="1" readonly>
                            <div class="form-text small">Siempre 1 Master</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Costo Mensual por Slave ($)</label>
                            <input type="number" step="0.01" name="pos_extra_monthly_fee" id="planPosMonthly" class="form-control" value="500.00">
                            <div class="form-text small">Precio por mes</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Costo Anual por Slave ($)</label>
                            <input type="number" step="0.01" name="pos_extra_annual_fee" id="planPosAnnual" class="form-control" value="5000.00">
                            <div class="form-text small">Recomendado: 10 meses</div>
                        </div>
                    </div>
                            <div class="row mb-3 p-3 border rounded bg-white mx-1 border-warning">
                                <h6 class="text-warning fw-bold mb-3"><i class="bi bi-phone"></i> Módulo Arcade Móvil</h6>
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="mobile_enabled" id="planMobileEnabled">
                                        <label class="form-check-label" for="planMobileEnabled">Activar Módulo PWA Arcade</label>
                                    </div>
                                    <small class="text-muted">Permite al tenant gestionar locales y empleados móviles para reporte de ventas.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Características (Separadas por comas)</label>
                        <textarea name="features_csv" id="planFeatures" class="form-control" rows="2" placeholder="Ej: Soporte VIP, Backup Diario"></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" id="planActive" checked>
                        <label class="form-check-label" for="planActive">Plan Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap !== 'undefined') {
            window.myPlanModal = new bootstrap.Modal(document.getElementById('planModal'));
        }
    });

    function openModal() {
        document.getElementById('planId').value = '';
        document.getElementById('planName').value = '';
        document.getElementById('planPrice').value = '';
        
        document.getElementById('planMpFee').value = '0';
        document.getElementById('planAllowMp').checked = true;
        
        document.getElementById('planModoFee').value = '0';
        document.getElementById('planAllowModo').checked = false;

        document.getElementById('planPosIncluded').value = '1';
        document.getElementById('planPosMonthly').value = '500';
        document.getElementById('planPosAnnual').value = '5000';
        
        document.getElementById('planMobileEnabled').checked = false;
        
        document.getElementById('planFeatures').value = '';
        document.getElementById('planActive').checked = true;
        document.getElementById('modalTitle').innerText = "Nuevo Plan";
        
        if (window.myPlanModal) window.myPlanModal.show();
    }

    function editPlan(btn) {
        document.getElementById('planId').value = btn.dataset.id;
        document.getElementById('planName').value = btn.dataset.name;
        document.getElementById('planPrice').value = btn.dataset.price;
        
        document.getElementById('planMpFee').value = btn.dataset.mp_fee || 0;
        document.getElementById('planAllowMp').checked = (btn.dataset.allow_mp == 1);
        
        document.getElementById('planModoFee').value = btn.dataset.modo_fee || 0;
        document.getElementById('planAllowModo').checked = (btn.dataset.allow_modo == 1);
        
        document.getElementById('planPosIncluded').value = btn.dataset.pos_included || 1;
        document.getElementById('planPosMonthly').value = btn.dataset.pos_monthly || 500;
        document.getElementById('planPosAnnual').value = btn.dataset.pos_annual || 5000;
        
        document.getElementById('planMobileEnabled').checked = (btn.dataset.mobile_enabled == 1);
        
        document.getElementById('planFeatures').value = btn.dataset.features;
        document.getElementById('planActive').checked = (btn.dataset.active == 1);
        document.getElementById('modalTitle').innerText = "Editar Plan";
        
        if (window.myPlanModal) window.myPlanModal.show();
    }
</script>

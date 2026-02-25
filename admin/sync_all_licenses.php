<?php
// admin/sync_all_licenses.php
// Utility script to sync all branches' licenses to device_licenses table

require_once __DIR__ . '/layout_head.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    try {
        // Get all branches with their licenses
        $stmt = $db->query("
            SELECT b.id, b.name, b.tenant_id, b.license_pos_expiry
            FROM branches b
            WHERE b.license_pos_expiry IS NOT NULL
        ");
        $branches = $stmt->fetchAll();
        
        $synced = 0;
        $failed = 0;
        
        foreach ($branches as $branch) {
            try {
                $tenantId = $branch['tenant_id'];
                $pos = $branch['license_pos_expiry'];
                
                if (!$tenantId) {
                    $results[] = [
                        'branch' => $branch['name'],
                        'status' => 'skipped',
                        'message' => 'No tenant_id asignado'
                    ];
                    continue;
                }
                
                // Update device_licenses for this tenant
                $expiryDatetime = $pos . ' 23:59:59';
                $stmt = $db->prepare("
                    UPDATE device_licenses 
                    SET expires_at = ?,
                        status = IF(? >= CURDATE(), 'active', 'expired'),
                        payment_status = IF(? >= CURDATE(), 'paid', 'overdue')
                    WHERE tenant_id = ?
                ");
                $stmt->execute([$expiryDatetime, $pos, $pos, $tenantId]);
                
                $rowsUpdated = $stmt->rowCount();
                
                $results[] = [
                    'branch' => $branch['name'],
                    'tenant' => $tenantId,
                    'expiry' => $pos,
                    'status' => 'success',
                    'devices_updated' => $rowsUpdated
                ];
                
                $synced++;
                
            } catch (PDOException $e) {
                $results[] = [
                    'branch' => $branch['name'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $failed++;
            }
        }
        
        $message = "Sincronización completada: {$synced} sucursales sincronizadas, {$failed} errores.";
        
    } catch (Exception $e) {
        $error = "Error al sincronizar: " . $e->getMessage();
    }
}

// Get current state for display
$stmt = $db->query("
    SELECT 
        b.id,
        b.name as branch_name,
        b.tenant_id,
        b.license_pos_expiry as branch_license,
        COUNT(dl.id) as device_count,
        MIN(dl.expires_at) as min_device_expiry,
        MAX(dl.expires_at) as max_device_expiry
    FROM branches b
    LEFT JOIN device_licenses dl ON dl.tenant_id = b.tenant_id
    GROUP BY b.id, b.name, b.tenant_id, b.license_pos_expiry
    ORDER BY b.name
");
$branchesStatus = $stmt->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-arrow-repeat me-2"></i>Sincronización de Licencias</h1>
    <a href="licenses.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle"></i> Acerca de esta herramienta</h5>
            <p>Esta utilidad sincroniza las fechas de licencia POS desde la tabla <code>branches</code> hacia la tabla <code>device_licenses</code>.</p>
            <p><strong>¿Cuándo usar?</strong> Cuando actualices licencias desde el admin y no se reflejen en el panel del cliente.</p>
        </div>
    </div>
</div>

<!-- Current State -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Estado Actual de Licencias</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        <th>Tenant ID</th>
                        <th>Licencia en Branches</th>
                        <th>Dispositivos</th>
                        <th>Licencias en Devices</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branchesStatus as $b): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                        <td><?= $b['tenant_id'] ?? '<span class="text-muted">Sin tenant</span>' ?></td>
                        <td>
                            <?php if ($b['branch_license']): ?>
                                <span class="badge <?= (strtotime($b['branch_license']) >= time()) ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $b['branch_license'] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Sin licencia</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $b['device_count'] ?></td>
                        <td>
                            <?php if ($b['device_count'] > 0): ?>
                                <?php 
                                $minDate = substr($b['min_device_expiry'], 0, 10);
                                $maxDate = substr($b['max_device_expiry'], 0, 10);
                                ?>
                                <?php if ($minDate === $maxDate): ?>
                                    <small><?= $minDate ?></small>
                                <?php else: ?>
                                    <small><?= $minDate ?> ~ <?= $maxDate ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $branchDate = $b['branch_license'] ? substr($b['branch_license'], 0, 10) : null;
                            $deviceDate = $b['device_count'] > 0 ? substr($b['min_device_expiry'], 0, 10) : null;
                            
                            if (!$branchDate) {
                                echo '<span class="badge bg-secondary">Sin licencia</span>';
                            } elseif ($branchDate === $deviceDate) {
                                echo '<span class="badge bg-success">Sincronizado</span>';
                            } else {
                                echo '<span class="badge bg-warning text-dark">Desincronizado</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sync Action -->
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Acción de Sincronización</h5>
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return confirm('¿Estás seguro de que quieres sincronizar TODAS las sucursales? Esto actualizará la tabla device_licenses para todos los tenants.');">
            <input type="hidden" name="sync_all" value="1">
            <p>Al hacer clic en "Sincronizar Todo", se copiarán las fechas de <code>branches.license_pos_expiry</code> a <code>device_licenses.expires_at</code> para todos los dispositivos de cada tenant.</p>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-arrow-repeat"></i> Sincronizar Todas las Sucursales
            </button>
        </form>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Resultados de la Sincronización</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        <th>Tenant</th>
                        <th>Nueva Fecha</th>
                        <th>Dispositivos Actualizados</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['branch']) ?></td>
                        <td><?= $r['tenant'] ?? '-' ?></td>
                        <td><?= $r['expiry'] ?? '-' ?></td>
                        <td><?= $r['devices_updated'] ?? '-' ?></td>
                        <td>
                            <?php if ($r['status'] === 'success'): ?>
                                <span class="badge bg-success">✓ Exitoso</span>
                            <?php elseif ($r['status'] === 'skipped'): ?>
                                <span class="badge bg-secondary">Omitido</span>
                                <small class="text-muted"><?= $r['message'] ?></small>
                            <?php else: ?>
                                <span class="badge bg-danger">Error</span>
                                <small class="text-danger"><?= $r['message'] ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'layout_foot.php'; ?>

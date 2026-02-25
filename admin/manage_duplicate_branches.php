<?php
// admin/manage_duplicate_branches.php
// Tool to identify and safely remove duplicate branch records

require_once __DIR__ . '/layout_head.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    $branchId = $_POST['branch_id'];
    $confirmed = isset($_POST['confirm_delete']) ? $_POST['confirm_delete'] : false;
    
    if (!$confirmed) {
        $error = "Debes confirmar la eliminación marcando la casilla.";
    } else {
        try {
            // Check if branch has any associated data
            $checks = [];
            
            // Check users
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
            $stmt->execute([$branchId]);
            $userCount = $stmt->fetchColumn();
            $checks['users'] = $userCount;
            
            // Check machines
            $stmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE branch_id = ?");
            $stmt->execute([$branchId]);
            $machineCount = $stmt->fetchColumn();
            $checks['machines'] = $machineCount;
            
            // Check sales
            $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE branch_id = ?");
            $stmt->execute([$branchId]);
            $salesCount = $stmt->fetchColumn();
            $checks['sales'] = $salesCount;
            
            // If has critical data, prevent deletion
            if ($userCount > 0 || $machineCount > 0 || $salesCount > 0) {
                $error = "No se puede eliminar esta sucursal porque tiene datos asociados: ";
                $error .= $userCount . " usuarios, ";
                $error .= $machineCount . " máquinas, ";
                $error .= $salesCount . " ventas. ";
                $error .= "Primero debes mover o eliminar estos datos.";
            } else {
                // Safe to delete
                $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
                $stmt->execute([$branchId]);
                
                $message = "Sucursal eliminada exitosamente (ID: {$branchId})";
            }
            
        } catch (PDOException $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    }
}

// Get all branches grouped by name to find duplicates
$stmt = $db->query("
    SELECT 
        b.id,
        b.name,
        b.tenant_id,
        b.license_pos_expiry,
        b.status,
        b.created_at,
        COUNT(u.id) as user_count,
        COUNT(m.id) as machine_count,
        (SELECT COUNT(*) FROM sales WHERE branch_id = b.id) as sales_count
    FROM branches b
    LEFT JOIN users u ON u.branch_id = b.id
    LEFT JOIN machines m ON m.branch_id = b.id
    GROUP BY b.id, b.name, b.tenant_id, b.license_pos_expiry, b.status, b.created_at
    ORDER BY b.name, b.created_at
");
$allBranches = $stmt->fetchAll();

// Group by name to identify duplicates
$groupedBranches = [];
foreach ($allBranches as $branch) {
    $name = $branch['name'];
    if (!isset($groupedBranches[$name])) {
        $groupedBranches[$name] = [];
    }
    $groupedBranches[$name][] = $branch;
}

// Filter only duplicates
$duplicates = array_filter($groupedBranches, function($group) {
    return count($group) > 1;
});

?>

<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-exclamation-triangle me-2"></i>Gestión de Sucursales Duplicadas</h1>
    <a href="branches.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
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

<div class="alert alert-warning">
    <h5><i class="bi bi-exclamation-triangle"></i> Acerca de esta herramienta</h5>
    <p>Esta herramienta identifica sucursales con el mismo nombre y te permite eliminar los duplicados de forma segura.</p>
    <p><strong>⚠️ IMPORTANTE:</strong> Solo puedes eliminar sucursales que NO tengan usuarios, máquinas o ventas asociadas.</p>
</div>

<?php if (empty($duplicates)): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> <strong>¡Excelente!</strong> No se encontraron sucursales duplicadas.
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Se encontraron <strong><?= count($duplicates) ?></strong> nombre(s) de sucursal con duplicados.
    </div>

    <?php foreach ($duplicates as $name => $branches): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-exclamation-triangle"></i> 
                Sucursal: <strong><?= htmlspecialchars($name) ?></strong> 
                <span class="badge bg-dark"><?= count($branches) ?> duplicados</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tenant ID</th>
                            <th>Licencia POS</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Usuarios</th>
                            <th>Máquinas</th>
                            <th>Ventas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $b): ?>
                        <?php 
                        $isEmpty = $b['user_count'] == 0 && $b['machine_count'] == 0 && $b['sales_count'] == 0;
                        $isActive = $b['license_pos_expiry'] && strtotime($b['license_pos_expiry']) >= time();
                        ?>
                        <tr class="<?= $isEmpty ? 'table-warning' : '' ?>">
                            <td><code><?= $b['id'] ?></code></td>
                            <td><?= $b['tenant_id'] ?? '<span class="text-muted">N/A</span>' ?></td>
                            <td>
                                <?php if ($b['license_pos_expiry']): ?>
                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $b['license_pos_expiry'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Sin licencia</span>
                                <?php endif; ?>
                            </td>
                            <td><?= ucfirst($b['status'] ?? 'N/A') ?></td>
                            <td><small><?= $b['created_at'] ?></small></td>
                            <td>
                                <span class="badge <?= $b['user_count'] > 0 ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= $b['user_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $b['machine_count'] > 0 ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= $b['machine_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $b['sales_count'] > 0 ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= $b['sales_count'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isEmpty): ?>
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $b['id'] ?>">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-info">Con datos</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?= $b['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">
                                            <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="delete_branch" value="1">
                                            <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
                                            
                                            <div class="alert alert-danger">
                                                <strong>⚠️ ADVERTENCIA:</strong> Esta acción es PERMANENTE y NO se puede deshacer.
                                            </div>
                                            
                                            <p><strong>Estás por eliminar:</strong></p>
                                            <ul>
                                                <li>Sucursal: <strong><?= htmlspecialchars($name) ?></strong></li>
                                                <li>ID: <code><?= $b['id'] ?></code></li>
                                                <li>Tenant ID: <?= $b['tenant_id'] ?? 'N/A' ?></li>
                                                <li>Creado: <?= $b['created_at'] ?></li>
                                            </ul>
                                            
                                            <div class="form-check mt-3">
                                                <input class="form-check-input" type="checkbox" name="confirm_delete" id="confirm<?= $b['id'] ?>" required>
                                                <label class="form-check-label text-danger" for="confirm<?= $b['id'] ?>">
                                                    <strong>Confirmo que quiero eliminar esta sucursal de forma permanente</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-trash"></i> Eliminar Definitivamente
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                <small>
                    <strong>💡 Recomendación:</strong> 
                    Elimina las sucursales que estén vacías (0 usuarios, 0 máquinas, 0 ventas). 
                    Si una tiene datos importantes, primero elimina los duplicados vacíos.
                </small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- All branches view -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Todas las Sucursales (para referencia)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tenant</th>
                        <th>Usuarios</th>
                        <th>Máquinas</th>
                        <th>Ventas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allBranches as $b): ?>
                    <tr>
                        <td><code><?= $b['id'] ?></code></td>
                        <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                        <td><?= $b['tenant_id'] ?? '-' ?></td>
                        <td><?= $b['user_count'] ?></td>
                        <td><?= $b['machine_count'] ?></td>
                        <td><?= $b['sales_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

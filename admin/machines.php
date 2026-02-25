<?php
// admin/machines.php
require_once 'layout_head.php';

// Allow Admin and Branch Manager
$auth->requireRole(['admin', 'branch_manager']);
$currentUser = $auth->getCurrentUser();
$isManager = ($currentUser['role'] === 'branch_manager');
$userBranchId = $currentUser['branch_id'];

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$driver = \Database::getInstance()->getDriver();
$tenantId = $currentUser['tenant_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // If Manager, force branch_id
    $branch_id_input = $isManager ? $userBranchId : ($_POST['branch_id'] ?: null);

    if ($action === 'create') {
        $id = strtoupper($_POST['id']);
        $name = $_POST['name'];
        $price = $_POST['price'];
        
        // Validation: Manager cannot create for other branches
        if ($isManager && $branch_id_input !== $userBranchId) {
             $error = "No tienes permiso para crear máquinas en otra sucursal.";
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "El ID ya existe.";
            } else {
                try {
                    $sql = ($driver === 'sqlite') 
                        ? "INSERT INTO machines (id, name, price, branch_id) VALUES (?, ?, ?, ?)"
                        : "INSERT INTO machines (id, tenant_id, name, price, branch_id) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    
                    if ($driver === 'sqlite') {
                        $stmt->execute([$id, $name, $price, $branch_id_input]);
                    } else {
                        $stmt->execute([$id, $tenantId, $name, $price, $branch_id_input]);
                    }
                    
                    // Queue for sync
                    $payload = json_encode(['id' => $id, 'name' => $name, 'price' => $price, 'branch_id' => $branch_id_input, 'active' => 1]);
                    $db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload) VALUES ('machine', ?, ?)")->execute([$id, $payload]);
                    
                    $message = "Máquina creada.";
                } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
            }
        }
    }
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $price = $_POST['price'];
        
        // Verify ownership if Manager
        if ($isManager) {
             $stmt = $db->prepare("SELECT branch_id FROM machines WHERE id = ?");
             $stmt->execute([$id]);
             $existing = $stmt->fetch();
             if ($existing && $existing['branch_id'] !== $userBranchId) {
                 $error = "No tienes permiso para editar esta máquina (pertenece a otra sucursal).";
             }
        }
        
        if (!$error) {
             try {
                $stmt = $db->prepare("UPDATE machines SET name=?, price=?, branch_id=? WHERE id=?");
                $stmt->execute([$name, $price, $branch_id_input, $id]);
                
                // Sync Queue (Edición)
                $payload = json_encode(['id' => $id, 'name' => $name, 'price' => $price, 'branch_id' => $branch_id_input, 'active' => 1]);
                $db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload) VALUES ('machine', ?, ?)")->execute([$id, $payload]);

                $message = "Máquina actualizada.";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
    elseif ($action === 'toggle') {
        $id = $_POST['id'];
        
         // Verify ownership if Manager
        if ($isManager) {
             $stmt = $db->prepare("SELECT branch_id FROM machines WHERE id = ?");
             $stmt->execute([$id]);
             $existing = $stmt->fetch();
             if ($existing && $existing['branch_id'] !== $userBranchId) {
                 $error = "No tienes permiso para modificar esta máquina.";
             }
        }

        if (!$error) {
            $current_status = $_POST['current_status'];
            $new_status = $current_status == 1 ? 0 : 1;
            try {
                // Get machine data for sync
                $machineData = $db->prepare("SELECT * FROM machines WHERE id = ?");
                $machineData->execute([$id]);
                $machine = $machineData->fetch();
                
                $stmt = $db->prepare("UPDATE machines SET active = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                
                // Queue for sync
                if ($machine) {
                    $payload = json_encode(['id' => $id, 'name' => $machine['name'], 'price' => $machine['price'], 'branch_id' => $machine['branch_id'], 'active' => $new_status]);
                    $db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload) VALUES ('machine', ?, ?)")->execute([$id, $payload]);
                }
                
                $message = "Estado actualizado.";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
    elseif ($action === 'delete') {
         $id = $_POST['id'];
         
         // Verify ownership if Manager
        if ($isManager) {
             $stmt = $db->prepare("SELECT branch_id FROM machines WHERE id = ?");
             $stmt->execute([$id]);
             $existing = $stmt->fetch();
             if ($existing && $existing['branch_id'] !== $userBranchId) {
                 $error = "No tienes permiso para eliminar esta máquina.";
             }
        }

         if (!$error) {
             try {
                 $stmt = $db->prepare("DELETE FROM machines WHERE id = ?");
                 $stmt->execute([$id]);
                 $message = "Máquina eliminada.";
             } catch (PDOException $e) { $error = "Error (probablemente tenga ventas asociadas): " . $e->getMessage(); }
         }
    }
}

// List Machines
if ($isManager) {
    $where = ($driver === 'sqlite') ? "WHERE m.branch_id = ?" : "WHERE m.tenant_id = ? AND m.branch_id = ?";
    $stmt = $db->prepare("
        SELECT m.*, b.name as branch_name 
        FROM machines m 
        LEFT JOIN branches b ON m.branch_id = b.id 
        $where
        ORDER BY m.id
    ");
    if ($driver === 'sqlite') {
        $stmt->execute([$userBranchId]);
    } else {
        $stmt->execute([$tenantId, $userBranchId]);
    }
    $machines = $stmt->fetchAll();
} else {
    // Admin sees all within tenant (or all in local)
    $where = ($driver === 'sqlite') ? "" : "WHERE m.tenant_id = ?";
    $stmt = $db->prepare("
        SELECT m.*, b.name as branch_name 
        FROM machines m 
        LEFT JOIN branches b ON m.branch_id = b.id 
        $where
        ORDER BY m.id
    ");
    if ($driver === 'sqlite') {
        $stmt->execute();
    } else {
        $stmt->execute([$tenantId]);
    }
    $machines = $stmt->fetchAll();
}

if ($driver === 'sqlite') {
    $branches = $db->query("SELECT * FROM branches WHERE status = 1")->fetchAll();
} else {
    $stmtB = $db->prepare("SELECT * FROM branches WHERE tenant_id = ? AND status = 1");
    $stmtB->execute([$tenantId]);
    $branches = $stmtB->fetchAll();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestión de Máquinas <?= $isManager ? '(Mi Sucursal)' : '' ?></h1>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="bi bi-controller"></i> Nueva Máquina
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Precio</th>
                <?php if(!$isManager): ?><th>Sucursal</th><?php endif; ?>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($machines as $mach): ?>
            <tr>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($mach['id']) ?></span></td>
                <td><?= htmlspecialchars($mach['name']) ?></td>
                <td>$<?= number_format($mach['price'], 2) ?></td>
                <?php if(!$isManager): ?>
                    <td><?= htmlspecialchars($mach['branch_name'] ?? 'Global') ?></td>
                <?php endif; ?>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $mach['id'] ?>">
                        <input type="hidden" name="current_status" value="<?= $mach['active'] ?>">
                        <button type="submit" class="btn btn-sm btn-<?= $mach['active'] ? 'success' : 'secondary' ?>">
                            <?= $mach['active'] ? 'Activa' : 'Inactiva' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" 
                        onclick="openEditModal('<?= $mach['id'] ?>', '<?= $mach['name'] ?>', '<?= $mach['price'] ?>', '<?= $mach['branch_id'] ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar máquina?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $mach['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="machineModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Máquina</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                
                <div class="mb-3">
                    <label>ID / Código</label>
                    <input type="text" name="id" id="id" class="form-control" required style="text-transform: uppercase;">
                    <small class="text-muted">Si edita, el ID no se puede cambiar.</small>
                </div>
                <div class="mb-3">
                    <label>Nombre del Juego</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Valor de la Ficha (ARS)</label>
                    <input type="number" name="price" id="price" class="form-control" required step="0.01" min="0">
                </div>
                
                <?php if ($isManager): ?>
                    <!-- Hidden Branch for Manager -->
                    <input type="hidden" name="branch_id" value="<?= $userBranchId ?>">
                <?php else: ?>
                    <div class="mb-3">
                        <label>Asignar a Sucursal</label>
                        <select name="branch_id" id="branch_id" class="form-select">
                            <option value="">-- Global / Todas --</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    var machineModal;
    document.addEventListener('DOMContentLoaded', function() {
        machineModal = new bootstrap.Modal(document.getElementById('machineModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nueva Máquina';
        document.getElementById('formAction').value = 'create';
        document.getElementById('id').value = '';
        document.getElementById('id').readOnly = false;
        document.getElementById('name').value = '';
        document.getElementById('price').value = '';
        
        <?php if (!$isManager): ?>
        document.getElementById('branch_id').value = '';
        <?php endif; ?>
        
        machineModal.show();
    }

    function openEditModal(id, name, price, branchId) {
        document.getElementById('modalTitle').innerText = 'Editar Máquina ' + id;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('id').value = id;
        document.getElementById('id').readOnly = true;
        document.getElementById('name').value = name;
        document.getElementById('price').value = price;
        
        <?php if (!$isManager): ?>
        document.getElementById('branch_id').value = branchId || '';
        <?php endif; ?>
        
        machineModal.show();
    }
</script>

<?php require_once 'layout_foot.php'; ?>

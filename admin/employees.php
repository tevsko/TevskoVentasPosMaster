<?php
// admin/employees.php
require_once 'layout_head.php';
require_once __DIR__ . '/../src/Uuid.php';

// Allow Admin OR Branch Manager
$auth->requireRole(['admin', 'branch_manager']);
$db = Database::getInstance()->getConnection();
$isManager = $auth->isBranchManager();
$managerBranchId = $isManager ? $currentUser['branch_id'] : null;
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = $_POST['username'];
        $emp_name = $_POST['emp_name'] ?? '';
        $emp_email = $_POST['emp_email'] ?? '';
        $password = $_POST['password'];
        $role = $_POST['role'];
        $branch_id = $_POST['branch_id'] ?: null;

        // Security override for Manager
        if ($isManager) {
            $role = 'employee';
            $branch_id = $managerBranchId;
        }
        
        // --- CHECK POS LIMIT for EMPLOYEES ---
        if ($role === 'employee' && $branch_id) {
            $stmt = $db->prepare("SELECT pos_license_limit FROM branches WHERE id = ?");
            $stmt->execute([$branch_id]);
            $limit = $stmt->fetchColumn() ?: 1;
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ? AND role = 'employee'");
            $stmt->execute([$branch_id]);
            $currentEmployees = $stmt->fetchColumn();
            
            if ($currentEmployees >= $limit) {
                $error = "Límite de empleados (Cajeros POS) alcanzado para esta sucursal ($currentEmployees / $limit).";
            }
        }
        // -------------------------------------
        
        if (!$error) {
            // Check uniqueness scoped by branch
            if ($branch_id) {
                // Check in specific branch
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND branch_id = ?");
                $stmt->execute([$username, $branch_id]);
            } else {
                // Check in global scope
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND branch_id IS NULL");
                $stmt->execute([$username]);
            }

            if ($stmt->fetchColumn() > 0) {
                $error = "El usuario ya existe " . ($branch_id ? "en esta sucursal." : "como administrador global.");
            } else {
                $id = Uuid::generate();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $db->prepare("INSERT INTO users (id, username, emp_name, emp_email, password_hash, role, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $username, $emp_name, $emp_email, $hash, $role, $branch_id]);
                    $message = "Empleado creado correctamente.";
                } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
            }
        }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $emp_name = $_POST['emp_name'] ?? '';
        $emp_email = $_POST['emp_email'] ?? '';
        $role = $_POST['role'];
        $branch_id = $_POST['branch_id'] ?: null;
        
        // Security override for Manager
        if ($isManager) {
            // Verify target user belongs to my branch
            $stmtCheck = $db->prepare("SELECT branch_id FROM users WHERE id = ?");
            $stmtCheck->execute([$id]);
            $targetUserBranch = $stmtCheck->fetchColumn();
            
            if ($targetUserBranch !== $managerBranchId) {
                die("Acceso Denegado: No puedes editar usuarios de otra sucursal.");
            }
            
            $role = 'employee';
            $branch_id = $managerBranchId;
        }
        // Solo actualizar password si se ingresa
        $new_pass = $_POST['password'];

        try {
            $sql = "UPDATE users SET emp_name = ?, emp_email = ?, role = ?, branch_id = ?";
            $params = [$emp_name, $emp_email, $role, $branch_id];
            
            if (!empty($new_pass)) {
                $sql .= ", password_hash = ?";
                $params[] = password_hash($new_pass, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $message = "Usuario actualizado.";
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    }
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        if ($id === $currentUser['id']) {
            $error = "No puedes eliminar tu propia cuenta.";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Usuario eliminado.";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
    elseif ($action === 'toggle_active') {
        $id = $_POST['id'];
        $current_status = $_POST['current_status'];
        
        if ($id === $currentUser['id']) {
            $error = "No puedes desactivar tu propia cuenta.";
        } else {
            // Check permissions
            if ($isManager) {
                $stmt = $db->prepare("SELECT branch_id, role FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $target = $stmt->fetch();
                
                if (!$target || $target['branch_id'] !== $managerBranchId) {
                    $error = "No tienes permiso para modificar este usuario.";
                } elseif ($target['role'] === 'admin') {
                     $error = "No puedes modificar a un administrador.";
                }
            }
            
            if (!$error) {
                try {
                    $new_status = $current_status == 1 ? 0 : 1;
                    $stmt = $db->prepare("UPDATE users SET active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $id]);
                    $message = "Estado del usuario actualizado.";
                } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
            }
        }
    }
}

// List Users
// List Users Logic
$sqlUsers = "
    SELECT u.*, b.name as branch_name 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.id 
";

if ($isManager) {
    $sqlUsers .= " WHERE u.branch_id = '$managerBranchId' AND u.role != 'admin' ";
}

$sqlUsers .= " ORDER BY u.role, u.username";
$users = $db->query($sqlUsers)->fetchAll();

$branches = $db->query("SELECT * FROM branches WHERE status = 1")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestión de Empleados</h1>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="bi bi-person-plus"></i> Nuevo Empleado
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
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Sucursal Asignada</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['emp_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($user['emp_email'] ?? '-') ?></td>
                <td>
                    <?php if($user['role'] === 'admin'): ?>
                        <span class="badge bg-danger">ADMIN</span>
                    <?php elseif($user['role'] === 'branch_manager'): ?>
                        <span class="badge bg-warning text-dark">GERENTE</span>
                    <?php else: ?>
                        <span class="badge bg-success">EMPLEADO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-<?= ($user['active'] ?? 1) ? 'success' : 'danger' ?>">
                        <?= ($user['active'] ?? 1) ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($user['branch_name'] ?? 'Global / N/A') ?></td>
                <td>
                    <?php if ($user['id'] !== $currentUser['id']): ?>
                    <form method="POST" style="display:inline;" class="me-1">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="current_status" value="<?= $user['active'] ?? 1 ?>">
                        <button type="submit" class="btn btn-sm btn-<?= ($user['active'] ?? 1) ? 'secondary' : 'success' ?>" title="<?= ($user['active'] ?? 1) ? 'Desactivar' : 'Activar' ?>">
                            <i class="bi bi-power"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" 
                            onclick="openEditModal('<?= $user['id'] ?>', '<?= $user['username'] ?>', '<?= htmlspecialchars($user['emp_name'] ?? '') ?>', '<?= htmlspecialchars($user['emp_email'] ?? '') ?>', '<?= $user['role'] ?>', '<?= $user['branch_id'] ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    
                    <?php if ($user['username'] !== 'admin' && $user['id'] !== $currentUser['id']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que desea eliminar?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Form -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="userId">
                
                <div class="mb-3">
                    <label>Nombre de Usuario (Login)</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                    <small class="text-muted">Necesario para iniciar sesión</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nombre del Empleado</label>
                        <input type="text" name="emp_name" id="emp_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="email" name="emp_email" id="emp_email" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Dejar vacío para no cambiar (en edición)">
                </div>
                
                <div class="mb-3">
                    <label>Rol</label>
                    <select name="role" id="role" class="form-select" <?= $isManager ? 'disabled' : '' ?>>
                        <option value="employee">Empleado</option>
                        <option value="branch_manager">Gerente de Sucursal</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label>Asignar a Sucursal</label>
                    <select name="branch_id" id="branch_id" class="form-select" <?= $isManager ? 'disabled' : '' ?>>
                        <option value="">-- Ninguna / Global --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($isManager): ?>
                        <small class="text-muted">Se asignará automáticamente a su sucursal.</small>
                    <?php else: ?>
                        <small class="text-muted">Si es Gerente, esta será la sucursal que podrá administrar.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    var userModal;
    document.addEventListener('DOMContentLoaded', function() {
        userModal = new bootstrap.Modal(document.getElementById('userModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Usuario';
        document.getElementById('formAction').value = 'create';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('username').readOnly = false;
        
        document.getElementById('emp_name').value = '';
        document.getElementById('emp_email').value = '';

        document.getElementById('role').value = 'employee';
        document.getElementById('branch_id').value = '';
        userModal.show();
    }

    function openEditModal(id, username, empName, empEmail, role, branchId) {
        document.getElementById('modalTitle').innerText = 'Editar Usuario: ' + username;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('userId').value = id;
        document.getElementById('username').value = username;
        document.getElementById('username').readOnly = true; 
        
        document.getElementById('emp_name').value = empName || '';
        document.getElementById('emp_email').value = empEmail || '';

        document.getElementById('role').value = role;
        document.getElementById('branch_id').value = branchId || '';
        userModal.show();
    }
</script>

<?php require_once 'layout_foot.php'; ?>

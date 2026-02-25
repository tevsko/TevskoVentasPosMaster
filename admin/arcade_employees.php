<?php
// admin/arcade_employees.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$tenantId = $currentUser['tenant_id'] ?? null;

// Get available locations for this tenant
$stmtL = $db->prepare("SELECT id, location_name FROM arcade_locations WHERE tenant_id = ? AND active = 1 ORDER BY location_name ASC");
$stmtL->execute([$tenantId]);
$locations = $stmtL->fetchAll();

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $location_id = $_POST['location_id'];
        $full_name = $_POST['full_name'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $salary = $_POST['salary'] ?: 20000;
        
        try {
            // Check if username already exists in this location
            $stmtCheck = $db->prepare("SELECT id FROM arcade_employees WHERE location_id = ? AND username = ?");
            $stmtCheck->execute([$location_id, $username]);
            if ($stmtCheck->fetch()) {
                $error = "El nombre de usuario ya existe en este local.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO arcade_employees (location_id, username, password_hash, full_name, daily_salary, active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$location_id, $username, $password_hash, $full_name, $salary]);
                $message = "Empleado creado correctamente.";
            }
        } catch (PDOException $e) {
            $error = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $location_id = $_POST['location_id'];
        $full_name = $_POST['full_name'];
        $salary = $_POST['salary'];
        $active = isset($_POST['active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE arcade_employees SET location_id = ?, full_name = ?, daily_salary = ?, active = ? WHERE id = ?");
            $stmt->execute([$location_id, $full_name, $salary, $active, $id]);
            
            // Si hay nueva contraseña
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmtP = $db->prepare("UPDATE arcade_employees SET password_hash = ? WHERE id = ?");
                $stmtP->execute([$password_hash, $id]);
            }
            
            $message = "Empleado actualizado.";
        } catch (PDOException $e) {
            $error = "Error al editar: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        try {
            $stmt = $db->prepare("UPDATE arcade_employees SET active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Filter logic
$filter_location = $_GET['location_id'] ?? '';
$sql = "SELECT e.*, l.location_name FROM arcade_employees e 
        JOIN arcade_locations l ON e.location_id = l.id 
        WHERE l.tenant_id = ?";
$params = [$tenantId];

if ($filter_location) {
    $sql .= " AND e.location_id = ?";
    $params[] = $filter_location;
}

$sql .= " ORDER BY l.location_name ASC, e.full_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-person-badge me-2"></i>Empleados Móviles</h1>
    <div class="d-flex gap-2">
        <form class="d-flex gap-2 align-items-center">
            <select name="location_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Todos los Locales --</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filter_location == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" onclick="openCreateModal()">
            <i class="bi bi-person-plus"></i> Nuevo Empleado
        </button>
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

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr>
                        <th>Empleado</th>
                        <th>Usuario</th>
                        <th>Local</th>
                        <th>Sueldo Diario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No se encontraron empleados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($emp['full_name']) ?></div>
                                    <div class="small text-muted">ID: #<?= $emp['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($emp['username']) ?></code></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($emp['location_name']) ?></span></td>
                        <td>$<?= number_format($emp['daily_salary'], 2) ?></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       <?= $emp['active'] ? 'checked' : '' ?> 
                                       onchange="toggleStatus(<?= $emp['id'] ?>, this.checked)">
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="openEditModal(<?= $emp['id'] ?>, <?= $emp['location_id'] ?>, '<?= htmlspecialchars($emp['full_name']) ?>', '<?= htmlspecialchars($emp['username']) ?>', <?= $emp['daily_salary'] ?>, <?= $emp['active'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nuevo Empleado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="empId">
                
                <div class="mb-3">
                    <label class="form-label">Local Asignado</label>
                    <select name="location_id" id="empLocation" class="form-select" required>
                        <option value="">-- Seleccionar Local --</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" name="full_name" id="empFullName" class="form-control" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="username" id="empUsername" class="form-control" required>
                        <small class="text-muted">Usado para el login en la app.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" id="empPassword" class="form-control">
                        <small id="pwHelp" class="text-muted">Requerido para nuevos.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Sueldo Diario ($)</label>
                    <input type="number" name="salary" id="empSalary" class="form-control" value="20000" step="0.01">
                </div>

                <div class="form-check form-switch mt-3" id="activeContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" name="active" id="empActive" checked>
                    <label class="form-check-label">Empleado Activo</label>
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
    var employeeModal;
    document.addEventListener('DOMContentLoaded', function() {
        employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Empleado Móvil';
        document.getElementById('formAction').value = 'create';
        document.getElementById('empId').value = '';
        document.getElementById('empLocation').value = '<?= $filter_location ?>';
        document.getElementById('empFullName').value = '';
        document.getElementById('empUsername').value = '';
        document.getElementById('empUsername').readOnly = false;
        document.getElementById('empPassword').required = true;
        document.getElementById('pwHelp').innerText = 'Requerido para entrar a la app.';
        document.getElementById('empSalary').value = '20000';
        document.getElementById('activeContainer').style.display = 'none';
        employeeModal.show();
    }

    function openEditModal(id, locId, fullName, username, salary, active) {
        document.getElementById('modalTitle').innerText = 'Editar Empleado';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('empId').value = id;
        document.getElementById('empLocation').value = locId;
        document.getElementById('empFullName').value = fullName;
        document.getElementById('empUsername').value = username;
        document.getElementById('empUsername').readOnly = true;
        document.getElementById('empPassword').required = false;
        document.getElementById('pwHelp').innerText = 'Dejar en blanco para mantener actual.';
        document.getElementById('empSalary').value = salary;
        document.getElementById('empActive').checked = (active == 1);
        document.getElementById('activeContainer').style.display = 'block';
        employeeModal.show();
    }

    function toggleStatus(id, active) {
        const status = active ? 1 : 0;
        fetch('arcade_employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_status&id=${id}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error al actualizar estado: ' + data.error);
                location.reload();
            }
        });
    }
</script>

<?php require_once 'layout_foot.php'; ?>

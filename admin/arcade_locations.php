<?php
// admin/arcade_locations.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';
$tenantId = $currentUser['tenant_id'] ?? null;

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = $_POST['name'];
        $address = $_POST['address'];
        
        try {
            $stmt = $db->prepare("INSERT INTO arcade_locations (tenant_id, location_name, address, active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$tenantId, $name, $address]);
            $message = "Local creado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $address = $_POST['address'];
        $active = isset($_POST['active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE arcade_locations SET location_name = ?, address = ?, active = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $address, $active, $id, $tenantId]);
            $message = "Local actualizado.";
        } catch (PDOException $e) {
            $error = "Error al editar: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        try {
            $stmt = $db->prepare("UPDATE arcade_locations SET active = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$status, $id, $tenantId]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// List Locations
$stmt = $db->prepare("SELECT * FROM arcade_locations WHERE tenant_id = ? ORDER BY id DESC");
$stmt->execute([$tenantId]);
$locations = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-geo-alt me-2"></i>Locales de Arcade</h1>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="bi bi-plus-lg"></i> Nuevo Local
    </button>
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
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Local</th>
                        <th>Dirección</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No hay locales registrados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td><span class="text-muted">#<?= $loc['id'] ?></span></td>
                        <td><strong><?= htmlspecialchars($loc['location_name']) ?></strong></td>
                        <td><?= htmlspecialchars($loc['address'] ?: '-') ?></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       <?= $loc['active'] ? 'checked' : '' ?> 
                                       onchange="toggleStatus(<?= $loc['id'] ?>, this.checked)">
                                <span class="badge <?= $loc['active'] ? 'bg-success' : 'bg-secondary' ?> ms-2">
                                    <?= $loc['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="openEditModal(<?= $loc['id'] ?>, '<?= htmlspecialchars($loc['location_name']) ?>', '<?= htmlspecialchars($loc['address']) ?>', <?= $loc['active'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="arcade_products.php?location_id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-info" title="Gestionar Productos">
                                    <i class="bi bi-basket"></i>
                                </a>
                                <a href="arcade_employees.php?location_id=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-warning" title="Gestionar Empleados">
                                    <i class="bi bi-people"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Creación/Edición -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nuevo Local</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="locId">
                <div class="mb-3">
                    <label class="form-label">Nombre del Local</label>
                    <input type="text" name="name" id="locName" class="form-control" placeholder="Ej: Arcade Central" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="address" id="locAddress" class="form-control" placeholder="Ej: Av. Principal 123">
                </div>
                <div class="form-check form-switch mt-3" id="activeContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" name="active" id="locActive" checked>
                    <label class="form-check-label">Local Activo</label>
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
    var locationModal;
    document.addEventListener('DOMContentLoaded', function() {
        locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Local';
        document.getElementById('formAction').value = 'create';
        document.getElementById('locId').value = '';
        document.getElementById('locName').value = '';
        document.getElementById('locAddress').value = '';
        document.getElementById('activeContainer').style.display = 'none';
        locationModal.show();
    }

    function openEditModal(id, name, address, active) {
        document.getElementById('modalTitle').innerText = 'Editar Local';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('locId').value = id;
        document.getElementById('locName').value = name;
        document.getElementById('locAddress').value = address;
        document.getElementById('locActive').checked = (active == 1);
        document.getElementById('activeContainer').style.display = 'block';
        locationModal.show();
    }

    function toggleStatus(id, active) {
        const status = active ? 1 : 0;
        fetch('arcade_locations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_status&id=${id}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error al actualizar estado: ' + data.error);
                location.reload();
            } else {
                location.reload(); // To update the badge colors
            }
        });
    }
</script>

<?php require_once 'layout_foot.php'; ?>

<?php
// admin/branches.php
require_once 'layout_head.php';
require_once __DIR__ . '/../src/Uuid.php';

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle Create/Delete/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = $_POST['name'];
        $address = $_POST['address'];
        $id = Uuid::generate();
        
        try {
            $stmt = $db->prepare("INSERT INTO branches (id, name, address) VALUES (?, ?, ?)");
            $stmt->execute([$id, $name, $address]);
            $message = "Sucursal creada correctamente.";
        } catch (PDOException $e) {
            $error = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $address = $_POST['address'];
        
        try {
            $stmt = $db->prepare("UPDATE branches SET name = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $address, $id]);
            $message = "Sucursal actualizada.";
        } catch (PDOException $e) {
            $error = "Error al editar: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $db->prepare("UPDATE branches SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Sucursal eliminada (desactivada).";
        } catch (PDOException $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    }
}

// List Branches
$branches = $db->query("SELECT * FROM branches WHERE status = 1")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestión de Sucursales</h1>
    <button class="btn btn-primary" onclick="openCreateModal()">
        <i class="bi bi-plus-lg"></i> Nueva Sucursal
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID (UUID)</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($branches as $branch): ?>
            <tr>
                <td><small class="text-muted"><?= $branch['id'] ?></small></td>
                <td><?= htmlspecialchars($branch['name']) ?></td>
                <td><?= htmlspecialchars($branch['address']) ?></td>
                <td>
                    <a href="branch_view.php?id=<?= $branch['id'] ?>" class="btn btn-sm btn-info text-white me-1" title="Ver Panel">
                        <i class="bi bi-eye"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-primary me-1" 
                            onclick="openEditModal('<?= $branch['id'] ?>', '<?= htmlspecialchars($branch['name']) ?>', '<?= htmlspecialchars($branch['address']) ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que desea eliminar?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Creación/Edición -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Sucursal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="branchId">
                <div class="mb-3">
                    <label>Nombre Local</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Dirección</label>
                    <input type="text" name="address" id="address" class="form-control">
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
    var branchModal;
    document.addEventListener('DOMContentLoaded', function() {
        branchModal = new bootstrap.Modal(document.getElementById('branchModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nueva Sucursal';
        document.getElementById('formAction').value = 'create';
        document.getElementById('branchId').value = '';
        document.getElementById('name').value = '';
        document.getElementById('address').value = '';
        branchModal.show();
    }

    function openEditModal(id, name, address) {
        document.getElementById('modalTitle').innerText = 'Editar Sucursal';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('branchId').value = id;
        document.getElementById('name').value = name;
        document.getElementById('address').value = address;
        branchModal.show();
    }
</script>

<?php require_once 'layout_foot.php'; ?>

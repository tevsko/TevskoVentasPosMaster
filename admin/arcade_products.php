<?php
// admin/arcade_products.php
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
        $product_name = $_POST['product_name'];
        $price = $_POST['price'];
        $order = $_POST['display_order'] ?: 0;
        
        try {
            $stmt = $db->prepare("INSERT INTO arcade_products (location_id, product_name, price, active, display_order) VALUES (?, ?, ?, 1, ?)");
            $stmt->execute([$location_id, $product_name, $price, $order]);
            $message = "Producto creado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $location_id = $_POST['location_id'];
        $product_name = $_POST['product_name'];
        $price = $_POST['price'];
        $order = $_POST['display_order'];
        $active = isset($_POST['active']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE arcade_products SET location_id = ?, product_name = ?, price = ?, display_order = ?, active = ? WHERE id = ?");
            $stmt->execute([$location_id, $product_name, $price, $order, $active, $id]);
            $message = "Producto actualizado.";
        } catch (PDOException $e) {
            $error = "Error al editar: " . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        try {
            $stmt = $db->prepare("UPDATE arcade_products SET active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'apply_template') {
        $location_id = $_POST['location_id'];
        if (!$location_id) {
            $error = "Debe seleccionar un local.";
        } else {
            try {
                $template = [
                    ['Ficha $100', 100, 1],
                    ['Ficha $200', 200, 2],
                    ['Ficha $500', 500, 3]
                ];
                $stmt = $db->prepare("INSERT INTO arcade_products (location_id, product_name, price, active, display_order) VALUES (?, ?, ?, 1, ?)");
                foreach ($template as $t) {
                    $stmt->execute([$location_id, $t[0], $t[1], $t[2]]);
                }
                $message = "Plantilla aplicada exitosamente.";
            } catch (Exception $e) {
                $error = "Error al aplicar plantilla: " . $e->getMessage();
            }
        }
    }
}

// Filter logic
$filter_location = $_GET['location_id'] ?? '';
$sql = "SELECT p.*, l.location_name FROM arcade_products p 
        JOIN arcade_locations l ON p.location_id = l.id 
        WHERE l.tenant_id = ?";
$params = [$tenantId];

if ($filter_location) {
    $sql .= " AND p.location_id = ?";
    $params[] = $filter_location;
}

$sql .= " ORDER BY l.location_name ASC, p.display_order ASC, p.id ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-box me-2"></i>Gestión de Fichas (Productos)</h1>
    <div class="d-flex gap-2">
        <?php if ($filter_location): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Aplicar plantilla de 3 fichas ($100, $200, $500)?');">
            <input type="hidden" name="action" value="apply_template">
            <input type="hidden" name="location_id" value="<?= $filter_location ?>">
            <button type="submit" class="btn btn-outline-info btn-sm">
                <i class="bi bi-magic"></i> Aplicar Plantilla
            </button>
        </form>
        <?php endif; ?>
        <form class="d-flex gap-2 align-items-center">
            <select name="location_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Todos los Locales --</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filter_location == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" onclick="openCreateModal()">
            <i class="bi bi-plus-circle"></i> Nuevo Producto
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
                        <th>Producto</th>
                        <th>Local</th>
                        <th>Precio</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No se encontraron productos.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($products as $prod): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($prod['product_name']) ?></strong></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($prod['location_name']) ?></span></td>
                        <td class="fw-bold text-success">$<?= number_format($prod['price'], 2) ?></td>
                        <td><span class="badge bg-secondary"><?= $prod['display_order'] ?></span></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       <?= $prod['active'] ? 'checked' : '' ?> 
                                       onchange="toggleStatus(<?= $prod['id'] ?>, this.checked)">
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="openEditModal(<?= $prod['id'] ?>, <?= $prod['location_id'] ?>, '<?= htmlspecialchars($prod['product_name']) ?>', <?= $prod['price'] ?>, <?= $prod['display_order'] ?>, <?= $prod['active'] ?>)">
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
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="prodId">
                
                <div class="mb-3">
                    <label class="form-label">Local</label>
                    <select name="location_id" id="prodLocation" class="form-select" required>
                        <option value="">-- Seleccionar Local --</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nombre del Producto</label>
                    <input type="text" name="product_name" id="prodName" class="form-control" placeholder="Ej: Ficha $100" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Precio ($)</label>
                        <input type="number" step="0.01" name="price" id="prodPrice" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Orden de Visualización</label>
                        <input type="number" name="display_order" id="prodOrder" class="form-control" value="0">
                        <small class="text-muted">Menor número aparece primero.</small>
                    </div>
                </div>

                <div class="form-check form-switch mt-3" id="activeContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" name="active" id="prodActive" checked>
                    <label class="form-check-label">Producto Activo</label>
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
    var productModal;
    document.addEventListener('DOMContentLoaded', function() {
        productModal = new bootstrap.Modal(document.getElementById('productModal'));
    });

    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Producto';
        document.getElementById('formAction').value = 'create';
        document.getElementById('prodId').value = '';
        document.getElementById('prodLocation').value = '<?= $filter_location ?>';
        document.getElementById('prodName').value = '';
        document.getElementById('prodPrice').value = '';
        document.getElementById('prodOrder').value = '0';
        document.getElementById('activeContainer').style.display = 'none';
        productModal.show();
    }

    function openEditModal(id, locId, name, price, order, active) {
        document.getElementById('modalTitle').innerText = 'Editar Producto';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('prodId').value = id;
        document.getElementById('prodLocation').value = locId;
        document.getElementById('prodName').value = name;
        document.getElementById('prodPrice').value = price;
        document.getElementById('prodOrder').value = order;
        document.getElementById('prodActive').checked = (active == 1);
        document.getElementById('activeContainer').style.display = 'block';
        productModal.show();
    }

    function toggleStatus(id, active) {
        const status = active ? 1 : 0;
        fetch('arcade_products.php', {
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

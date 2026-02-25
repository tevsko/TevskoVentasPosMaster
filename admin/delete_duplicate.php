<?php
// admin/delete_duplicate.php
// Quick fix to delete the duplicate "space" branch

require_once __DIR__ . '/layout_head.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Find all "space" branches
$stmt = $db->prepare("SELECT * FROM branches WHERE name = 'space' ORDER BY id");
$stmt->execute();
$spaceBranches = $stmt->fetchAll();

// Count associated data for each
foreach ($spaceBranches as &$branch) {
    $id = $branch['id'];
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
    $stmt->execute(array($id));
    $branch['user_count'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE branch_id = ?");
    $stmt->execute(array($id));
    $branch['machine_count'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE branch_id = ?");
    $stmt->execute(array($id));
    $branch['sales_count'] = $stmt->fetchColumn();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];
    
    // Double check it's empty
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
    $stmt->execute(array($deleteId));
    $hasUsers = $stmt->fetchColumn() > 0;
    
    if ($hasUsers) {
        $error = "NO se puede eliminar: Esta sucursal tiene usuarios asignados.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute(array($deleteId));
            $message = "Sucursal eliminada exitosamente (ID: {$deleteId})";
            
            // Refresh data
            header("Location: delete_duplicate.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $message = "Sucursal duplicada eliminada exitosamente";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Eliminar Duplicado - SpacePark</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-trash"></i> Eliminar Sucursal Duplicada "space"</h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <strong>Instrucciones:</strong> Identifica la sucursal DUPLICADA (la que NO tiene usuarios) y elimínala.
                    </div>
                    
                    <h5>Sucursales "space" encontradas:</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tenant ID</th>
                                    <th>Licencia POS</th>
                                    <th>Usuarios</th>
                                    <th>Máquinas</th>
                                    <th>Ventas</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spaceBranches as $b): ?>
                                <?php 
                                $isEmpty = $b['user_count'] == 0 && $b['machine_count'] == 0 && $b['sales_count'] == 0;
                                ?>
                                <tr class="<?php echo $isEmpty ? 'table-danger' : 'table-success'; ?>">
                                    <td><code><?php echo $b['id']; ?></code></td>
                                    <td><?php echo $b['tenant_id'] ? $b['tenant_id'] : '-'; ?></td>
                                    <td>
                                        <strong><?php echo $b['license_pos_expiry'] ? $b['license_pos_expiry'] : 'Sin fecha'; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $b['user_count'] > 0 ? 'primary' : 'secondary'; ?>">
                                            <?php echo $b['user_count']; ?> usuarios
                                        </span>
                                    </td>
                                    <td><?php echo $b['machine_count']; ?></td>
                                    <td><?php echo $b['sales_count']; ?></td>
                                    <td>
                                        <?php if ($isEmpty): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿SEGURO que quieres eliminar esta sucursal? ID: <?php echo $b['id']; ?>');">
                                                <input type="hidden" name="delete_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash"></i> ELIMINAR
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success">✓ MANTENER (tiene datos)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <strong>⚠️ IMPORTANTE:</strong>
                        <ul class="mb-0">
                            <li>Solo elimina la fila ROJA (vacía, sin usuarios)</li>
                            <li>MANTÉN la fila VERDE (tiene 2 usuarios)</li>
                            <li>Si ambas tienen datos, NO uses esta herramienta</li>
                        </ul>
                    </div>
                    
                    <a href="licenses.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver a Licencias
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

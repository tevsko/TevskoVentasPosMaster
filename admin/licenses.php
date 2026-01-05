<?php
// admin/licenses.php
require_once 'layout_head.php';

// Only Admin access
$auth->requireRole('admin');
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_licenses') {
        $branch_id = $_POST['branch_id'];
        $base = $_POST['license_expiry'] ?: null;
        $pos = $_POST['license_pos_expiry'] ?: null;
        $mp = $_POST['license_mp_expiry'] ?: null;
        $cloud = $_POST['license_cloud_expiry'] ?: null;
        $limit = $_POST['pos_license_limit'] ?? 1;
        
        try {
            $stmt = $db->prepare("UPDATE branches SET license_expiry = ?, license_pos_expiry = ?, license_mp_expiry = ?, license_cloud_expiry = ?, pos_license_limit = ? WHERE id = ?");
            $stmt->execute([$base, $pos, $mp, $cloud, $limit, $branch_id]);
            $message = "Licencias actualizadas correctamente.";
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Get Branches with License Info
$branches = $db->query("SELECT id, name, status, license_expiry, license_pos_expiry, license_mp_expiry, license_cloud_expiry, pos_license_limit FROM branches ORDER BY name")->fetchAll();

function getDaysLeft($date) {
    if (!$date) return -999;
    $today = new DateTime();
    $target = new DateTime($date);
    $interval = $today->diff($target);
    return (int)$interval->format('%r%a');
}

function getStatusBadge($days) {
    if ($days == -999) return '<span class="badge bg-danger">Inactiva</span>';
    if ($days < 0) return '<span class="badge bg-danger">Vencida</span>';
    if ($days < 15) return '<span class="badge bg-warning text-dark">Próx. Vencer</span>';
    return '<span class="badge bg-success">Activa</span>';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-key me-2"></i>Gestión de Licencias</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Seleccione la opción <strong>Gestionar</strong> en una sucursal para administrar sus fechas de vencimiento y límites.
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sucursal</th>
                                <th class="text-center">Base (Login)</th>
                                <th class="text-center">POS</th>
                                <th class="text-center">Límite POS</th>
                                <th class="text-center">M. Pago</th>
                                <th class="text-center">Nube</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $b): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($b['name']) ?></strong><br>
                                    <small class="text-muted"><?= $b['id'] ?></small>
                                </td>
                                <td class="text-center"><?= getStatusBadge(getDaysLeft($b['license_expiry'])) ?><br><small><?= $b['license_expiry'] ?? '-' ?></small></td>
                                <td class="text-center"><?= getStatusBadge(getDaysLeft($b['license_pos_expiry'])) ?><br><small><?= $b['license_pos_expiry'] ?? '-' ?></small></td>
                                <td class="text-center fw-bold"><?= $b['pos_license_limit'] ?? 1 ?> Usuarios</td>
                                <td class="text-center"><?= getStatusBadge(getDaysLeft($b['license_mp_expiry'])) ?><br><small><?= $b['license_mp_expiry'] ?? '-' ?></small></td>
                                <td class="text-center"><?= getStatusBadge(getDaysLeft($b['license_cloud_expiry'])) ?><br><small><?= $b['license_cloud_expiry'] ?? '-' ?></small></td>
                                <td class="text-end">
                                    <a href="branch_view.php?id=<?= $b['id'] ?>&tab=licenses" class="btn btn-primary btn-sm">
                                        <i class="bi bi-gear-fill"></i> Gestionar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Removed Modal and Scripts as we now delegate to branch_view.php
require_once 'layout_foot.php'; 
?>

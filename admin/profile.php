<?php
// admin/profile.php - User profile page for all admin/branch_manager roles
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];
$message = '';
$messageType = '';

// Fetch full user data
$stmt = $db->prepare("SELECT id, username, role, emp_name, emp_email, branch_id, active, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $empName  = trim($_POST['emp_name'] ?? '');
        $empEmail = trim($_POST['emp_email'] ?? '');

        if (empty($empEmail) || !filter_var($empEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Por favor ingrese un correo electrónico válido.';
            $messageType = 'danger';
        } else {
            try {
                $db->prepare("UPDATE users SET emp_name = ?, emp_email = ? WHERE id = ?")
                   ->execute([$empName, $empEmail, $userId]);
                $_SESSION['username'] = $userData['username']; // refresh session
                $message = '✅ Perfil actualizado correctamente.';
                $messageType = 'success';
                // Re-fetch updated data
                $stmt->execute([$userId]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $message = 'Error al guardar: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch hash
        $stmtHash = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmtHash->execute([$userId]);
        $hash = $stmtHash->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $message = '❌ La contraseña actual es incorrecta.';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'La nueva contraseña debe tener al menos 6 caracteres.';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Las contraseñas nuevas no coinciden.';
            $messageType = 'danger';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);
            $auth->logActivity($userId, 'password_change', 'Cambio de contraseña desde perfil');
            $message = '✅ Contraseña actualizada correctamente.';
            $messageType = 'success';
        }
    }
}

$roleLabel = [
    'admin'          => 'Super Administrador',
    'branch_manager' => 'Administrador de Local',
    'employee'       => 'Empleado',
];
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-person-circle me-2"></i>Mi Perfil</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- Datos Personales -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Datos Personales</h5>
            </div>
            <div class="card-body">
                <!-- Read-only info -->
                <div class="mb-3">
                    <label class="form-label text-muted small">Usuario del Sistema</label>
                    <div class="form-control bg-light text-muted">
                        <i class="bi bi-person-badge me-2"></i><?= htmlspecialchars($userData['username']) ?>
                    </div>
                    <div class="form-text">El nombre de usuario no se puede cambiar.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Rol</label>
                    <div class="form-control bg-light text-muted">
                        <i class="bi bi-shield me-2"></i><?= $roleLabel[$userData['role']] ?? $userData['role'] ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Miembro desde</label>
                    <div class="form-control bg-light text-muted">
                        <i class="bi bi-calendar me-2"></i><?= $userData['created_at'] ?? 'N/D' ?>
                    </div>
                </div>

                <hr>

                <!-- Editable info -->
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre Completo</label>
                        <input type="text" name="emp_name" class="form-control" value="<?= htmlspecialchars($userData['emp_name'] ?? '') ?>" placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="emp_email" class="form-control" value="<?= htmlspecialchars($userData['emp_email'] ?? '') ?>" required placeholder="tu@correo.com">
                        <div class="form-text">Este correo se usa para recuperar tu contraseña.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cambiar Contraseña -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-4">Para cambiar tu contraseña necesitás confirmar la contraseña actual.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña Actual</label>
                        <div class="input-group">
                            <input type="password" name="current_password" id="curPwd" class="form-control" required placeholder="••••••••">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('curPwd', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="newPwd" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confPwd" class="form-control" required minlength="6" placeholder="Repetí la contraseña">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confPwd', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-lock me-2"></i>Actualizar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<?php require_once 'layout_foot.php'; ?>

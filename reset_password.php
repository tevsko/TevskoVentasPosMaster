<?php
// reset_password.php - Password reset form (accessed via email link)
require_once __DIR__ . '/bootstrap_error_handler.php';
require_once 'config/db.php';
require_once 'src/Database.php';
require_once 'src/Auth.php';

$auth = new Auth();
if ($auth->isAuthenticated()) {
    header('Location: admin/dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$validToken = null;

// Validate token
if ($token) {
    try {
        $stmt = $db->prepare("SELECT prt.*, u.username, u.emp_name FROM password_reset_tokens prt JOIN users u ON u.id = prt.user_id WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > ? LIMIT 1");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $validToken = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error interno. Intente de nuevo.';
    }
}

if (!$token || !$validToken) {
    $error = '❌ El link de recuperación no es válido o ha expirado. Solicita uno nuevo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $validToken['user_id']]);
        $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?")->execute([$token]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - SpacePark</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/favicon_astronaut.png">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .card-box { background: rgba(255,255,255,0.97); padding: 2rem; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 420px; }
        .form-control { border-radius: 25px; padding: 10px 20px; }
        .btn-main { border-radius: 25px; padding: 10px; background: #1e3c72; border: none; width: 100%; font-weight: bold; margin-top: 1rem; }
        .btn-main:hover { background: #2a5298; }
    </style>
</head>
<body>
<div class="card-box">
    <div class="text-center mb-4">
        <h2 class="fw-bold" style="color:#1e3c72">SpacePark</h2>
        <p class="text-muted">Nueva Contraseña</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success text-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>¡Contraseña actualizada!</strong><br>
            Ya podés ingresar con tu nueva contraseña.
        </div>
        <a href="login.php" class="btn btn-primary btn-main mt-2">
            <i class="bi bi-box-arrow-in-right me-2"></i>Ir al Login
        </a>
    <?php elseif ($error && !$validToken): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <a href="forgot_password.php" class="btn btn-primary btn-main">Solicitar nuevo link</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <p class="text-muted small text-center">Hola, <strong><?= htmlspecialchars($validToken['emp_name'] ?: $validToken['username']) ?></strong>. Ingresá tu nueva contraseña.</p>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label ms-2 text-muted small">Nueva Contraseña</label>
                <input type="password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres" required autofocus minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label ms-2 text-muted small">Confirmar Contraseña</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repetí la contraseña" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-main">
                <i class="bi bi-shield-lock me-2"></i>Guardar Nueva Contraseña
            </button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="login.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Volver al Login</a>
    </div>
</div>
</body>
</html>

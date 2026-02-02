<?php
// login.php
require_once 'src/Auth.php';

$auth = new Auth();

// Si ya está logueado, redirigir
if ($auth->isAuthenticated()) {
    if ($auth->isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: pos/index.php');
    }
    exit;
}

$error = '';
// Fetch Branches for Selector
require_once 'config/db.php';
$stmt = $pdo->query("SELECT id, name FROM branches WHERE status = 1 ORDER BY name ASC");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    require_once 'config/db.php'; // Access to $pdo needed to fetch branches? Or use Auth's connection?
    // Auth has DB connection but it's private. Best to query branches via a new connection or add getBranches to Auth.
    // Simpler: Just rely on Auth having a helper or quick query here.
    // Let's add a quick query here using the db config directly since Auth logic is separate.
    
    $branch_id = $_POST['branch_id'] ?? '';
    $loginResult = $auth->login($username, $password, $branch_id);

    if ($loginResult === true) {
        if ($auth->isAdmin()) {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: pos/index.php');
        }
        exit;
    } elseif ($loginResult === 'license_expired') {
        $error = 'La licencia de su sucursal ha expirado. Contacte al administrador.';
    } elseif ($loginResult === 'user_inactive') {
        $error = 'Su usuario ha sido desactivado. Contacte a su gerente.';
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SpacePark</title>
    <!-- Bootstrap CSS Local o CDN -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        .login-header h2 {
            font-weight: 700;
            color: #1e3c72;
        }
        .form-control {
            border-radius: 25px;
            padding: 10px 20px;
        }
        .btn-login {
            border-radius: 25px;
            padding: 10px;
            background: #1e3c72;
            border: none;
            width: 100%;
            font-weight: bold;
            margin-top: 1rem;
        }
        .btn-login:hover {
            background: #2a5298;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2>SpacePark</h2>
            <p class="text-muted">Sistema de Facturación</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center p-2 mb-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
             <div class="mb-3">
                <label class="form-label ms-2 text-muted small">Sucursal</label>
                <select name="branch_id" class="form-select" style="border-radius: 25px; padding: 10px 20px;">
                    <option value="">Administración / Global</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= htmlspecialchars($branch['id']) ?>">
                            <?= htmlspecialchars($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label ms-2 text-muted small">Usuario</label>
                <input type="text" name="username" class="form-control" placeholder="Ingrese su usuario" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label ms-2 text-muted small">Contraseña</label>
                <div class="position-relative">
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Ingrese su contraseña" required>
                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted p-0 me-2" onclick="togglePassword()" style="text-decoration: none;">
                        <i class="bi bi-eye" id="passwordToggle"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login">INGRESAR</button>
        </form>
        
        <div class="text-center mt-3">
             <small class="text-muted">¿Olvido su contraseña? Contacte al Admin</small>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('bi-eye');
                passwordToggle.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('bi-eye-slash');
                passwordToggle.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>

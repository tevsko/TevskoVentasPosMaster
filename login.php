<?php
// login.php
// CRITICAL: Load error handler FIRST
require_once __DIR__ . '/bootstrap_error_handler.php';

require_once 'config/db.php'; // IMPORTANTE: Cargar configuración antes que nada
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

$db = Database::getInstance()->getConnection();
$driver = Database::getInstance()->getDriver();
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$currentTenantId = null;

if ($driver !== 'sqlite') {
    // Asegurar que la sesión esté iniciada para leer tenant cache
    if (session_status() === PHP_SESSION_NONE) @session_start();
    
    // MySQL (Cloud) logic: identify tenant
    $tenantQuery = null;
    if (isset($_GET['tenant'])) {
        $tenantQuery = $_GET['tenant'];
    } elseif (isset($_SESSION['current_tenant_name'])) {
        $tenantQuery = $_SESSION['current_tenant_name'];
    }
    
    $hostParts = explode('.', $host);
    $hostPart = $hostParts[0];
    
    if ($tenantQuery) {
        $stmtTenant = $db->prepare("SELECT id, subdomain FROM tenants WHERE subdomain = ?");
        $stmtTenant->execute([$tenantQuery]);
        $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        if ($tenantData) {
            $currentTenantId = $tenantData['id'];
            $_SESSION['current_tenant_name'] = $tenantData['subdomain'];
        }
    } 
    
    if (!$currentTenantId && $hostPart !== 'www' && $hostPart !== 'tevsko') {
        $stmtTenant = $db->prepare("SELECT id, subdomain FROM tenants WHERE subdomain = ? OR ? LIKE CONCAT(subdomain, '.%')");
        $stmtTenant->execute([$hostPart, $host]);
        $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        if ($tenantData) {
            $currentTenantId = $tenantData['id'];
            $_SESSION['current_tenant_name'] = $tenantData['subdomain'];
        }
    }
} else {
    // SQLite (Local) logic: usually single tenant
    try {
        $currentTenantId = $db->query("SELECT id FROM tenants LIMIT 1")->fetchColumn();
    } catch (Exception $e) { }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    $loginResult = $auth->login($username, $password, $currentTenantId);

    if ($loginResult === true) {
        if ($auth->isAdmin()) {
            if (!headers_sent()) {
                header('Location: admin/dashboard.php');
            } else {
                echo "<script>window.location.href='admin/dashboard.php';</script>";
            }

        } else {
            if (!headers_sent()) {
                header('Location: pos/index.php');
            } else {
                echo "<script>window.location.href='pos/index.php';</script>";
            }

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
    <link rel="icon" type="image/png" href="assets/img/favicon_astronaut.png">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
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
             <?php 
             // Show password recovery link only on cloud (MySQL) deployments
             $isCloud = (isset($driver) && $driver !== 'sqlite') || file_exists(__DIR__ . '/config/db.php');
             ?>
             <?php if ($driver !== 'sqlite'): ?>
             <a href="forgot_password.php" class="text-decoration-none small text-primary">
                 <i class="bi bi-key me-1"></i>¿Olvidaste tu contraseña?
             </a>
             <?php else: ?>
             <small class="text-muted">¿Olvidaste tu contraseña? Contacta al Administrador</small>
             <?php endif; ?>
             <?php if (!file_exists('landing.php')): ?>
             <div class="mt-2">
                 <a href="setup_client.php" class="text-decoration-none small text-primary">
                    <i class="bi bi-gear"></i> Configurar Sincronización
                 </a>
             </div>
             <?php endif; ?>
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

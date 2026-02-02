<?php
// install/index.php
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? 'spacepark_db';
    
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? 'admin123';

    try {
        // Conectar a MySQL sin seleccionar BD para poder crearla
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear Base de Datos
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");

        // Crear Tablas
        
        // Tenants (SaaS - NUEVO)
        $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subdomain VARCHAR(50) NOT NULL UNIQUE,
            business_name VARCHAR(255) NOT NULL,
            db_name VARCHAR(100) DEFAULT NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Users (con UUID y Tenant ID)
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id CHAR(36) PRIMARY KEY,
            tenant_id INT NULL,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee', 'branch_manager') NOT NULL,
            emp_name VARCHAR(100) NULL,
            emp_email VARCHAR(100) NULL,
            branch_id CHAR(36) NULL,
            active TINYINT(1) DEFAULT 1,
            last_activity DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_user_branch (username, branch_id),
            INDEX idx_tenant_id (tenant_id)
        )");

        // User Logs (NUEVO)
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id CHAR(36) NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Branches (Locales)
        $pdo->exec("CREATE TABLE IF NOT EXISTS branches (
            id CHAR(36) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            address TEXT,
            phone VARCHAR(50) NULL,
            cuit VARCHAR(20) NULL,
            fiscal_data TEXT NULL,
            license_expiry DATE NULL,
            license_pos_expiry DATE NULL,
            license_mp_expiry DATE NULL,
            license_cloud_expiry DATE NULL,
            pos_license_limit INT DEFAULT 1,
            pos_title VARCHAR(100) DEFAULT 'SpacePark POS',
            cloud_host VARCHAR(100) NULL,
            cloud_db VARCHAR(50) NULL,
            cloud_user VARCHAR(50) NULL,
            cloud_pass VARCHAR(255) NULL,
            mp_token VARCHAR(255) NULL,
            mp_collector_id VARCHAR(50) NULL,
            mp_status TINYINT(1) DEFAULT 0,
            status TINYINT(1) DEFAULT 1
        )");

        // Machines (Maquinas/Productos)
        // ID es varchar manual (ej: 'M001')
        $pdo->exec("CREATE TABLE IF NOT EXISTS machines (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            branch_id CHAR(36) NULL,
            active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        )");

        // Sales (Ventas)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id CHAR(36) PRIMARY KEY,
            user_id CHAR(36) NOT NULL,
            branch_id CHAR(36) NULL,
            machine_id VARCHAR(50) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method ENUM('cash', 'qr') NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sync_status TINYINT(1) DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (machine_id) REFERENCES machines(id)
        )");

        // Licenses
        $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
            license_key VARCHAR(100) PRIMARY KEY,
            branch_id CHAR(36) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'inactive',
            device_id VARCHAR(100) NULL,
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        )");
        
        // Sync Logs
         $pdo->exec("CREATE TABLE IF NOT EXISTS sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            last_sync DATETIME,
            details TEXT,
            status ENUM('success', 'error')
        )");

        // Settings (Configuraciones)
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        )");

        // Crear Usuario Admin Default
        function gen_uuid() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                mt_rand( 0, 0xffff ),
                mt_rand( 0, 0x0fff ) | 0x4000,
                mt_rand( 0, 0x3fff ) | 0x8000,
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }

        // Verificar si existe admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$admin_user]);
        if ($stmt->fetchColumn() == 0) {
            $admin_uuid = gen_uuid();
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            // Default Admin is Global (tenant_id NULL)
            $stmt = $pdo->prepare("INSERT INTO users (id, tenant_id, username, password_hash, role) VALUES (?, NULL, ?, ?, 'admin')");
            $stmt->execute([$admin_uuid, $admin_user, $hash]);
        }

        // Guardar archivo de configuración
        $config_content = "<?php
define('DB_HOST', '$db_host');
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');

try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    \$pdo->exec(\"SET NAMES 'utf8mb4'\");

    // Initialize Tenant Manager
    require_once __DIR__ . '/../src/TenantManager.php';
    TenantManager::init(\$pdo);

} catch (PDOException \$e) {
    die(\"Error de conexión: \" . \$e->getMessage());
}
";
        file_put_contents(__DIR__ . '/../config/db.php', $config_content);

        $message = "Instalación completada con éxito. Usuario Admin creado.";
        // Eliminar instalador (en producción) o renombrar
        // rename(__FILE__, __FILE__ . '.bak');
        
    } catch (PDOException $e) {
        $error = "Error de Base de Datos: " . $e->getMessage();
    } catch (Exception $e) {
         $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador SpacePark</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .install-card { max-width: 500px; width: 100%; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card install-card p-4">
        <h3 class="text-center mb-4">🚀 Instalador SpacePark</h3>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
                <br>
                <a href="../login.php" class="btn btn-success mt-2 w-100">Ir al Login</a>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <h5 class="mb-3">Configuración de Base de Datos</h5>
            <div class="mb-3">
                <label>Host</label>
                <input type="text" name="db_host" class="form-control" value="localhost" required>
            </div>
            <div class="mb-3">
                <label>Usuario BD</label>
                <input type="text" name="db_user" class="form-control" value="root" required>
            </div>
            <div class="mb-3">
                <label>Contraseña BD</label>
                <input type="password" name="db_pass" class="form-control">
            </div>
            <div class="mb-3">
                <label>Nombre de la BD</label>
                <input type="text" name="db_name" class="form-control" value="spacepark_db" required>
            </div>

            <hr>
            <h5 class="mb-3">Cuenta de Administrador</h5>
            <div class="mb-3">
                <label>Usuario Admin</label>
                <input type="text" name="admin_user" class="form-control" value="admin" required>
            </div>
            <div class="mb-3">
                <label>Contraseña Admin</label>
                <input type="password" name="admin_pass" class="form-control" value="admin123" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Instalar Sistema</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>

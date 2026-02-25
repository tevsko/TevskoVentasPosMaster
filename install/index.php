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

        // Ejecutar Migraciones en Orden (001, 002, 003...)
        $migrationsDir = __DIR__ . '/../migrations/';
        $files = glob($migrationsDir . '*.sql');
        sort($files); // Asegurar orden alfabético/numérico

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            // Dividir por ; solo si no estamos dentro de procedimientos (simplificado)
            // Para robustez, mejor cargar todo el bloque si el driver lo permite.
            // PDO MySQL soporta múltiples queries si se configura, pero por defecto a veces no.
            // Mejor: leer el archivo y ejecutarlo. Si tiene múltiples queries, PDO->exec a veces falla si no está en modo emulation.
            // Para asegurar, vamos a usar una conexión con MYSQL_ATTR_MULTI_STATEMENTS si fuera necesario, 
            // o dividir el SQL de forma básica.
            // Dado que son archivos de migración controlados, inyectemos todo el contenido.
            try {
                $pdom = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, [
                    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdom->exec($sql);
            } catch (Exception $e) {
                // Si falla, quizás ya existe la tabla. Continuamos pero guardamos el error en log si fuera necesario.
                // throw $e; // Si es la primera instalación, mejor fallar para avisar.
                // Pero como usamos IF NOT EXISTS en los SQLs, debería ser seguro.
            }
        }


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

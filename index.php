<?php
// index.php
// CRITICAL: Suprimir TODOS los errores INMEDIATAMENTE
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_errors.log');
@error_reporting(0); // Suprimir TODO
ob_start(); // Iniciar buffering INMEDIATAMENTE


session_start();

// Check maintenance mode (before anything else)
require_once __DIR__ . '/src/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['maintenance_mode']);
    $maintenanceMode = $stmt->fetchColumn();
    
    if ($maintenanceMode === '1') {
        // Redirect to maintenance page
        header('Location: maintenance.html');
        exit;
    }
} catch (Exception $e) {
    // If database not ready, continue normally (e.g., during install)
}

// Definir ruta base
define('BASE_PATH', __DIR__);

// Verificar si existe el archivo de configuración
if (!file_exists(BASE_PATH . '/config/db.php')) {
    header('Location: install/index.php');
    exit;
}

// Verificar sesión
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: pos/index.php');
    }
} else {
    // CAMBIO: Redirigir a landing por defecto o a login si hay tenant context
    if (file_exists(BASE_PATH . '/landing.php')) {
        if (isset($_GET['tenant'])) {
            header('Location: login.php?tenant=' . urlencode($_GET['tenant']));
        } else {
            header('Location: landing.php');
        }
    } else {
        // MODO CLIENTE OFFLINE
        // Verificar si ya está configurado (tiene token)
        try {
            require_once 'config/db.php';
            require_once 'src/Database.php';
            $pdo = Database::getInstance()->getConnection();
            
            // Verificar si hay token configurado
            // OJO: La tabla settings puede estar vacía al inicio.
            // Database.php ya hizo auto-heal, así que la tabla existe.
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'sync_token'");
            $token = $stmt->fetchColumn();

            if (!$token) {
                // No hay token -> Ir a configuración inicial
                header('Location: setup_client.php');
                exit;
            }
        } catch (Exception $e) {
            // Si falla algo, mejor dejar que vaya al login o setup
        }

        header('Location: login.php');
    }
}
exit;

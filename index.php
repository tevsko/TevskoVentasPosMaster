<?php
// index.php
session_start();

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
    header('Location: login.php');
}
exit;

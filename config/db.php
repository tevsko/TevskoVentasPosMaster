<?php
/**
 * config/db.php - ARCHIVO UNIVERSAL (LOCAL + CLOUD)
 * Este archivo detecta automáticamente si está en tu Windows (Local) o en el Hosting (Linux)
 */
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. Detección de Sistema Operativo
$esLocal = (strpos(strtoupper(PHP_OS), 'WIN') !== false);

if ($esLocal) {
    // --- CONFIGURACIÓN PARA EL CLIENTE LOCAL (EXE) ---
    if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
    if (!defined('DB_SQLITE_FILE')) {
        define('DB_SQLITE_FILE', getenv('APPDATA') . '/SpacePark/data/data.sqlite');
    }
} else {
    // --- CONFIGURACIÓN PARA EL WEBHOSTING (LINUX) ---
    if (!defined('DB_DRIVER')) define('DB_DRIVER', 'mysql');
    
    // IMPORTANTE: Asegúrate de que estos datos coincidan con tu Hosting
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'spacepark_db');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', 'root');
}

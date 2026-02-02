<?php
// DB configuration supporting MySQL (default) and SQLite (local client)
// Set DB_DRIVER to 'sqlite' to use a local SQLite file (DB_SQLITE_FILE defines path)
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'mysql');

if (DB_DRIVER === 'sqlite') {
    if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/data.sqlite');
    // Ensure directory exists
    $dir = dirname(DB_SQLITE_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    try {
        $pdo = new PDO('sqlite:' . DB_SQLITE_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Recommended pragmas for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } catch (PDOException $e) {
        // In installer environment, do not die hard
        // echo "SQLite connection error: " . $e->getMessage();
    }
} else {
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
    if (!defined('DB_NAME')) define('DB_NAME', 'spacepark_db');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', 'root');

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET NAMES 'utf8mb4'");
    } catch (PDOException $e) {
        // echo "MySQL connection error: " . $e->getMessage();
    }
}

// Initialize Tenant Manager if available
if (isset($pdo) && file_exists(__DIR__ . '/../src/TenantManager.php')) {
    require_once __DIR__ . '/../src/TenantManager.php';
    TenantManager::init($pdo);
}


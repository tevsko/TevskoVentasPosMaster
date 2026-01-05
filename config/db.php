<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'spacepark_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la BD no existe, no matar el script inmediatamente, el instalador lo manejaría.
    // Pero en producción esto debería morir o redirigir al instalador si es un error específico.
    // Para simplificar:
    // die("Error de conexión: " . $e->getMessage());
}

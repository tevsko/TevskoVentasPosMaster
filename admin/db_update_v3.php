<?php
// admin/db_update_v3.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Iniciando actualización de base de datos (v3 - Configuración Descentralizada)...\n";

    // Agregar columnas a la tabla branches
    // pos_title
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN pos_title VARCHAR(100) DEFAULT 'SpacePark POS'");
        echo "Columna 'pos_title' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: pos_title ya existe o error: " . $e->getMessage() . "\n"; }

    // cloud_host
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN cloud_host VARCHAR(100) NULL");
        echo "Columna 'cloud_host' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: cloud_host ya existe.\n"; }

    // cloud_db
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN cloud_db VARCHAR(50) NULL");
        echo "Columna 'cloud_db' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: cloud_db ya existe.\n"; }

    // cloud_user
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN cloud_user VARCHAR(50) NULL");
        echo "Columna 'cloud_user' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: cloud_user ya existe.\n"; }

    // cloud_pass
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN cloud_pass VARCHAR(255) NULL");
        echo "Columna 'cloud_pass' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: cloud_pass ya existe.\n"; }

    // mp_token
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN mp_token VARCHAR(255) NULL");
        echo "Columna 'mp_token' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: mp_token ya existe.\n"; }

    // mp_status
    try {
        $pdo->exec("ALTER TABLE branches ADD COLUMN mp_status TINYINT(1) DEFAULT 0");
        echo "Columna 'mp_status' agregada.\n";
    } catch (PDOException $e) { echo "Omitido: mp_status ya existe.\n"; }

    echo "Actualización completada.\n";

} catch (PDOException $e) {
    die("Error fatal: " . $e->getMessage());
}

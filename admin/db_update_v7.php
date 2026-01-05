<?php
// admin/db_update_v7.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Iniciando Actualización de Base de Datos v7 (Control de Actividad de Usuarios)...\n";

    // 1. Add 'active' column to users
    // Default 1 (Active)
    $cols = [
        "ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER role"
    ];

    foreach ($cols as $sql) {
        try {
            $pdo->exec("ALTER TABLE users $sql");
            echo " [OK] $sql\n";
        } catch (PDOException $e) {
            // Ignore "Duplicate column name" error
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo " [SKIP] Columna ya existe ($sql)\n";
            } else {
                echo " [ERROR] " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Actualización v7 completada.\n";
    echo "Ahora los usuarios tienen una bandera de 'activo'.\n";

} catch (PDOException $e) {
    die("Error crítico de BD: " . $e->getMessage());
}
?>

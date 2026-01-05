<?php
// admin/db_update_v4.php
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Actualizando base de datos a v4...\n";
    
    // Add columns to branches
    // phone, cuit, fiscal_data, license_expiry, license_key
    $cols = [
        "ADD COLUMN phone VARCHAR(50) NULL AFTER address",
        "ADD COLUMN cuit VARCHAR(20) NULL AFTER phone",
        "ADD COLUMN fiscal_data TEXT NULL AFTER cuit",
        "ADD COLUMN license_expiry DATE NULL AFTER fiscal_data",
        "ADD COLUMN license_key VARCHAR(100) NULL AFTER license_expiry"
    ];

    foreach ($cols as $col_def) {
        try {
            $sql = "ALTER TABLE branches $col_def";
            $db->exec($sql);
            echo "Columna agregada: $col_def\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Columna ya existe, saltando.\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "Migración completada con éxito.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

<?php
// admin/db_update_v5.php
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Actualizando base de datos a v5 (Licenciamiento Modular)...\n";
    
    // Add columns to branches
    $cols = [
        "ADD COLUMN license_pos_expiry DATE NULL AFTER license_expiry",
        "ADD COLUMN license_mp_expiry DATE NULL AFTER license_pos_expiry",
        "ADD COLUMN license_cloud_expiry DATE NULL AFTER license_mp_expiry"
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
    
    echo "Migración v5 completada con éxito.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

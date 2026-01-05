<?php
// admin/db_update_v6.php
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Actualizando base de datos a v6 (Límite Multi-POS)...\n";
    
    // Add pos_license_limit to branches
    $col_def = "ADD COLUMN pos_license_limit INT NOT NULL DEFAULT 1 AFTER license_pos_expiry";

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
    
    echo "Migración v6 completada con éxito.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

<?php
// admin/db_update_v6.php
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Actualizando base de datos a v6 (Licencia MODO)...\n";
    
    // Add columns to branches (MySQL syntax)
    $sql = "ALTER TABLE branches ADD COLUMN license_modo_expiry DATE NULL AFTER license_mp_expiry";
    
    try {
        $db->exec($sql);
        echo "Columna agregada: license_modo_expiry\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "La columna ya existe, saltando.\n";
        } else {
            throw $e;
        }
    }
    
    echo "Migración v6 completada con éxito.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

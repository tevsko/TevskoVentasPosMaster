<?php
require_once 'config/db.php';

try {
    // 1. Opcion de Habilitar/Deshabilitar la oferta de integración MP (aunque tenga precio 0)
    $pdo->exec("ALTER TABLE plans ADD COLUMN allow_mp_integration TINYINT(1) DEFAULT 1 AFTER mp_fee");
    
    // 2. Limite de POS por defecto (1)
    $pdo->exec("ALTER TABLE plans ADD COLUMN pos_limit INT DEFAULT 1 AFTER allow_mp_integration");
    
    // 3. Costo por POS Extra
    $pdo->exec("ALTER TABLE plans ADD COLUMN pos_extra_fee DECIMAL(10,2) DEFAULT 0.00 AFTER pos_limit");

    echo "Columnas agregadas: allow_mp_integration, pos_limit, pos_extra_fee.<br>";
    echo "Migración completada.";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "Las columnas ya existen.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

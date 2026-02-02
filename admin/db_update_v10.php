<?php
// db_update_v10.php - Add mp_collector_id field for Mercado Pago QR
require_once '../config/db.php';

try {
    // Add mp_collector_id column if not exists
    $pdo->exec("ALTER TABLE branches ADD COLUMN mp_collector_id VARCHAR(50) NULL AFTER mp_token");
    echo "Columna 'mp_collector_id' agregada exitosamente.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columna 'mp_collector_id' ya existe.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

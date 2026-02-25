<?php
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE plans ADD COLUMN mp_fee DECIMAL(10,2) DEFAULT 0.00 AFTER price");
    echo "Columna mp_fee agregada con éxito.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "La columna ya existe.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

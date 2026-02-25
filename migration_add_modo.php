<?php
require_once 'config/db.php';

try {
    // 1. Columnas en PLANS para MODO
    $pdo->exec("ALTER TABLE plans ADD COLUMN allow_modo_integration TINYINT(1) DEFAULT 0 AFTER allow_mp_integration");
    $pdo->exec("ALTER TABLE plans ADD COLUMN modo_fee DECIMAL(10,2) DEFAULT 0.00 AFTER modo_fee"); // Error trap below if exists

    // 2. Columna en BRANCHES para saber qué módulos pagó (JSON: ['mp', 'modo', 'pos_extra'])
    $pdo->exec("ALTER TABLE branches ADD COLUMN modules JSON DEFAULT NULL AFTER pos_license_limit");

    echo "Migración MODO completada (Columnas en plans y branches).";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        // Fallback or ignore
        echo "Columnas ya existían o error parcial: " . $e->getMessage();
    } elseif (strpos($e->getMessage(), "Unknown column") !== false) {
         // Try correcting order if 'modo_fee' reference failed above
         try {
             $pdo->exec("ALTER TABLE plans ADD COLUMN modo_fee DECIMAL(10,2) DEFAULT 0.00 AFTER pos_extra_fee");
             echo "Columna modo_fee agregada (intento 2).";
         } catch(Exception $ex) { echo $ex->getMessage(); }
    } else {
        echo "Error: " . $e->getMessage();
    }
}

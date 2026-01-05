<?php
// admin/db_update_v9.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Iniciando Actualización de Base de Datos v9 (Indices de Reportes)...\n";

    // Objetivo: Optimizar reportes por sucursal y fecha
    // Agregar índice compuesto en tabla 'sales': (branch_id, created_at)
    
    // Primero verificar si existe
    $indexName = 'idx_sales_branch_date';
    $exists = false;
    
    try {
        $stmt = $pdo->query("SHOW INDEX FROM sales WHERE Key_name = '$indexName'");
        if ($stmt->fetch()) {
            $exists = true;
        }
    } catch (PDOException $e) {
        // Tabla sales no existe? Raro, pero posible
    }

    if (!$exists) {
        $pdo->exec("ALTER TABLE sales ADD INDEX $indexName (branch_id, created_at)");
        echo " [OK] Indice '$indexName' creado exitosamente.\n";
    } else {
        echo " [SKIP] El indice '$indexName' ya existe.\n";
    }
    
    echo "Actualización v9 completada.\n";

} catch (PDOException $e) {
    die("Error crítico de BD: " . $e->getMessage());
}
?>

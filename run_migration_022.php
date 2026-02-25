<?php
/**
 * Script para ejecutar la migración 022 - Módulo Móvil
 */

require_once __DIR__ . '/config/db.php';

try {
    echo "==========================================\n";
    echo "  Ejecutando Migración 022\n";
    echo "  Módulo Móvil - Control de Arcade\n";
    echo "==========================================\n\n";
    
    // Leer archivo SQL
    $sqlFile = __DIR__ . '/migrations/022_arcade_mobile.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo de migración no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Crear conexión mysqli para multi_query
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Error de conexión: " . $mysqli->connect_error);
    }
    
    echo "[*] Ejecutando migración SQL...\n\n";
    
    // Ejecutar todo el SQL de una vez
    if ($mysqli->multi_query($sql)) {
        do {
            // Procesar cada resultado
            if ($result = $mysqli->store_result()) {
                while ($row = $result->fetch_assoc()) {
                    // Mostrar resultados de SELECT (verificaciones)
                    if (isset($row['status'])) {
                        echo "  [✓] " . $row['status'] . "\n";
                    }
                }
                $result->free();
            }
            
            // Mostrar errores si los hay
            if ($mysqli->error) {
                // Ignorar errores de "ya existe"
                if (strpos($mysqli->error, 'already exists') === false &&
                    strpos($mysqli->error, 'Duplicate column') === false &&
                    strpos($mysqli->error, 'duplicate column name') === false) {
                    echo "  [!] Warning: " . $mysqli->error . "\n";
                }
            }
            
        } while ($mysqli->more_results() && $mysqli->next_result());
    } else {
        throw new Exception("Error ejecutando SQL: " . $mysqli->error);
    }
    
    echo "\n==========================================\n";
    echo "  Migración Completada\n";
    echo "==========================================\n\n";
    
    // Verificar tablas creadas
    echo "[*] Verificando tablas...\n";
    
    $tables = [
        'mobile_module_config',
        'arcade_locations',
        'arcade_products',
        'arcade_employees',
        'arcade_daily_reports'
    ];
    
    foreach ($tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $countResult = $mysqli->query("SELECT COUNT(*) as count FROM $table");
            $count = $countResult->fetch_assoc()['count'];
            echo "  [✓] $table ($count registros)\n";
        } else {
            echo "  [✗] $table NO EXISTE\n";
        }
    }
    
    $mysqli->close();
    
    echo "\n==========================================\n";
    echo "  ¡Listo!\n";
    echo "==========================================\n";
    
} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}


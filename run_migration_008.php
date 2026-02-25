<?php
/**
 * Script para ejecutar la migración 008_update_plans_pricing.sql
 * Este script actualiza los planes de suscripción con la nueva estructura de precios
 */

require_once __DIR__ . '/src/Database.php';

echo "=== Ejecutando Migración 008: Actualización de Planes ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/migrations/008_update_plans_pricing.sql';
    
    if (!file_exists($sqlFile)) {
        die("ERROR: No se encontró el archivo de migración: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Separar por sentencias (punto y coma)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filtrar comentarios y líneas vacías
            return !empty($stmt) && 
                   !preg_match('/^\s*--/', $stmt) &&
                   !preg_match('/^\s*SELECT/', $stmt); // Omitir SELECT de verificación
        }
    );
    
    echo "Ejecutando " . count($statements) . " sentencias SQL...\n\n";
    
    $db->beginTransaction();
    
    foreach ($statements as $index => $statement) {
        echo "Ejecutando sentencia " . ($index + 1) . "...\n";
        $db->exec($statement);
    }
    
    $db->commit();
    
    echo "\n✓ Migración completada exitosamente!\n\n";
    
    // Verificar planes creados
    echo "=== Planes Actualizados ===\n";
    $stmt = $db->query("
        SELECT id, code, name, price, period, pos_limit, pos_extra_fee, mp_fee, modo_fee 
        FROM plans 
        WHERE code IN ('basic_monthly', 'standard_quarterly', 'standard_annual')
        ORDER BY 
            CASE period 
                WHEN 'monthly' THEN 1 
                WHEN 'quarterly' THEN 2 
                WHEN 'annual' THEN 3 
            END
    ");
    
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($plans)) {
        echo "⚠ ADVERTENCIA: No se encontraron planes. Verifica la migración.\n";
    } else {
        foreach ($plans as $plan) {
            echo "\n";
            echo "ID: {$plan['id']}\n";
            echo "Código: {$plan['code']}\n";
            echo "Nombre: {$plan['name']}\n";
            echo "Precio: $" . number_format($plan['price'], 2) . "\n";
            echo "Período: {$plan['period']}\n";
            echo "POS Incluidos: {$plan['pos_limit']}\n";
            echo "POS Extra: $" . number_format($plan['pos_extra_fee'], 2) . "\n";
            echo "MP Fee: $" . number_format($plan['mp_fee'], 2) . ($plan['mp_fee'] == 0 ? ' (Incluido)' : '') . "\n";
            echo "MODO Fee: $" . number_format($plan['modo_fee'], 2) . "\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n¡Listo! Los planes han sido actualizados correctamente.\n";

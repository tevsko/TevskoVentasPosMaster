<?php
// run_license_migrations.php
// Ejecutar migraciones para sistema de renovación de licencias

require_once 'src/Database.php';

echo "===========================================\n";
echo "  Migraciones: Sistema de Renovación\n";
echo "===========================================\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Migración 009: module_prices
    echo "[1/2] Creando tabla module_prices...\n";
    $sql009 = file_get_contents(__DIR__ . '/migrations/009_module_prices.sql');
    $db->exec($sql009);
    echo "✓ Tabla module_prices creada con precios de renovación\n\n";
    
    // Migración 010: license_payments
    echo "[2/2] Creando tabla license_payments...\n";
    $sql010 = file_get_contents(__DIR__ . '/migrations/010_license_payments.sql');
    $db->exec($sql010);
    echo "✓ Tabla license_payments creada\n\n";
    
    echo "===========================================\n";
    echo "  ✓ Migraciones completadas exitosamente\n";
    echo "===========================================\n\n";
    
    echo "Precios de renovación configurados:\n";
    echo "- Acceso Base: $2,000/mes\n";
    echo "- Módulo POS: $3,000/mes\n";
    echo "- Mercado Pago: $1,500/mes\n";
    echo "- MODO: $1,500/mes\n";
    echo "- Nube: $2,500/mes\n\n";
    
    echo "Descuentos automáticos:\n";
    echo "- Trimestral: 10% OFF\n";
    echo "- Anual: 20% OFF\n\n";
    
    echo "Sistema de renovación listo para usar!\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

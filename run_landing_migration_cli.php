<?php
/**
 * Script CLI para ejecutar la migración del CMS de Landing Page
 * Ejecutar desde terminal: php run_landing_migration_cli.php
 */

require_once __DIR__ . '/src/Database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   MIGRACIÓN: Landing Page CMS - SpacePark                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Leer archivo SQL
    $sqlFile = __DIR__ . '/migrations/020_landing_cms.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("❌ Archivo de migración no encontrado: $sqlFile");
    }
    
    echo "📁 Leyendo archivo: migrations/020_landing_cms.sql\n";
    
    $sql = file_get_contents($sqlFile);
    
    // Dividir en statements individuales
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "📝 Encontrados " . count($statements) . " statements SQL\n";
    echo str_repeat("─", 60) . "\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    $tables = [];
    
    foreach ($statements as $statement) {
        // Limpiar comentarios de línea
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            
            // Detectar tipo de operación
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                $tables[] = $matches[1];
                echo "✅ Tabla creada: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Datos insertados en: {$matches[1]}\n";
            }
            
            $successCount++;
            
        } catch (PDOException $e) {
            // Ignorar errores de "tabla ya existe"
            if (strpos($e->getMessage(), 'already exists') !== false) {
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "ℹ️  Tabla ya existe: {$matches[1]} (omitido)\n";
                }
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n" . str_repeat("─", 60) . "\n";
    
    // Crear directorios de upload si no existen
    echo "\n📁 Verificando directorios de upload...\n";
    
    $uploadDirs = [
        'assets/uploads/carousel',
        'assets/uploads/testimonials',
        'assets/uploads/popup'
    ];
    
    foreach ($uploadDirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (!file_exists($fullPath)) {
            if (mkdir($fullPath, 0755, true)) {
                echo "✅ Directorio creado: $dir\n";
            } else {
                echo "❌ Error creando: $dir\n";
            }
        } else {
            echo "ℹ️  Directorio ya existe: $dir\n";
        }
    }
    
    echo "\n" . str_repeat("═", 60) . "\n";
    echo "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo str_repeat("═", 60) . "\n\n";
    
    echo "📊 Resumen:\n";
    echo "   • Statements ejecutados: $successCount\n";
    if ($errorCount > 0) {
        echo "   • Errores: $errorCount\n";
    }
    if (!empty($tables)) {
        echo "   • Tablas creadas: " . implode(', ', $tables) . "\n";
    }
    
    echo "\n🎯 Próximos pasos:\n";
    echo "   1. Abrir navegador: http://localhost/admin/landing_editor.php\n";
    echo "   2. Editar contenido de la landing page\n";
    echo "   3. Ver resultado: http://localhost/landing.php\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR FATAL:\n";
    echo "   " . $e->getMessage() . "\n\n";
    exit(1);
}

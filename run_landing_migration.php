<?php
/**
 * Script para ejecutar la migración del CMS de Landing Page
 * Ejecutar desde el navegador: http://localhost/run_landing_migration.php
 */

require_once __DIR__ . '/src/Database.php';

// Verificar que solo se ejecute en modo desarrollo o con confirmación
$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migración Landing CMS</title>
        <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">🚀 Migración: Landing Page CMS</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>⚠️ Atención:</strong> Esta migración creará las siguientes tablas:
                            </div>
                            
                            <ul class="list-group mb-4">
                                <li class="list-group-item">
                                    <strong>landing_carousel</strong> - Slides del carousel principal
                                </li>
                                <li class="list-group-item">
                                    <strong>landing_features</strong> - Características del producto
                                </li>
                                <li class="list-group-item">
                                    <strong>landing_testimonials</strong> - Testimonios de clientes
                                </li>
                                <li class="list-group-item">
                                    <strong>landing_settings</strong> - Configuración general
                                </li>
                                <li class="list-group-item">
                                    <strong>landing_visits</strong> - Contador de visitas
                                </li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <strong>ℹ️ Incluye:</strong>
                                <ul class="mb-0">
                                    <li>3 slides de ejemplo en el carousel</li>
                                    <li>6 características del producto</li>
                                    <li>3 testimonios de ejemplo</li>
                                    <li>Configuración inicial completa</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="?confirm=yes" class="btn btn-primary btn-lg">
                                    ✅ Ejecutar Migración
                                </a>
                                <a href="admin/dashboard.php" class="btn btn-secondary">
                                    ❌ Cancelar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Ejecutar migración
try {
    $db = Database::getInstance()->getConnection();
    
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Resultado de Migración</title>
        <link href='assets/vendor/bootstrap/css/bootstrap.min.css' rel='stylesheet'>
        <style>
            .log-success { color: #28a745; }
            .log-error { color: #dc3545; }
            .log-info { color: #17a2b8; }
        </style>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-10'>
                    <div class='card shadow'>
                        <div class='card-header bg-success text-white'>
                            <h4 class='mb-0'>📊 Ejecutando Migración...</h4>
                        </div>
                        <div class='card-body'>
                            <div class='font-monospace small'>";
    
    // Leer archivo SQL
    $sqlFile = __DIR__ . '/migrations/020_landing_cms.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo de migración no encontrado: $sqlFile");
    }
    
    echo "<p class='log-info'>📁 Leyendo archivo: migrations/020_landing_cms.sql</p>";
    
    $sql = file_get_contents($sqlFile);
    
    // Dividir en statements individuales
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "<p class='log-info'>📝 Encontrados " . count($statements) . " statements SQL</p>";
    echo "<hr>";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        // Limpiar comentarios de línea
        $statement = preg_replace('/--.*$/m', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            
            // Detectar tipo de operación
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<p class='log-success'>✅ Tabla creada: <strong>{$matches[1]}</strong></p>";
            } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<p class='log-success'>✅ Datos insertados en: <strong>{$matches[1]}</strong></p>";
            } else {
                echo "<p class='log-success'>✅ Statement ejecutado correctamente</p>";
            }
            
            $successCount++;
            
        } catch (PDOException $e) {
            // Ignorar errores de "tabla ya existe"
            if (strpos($e->getMessage(), 'already exists') !== false) {
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "<p class='log-info'>ℹ️ Tabla ya existe: <strong>{$matches[1]}</strong> (omitido)</p>";
                }
            } else {
                echo "<p class='log-error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                $errorCount++;
            }
        }
    }
    
    echo "<hr>";
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Migración Completada</h5>";
    echo "<p class='mb-0'>";
    echo "<strong>Exitosos:</strong> $successCount<br>";
    if ($errorCount > 0) {
        echo "<strong>Errores:</strong> $errorCount<br>";
    }
    echo "</p>";
    echo "</div>";
    
    // Crear directorios de upload si no existen
    echo "<hr>";
    echo "<p class='log-info'>📁 Creando directorios de upload...</p>";
    
    $uploadDirs = [
        'assets/uploads/carousel',
        'assets/uploads/testimonials',
        'assets/uploads/popup'
    ];
    
    foreach ($uploadDirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (!file_exists($fullPath)) {
            if (mkdir($fullPath, 0755, true)) {
                echo "<p class='log-success'>✅ Directorio creado: $dir</p>";
            } else {
                echo "<p class='log-error'>❌ Error creando: $dir</p>";
            }
        } else {
            echo "<p class='log-info'>ℹ️ Directorio ya existe: $dir</p>";
        }
    }
    
    echo "</div>
                        <div class='card-footer'>
                            <div class='d-grid gap-2'>
                                <a href='admin/landing_editor.php' class='btn btn-primary'>
                                    🎨 Ir al Editor de Landing
                                </a>
                                <a href='landing.php' class='btn btn-success' target='_blank'>
                                    👁️ Ver Landing Page
                                </a>
                                <a href='admin/dashboard.php' class='btn btn-secondary'>
                                    🏠 Volver al Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Error Fatal</h5>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "<a href='admin/dashboard.php' class='btn btn-secondary'>Volver</a>";
}

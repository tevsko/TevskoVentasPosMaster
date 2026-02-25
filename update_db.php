<?php
// update_db.php
// Ejecuta todas las migraciones pendientes en la base de datos
// Util para arreglar instalaciones incompletas
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Actualizador de Base de Datos</h1>";

$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) {
    die("<p style='color:red'>No se encuentra config/db.php. El sistema no está instalado.</p>");
}

require_once $configFile;

if (!isset($pdo)) {
    die("<p style='color:red'>Error al cargar conexión PDO.</p>");
}

echo "<p>Conectado a la base de datos.</p>";

$migrationsDir = __DIR__ . '/migrations/';
$files = glob($migrationsDir . '*.sql');
sort($files);

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1); // Permitir multi-queries si es necesario

echo "<ul>";
foreach ($files as $file) {
    $filename = basename($file);
    echo "<li>Procesando <strong>$filename</strong>... ";
    
    $sql = file_get_contents($file);
    if (empty(trim($sql))) {
        echo " (Vacío)</li>";
        continue;
    }

    try {
        // Ejecutar todo el archivo
        $pdo->exec($sql);
        echo "<span style='color:green'>OK</span>";
    } catch (PDOException $e) {
        // Ignorar error si es "Tabla ya existe" o "Columna ya existe".
        // MySQL 1050 = Table already exists
        // MySQL 1060 = Duplicate column name
        if ($e->getCode() == '42S01' || strpos($e->getMessage(), '1050') !== false || strpos($e->getMessage(), '1060') !== false) {
             echo "<span style='color:orange'>Ya aplicado (Saltado)</span>";
             // echo "<br><small>" . $e->getMessage() . "</small>";
        } else {
             echo "<span style='color:red'>Error: " . $e->getMessage() . "</span>";
        }
    }
    echo "</li>";
}
echo "</ul>";

echo "<h2>¡Proceso finalizado!</h2>";
echo "<p>Ahora la Landing Page debería funcionar.</p>";
echo "<a href='landing.php'>Ir a Landing Page</a>";

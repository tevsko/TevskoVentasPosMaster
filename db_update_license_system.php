<?php
/**
 * db_update_license_system.php
 * Actualización de Base de Datos - Sistema de Renovación de Licencias
 * 
 * INSTRUCCIONES:
 * 1. Subir este archivo a la raíz del servidor web
 * 2. Acceder desde el navegador: https://tudominio.com/db_update_license_system.php
 * 3. Eliminar este archivo después de ejecutarlo
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Actualización BD - Sistema de Licencias</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid green; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid red; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid blue; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Actualización de Base de Datos</h1>
    <h2>Sistema de Renovación de Licencias</h2>
";

try {
    require_once __DIR__ . '/src/Database.php';
    
    $db = Database::getInstance()->getConnection();
    
    echo "<div class='info'>✓ Conexión a base de datos establecida</div>";
    
    // ========================================
    // 1. Crear tabla module_prices
    // ========================================
    echo "<h3>1. Creando tabla module_prices...</h3>";
    
    $sql1 = "CREATE TABLE IF NOT EXISTS module_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_code VARCHAR(50) NOT NULL UNIQUE,
        module_name VARCHAR(100) NOT NULL,
        description TEXT,
        
        monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quarterly_price DECIMAL(10,2) DEFAULT NULL,
        annual_price DECIMAL(10,2) DEFAULT NULL,
        
        active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_module_code (module_code),
        INDEX idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql1);
    echo "<div class='success'>✓ Tabla module_prices creada</div>";
    
    // Insertar precios iniciales
    echo "<h3>2. Insertando precios de módulos...</h3>";
    
    $sql2 = "INSERT IGNORE INTO module_prices (module_code, module_name, description, monthly_price, quarterly_price, annual_price, display_order) VALUES
        ('base', 'Acceso Base (Login)', 'Acceso al sistema y funcionalidades básicas', 2000.00, 5400.00, 19200.00, 1),
        ('pos', 'Módulo POS (Ventas)', 'Sistema de punto de venta completo con reportes', 3000.00, 8100.00, 28800.00, 2),
        ('mercadopago', 'Módulo Mercado Pago', 'Integración de pagos con Mercado Pago', 1500.00, 4050.00, 14400.00, 3),
        ('modo', 'Módulo MODO', 'Integración de pagos con MODO', 1500.00, 4050.00, 14400.00, 4),
        ('nube', 'Módulo Nube', 'Sincronización en la nube y backups automáticos', 2500.00, 6750.00, 24000.00, 5)";
    
    $db->exec($sql2);
    echo "<div class='success'>✓ Precios de módulos insertados</div>";
    
    // ========================================
    // 3. Crear tabla license_payments
    // ========================================
    echo "<h3>3. Creando tabla license_payments...</h3>";
    
    $sql3 = "CREATE TABLE IF NOT EXISTS license_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        tenant_id INT NOT NULL,
        branch_id INT,
        user_id INT,
        
        module_code VARCHAR(50) NOT NULL,
        period_type ENUM('monthly', 'quarterly', 'annual') NOT NULL,
        months_duration INT NOT NULL DEFAULT 1,
        
        amount DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) DEFAULT 0.00,
        final_amount DECIMAL(10,2) NOT NULL,
        
        payment_method ENUM('mercadopago', 'transfer', 'cash', 'other') NOT NULL,
        payment_status ENUM('pending', 'approved', 'rejected', 'cancelled', 'refunded') DEFAULT 'pending',
        
        mp_preference_id VARCHAR(255),
        mp_payment_id VARCHAR(255),
        mp_status VARCHAR(50),
        mp_status_detail VARCHAR(100),
        
        paid_at DATETIME,
        license_start_date DATE,
        license_end_date DATE,
        
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_tenant (tenant_id),
        INDEX idx_branch (branch_id),
        INDEX idx_module (module_code),
        INDEX idx_status (payment_status),
        INDEX idx_mp_preference (mp_preference_id),
        INDEX idx_mp_payment (mp_payment_id),
        INDEX idx_paid_at (paid_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql3);
    echo "<div class='success'>✓ Tabla license_payments creada</div>";
    
    // ========================================
    // 4. Verificar tablas creadas
    // ========================================
    echo "<h3>4. Verificando tablas...</h3>";
    
    $stmt = $db->query("SHOW TABLES LIKE 'module_prices'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✓ module_prices existe</div>";
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM module_prices");
        $count = $stmt->fetch()['total'];
        echo "<div class='info'>→ {$count} módulos configurados</div>";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE 'license_payments'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✓ license_payments existe</div>";
    }
    
    // ========================================
    // 5. Mostrar precios configurados
    // ========================================
    echo "<h3>5. Precios Configurados:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #007bff; color: white;'>
            <th>Módulo</th>
            <th>Mensual</th>
            <th>Trimestral</th>
            <th>Anual</th>
            <th>Estado</th>
          </tr>";
    
    $stmt = $db->query("SELECT * FROM module_prices ORDER BY display_order");
    while ($row = $stmt->fetch()) {
        $active = $row['active'] ? '✓ Activo' : '✗ Inactivo';
        echo "<tr>
                <td><strong>{$row['module_name']}</strong></td>
                <td>\${$row['monthly_price']}</td>
                <td>\${$row['quarterly_price']}</td>
                <td>\${$row['annual_price']}</td>
                <td>{$active}</td>
              </tr>";
    }
    echo "</table>";
    
    // ========================================
    // Resumen final
    // ========================================
    echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>
        <h2>✅ Actualización Completada Exitosamente</h2>
        <p><strong>Sistema de Renovación de Licencias instalado correctamente</strong></p>
        <ul>
            <li>✓ Tabla module_prices creada con 5 módulos</li>
            <li>✓ Tabla license_payments creada</li>
            <li>✓ Precios configurados con descuentos automáticos</li>
        </ul>
    </div>";
    
    echo "<div class='info'>
        <h3>📋 Próximos Pasos:</h3>
        <ol>
            <li><strong>Eliminar este archivo</strong> por seguridad</li>
            <li>Configurar Mercado Pago en: <strong>Admin > Configuración > Facturación</strong></li>
            <li>Ajustar precios en: <strong>Admin > Precios Renovación</strong></li>
            <li>Probar renovación desde: <strong>Mi Licencia</strong></li>
        </ol>
    </div>";
    
    echo "<div class='error'>
        <h3>⚠️ IMPORTANTE:</h3>
        <p><strong>Elimine este archivo (db_update_license_system.php) inmediatamente después de ejecutarlo</strong></p>
        <p>No debe estar accesible públicamente por seguridad.</p>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error durante la actualización:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";

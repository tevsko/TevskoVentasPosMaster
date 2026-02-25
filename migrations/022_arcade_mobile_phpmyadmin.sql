-- =====================================================
-- Migración 022: Módulo Móvil - Control de Arcade
-- =====================================================
-- INSTRUCCIONES:
-- 1. Abrir phpMyAdmin
-- 2. Seleccionar la base de datos
-- 3. Ir a la pestaña "SQL"
-- 4. Copiar y pegar este contenido completo
-- 5. Hacer clic en "Continuar"
-- =====================================================

-- 1. Configuración del módulo por tenant
CREATE TABLE IF NOT EXISTS mobile_module_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    enabled TINYINT DEFAULT 0,
    max_locations INT DEFAULT 3,
    max_employees_per_location INT DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant (tenant_id),
    INDEX idx_tenant_enabled (tenant_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Locales de arcade (sub-sucursales)
CREATE TABLE IF NOT EXISTS arcade_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_tenant_active (tenant_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Productos (fichas) por local
CREATE TABLE IF NOT EXISTS arcade_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    active TINYINT DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES arcade_locations(id) ON DELETE CASCADE,
    INDEX idx_location (location_id),
    INDEX idx_location_active (location_id, active),
    INDEX idx_location_order (location_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Empleados móviles
CREATE TABLE IF NOT EXISTS arcade_employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    daily_salary DECIMAL(10,2) DEFAULT 0,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (location_id) REFERENCES arcade_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_username_location (location_id, username),
    INDEX idx_location (location_id),
    INDEX idx_location_active (location_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Reportes diarios
CREATE TABLE IF NOT EXISTS arcade_daily_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    employee_id INT NOT NULL,
    report_date DATE NOT NULL,
    
    -- Ventas por producto (JSON)
    products_sold JSON NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    
    -- Pagos recibidos
    cash_received DECIMAL(10,2) DEFAULT 0,
    mercadopago_received DECIMAL(10,2) DEFAULT 0,
    transfer_received DECIMAL(10,2) DEFAULT 0,
    total_payments DECIMAL(10,2) NOT NULL,
    
    -- Gastos (JSON)
    expenses JSON,
    total_expenses DECIMAL(10,2) DEFAULT 0,
    
    -- Empleado
    employee_paid TINYINT DEFAULT 0,
    employee_salary DECIMAL(10,2) DEFAULT 0,
    
    -- Cálculo final
    expected_cash DECIMAL(10,2) NOT NULL,
    
    -- Foto manuscrita
    photo_url VARCHAR(255),
    
    -- Metadata
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    device_info VARCHAR(255),
    is_offline_sync TINYINT DEFAULT 0,
    
    FOREIGN KEY (location_id) REFERENCES arcade_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES arcade_employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_location_date (location_id, report_date),
    INDEX idx_location_date (location_id, report_date),
    INDEX idx_employee (employee_id),
    INDEX idx_date (report_date),
    INDEX idx_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Agregar columna a tenant_plans
-- Nota: Si la columna ya existe, este comando fallará pero se puede ignorar
ALTER TABLE tenant_plans 
ADD COLUMN mobile_module_enabled TINYINT DEFAULT 0;

-- =====================================================
-- VERIFICACIÓN
-- =====================================================

SELECT 'Migración completada exitosamente' AS status;
SELECT COUNT(*) AS mobile_configs FROM mobile_module_config;
SELECT COUNT(*) AS locations FROM arcade_locations;
SELECT COUNT(*) AS products FROM arcade_products;
SELECT COUNT(*) AS employees FROM arcade_employees;
SELECT COUNT(*) AS reports FROM arcade_daily_reports;

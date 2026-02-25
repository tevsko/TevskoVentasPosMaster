-- Migration 009: Module Prices for License Renewal
-- Precios de renovación de módulos individuales (diferentes a planes iniciales)

CREATE TABLE IF NOT EXISTS module_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(50) NOT NULL UNIQUE,
    module_name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Precios de renovación (más bajos que plan inicial)
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quarterly_price DECIMAL(10,2) DEFAULT NULL,
    annual_price DECIMAL(10,2) DEFAULT NULL,
    
    -- Configuración
    active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_module_code (module_code),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar precios de renovación por módulo
INSERT INTO module_prices (module_code, module_name, description, monthly_price, quarterly_price, annual_price, display_order) VALUES
('base', 'Acceso Base (Login)', 'Acceso al sistema y funcionalidades básicas', 2000.00, 5400.00, 19200.00, 1),
('pos', 'Módulo POS (Ventas)', 'Sistema de punto de venta completo con reportes', 3000.00, 8100.00, 28800.00, 2),
('mercadopago', 'Módulo Mercado Pago', 'Integración de pagos con Mercado Pago', 1500.00, 4050.00, 14400.00, 3),
('modo', 'Módulo MODO', 'Integración de pagos con MODO', 1500.00, 4050.00, 14400.00, 4),
('nube', 'Módulo Nube', 'Sincronización en la nube y backups automáticos', 2500.00, 6750.00, 24000.00, 5);

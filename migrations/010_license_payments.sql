-- Migration 010: License Payments
-- Registro de pagos de renovación de licencias

CREATE TABLE IF NOT EXISTS license_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relaciones
    tenant_id INT NOT NULL,
    branch_id INT,
    user_id INT,
    
    -- Módulo y período
    module_code VARCHAR(50) NOT NULL,
    period_type ENUM('monthly', 'quarterly', 'annual') NOT NULL,
    months_duration INT NOT NULL DEFAULT 1,
    
    -- Montos
    amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL,
    
    -- Método de pago
    payment_method ENUM('mercadopago', 'transfer', 'cash', 'other') NOT NULL,
    payment_status ENUM('pending', 'approved', 'rejected', 'cancelled', 'refunded') DEFAULT 'pending',
    
    -- Mercado Pago
    mp_preference_id VARCHAR(255),
    mp_payment_id VARCHAR(255),
    mp_status VARCHAR(50),
    mp_status_detail VARCHAR(100),
    
    -- Fechas
    paid_at DATETIME,
    license_start_date DATE,
    license_end_date DATE,
    
    -- Metadata
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    
    INDEX idx_tenant (tenant_id),
    INDEX idx_branch (branch_id),
    INDEX idx_module (module_code),
    INDEX idx_status (payment_status),
    INDEX idx_mp_preference (mp_preference_id),
    INDEX idx_mp_payment (mp_payment_id),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

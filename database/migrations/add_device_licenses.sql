-- Migration: Add Device Licenses System
-- Date: 2026-02-04
-- Description: Add tables for device licensing control (Master/Slave POS)

-- Table: device_licenses
CREATE TABLE IF NOT EXISTS device_licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    device_id VARCHAR(64) NOT NULL UNIQUE,
    device_name VARCHAR(255),
    device_role ENUM('master', 'slave') DEFAULT 'master',
    license_type ENUM('included', 'paid') DEFAULT 'included',
    
    -- License dates
    activated_at DATETIME,
    expires_at DATETIME,
    last_payment_date DATETIME,
    
    -- Status
    status ENUM('active', 'expired', 'suspended', 'pending_payment') DEFAULT 'active',
    
    -- Technical info
    ip_address VARCHAR(45),
    last_seen_at DATETIME,
    
    -- Billing
    monthly_fee DECIMAL(10,2) DEFAULT 0.00,
    payment_period ENUM('monthly', 'annual') DEFAULT 'monthly',
    payment_status ENUM('paid', 'pending', 'overdue') DEFAULT 'paid',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: device_payments
CREATE TABLE IF NOT EXISTS device_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_license_id INT NOT NULL,
    tenant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    external_payment_id VARCHAR(255),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (device_license_id) REFERENCES device_licenses(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_external_payment (external_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modify plans table
ALTER TABLE plans 
ADD COLUMN IF NOT EXISTS pos_included INT DEFAULT 1 COMMENT 'POS incluidos (solo Master)',
ADD COLUMN IF NOT EXISTS pos_extra_monthly_fee DECIMAL(10,2) DEFAULT 500.00 COMMENT 'Costo mensual por POS adicional (Slave)',
ADD COLUMN IF NOT EXISTS pos_extra_annual_fee DECIMAL(10,2) DEFAULT 5000.00 COMMENT 'Costo anual por POS adicional (Slave)';

-- Update existing plans to have default values
UPDATE plans SET 
    pos_included = 1,
    pos_extra_monthly_fee = 500.00,
    pos_extra_annual_fee = 5000.00
WHERE pos_included IS NULL;

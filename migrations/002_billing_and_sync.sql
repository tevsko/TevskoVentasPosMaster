-- Migration: 002_billing_and_sync.sql
-- Adds billing tables (plans, subscriptions) and sync queue + enhancements to sync_logs

-- 1. Plans
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    period ENUM('monthly','quarterly','annual') NOT NULL,
    features JSON NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Subscriptions / Orders
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    plan_id INT NOT NULL,
    external_id VARCHAR(255) NULL,
    status ENUM('pending','active','cancelled','failed') DEFAULT 'pending',
    amount DECIMAL(10,2) NULL,
    period ENUM('monthly','quarterly','annual') NULL,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (plan_id),
    INDEX (tenant_id),
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Sync queue for robust sync operations
CREATE TABLE IF NOT EXISTS sync_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resource_type VARCHAR(50) NOT NULL,
  resource_uuid CHAR(36) NOT NULL,
  payload JSON NOT NULL,
  attempts INT DEFAULT 0,
  locked TINYINT(1) DEFAULT 0,
  locked_at DATETIME NULL,
  next_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_next_attempt (next_attempt),
  UNIQUE KEY uq_resource (resource_type, resource_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Enhance sync_logs
ALTER TABLE sync_logs 
  ADD COLUMN attempts INT DEFAULT 0,
  ADD COLUMN meta JSON NULL,
  ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

-- 5. Seed default plans (you can modify prices later in admin)
INSERT INTO plans (code, name, price, period, features, active) VALUES
('starter_monthly', 'Starter (Monthly)', 9.99, 'monthly', JSON_ARRAY('Soporte básico', '1 Sucursal'), 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), active = VALUES(active);

INSERT INTO plans (code, name, price, period, features, active) VALUES
('starter_quarterly', 'Starter (Quarterly)', 27.99, 'quarterly', JSON_ARRAY('Soporte básico', '1 Sucursal'), 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), active = VALUES(active);

INSERT INTO plans (code, name, price, period, features, active) VALUES
('starter_annual', 'Starter (Annual)', 99.99, 'annual', JSON_ARRAY('Soporte básico', '1 Sucursal'), 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), active = VALUES(active);

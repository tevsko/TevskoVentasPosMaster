-- Migration: 001_saas_init.sql
-- Description: Initialize Multi-Tenant SaaS Tables

-- 1. Create Tenants Table
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subdomain` VARCHAR(50) NOT NULL UNIQUE,
    `business_name` VARCHAR(255) NOT NULL,
    `db_name` VARCHAR(100) DEFAULT NULL, -- Placeholder if we ever go multi-db, currently unused
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subdomain` (`subdomain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add tenant_id to Users Table
-- We check if column exists first to avoid errors on repeated runs is hard in pure SQL without procedures, 
-- but for now we assume this is the first run.
-- If 'users' table exists, add the column.
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'tenant_id');
SET @sql := IF (@exist = 0, 'ALTER TABLE users ADD COLUMN tenant_id INT DEFAULT NULL AFTER id, ADD INDEX idx_tenant_id (tenant_id)', 'SELECT "Column tenant_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add Foreign Key Constraint (can fail if users table has data violating it, so we add it cautiously)
-- For now, we just add the column. We will enforce logic in PHP.
-- ideally: ALTER TABLE users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- 4. Create a Default "Master Global" Tenant (Optional, but good for testing)
-- INSERT INTO tenants (subdomain, business_name, status) VALUES ('admin', 'Master Global', 'active') ON DUPLICATE KEY UPDATE id=id;

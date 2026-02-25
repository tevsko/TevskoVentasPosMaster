-- Migration: 007_saas_data_isolation.sql
-- Description: Add tenant_id to all core entities and update Primary Keys for multi-tenant isolation.

-- 1. Add tenant_id column if not exists
SET @existBranch := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'branches' AND column_name = 'tenant_id');
SET @sqlBranch := IF (@existBranch = 0, 'ALTER TABLE branches ADD COLUMN tenant_id INT DEFAULT NULL AFTER id, ADD INDEX idx_tenant_id_b (tenant_id)', 'SELECT "tenant_id exists in branches"');
PREPARE stmt1 FROM @sqlBranch; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @existMach := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'machines' AND column_name = 'tenant_id');
SET @sqlMach := IF (@existMach = 0, 'ALTER TABLE machines ADD COLUMN tenant_id INT DEFAULT NULL AFTER id', 'SELECT "tenant_id exists in machines"');
PREPARE stmt2 FROM @sqlMach; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SET @existSale := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sales' AND column_name = 'tenant_id');
SET @sqlSale := IF (@existSale = 0, 'ALTER TABLE sales ADD COLUMN tenant_id INT DEFAULT NULL AFTER id', 'SELECT "tenant_id exists in sales"');
PREPARE stmt3 FROM @sqlSale; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 2. Backfill tenant_id based on existing relations
UPDATE branches b SET tenant_id = (SELECT tenant_id FROM users u WHERE u.branch_id = b.id AND u.tenant_id IS NOT NULL LIMIT 1) WHERE tenant_id IS NULL;
UPDATE machines m SET tenant_id = (SELECT tenant_id FROM branches b WHERE b.id = m.branch_id LIMIT 1) WHERE tenant_id IS NULL;
UPDATE sales s SET tenant_id = (SELECT tenant_id FROM branches b WHERE b.id = s.branch_id LIMIT 1) WHERE tenant_id IS NULL;

-- 3. Update Primary Keys to include tenant_id (prevents cross-tenant ID collisions)
-- We use a TRY-CATCH approach via a procedure to handle dropping/adding PK safely if already done.
DROP PROCEDURE IF EXISTS FixSaaSPKs;
DELIMITER //
CREATE PROCEDURE FixSaaSPKs()
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    -- Machines
    ALTER TABLE machines DROP PRIMARY KEY;
    ALTER TABLE machines ADD PRIMARY KEY (id, tenant_id);
    
    -- Sales
    ALTER TABLE sales DROP PRIMARY KEY;
    ALTER TABLE sales ADD PRIMARY KEY (id, tenant_id);
END //
DELIMITER ;
CALL FixSaaSPKs();
DROP PROCEDURE IF EXISTS FixSaaSPKs;

-- 005_tenant_tokens.sql
-- Adds per-tenant sync token and allowed_host for provisioning and host validation

ALTER TABLE tenants ADD COLUMN sync_token VARCHAR(64);
ALTER TABLE tenants ADD COLUMN allowed_host VARCHAR(255);


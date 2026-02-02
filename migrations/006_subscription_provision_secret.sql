-- 006_subscription_provision_secret.sql
-- Adds a one-time provision_secret to subscriptions to allow secure retrieval of tenant sync token by purchaser

ALTER TABLE subscriptions ADD COLUMN provision_secret VARCHAR(64);
ALTER TABLE subscriptions ADD COLUMN provisioned_at DATETIME NULL;
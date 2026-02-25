-- Migration: Add maintenance_mode setting

-- For SQLite:
-- INSERT INTO settings (setting_key, setting_value) 
-- VALUES ('maintenance_mode', '0')
-- ON CONFLICT(setting_key) DO NOTHING;

-- For MySQL:
INSERT IGNORE INTO settings (setting_key, setting_value) 
VALUES ('maintenance_mode', '0');

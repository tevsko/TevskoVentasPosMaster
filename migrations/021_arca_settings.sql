INSERT INTO landing_settings (setting_key, setting_value, setting_type) 
VALUES ('arca_enabled', '1', 'text')
ON DUPLICATE KEY UPDATE setting_value = '1';

INSERT INTO landing_settings (setting_key, setting_value, setting_type) 
VALUES ('arca_qr_url', 'http://qr.arca.gob.ar/?qr=bzxycYWFjNx2rzg0Skbz_g,,', 'text')
ON DUPLICATE KEY UPDATE setting_value = 'http://qr.arca.gob.ar/?qr=bzxycYWFjNx2rzg0Skbz_g,,';

INSERT INTO landing_settings (setting_key, setting_value, setting_type) 
VALUES ('arca_image_url', 'https://www.arca.gob.ar/images/f960/DATAWEB.jpg', 'text')
ON DUPLICATE KEY UPDATE setting_value = 'https://www.arca.gob.ar/images/f960/DATAWEB.jpg';

-- Verificar que se insertaron correctamente
SELECT * FROM landing_settings WHERE setting_key LIKE 'arca_%';

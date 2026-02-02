<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('site_url', ?)")->execute(['http://127.0.0.1:8000']);
$db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('sync_api_token', ?)")->execute(['']);
echo "Settings updated\n";
<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$k = $argv[1] ?? null; $v = $argv[2] ?? null;
if (!$k) { echo "Usage: php set_sqlite_setting.php key value\n"; exit(1); }
$stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
$stmt->execute([$k, $v]);
echo "Set $k = $v\n";
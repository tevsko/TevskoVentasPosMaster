<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Uuid.php';
$db = Database::getInstance()->getConnection();

// Create branch
$branch_id = Uuid::generate();
$db->prepare("INSERT OR IGNORE INTO branches (id, name, status) VALUES (?, ?, 1)")->execute([$branch_id, 'Test Branch']);

// Create user
$user_id = Uuid::generate();
$hash = password_hash('pass123', PASSWORD_DEFAULT);
$db->prepare("INSERT OR IGNORE INTO users (id, tenant_id, username, password_hash, role, created_at, branch_id) VALUES (?, NULL, ?, ?, 'admin', datetime('now'), ?)")
   ->execute([$user_id, 'localadmin', $hash, $branch_id]);

echo "Created test user: localadmin (branch: $branch_id)\n";
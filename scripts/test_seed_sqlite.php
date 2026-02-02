<?php
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/init_sqlite.php';
require_once __DIR__ . '/seed_plans_sqlite.php';
echo "Test sqlite seeded.\n";
<?php
// RUN THIS TO TEST SQLITE INIT WITHOUT CHANGING GLOBAL CONFIG
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
if (!defined('DB_SQLITE_FILE')) define('DB_SQLITE_FILE', __DIR__ . '/../data/test_data.sqlite');
require_once __DIR__ . '/init_sqlite.php';
echo "Test sqlite init completed.\n";
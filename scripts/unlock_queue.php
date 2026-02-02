<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$db->exec("UPDATE sync_queue SET locked = 0, locked_at = NULL WHERE locked = 1");
echo "Unlocked\n";
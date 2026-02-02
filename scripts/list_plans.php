<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$plans = $db->query('SELECT id, code, name FROM plans')->fetchAll(PDO::FETCH_ASSOC);
foreach ($plans as $p) echo $p['id'] . ' - ' . $p['code'] . ' - ' . $p['name'] . "\n";
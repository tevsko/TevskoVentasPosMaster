<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$sales = $db->query('SELECT id, amount, sync_status, last_synced_at FROM sales ORDER BY created_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($sales, JSON_PRETTY_PRINT);

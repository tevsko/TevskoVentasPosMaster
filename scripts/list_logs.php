<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$logs = $db->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($logs, JSON_PRETTY_PRINT);

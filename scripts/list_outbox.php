<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$rows = $db->query('SELECT * FROM outbox_emails ORDER BY created_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);

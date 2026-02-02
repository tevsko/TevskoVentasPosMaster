<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$q = $db->query('SELECT id, resource_uuid, attempts, locked FROM sync_queue ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($q, JSON_PRETTY_PRINT);

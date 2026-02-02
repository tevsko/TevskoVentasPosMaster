<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$plan_id = $argv[1] ?? 1;
$amount = $db->query("SELECT price FROM plans WHERE id = " . (int)$plan_id)->fetchColumn();
$stmt = $db->prepare("INSERT INTO subscriptions (plan_id, amount, period, status, created_at) VALUES (?, ?, (SELECT period FROM plans WHERE id = ?), 'pending', " . \Database::nowSql() . ")");
$stmt->execute([$plan_id, $amount, $plan_id]);
echo $db->lastInsertId();

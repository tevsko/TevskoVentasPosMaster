<?php
// api/get_user_logs.php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Auth.php';

$auth = new Auth();
if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("
        SELECT action, details, ip_address, created_at 
        FROM user_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

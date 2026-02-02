<?php
// api/import_token.php
// Accepts a token POST from the installer/admin to configure local sync token and optional allowed_host
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$token = trim($_POST['sync_token'] ?? '');
$allowed_host = trim($_POST['allowed_host'] ?? '');

if (!$token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'sync_token required']);
    exit;
}

// Basic validation
if (!preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid token format']);
    exit;
}

$stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('sync_api_token', ?)");
$stmt->execute([$token]);

if ($allowed_host !== '') {
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('allowed_host', ?)");
    $stmt->execute([$allowed_host]);
}

echo json_encode(['ok' => true, 'sync_token' => $token, 'allowed_host' => $allowed_host]);

<?php
// api/check_subdomain.php
require_once __DIR__ . '/../src/Database.php';

$subdomain = $_GET['subdomain'] ?? '';

// Validar formato (solo letras, numeros, guiones)
if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
    echo json_encode(['available' => false, 'reason' => 'invalid_format']);
    exit;
}

// Reservados
$reserved = ['www', 'admin', 'api', 'mail', 'webmail', 'cpanel', 'landing', 'login'];
if (in_array($subdomain, $reserved)) {
    echo json_encode(['available' => false, 'reason' => 'reserved']);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT COUNT(*) FROM tenants WHERE subdomain = ?");
$stmt->execute([$subdomain]);
$count = $stmt->fetchColumn();

echo json_encode(['available' => ($count == 0)]);

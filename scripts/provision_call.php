<?php
require_once __DIR__ . '/../api/provision.php';
try {
    $res = provision_tenant_for_subscription($argv[1] ?? 1);
    echo json_encode(['ok' => true, 'data' => $res], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

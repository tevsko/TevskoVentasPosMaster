<?php
// health.php - simple health check for sync worker
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'time' => date('c')]);
exit;
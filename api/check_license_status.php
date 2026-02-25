<?php
// api/check_license_status.php
// API para verificar estado de licencia desde cliente local

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../src/Database.php';

// Obtener parámetros
$licenseKey = $_GET['license_key'] ?? $_POST['license_key'] ?? null;

if (!$licenseKey) {
    echo json_encode([
        'success' => false,
        'error' => 'License key is required'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $driver = Database::getInstance()->getDriver();
    
    if ($driver === 'sqlite') {
        // En cliente local, no tiene sentido verificar licencia
        echo json_encode([
            'success' => false,
            'error' => 'This endpoint is only available on web server'
        ]);
        exit;
    }
    
    // Buscar tenant por license_key
    $stmt = $db->prepare("
        SELECT 
            id,
            business_name,
            license_key,
            plan_name,
            expiry_date,
            status,
            CASE 
                WHEN expiry_date >= CURDATE() THEN 'active'
                ELSE 'expired'
            END as license_status
        FROM tenants 
        WHERE license_key = ?
    ");
    
    $stmt->execute([$licenseKey]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo json_encode([
            'success' => false,
            'error' => 'License key not found'
        ]);
        exit;
    }
    
    // Calcular días restantes
    $expiryDate = new DateTime($tenant['expiry_date']);
    $today = new DateTime();
    $daysRemaining = $today->diff($expiryDate)->days;
    
    if ($expiryDate < $today) {
        $daysRemaining = -$daysRemaining;
    }
    
    echo json_encode([
        'success' => true,
        'license' => [
            'key' => $tenant['license_key'],
            'status' => $tenant['license_status'],
            'business_name' => $tenant['business_name'],
            'plan_name' => $tenant['plan_name'],
            'expiry_date' => $tenant['expiry_date'],
            'expiry_formatted' => date('d/m/Y', strtotime($tenant['expiry_date'])),
            'days_remaining' => $daysRemaining,
            'is_active' => ($tenant['license_status'] === 'active'),
            'renewal_url' => 'https://tevsko.com.ar/admin/dashboard.php'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

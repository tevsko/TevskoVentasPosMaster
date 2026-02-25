<?php
// scripts/sync_pull.php
// Cliente: Descargar productos y usuarios del servidor
require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();

// Get sync configuration
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$stmt->execute(['sync_token']);
$token = $stmt->fetchColumn();

$stmt->execute(['cloud_host']);
$cloudUrl = $stmt->fetchColumn();

if (!$token || !$cloudUrl) {
    echo json_encode(['ok' => false, 'error' => 'No sync configuration']);
    exit;
}

// Call server API
$ch = curl_init($cloudUrl . '/api/sync_pull.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['ok' => false, 'error' => 'Server returned ' . $httpCode, 'response' => $response]);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['ok']) || !$data['ok']) {
    echo json_encode(['ok' => false, 'error' => 'Invalid server response', 'data' => $data]);
    exit;
}

// Process machines
$machinesUpdated = 0;
$machinesInserted = 0;

try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("INSERT OR REPLACE INTO machines (id, name, price, branch_id, active) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($data['machines'] as $m) {
        // Check if exists
        $checkStmt = $db->prepare("SELECT id FROM machines WHERE id = ?");
        $checkStmt->execute([$m['id']]);
        $exists = $checkStmt->fetchColumn();
        
        $stmt->execute([
            $m['id'],
            $m['name'],
            $m['price'],
            $m['branch_id'],
            $m['active'] ?? 1
        ]);
        
        if ($exists) {
            $machinesUpdated++;
        } else {
            $machinesInserted++;
        }
    }
    
    // Process users (optional - solo si quiere sincronizar empleados también)
    $usersUpdated = 0;
    $usersInserted = 0;
    
    if (!empty($data['users'])) {
        $stmtUser = $db->prepare("INSERT OR REPLACE INTO users (id, username, emp_name, emp_email, role, branch_id, active, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($data['users'] as $u) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$u['id']]);
            $exists = $checkStmt->fetchColumn();
            
            // Si el usuario ya existe localmente, mantener su password
            if ($exists) {
                $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $pwStmt->execute([$u['id']]);
                $existingPw = $pwStmt->fetchColumn();
                $usersUpdated++;
            } else {
                // Nuevo usuario: generar password temporal
                $existingPw = password_hash('1234', PASSWORD_DEFAULT);
                $usersInserted++;
            }
            
            $stmtUser->execute([
                $u['id'],
                $u['username'],
                $u['emp_name'] ?? '',
                $u['emp_email'] ?? '',
                $u['role'],
                $u['branch_id'] ?? $data['branch_id'],
                $u['active'] ?? 1,
                $existingPw
            ]);
        }
    }

    // Process Sales (for local reporting)
    $salesSynced = 0;
    if (!empty($data['sales'])) {
        $stmtSale = $db->prepare("INSERT OR REPLACE INTO sales (id, tenant_id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        foreach ($data['sales'] as $s) {
            $stmtSale->execute([
                $s['id'], $s['tenant_id'], $s['user_id'], $s['branch_id'],
                $s['machine_id'], $s['amount'], $s['payment_method'], $s['created_at']
            ]);
            $salesSynced++;
        }
    }

    // Process Branch Config (Mercado Pago, Licenses, etc.)
    if (!empty($data['branch'])) {
        $b = $data['branch'];
        $stmtBranch = $db->prepare("UPDATE branches SET 
            name = ?, address = ?, phone = ?, cuit = ?, 
            license_expiry = ?, license_pos_expiry = ?, 
            license_mp_expiry = ?, license_modo_expiry = ?, license_cloud_expiry = ?,
            mp_token = ?, mp_collector_id = ?, mp_status = ?
            WHERE id = ?");
        $stmtBranch->execute([
            $b['name'], $b['address'] ?? null, $b['phone'] ?? null, $b['cuit'] ?? null,
            $b['license_expiry'] ?? null, $b['license_pos_expiry'] ?? null,
            $b['license_mp_expiry'] ?? null, $b['license_modo_expiry'] ?? null, $b['license_cloud_expiry'] ?? null,
            $b['mp_token'] ?? null, $b['mp_collector_id'] ?? null, $b['mp_status'] ?? 0,
            $b['id']
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'ok' => true,
        'machines' => [
            'inserted' => $machinesInserted,
            'updated' => $machinesUpdated
        ],
        'users' => [
            'inserted' => $usersInserted,
            'updated' => $usersUpdated
        ],
        'sales_synced' => $salesSynced
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>

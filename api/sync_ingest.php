<?php
// api/sync_ingest.php
// Example cloud endpoint to receive sync batches. Requires Authorization: Bearer <token> if configured.
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Billing.php';

$db = Database::getInstance()->getConnection();

// Parse Authorization: Bearer <token>
$auth = null;
$headers = (function_exists('getallheaders')) ? getallheaders() : [];

if (!empty($headers['Authorization'])) $auth = trim($headers['Authorization']);
elseif (!empty($headers['authorization'])) $auth = trim($headers['authorization']);
elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);

$token = null;
if ($auth && stripos($auth, 'Bearer ') === 0) {
    $token = trim(substr($auth, 7));
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: token required']);
    exit;
}

// Lookup tenant by token
$stmt = $db->prepare("SELECT id, subdomain, allowed_host FROM tenants WHERE sync_token = ? LIMIT 1");
$stmt->execute([$token]);
$tenant = $stmt->fetch();
if (!$tenant) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: invalid token']);
    exit;
}

// Optional host verification
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($headers['Host']) ? $headers['Host'] : (isset($headers['host']) ? $headers['host'] : null));
if (!empty($tenant['allowed_host']) && $tenant['allowed_host'] !== $host) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden: host mismatch']);
    exit;
}

$tenantId = $tenant['id'];

// --- INTELLIGENT FALLBACK MAPPING (Resolve FK and Branch mismatches) ---
// Get the primary branch for this tenant
$stmtB = $db->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
$stmtB->execute([$tenantId]);
$primaryBranchId = $stmtB->fetchColumn();

// Get the main manager/admin for this tenant (to resolve FK user_id errors)
$stmtU = $db->prepare("SELECT id FROM users WHERE tenant_id = ? AND (role = 'admin' OR role = 'branch_manager') LIMIT 1");
$stmtU->execute([$tenantId]);
$primaryUserId = $stmtU->fetchColumn();
// ----------------------------------------------------------------------

$input = file_get_contents('php://input');
$body = json_decode($input, true);
$entries = isset($body['entries']) ? $body['entries'] : array();
if (empty($entries)) {
    echo json_encode(['ok' => false, 'error' => 'No entries']);
    exit;
}

$inserted = 0; $duplicates = 0; $errors = array();
$insertedMachines = 0; $duplicatesMachines = 0;

try {
    $db->beginTransaction();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    // Prepare statements for sales
    if ($driver === 'sqlite') {
        $insertSaleSql = "INSERT OR IGNORE INTO sales (id, tenant_id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $insertMachineSql = "INSERT OR REPLACE INTO machines (id, tenant_id, name, price, branch_id, active) VALUES (?, ?, ?, ?, ?, ?)";
    } else {
        // En debug, quitamos IGNORE para capturar el error exacto en el catch
        $insertSaleSql = "INSERT INTO sales (id, tenant_id, user_id, branch_id, machine_id, amount, payment_method, created_at, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $insertMachineSql = "INSERT INTO machines (id, tenant_id, name, price, branch_id, active) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), active=VALUES(active), branch_id=VALUES(branch_id)";
        $insertUserSql = "INSERT INTO users (id, tenant_id, username, password_hash, role, emp_name, emp_email, branch_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), role=VALUES(role), emp_name=VALUES(emp_name), emp_email=VALUES(emp_email), branch_id=VALUES(branch_id), active=VALUES(active)";
    }
    
    $stmtSale = $db->prepare($insertSaleSql);
    $stmtMachine = $db->prepare($insertMachineSql);
    $stmtUser = isset($insertUserSql) ? $db->prepare($insertUserSql) : null;
    
    // Fallback for user stmt on sqlite
    if ($driver === 'sqlite') {
        $stmtUser = $db->prepare("INSERT OR REPLACE INTO users (id, tenant_id, username, password_hash, role, emp_name, emp_email, branch_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    }
    
    foreach ($entries as $e) {
        $type = isset($e['type']) ? $e['type'] : 'sale'; // Default to sale for backward compatibility
        $p = isset($e['payload']) ? $e['payload'] : null;
        
        if (!$p || empty($p['id'])) { 
            $errors[] = array('entry' => $e, 'error' => 'Missing payload or ID'); 
            continue; 
        }
        
        if ($type === 'sale') {
            $entryBranchId = isset($p['branch_id']) ? $p['branch_id'] : null;
            if ($primaryBranchId && $entryBranchId !== $primaryBranchId) {
                $entryBranchId = $primaryBranchId;
            }

            // --- USER_ID COMPATIBILITY (FK Resolve) ---
            $entryUserId = isset($p['user_id']) ? $p['user_id'] : null;
            // Check if user exists on server
            $stmtCheckU = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmtCheckU->execute(array($entryUserId));
            if ($stmtCheckU->fetchColumn() === false) {
                // User doesn't exist, fallback to tenant owner to satisfy FK
                $entryUserId = $primaryUserId;
            }
            // -------------------------------------------

            // --- FIX ENUM MISMATCH ---
            $method = isset($p['payment_method']) ? $p['payment_method'] : 'cash';
            if (!in_array($method, array('cash', 'qr'))) $method = 'cash'; 
            // -------------------------

            try {
                $stmtSale->execute(array(
                    $p['id'], 
                    $tenantId,
                    $entryUserId, // Use resolved user
                    $entryBranchId, 
                    $p['machine_id'] ?? null, 
                    $p['amount'] ?? 0, 
                    $method, 
                    $p['created_at'] ?? date('Y-m-d H:i:s')
                ]);
                if ($stmtSale->rowCount() > 0) {
                    $inserted++;
                } else {
                    $duplicates++;
                }
            } catch (Exception $e) {
                $duplicates++;
                $errors[] = ['type' => 'sale', 'id' => $p['id'], 'error' => $e->getMessage(), 'payload' => $p];
            }
            
        } elseif ($type === 'machine') {
            $entryBranchId = isset($p['branch_id']) ? $p['branch_id'] : null;
            if ($primaryBranchId && $entryBranchId !== $primaryBranchId) {
                $entryBranchId = $primaryBranchId;
            }

            try {
                $stmtMachine->execute(array(
                    $p['id'],
                    $tenantId,
                    isset($p['name']) ? $p['name'] : 'Producto',
                    isset($p['price']) ? $p['price'] : 0,
                    $entryBranchId,
                    isset($p['active']) ? $p['active'] : 1
                ));
                if ($stmtMachine->rowCount() > 0) {
                    $insertedMachines++;
                } else {
                    $duplicatesMachines++;
                }
            } catch (Exception $e) {
                $duplicatesMachines++;
                $errors[] = array('type' => 'machine', 'id' => $p['id'], 'error' => $e->getMessage());
            }
        } elseif ($type === 'user') {
            try {
                $stmtUser->execute(array(
                    $p['id'],
                    $tenantId,
                    isset($p['username']) ? $p['username'] : 'user_'.time(),
                    isset($p['password_hash']) ? $p['password_hash'] : 'NO_PASS_SYNCED',
                    isset($p['role']) ? $p['role'] : 'employee',
                    isset($p['emp_name']) ? $p['emp_name'] : null,
                    isset($p['emp_email']) ? $p['emp_email'] : null,
                    isset($p['branch_id']) ? $p['branch_id'] : $primaryBranchId,
                    isset($p['active']) ? $p['active'] : 1
                ));
                $inserted++; // Reusing counters for simplicity or add more if needed
            } catch (Exception $e) {
                $errors[] = array('type' => 'user', 'id' => $p['id'], 'error' => $e->getMessage());
            }
        }
    }
    $db->commit();
    // Audit log
    try {
        $details = "Ingest from tenant " . $tenantId . ": sales(inserted=" . $inserted . ", duplicates=" . $duplicates . "), machines(inserted=" . $insertedMachines . ", duplicates=" . $duplicatesMachines . ")";
        $meta = json_encode(array('tenant_id' => $tenantId, 'errors' => $errors, 'payload_sample' => count($entries) > 0 ? $entries[0] : null));
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'success', ?)")->execute(array($details, $meta));
    } catch (Exception $ex) {
        // Non-fatal; don't break response
    }
    echo json_encode(array(
        'ok' => true, 
        'sales' => array('inserted' => $inserted, 'duplicates' => $duplicates),
        'machines' => array('inserted' => $insertedMachines, 'duplicates' => $duplicatesMachines),
        'errors' => $errors
    ));
} catch (Exception $e) {
    $db->rollBack();
    try {
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")->execute(array("Ingest error: " . $e->getMessage(), json_encode(array('tenant_id' => $tenantId))));
    } catch (Exception $ex) {}
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
?>
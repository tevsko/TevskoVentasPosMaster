<?php
// api/sync_debug.php
// Script de diagnóstico para verificar por qué no se ven los datos sincronizados.

header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../config/db.php';

// Ensure $pdo is available for queries
$pdo = Database::getInstance()->getConnection();

$debug = array();

try {
    // 1. Verificar Conexión y Driver
    $driver = \Database::getInstance()->getDriver();
    $debug['driver'] = $driver;

    // 2. Verificar Columnas en Tablas Clave
    $tables = ['tenants', 'users', 'branches', 'machines', 'sales'];
    foreach ($tables as $t) {
        if ($driver === 'mysql') {
            // Column info
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$t` LIKE 'tenant_id'");
            $stmt->execute();
            $debug['columns'][$t]['has_tenant_id'] = (bool)$stmt->fetch();
            
            // Full description
            $debug['columns'][$t]['schema'] = $pdo->query("DESCRIBE `$t`")->fetchAll();
        } else {
             $cols = $pdo->query("PRAGMA table_info(`$t`)")->fetchAll();
             $debug['columns'][$t]['has_tenant_id'] = false;
             $debug['columns'][$t]['schema'] = $cols;
             foreach($cols as $c) { if($c['name'] === 'tenant_id') $debug['columns'][$t]['has_tenant_id'] = true; }
        }
    }

    // 3. Contar registros totales y huérfanos (sin tenant_id)
    if ($debug['columns']['sales']['has_tenant_id']) {
        $debug['counts']['sales_total'] = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
        $debug['counts']['sales_orphans'] = $pdo->query("SELECT COUNT(*) FROM sales WHERE tenant_id IS NULL")->fetchColumn();
        $debug['counts']['sales_by_tenant'] = $pdo->query("SELECT tenant_id, COUNT(*) as qty FROM sales GROUP BY tenant_id")->fetchAll();
    }
    
    if ($debug['columns']['machines']['has_tenant_id']) {
        $debug['counts']['machines_total'] = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();
        $debug['counts']['machines_orphans'] = $pdo->query("SELECT COUNT(*) FROM machines WHERE tenant_id IS NULL")->fetchColumn();
        $debug['counts']['machines_by_tenant'] = $pdo->query("SELECT tenant_id, COUNT(*) as qty FROM machines GROUP BY tenant_id")->fetchAll();
    }

    // 4. Verificar Token (si se provee por GET)
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        $stmt = $pdo->prepare("SELECT id, subdomain, business_name FROM tenants WHERE sync_token = ?");
        $stmt->execute(array($token));
        $tData = $stmt->fetch();
        $debug['token_check'] = $tData ? $tData : 'Token Inválido';
    }

    // 5. REPAIR LOGIC (Opcional: ?repair=1)
    if (isset($_GET['repair']) && $_GET['repair'] == 1 && isset($_GET['token'])) {
        $token = $_GET['token'];
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE sync_token = ?");
        $stmt->execute(array($token));
        $tId = $stmt->fetchColumn();
        
        if ($tId) {
            $stmtB = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
            $stmtB->execute(array($tId));
            $bId = $stmtB->fetchColumn();
            
            if ($bId) {
                // Reparar Ventas Huérfanas
                $upd1 = $pdo->prepare("UPDATE sales SET tenant_id = ?, branch_id = ? WHERE tenant_id IS NULL OR branch_id NOT IN (SELECT id FROM branches WHERE tenant_id = ?)");
                $upd1->execute(array($tId, $bId, $tId));
                $debug['repair']['sales_fixed'] = $upd1->rowCount();
                
                // Reparar Máquinas Huérfanas
                $upd2 = $pdo->prepare("UPDATE machines SET tenant_id = ?, branch_id = ? WHERE tenant_id IS NULL OR branch_id NOT IN (SELECT id FROM branches WHERE tenant_id = ?)");
                $upd2->execute(array($tId, $bId, $tId));
                $debug['repair']['machines_fixed'] = $upd2->rowCount();
                
                $debug['repair']['status'] = 'Completado';
            } else {
                $debug['repair']['error'] = 'No se encontró sucursal para este tenant.';
            }
        } else {
            $debug['repair']['error'] = 'Token inválido para reparación.';
        }
    }

    // 6. Verificar Usuario Actual (si el usuario está logueado en la misma sesión/navegador)
    if (session_status() === PHP_SESSION_NONE) session_start();
    $debug['session'] = array(
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A',
        'tenant_id' => isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'N/A',
        'role' => isset($_SESSION['role']) ? $_SESSION['role'] : 'N/A'
    );

    // 7. Mostrar últimos Logs de Sincronización
    $debug['recent_logs'] = $pdo->query("SELECT last_sync, details, status, meta FROM sync_logs ORDER BY last_sync DESC LIMIT 5")->fetchAll();

    echo json_encode($debug, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

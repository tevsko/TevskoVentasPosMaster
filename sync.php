<?php
// sync.php
require_once __DIR__ . '/src/Database.php';

// Aumentar tiempo ejecución
set_time_limit(300);

echo "--- Iniciando Proceso de Sincronización Multi-Sucursal ---\n";
$local_db = Database::getInstance()->getConnection();

// 1. Identificar sucursales con ventas pendientes
// (Ignoramos ventas sin branch_id por ahora, o podríamos asignarlas a un default si fuera necesario)
$stmt = $local_db->query("SELECT DISTINCT branch_id FROM sales WHERE sync_status = 0 AND branch_id IS NOT NULL");
$branchesParams = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($branchesParams)) {
    echo "No hay sucursales con ventas pendientes de sincronización.\n";
    exit;
}

echo "Sucursales a sincronizar: " . count($branchesParams) . "\n";

foreach ($branchesParams as $bid) {
    echo "\nProcesando Sucursal ID: $bid ...\n";

    // 2. Obtener Config Nube de la Sucursal
    $stmt = $local_db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$bid]);
    $branch = $stmt->fetch();

    if (!$branch) {
        echo " [X] Sucursal no encontrada en DB local. Saltando.\n";
        continue;
    }

    if (empty($branch['cloud_host']) || empty($branch['cloud_db'])) {
        echo " [!] Configuración de nube incompleta para sucursal '{$branch['name']}'. Saltando.\n";
        continue;
    }

    // --- CHECK LICENCIA NUBE ---
    $lic_cloud = $branch['license_cloud_expiry'];
    if (!$lic_cloud || $lic_cloud < date('Y-m-d')) {
        echo " [!] Licencia Módulo Nube vencida o inexistente. Saltando.\n";
        // Opcional: Loggear advertencia
        continue;
    }
    // ---------------------------

    try {
        // 3. Conexión Remota Específica
        $dsn = "mysql:host={$branch['cloud_host']};dbname={$branch['cloud_db']};charset=utf8mb4";
        $cloud_db = new PDO($dsn, $branch['cloud_user'], $branch['cloud_pass']);
        $cloud_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo " [OK] Conectado a Nube ({$branch['cloud_host']}).\n";

        // 4. Obtener ventas pendientes de ESTA sucursal
        $stmt_sales = $local_db->prepare("SELECT * FROM sales WHERE sync_status = 0 AND branch_id = ? LIMIT 50");
        $stmt_sales->execute([$bid]);
        $sales = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

        if (count($sales) > 0) {
            echo "     Sincronizando " . count($sales) . " ventas...\n";
            
            $cloud_db->beginTransaction();
            $ids_synced = [];

            // Asumimos misma estructura en nube
            $insert_sql = "INSERT IGNORE INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_cloud = $cloud_db->prepare($insert_sql);

            foreach ($sales as $s) {
                $stmt_cloud->execute([
                    $s['id'], $s['user_id'], $s['branch_id'], $s['machine_id'], 
                    $s['amount'], $s['payment_method'], $s['created_at']
                ]);
                $ids_synced[] = $s['id'];
            }

            $cloud_db->commit();

            // 5. Marcar como sincronizados en local
            if (!empty($ids_synced)) {
                $in_query = implode(',', array_fill(0, count($ids_synced), '?'));
                $update_local = $local_db->prepare("UPDATE sales SET sync_status = 1 WHERE id IN ($in_query)");
                $update_local->execute($ids_synced);
                
                // Log Success
                $logDetails = "Sucursal {$branch['name']}: Subidas " . count($sales) . " ventas";
                $local_db->prepare("INSERT INTO sync_logs (last_sync, details, status) VALUES (NOW(), ?, 'success')")->execute([$logDetails]);
                echo "     [OK] Lote completado.\n";
            }

        } else {
            echo "     No hay ventas pendientes en este lote.\n";
        }

    } catch (PDOException $e) {
        echo " [X] ERROR DE SINCRONIZACIÓN: " . $e->getMessage() . "\n";
        // Log Error
        $logDetails = "Error Sucursal {$branch['name']}: " . $e->getMessage();
        $stmt = $local_db->prepare("INSERT INTO sync_logs (last_sync, details, status) VALUES (NOW(), ?, 'error')");
        $stmt->execute([$logDetails]);
    }
}
echo "\n--- Fin del Proceso ---\n";
?>

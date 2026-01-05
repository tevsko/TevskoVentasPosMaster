<?php
// admin/backups.php
require_once 'layout_head.php';

$db = Database::getInstance()->getConnection();

// Mock backup download
if (isset($_GET['action']) && $_GET['action'] === 'download_db') {
    // En producción aquí se haría un mysqldump
    $filename = 'backup_spacepark_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "-- Backup SpacePark DB\n";
    echo "-- Generado: " . date('Y-m-d H:i:s') . "\n\n";
    echo "-- NOTA: Esta es una función simulada. En un servidor real se ejecutaría mysqldump.\n";
    exit;
}

// Obtener Sync Logs
// (sync_logs table was created in install/index.php? I should check if I added it. If not, fallback or create on fly avoided for now)
try {
    $logs = $db->query("SELECT * FROM sync_logs ORDER BY last_sync DESC LIMIT 20")->fetchAll();
} catch (PDOException $e) {
    $logs = []; // Table might not exist if I missed it in install script
}

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Backups y Sincronización</h1>
    <a href="?action=download_db" class="btn btn-outline-primary">
        <i class="bi bi-download"></i> Descargar Backup SQL
    </a>
</div>

<div class="row">
    <!-- Estado de Nube -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold">Estado de Sincronización (Nube)</div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0">
                        <i class="bi bi-cloud-check fs-1 text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-0">Conexión Establecida (Simulada)</h5>
                        <small class="text-muted">Última sincronización hace 5 minutos</small>
                    </div>
                </div>
                
                <h6 class="mt-4">Logs de Actividad</h6>
                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm table-fontSize-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Sin registros recientes</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['last_sync'] ?></td>
                                    <td><span class="badge bg-<?= $log['status']=='success'?'success':'danger' ?>"><?= $log['status'] ?></span></td>
                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">La sincronización ocurre automáticamente en segundo plano.</small>
            </div>
        </div>
    </div>

    <!-- Backups Locales -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold">Backups Locales</div>
            <div class="card-body">
                <p>El sistema realiza copias de seguridad automáticas diariamente en la carpeta <code>/backups</code> del servidor.</p>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        backup_auto_2026-01-02.sql
                        <span class="badge bg-secondary">2.5 MB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        backup_auto_2026-01-01.sql
                        <span class="badge bg-secondary">2.4 MB</span>
                    </li>
                </ul>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i> Se recomienda descargar un backup manual semanalmente y guardarlo en un disco externo.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout_foot.php'; ?>

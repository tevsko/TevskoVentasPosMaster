<?php
// admin/layout_head.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
// Permitir Admin y Gerente de Sucursal
$auth->requireRole(['admin', 'branch_manager']);
$currentUser = $auth->getCurrentUser();

// Tracking de actividad
$auth->updateLastActivity($currentUser['id']);

$isAdmin = ($currentUser['role'] === 'admin');
$isManager = ($currentUser['role'] === 'branch_manager');

// Database Setup
$db = Database::getInstance()->getConnection();
$driver = Database::getInstance()->getDriver();

$tenantId = $currentUser['tenant_id'] ?? null;
$businessName = 'SpacePark';

if ($driver === 'sqlite') {
    // SQLite Client: Get business name from settings or tenants
    try {
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name' LIMIT 1");
        $res = $stmt->fetch();
        if ($res && !empty($res['setting_value'])) {
            $businessName = $res['setting_value'];
        } else {
            // Fallback: try tenants table
            $stmt = $db->query("SELECT business_name FROM tenants LIMIT 1");
            $res = $stmt->fetch();
            if ($res && !empty($res['business_name'])) {
                $businessName = $res['business_name'];
            }
        }
    } catch (Exception $e) {
        // Fallback to 'SpacePark'
    }
} elseif ($tenantId) {
    // MySQL Server: Get business name from tenants
    try {
        $stmt = $db->prepare("SELECT business_name FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $res = $stmt->fetch();
        if ($res && !empty($res['business_name'])) {
            $businessName = $res['business_name'];
        }
    } catch (Exception $e) {
        // Fallback to 'SpacePark' if table doesn't exist or query fails
    }
}

// Auto-migration: Ensure maintenance_mode setting exists
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        // Use correct syntax based on database driver
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
        } else {
            // MySQL: Use INSERT IGNORE to avoid duplicate key error
            $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
        }
        $stmt->execute();
    }
} catch (Exception $e) {
    // Silent fail - table might not exist yet or other issues
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SpacePark</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/img/favicon_astronaut.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .sidebar { 
            min-height: 100vh; 
            background: #343a40; 
            color: #fff; 
            position: fixed;
            top: 0;
            left: 0;
            width: 180px; /* 30% más compacto: era 220px, ahora 180px */
            z-index: 1000;
            transform: translateX(0);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar.collapsed { 
            transform: translateX(-100%); 
        }
        .sidebar a { 
            color: #cfd2d6; 
            text-decoration: none; 
            padding: 8px 12px; /* Reducido de 10px 15px */
            display: block;
            line-height: 1.2;
            font-size: 0.85rem;
        }
        .sidebar a:hover, .sidebar a.active { background: #495057; color: #fff; }
        .sidebar .brand { 
            font-size: 1.2rem; /* Reducido de 1.5rem */
            font-weight: bold; 
            padding: 12px; /* Reducido de 20px */
            text-align: center; 
            border-bottom: 1px solid #4b545c; 
        }
        .sidebar a i {
            font-size: 0.9rem;
            margin-right: 6px;
            min-width: 16px;
            text-align: center;
        }
        
        .content { 
            padding: 20px;
            margin-left: 180px; /* Ajustado al nuevo ancho */
            transition: margin-left 0.3s ease;
        }
        
        .card-stat { border-radius: 10px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-stat .icon { font-size: 2.5rem; opacity: 0.2; }
        
        @media (min-width: 768px) {
            .sidebar { 
                width: 180px;
            }
            .content { margin-left: 180px; }
        }
        
        @media (max-width: 767px) {
            /* Mobile: Sidebar SUPER compacto siempre visible */
            .sidebar { 
                width: 120px !important;
                padding: 5px 0;
                font-size: 0.65rem;
            }
            .sidebar .brand { 
                font-size: 0.8rem !important; 
                padding: 6px 4px !important; 
            }
            .sidebar a { 
                padding: 5px 6px !important; 
                font-size: 0.62rem !important;
                line-height: 1.1;
            }
            .sidebar a i {
                font-size: 0.75rem;
                margin-right: 3px;
            }
            .content { 
                margin-left: 120px !important; 
                padding: 8px !important;
            }
                right: 10px;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="brand"><?= htmlspecialchars($businessName) ?> <span class="badge bg-primary small"><?= $isAdmin ? 'Admin' : 'Manager' ?></span></div>
        <div class="mt-3">
            
            <?php if ($isAdmin && !$tenantId): // GLOBAL SUPER ADMIN VIEW ?>
                <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2 me-2"></i> Inicio</a>
                <a href="branches.php" class="<?= basename($_SERVER['PHP_SELF'])=='branches.php'?'active':'' ?>"><i class="bi bi-shop me-2"></i> Sucursales</a>
                <a href="employees.php" class="<?= basename($_SERVER['PHP_SELF'])=='employees.php'?'active':'' ?>"><i class="bi bi-people me-2"></i> Empleados</a>
                <a href="machines.php" class="<?= basename($_SERVER['PHP_SELF'])=='machines.php'?'active':'' ?>"><i class="bi bi-joystick me-2"></i> Máquinas</a>
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="bi bi-bar-chart me-2"></i> Reportes</a>
                <a href="module_prices.php" class="<?= basename($_SERVER['PHP_SELF'])=='module_prices.php'?'active':'' ?>"><i class="bi bi-cash-coin me-2"></i> Precios Renovación</a>
                <a href="licenses.php" class="<?= basename($_SERVER['PHP_SELF'])=='licenses.php'?'active':'' ?>"><i class="bi bi-key me-2"></i> Licencias</a>
                <a href="plans_manage.php" class="<?= basename($_SERVER['PHP_SELF'])=='plans_manage.php'?'active':'' ?>"><i class="bi bi-credit-card-2-front me-2"></i> Planes SaaS</a>
                <a href="backups.php" class="<?= basename($_SERVER['PHP_SELF'])=='backups.php'?'active':'' ?>"><i class="bi bi-cloud-arrow-up me-2"></i> Backups & Sync</a>
                <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active':'' ?>"><i class="bi bi-gear me-2"></i> Configuración</a>
                <a href="landing_editor.php" class="<?= basename($_SERVER['PHP_SELF'])=='landing_editor.php'?'active':'' ?>"><i class="bi bi-globe me-2"></i> Editor Landing</a>
                <a href="landing_analytics.php" class="<?= basename($_SERVER['PHP_SELF'])=='landing_analytics.php'?'active':'' ?>"><i class="bi bi-graph-up me-2"></i> Analytics Landing</a>
                <a href="tenants.php" class="<?= basename($_SERVER['PHP_SELF'])=='tenants.php'?'active':'' ?>"><i class="bi bi-people-fill me-2"></i> Tenants</a>
            <?php endif; ?>

            <?php if ($tenantId || $driver === 'sqlite'): // CLIENT VIEW (Manager or Tenant Admin) OR SQLITE CLIENT ?>
                 <a href="branch_view.php" class="<?= basename($_SERVER['PHP_SELF'])=='branch_view.php'?'active':'' ?>"><i class="bi bi-shop-window me-2"></i> Mi Sucursal</a>
                 <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="bi bi-bar-chart me-2"></i> Reportes</a>
                 <a href="employees.php" class="<?= basename($_SERVER['PHP_SELF'])=='employees.php'?'active':'' ?>"><i class="bi bi-people me-2"></i> Empleados</a>
                 <a href="machines.php" class="<?= basename($_SERVER['PHP_SELF'])=='machines.php'?'active':'' ?>"><i class="bi bi-joystick me-2"></i> Máquinas</a>
                 <a href="license.php" class="<?= basename($_SERVER['PHP_SELF'])=='license.php'?'active':'' ?>"><i class="bi bi-award me-2"></i> Mi Licencia</a>
                 <a href="downloads.php" class="<?= basename($_SERVER['PHP_SELF'])=='downloads.php'?'active':'' ?>"><i class="bi bi-download me-2"></i> Descargas</a>

                 <?php 
                 // --- ARCADE MOBILE VISIBILITY CHECK (Super Defensive) ---
                 $mobileEnabled = false;
                 try {
                     $today = date('Y-m-d');
                     // 1. Check if column exists first to avoid 500
                     $hasMobileCol = false;
                     if ($driver === 'sqlite') {
                         $cols = $db->query("PRAGMA table_info(plans)")->fetchAll();
                         foreach ($cols as $c) { if ($c['name'] === 'mobile_module_enabled') { $hasMobileCol = true; break; } }
                     } else {
                         $checkCol = $db->query("SHOW COLUMNS FROM plans LIKE 'mobile_module_enabled'")->fetch();
                         if ($checkCol) $hasMobileCol = true;
                     }

                     if ($hasMobileCol) {
                         // Check Plan via Subscriptions
                         $stmtM = $db->prepare("SELECT COUNT(*) FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.tenant_id = ? AND s.status = 'active' AND p.mobile_module_enabled = 1");
                         $stmtM->execute([$tenantId]);
                         if ($stmtM->fetchColumn() > 0) {
                             $mobileEnabled = true;
                         }
                     }
                     
                     if (!$mobileEnabled) {
                         // Fallback 1: Check active licenses in any branch of this tenant
                         $stmtLic = $db->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND license_arcade_expiry >= ?");
                         $stmtLic->execute([$tenantId, $today]);
                         if ($stmtLic->fetchColumn() > 0) {
                             $mobileEnabled = true;
                         } else {
                             // Fallback 2: Check current branch specifically (if tenant_id mismatch or missing)
                             $currBranch = $_SESSION['branch_id'] ?? null;
                             if ($currBranch) {
                                 $stmtLicBr = $db->prepare("SELECT COUNT(*) FROM branches WHERE id = ? AND license_arcade_expiry >= ?");
                                 $stmtLicBr->execute([$currBranch, $today]);
                                 if ($stmtLicBr->fetchColumn() > 0) $mobileEnabled = true;
                             }
                         }
                     }
                 } catch (Exception $e) { 
                     $mobileEnabled = false; 
                 }

                 if ($mobileEnabled): ?>
                     <div class="mt-4 px-3 small text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Arcade Móvil</div>
                     <a href="arcade_config.php" class="<?= basename($_SERVER['PHP_SELF'])=='arcade_config.php'?'active':'' ?>"><i class="bi bi-gear me-2"></i> Configuración</a>
                     <a href="arcade_locations.php" class="<?= basename($_SERVER['PHP_SELF'])=='arcade_locations.php'?'active':'' ?>"><i class="bi bi-geo-alt me-2"></i> Locales & Prod.</a>
                     <a href="arcade_employees.php" class="<?= basename($_SERVER['PHP_SELF'])=='arcade_employees.php'?'active':'' ?>"><i class="bi bi-person-badge me-2"></i> Empleados PWA</a>
                     <a href="arcade_reports.php" class="<?= basename($_SERVER['PHP_SELF'])=='arcade_reports.php'?'active':'' ?>"><i class="bi bi-file-earmark-text me-2"></i> Reportes Diarios</a>
                 <?php endif; ?>
            <?php endif; ?>

            <hr style="border-color:#4b545c;margin:8px 12px">
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF'])=='profile.php'?'active':'' ?>"><i class="bi bi-person-circle me-2"></i> Mi Perfil</a>
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Salir</a>

            <?php if ($driver === 'sqlite'): ?>
            <div class="mt-4 px-3 small text-muted border-top pt-3">
                <div class="d-flex align-items-center gap-2">
                    <span id="syncIndicator" class="spinner-grow spinner-grow-sm text-secondary" role="status" style="display:none"></span>
                    <span id="syncStatusText">
                        <i class="bi bi-cloud-check text-success"></i> Sincronizado
                    </span>
                </div>
                <div id="syncTime" class="x-small mt-1">Último: <?= date('H:i') ?></div>
                <div id="licenseStatus" class="x-small mt-1">
                    <i class="bi bi-award text-warning"></i> <span id="licenseStatusText">Verificando...</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    <main class="content">
        <nav class="navbar navbar-light bg-white border-bottom shadow-sm mb-4 px-3 rounded">
            <div>
                <span class="navbar-brand mb-0 h1">Panel de Control</span>
                <?php if ($isManager): ?>
                    <span class="badge bg-warning text-dark ms-2">Vista de Gerente</span>
                <?php endif; ?>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5 me-2"></i>
                    <span><?= htmlspecialchars($currentUser['username']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </nav>
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>

        // --- BACKGROUND AUTO-SYNC (Solo en Local) ---
        <?php 
        if ($driver === 'sqlite'): 
        ?>
        let syncCycles = 0;
        
        // Verificar estado de licencia
        function checkLicenseStatus() {
            fetch('../api/settings.php?action=get&key=sync_token')
                .then(r => r.json())
                .then(data => {
                    if (data.value) {
                        const syncServer = '<?= $settings['sync_server'] ?? 'https://tevsko.com.ar' ?>';
                        return fetch(syncServer + '/api/check_license_status.php?license_key=' + encodeURIComponent(data.value));
                    }
                })
                .then(r => r ? r.json() : null)
                .then(data => {
                    const licenseStatusText = document.getElementById('licenseStatusText');
                    if (data && data.success && licenseStatusText) {
                        const license = data.license;
                        if (license.is_active) {
                            licenseStatusText.innerHTML = '<i class="bi bi-award text-success"></i> Licencia Activa (' + license.days_remaining + ' días)';
                        } else {
                            licenseStatusText.innerHTML = '<i class="bi bi-exclamation-triangle text-danger"></i> Licencia Vencida';
                        }
                    }
                })
                .catch(e => {
                    const licenseStatusText = document.getElementById('licenseStatusText');
                    if (licenseStatusText) {
                        licenseStatusText.innerHTML = '<i class="bi bi-cloud-slash text-muted"></i> Sin conexión';
                    }
                });
        }
        
        function runAutoSync() {
            const indicator = document.getElementById('syncIndicator');
            const statusText = document.getElementById('syncStatusText');
            const timeText = document.getElementById('syncTime');
            
            if (indicator) indicator.style.display = 'inline-block';
            if (statusText) statusText.innerHTML = '<i class="bi bi-cloud-arrow-up text-primary"></i> Sincronizando...';

            syncCycles++;

            // Subir cambios (Cada ciclo - 5 min)
            fetch('../scripts/sync_upload.php')
                .then(r => r.json())
                .then(data => {
                    if (indicator) indicator.style.display = 'none';
                    if (statusText) {
                        if (data.ok) {
                            statusText.innerHTML = '<i class="bi bi-cloud-check text-success"></i> Sincronizado';
                        } else {
                            statusText.innerHTML = '<i class="bi bi-cloud-slash text-danger"></i> Error Sync';
                        }
                    }
                    if (timeText) timeText.innerText = 'Último: ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                })
                .catch(e => {
                    if (indicator) indicator.style.display = 'none';
                    if (statusText) statusText.innerHTML = '<i class="bi bi-cloud-slash text-danger"></i> Sin conexión';
                });

            // Descargar cambios (Cada 2 ciclos - 10 min o primera ejecución)
            if (syncCycles === 1 || syncCycles % 2 === 0) {
                fetch('../scripts/sync_pull.php').catch(e => console.error(e));
            }
            
            // Verificar licencia (cada ciclo)
            checkLicenseStatus();
        }
        // Ejecutar cada 5 minutos
        setInterval(runAutoSync, 5 * 60 * 1000);
        // Primera ejecución a los 15 segundos
        setTimeout(runAutoSync, 15 * 1000);
        <?php endif; ?>
    </script>

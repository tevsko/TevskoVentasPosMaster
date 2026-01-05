<?php
// admin/layout_head.php
require_once __DIR__ . '/../src/Auth.php';
$auth = new Auth();
// Permitir Admin y Gerente de Sucursal
$auth->requireRole(['admin', 'branch_manager']);
$currentUser = $auth->getCurrentUser();

// Tracking de actividad
$auth->updateLastActivity($currentUser['id']);

$isAdmin = ($currentUser['role'] === 'admin');
$isManager = ($currentUser['role'] === 'branch_manager');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SpacePark</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #343a40; color: #fff; }
        .sidebar a { color: #cfd2d6; text-decoration: none; padding: 10px 20px; display: block; }
        .sidebar a:hover, .sidebar a.active { background: #495057; color: #fff; }
        .sidebar .brand { font-size: 1.5rem; font-weight: bold; padding: 20px; text-align: center; border-bottom: 1px solid #4b545c; }
        .content { padding: 20px; }
        .card-stat { border-radius: 10px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-stat .icon { font-size: 2.5rem; opacity: 0.2; }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="sidebar col-md-2 d-none d-md-block">
        <div class="brand">SpacePark <span class="badge bg-primary small"><?= $isAdmin ? 'Admin' : 'Manager' ?></span></div>
        <div class="mt-3">
            
            <?php if ($isAdmin): ?>
                <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2 me-2"></i> Inicio</a>
                <a href="branches.php" class="<?= basename($_SERVER['PHP_SELF'])=='branches.php'?'active':'' ?>"><i class="bi bi-shop me-2"></i> Sucursales</a>
                <a href="employees.php" class="<?= basename($_SERVER['PHP_SELF'])=='employees.php'?'active':'' ?>"><i class="bi bi-people me-2"></i> Empleados</a>
                <a href="machines.php" class="<?= basename($_SERVER['PHP_SELF'])=='machines.php'?'active':'' ?>"><i class="bi bi-joystick me-2"></i> Máquinas</a>
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="bi bi-bar-chart me-2"></i> Reportes</a>
                <a href="licenses.php" class="<?= basename($_SERVER['PHP_SELF'])=='licenses.php'?'active':'' ?>"><i class="bi bi-key me-2"></i> Licencias</a>
                <a href="backups.php" class="<?= basename($_SERVER['PHP_SELF'])=='backups.php'?'active':'' ?>"><i class="bi bi-cloud-arrow-up me-2"></i> Backups & Sync</a>
                <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF'])=='settings.php'?'active':'' ?>"><i class="bi bi-gear me-2"></i> Configuración</a>
            <?php endif; ?>

            <?php if ($isManager): ?>
                 <a href="branch_view.php" class="<?= basename($_SERVER['PHP_SELF'])=='branch_view.php'?'active':'' ?>"><i class="bi bi-shop-window me-2"></i> Mi Sucursal</a>
                 <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="bi bi-bar-chart me-2"></i> Reportes</a>
                 <a href="employees.php" class="<?= basename($_SERVER['PHP_SELF'])=='employees.php'?'active':'' ?>"><i class="bi bi-people me-2"></i> Empleados</a>
                 <a href="machines.php" class="<?= basename($_SERVER['PHP_SELF'])=='machines.php'?'active':'' ?>"><i class="bi bi-joystick me-2"></i> Máquinas</a>
            <?php endif; ?>

            <hr>
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Salir</a>
        </div>
    </nav>
    <main class="col-md-10 content w-100">
        <nav class="navbar navbar-light bg-white border-bottom shadow-sm mb-4 px-3 rounded">
            <div>
                <span class="navbar-brand mb-0 h1">Panel de Control</span>
                <?php if ($isManager): ?>
                    <span class="badge bg-warning text-dark ms-2">Vista de Gerente</span>
                <?php endif; ?>
            </div>
            <span class="text-muted">Hola, <?= htmlspecialchars($currentUser['username']) ?></span>
        </nav>

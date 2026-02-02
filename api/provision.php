<?php
// api/provision.php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Uuid.php';

function provision_tenant_for_subscription($subscriptionId, $allowedHost = null) {
    $db = Database::getInstance()->getConnection();

    // Obtener suscripción
    $stmt = $db->prepare("SELECT s.*, p.name as plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch();
    if (!$sub) throw new Exception('Subscription not found');

    // Si ya tiene tenant, devolver datos existentes (idempotente)
    if (!empty($sub['tenant_id'])) {
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$sub['tenant_id']]);
        $t = $stmt->fetch();
        return ['tenant' => $t, 'subscription' => $sub];
    }

    // Crear tenant
    $subdomain = 'client-' . $subscriptionId . '-' . substr(md5(time() . rand()), 0, 4);
    $business_name = 'Cliente - Subs ' . $subscriptionId;

    $stmt = $db->prepare("INSERT INTO tenants (subdomain, business_name, status, created_at) VALUES (?, ?, 'active', " . \Database::nowSql() . ")");
    $stmt->execute([$subdomain, $business_name]);
    $tenantId = (int)$db->lastInsertId();

    // Persist per-tenant sync token and optional allowed_host (provided via function param or POST)
    $syncToken = bin2hex(random_bytes(16));
    $finalAllowedHost = $allowedHost;
    if ($finalAllowedHost === null && !empty($_POST['allowed_host'])) {
        $finalAllowedHost = trim($_POST['allowed_host']);
    }
    $stmt = $db->prepare("UPDATE tenants SET sync_token = ?, allowed_host = ? WHERE id = ?");
    $stmt->execute([$syncToken, $finalAllowedHost, $tenantId]);

    // Crear una sucursal por defecto para el tenant
    $branch_uuid = Uuid::generate();
    $branch_name = $business_name . ' - Sucursal 1';
    $stmt = $db->prepare("INSERT INTO branches (id, name, status) VALUES (?, ?, 1)");
    $stmt->execute([$branch_uuid, $branch_name]);

    // Generar licencia simple ligada a la sucursal
    $licenseKey = 'LIC-' . strtoupper(substr(md5($tenantId . microtime(true) . rand()), 0, 12));
    $stmt = $db->prepare("INSERT INTO licenses (license_key, branch_id, status, device_id) VALUES (?, ?, 'active', NULL)");
    $stmt->execute([$licenseKey, $branch_uuid]);

    // Crear usuario admin para tenant
    $admin_uuid = Uuid::generate();
    $admin_user = 'admin@' . $subdomain;
    $admin_pass = bin2hex(random_bytes(4)); // 8 hex chars
    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (id, tenant_id, username, password_hash, role, created_at) VALUES (?, ?, ?, ?, 'admin', " . \Database::nowSql() . ")");
    $stmt->execute([$admin_uuid, $tenantId, $admin_user, $hash]);

    // Actualizar suscripción
    // Generate a one-time provision secret for the purchaser to retrieve the sync token
    $provisionSecret = bin2hex(random_bytes(12));
    $stmt = $db->prepare("UPDATE subscriptions SET tenant_id = ?, status = 'active', started_at = " . \Database::nowSql() . ", provision_secret = ?, provisioned_at = " . \Database::nowSql() . " WHERE id = ?");
    $stmt->execute([$tenantId, $provisionSecret, $subscriptionId]);

    // Build provisioning URL using site settings if available
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
    $stmt->execute();
    $siteSetting = $stmt->fetchColumn();
    $siteSetting = $siteSetting ?: null;
    $provisioningUrl = $siteSetting ? rtrim($siteSetting, '/') . '/provisioning.php?secret=' . $provisionSecret : null;

    // Queue welcome email with provisioning link
    require_once __DIR__ . '/../src/Mailer.php';
    $subject = "Bienvenido a SpacePark - {$sub['plan_name']}";
    $body = "Hola,\n\nTu tenant ha sido creado.\n\nAcceso administrador: {$admin_user}\nContraseña temporal: {$admin_pass}\n\nLicencia: {$licenseKey}\nToken de sincronización: {$syncToken}\nDominio permitido: " . ($finalAllowedHost ?? '(no restringido)') . "\n\nTambién puedes recuperar tu token en: " . ($provisioningUrl ?? '(no disponible)') . "\n\nSaludos,\nEquipo SpacePark";
    Mailer::queue($admin_user, $subject, $body);

    // Retornar info útil
    return [
        'tenant' => ['id' => $tenantId, 'subdomain' => $subdomain, 'business_name' => $business_name],
        'license' => ['license_key' => $licenseKey],
        'admin' => ['username' => $admin_user, 'password' => $admin_pass],
        'sync_token' => $syncToken,
        'allowed_host' => $finalAllowedHost,
        'provisioning_url' => $provisioningUrl,
        'subscription' => $subscriptionId
    ];
}

// Si se accede por POST para provisionar manualmente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subId = $_POST['subscription_id'] ?? null;
        if (!$subId) throw new Exception('subscription_id required');
        $allowedHost = $_POST['allowed_host'] ?? null;
        $res = provision_tenant_for_subscription($subId, $allowedHost);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $res]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

?>
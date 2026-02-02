<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

// Handle actions: rotate / revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../src/Mailer.php';
    if (!empty($_POST['rotate_token']) && !empty($_POST['tenant_id'])) {
        $new = bin2hex(random_bytes(16));
        $stmt = $db->prepare("UPDATE tenants SET sync_token = ? WHERE id = ?");
        $stmt->execute([$new, $_POST['tenant_id']]);
        $message = "Nuevo token generado.";
        // Notify tenant admin if exists
        $stmt = $db->prepare("SELECT username FROM users WHERE tenant_id = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$_POST['tenant_id']]);
        $adminUser = $stmt->fetchColumn();
        if ($adminUser) {
            $subject = 'Tu sync token ha sido rotado';
            $body = "Hola,\n\nTu token de sincronización fue actualizado por el administrador. Nuevo token: {$new}\n\nSaludos,\nEquipo SpacePark";
            Mailer::queue($adminUser, $subject, $body);
        }
    }
    if (!empty($_POST['revoke_token']) && !empty($_POST['tenant_id'])) {
        $stmt = $db->prepare("UPDATE tenants SET sync_token = NULL WHERE id = ?");
        $stmt->execute([$_POST['tenant_id']]);
        $message = "Token revocado.";
        $stmt = $db->prepare("SELECT username FROM users WHERE tenant_id = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$_POST['tenant_id']]);
        $adminUser = $stmt->fetchColumn();
        if ($adminUser) {
            $subject = 'Tu sync token fue revocado';
            $body = "Hola,\n\nTu token de sincronización fue revocado por el administrador. Si necesitas uno nuevo, ponte en contacto con soporte.";
            Mailer::queue($adminUser, $subject, $body);
        }
    }
    if (!empty($_POST['set_allowed_host']) && !empty($_POST['tenant_id'])) {
        $host = trim($_POST['allowed_host'] ?? '');
        $stmt = $db->prepare("UPDATE tenants SET allowed_host = ? WHERE id = ?");
        $stmt->execute([$host, $_POST['tenant_id']]);
        $message = "Allowed host actualizado.";
    }
    if (!empty($_POST['resend_provision']) && !empty($_POST['tenant_id'])) {
        $tid = $_POST['tenant_id'];
        // Find most recent subscription for tenant
        $stmt = $db->prepare("SELECT id, provision_secret FROM subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$tid]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sub) { $message = "No hay suscripción asociada a este tenant."; }
        else {
            $secret = $sub['provision_secret'];
            if (!$secret) {
                $secret = bin2hex(random_bytes(12));
                $stmt = $db->prepare("UPDATE subscriptions SET provision_secret = ?, provisioned_at = " . \Database::nowSql() . " WHERE id = ?");
                $stmt->execute([$secret, $sub['id']]);
            }
            // Build provisioning URL
            $siteStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
            $siteStmt->execute();
            $siteUrl = $siteStmt->fetchColumn() ?: '';
            $link = $siteUrl ? rtrim($siteUrl, '/') . '/provisioning.php?secret=' . $secret : '(site_url not configured)';
            // Send email to tenant admin if exists
            $stmt = $db->prepare("SELECT username FROM users WHERE tenant_id = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$tid]);
            $adminUser = $stmt->fetchColumn();
            if ($adminUser) {
                require_once __DIR__ . '/../src/Mailer.php';
                $subject = 'Provisioning link - SpacePark';
                $body = "Hola,\n\nPuedes recuperar tu Sync Token y QR desde el siguiente enlace: " . $link . "\n\nSaludos,\nEquipo SpacePark";
                Mailer::queue($adminUser, $subject, $body);
                $message = "Provision link reenviado al admin del tenant.";
            } else {
                $message = "Provision link: " . $link;
            }
        }
    }
} 

$tenants = $db->query("SELECT id, subdomain, business_name, sync_token, allowed_host, status, created_at FROM tenants ORDER BY id DESC LIMIT 200")->fetchAll();
?>
<?php require_once __DIR__ . '/layout_head.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Tenants</h3>
    </div>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <table class="table table-sm">
        <thead><tr><th>ID</th><th>Subdomain</th><th>Business</th><th>Token</th><th>Allowed Host</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['subdomain']) ?></td>
                    <td><?= htmlspecialchars($t['business_name']) ?></td>
                    <td>
                        <?php if ($t['sync_token']): ?>
                            <div class="d-flex align-items-center">
                                <input id="tok-<?= $t['id'] ?>" class="form-control form-control-sm me-2" value="<?= htmlspecialchars($t['sync_token']) ?>" readonly style="max-width:320px">
                                <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tok-<?= $t['id'] ?>').value)">Copiar</button>
                                <button class="btn btn-outline-info btn-sm ms-2" onclick="showQR('<?= htmlspecialchars($t['sync_token']) ?>')">QR</button>
                            </div>
                        <?php else: ?>
                            <em>Sin token</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-flex">
                            <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                            <input type="text" name="allowed_host" value="<?= htmlspecialchars($t['allowed_host']) ?>" class="form-control form-control-sm me-2" placeholder="allowed host">
                            <button class="btn btn-sm btn-secondary" name="set_allowed_host" value="1">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-warning" name="rotate_token" value="1">Rotar</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="revoke_token" value="1">Revocar</button>
                        </form>
                        <form method="POST" style="display:inline; margin-left:6px">
                            <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary" name="resend_provision" value="1">Reenviar Provision</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div id="qrModal" style="position:fixed; bottom:20px; right:20px; background:#fff; border:1px solid #ddd; padding:16px; display:none; box-shadow:0 4px 12px rgba(0,0,0,0.15)">
        <div id="qrCanvas"></div>
        <button class="btn btn-sm btn-secondary mt-2" onclick="document.getElementById('qrModal').style.display='none'">Cerrar</button>
    </div>
</div>
<script src="/assets/vendor/qrcode/qrcode.min.js"></script>
<script>
function showQR(text) {
    document.getElementById('qrCanvas').innerHTML = '';
    new QRCode(document.getElementById('qrCanvas'), { text: text, width: 192, height: 192 });
    document.getElementById('qrModal').style.display = 'block';
}
</script>
<?php require_once __DIR__ . '/layout_foot.php'; ?>
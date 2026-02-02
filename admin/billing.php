<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

// Guardar credenciales Mercado Pago y sincronización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['mp_access_token']) || isset($_POST['sync_api_token']) || isset($_POST['site_url']) || isset($_POST['mp_webhook_secret']) || isset($_POST['mail_from']))) {
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('mp_access_token', ?)");
    $stmt->execute([$_POST['mp_access_token'] ?? '']);
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('site_url', ?)");
    $stmt->execute([$_POST['site_url'] ?? '']);
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('sync_api_token', ?)");
    $stmt->execute([$_POST['sync_api_token'] ?? '']);
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('mp_webhook_secret', ?)");
    $stmt->execute([$_POST['mp_webhook_secret'] ?? '']);
    $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('mail_from', ?)");
    $stmt->execute([$_POST['mail_from'] ?? 'no-reply@localhost']);
    $message = 'Configuración guardada.';
}

// Crear plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_plan'])) {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $period = $_POST['period'];
    $features = json_encode(array_filter(array_map('trim', explode('\n', $_POST['features'] ?? ''))));
    $stmt = $db->prepare("INSERT INTO plans (code, name, price, period, features, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$code, $name, $price, $period, $features]);
    $message = 'Plan creado.';
}

$plans = $db->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();
$subs = $db->query("SELECT s.*, p.name as plan_name FROM subscriptions s LEFT JOIN plans p ON p.id = s.plan_id ORDER BY s.created_at DESC LIMIT 200")->fetchAll();
// Site URL used to build provisioning links
$site_setting_stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
$site_setting_stmt->execute();
$site_url = $site_setting_stmt->fetchColumn() ?: '';

// Obtener current MP token
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mp_access_token' LIMIT 1");
$stmt->execute(); $mp_token_row = $stmt->fetch();
$mp_token = $mp_token_row['setting_value'] ?? '';
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
$stmt->execute(); $site_row = $stmt->fetch();
$site_url = $site_row['setting_value'] ?? '';
$stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sync_api_token' LIMIT 1");
$stmt->execute(); $sync_row = $stmt->fetch();
$sync_token = $sync_row['setting_value'] ?? '';

?>

<?php require_once __DIR__ . '/layout_head.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Facturación & Mercado Pago</h3>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Configuración Mercado Pago (Sandbox/Production)</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">MP Access Token</label>
                    <input class="form-control" name="mp_access_token" value="<?= htmlspecialchars($mp_token) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Site URL (https://midominio.com)</label>
                    <input class="form-control" name="site_url" value="<?= htmlspecialchars($site_url) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sync API Token (Usado por `sync_worker.php`)</label>
                    <input class="form-control" name="sync_api_token" value="<?= htmlspecialchars($sync_token) ?>" placeholder="Token para autorización de ingest API">
                </div>
                <div class="mb-3">
                    <label class="form-label">MP Webhook Secret (opcional)</label>
                    <input class="form-control" name="mp_webhook_secret" value="<?= htmlspecialchars($db->query("SELECT setting_value FROM settings WHERE setting_key = 'mp_webhook_secret' LIMIT 1")->fetchColumn()) ?>" placeholder="Se usa para validar X-Hub-Signature">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mail From (para envíos salientes)</label>
                    <input class="form-control" name="mail_from" value="<?= htmlspecialchars($db->query("SELECT setting_value FROM settings WHERE setting_key = 'mail_from' LIMIT 1")->fetchColumn()) ?>" placeholder="no-reply@tudominio.com">
                </div>
                <button class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Crear Plan</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="create_plan" value="1">
                        <div class="mb-2"><input class="form-control" name="code" placeholder="Código (ej: starter_monthly)" required></div>
                        <div class="mb-2"><input class="form-control" name="name" placeholder="Nombre" required></div>
                        <div class="mb-2"><input class="form-control" name="price" placeholder="Precio" required></div>
                        <div class="mb-2">
                            <select class="form-control" name="period">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="mb-2"><textarea class="form-control" name="features" rows="3" placeholder="Una característica por línea"></textarea></div>
                        <button class="btn btn-success">Crear Plan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Planes Existentes</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($plans as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                    <small class="text-muted">$<?= number_format($p['price'],2) ?> - <?= $p['period'] ?></small>
                                </div>
                                <span class="badge bg-primary">ID <?= $p['id'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Suscripciones Recientes</div>
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>ID</th><th>Plan</th><th>Estado</th><th>Monto</th><th>Creada</th><th>Provision</th></tr></thead>
                <tbody>
                    <?php foreach ($subs as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars($s['plan_name'] ?? 'N/A') ?></td>
                            <td><?= $s['status'] ?></td>
                            <td><?= $s['amount'] ?></td>
                            <td><?= $s['created_at'] ?></td>
                            <td>
                                <?php if (!empty($s['provision_secret'])): ?>
                                    <?php $link = $site_url ? htmlspecialchars(rtrim($site_url, '/') . '/provisioning.php?secret=' . $s['provision_secret']) : '(site_url no configurado)'; ?>
                                    <a href="<?= $link ?>" target="_blank">Ver Provision</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php require_once __DIR__ . '/layout_foot.php'; ?>
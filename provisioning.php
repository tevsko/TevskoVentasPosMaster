<?php
// provisioning.php
require_once __DIR__ . '/src/Database.php';
$db = Database::getInstance()->getConnection();

$secret = $_GET['secret'] ?? null;
if (!$secret) {
    http_response_code(400);
    echo "Provision secret required";
    exit;
}

$stmt = $db->prepare("SELECT s.id as subscription_id, s.provisioned_at, t.sync_token, t.allowed_host, t.subdomain, t.business_name FROM subscriptions s JOIN tenants t ON t.id = s.tenant_id WHERE s.provision_secret = ? LIMIT 1");
$stmt->execute([$secret]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo "Provisioning not found or expired.";
    exit;
}

// Render a minimal page with token and QR (uses qrcode.min.js from assets)
$safeToken = htmlspecialchars($row['sync_token']);
$allowedHost = htmlspecialchars($row['allowed_host']);
$subdomain = htmlspecialchars($row['subdomain']);
$business = htmlspecialchars($row['business_name']);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Provisioning - SpacePark</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h3>Provisioning - <?= $business ?> (<?= $subdomain ?>)</h3>
    <p>Usa el siguiente <strong>Sync Token</strong> en la instalación local o escanea el QR con otro dispositivo para copiarlo rápidamente.</p>
    <div class="mb-3">
        <label class="form-label">Sync Token</label>
        <div class="input-group">
            <input id="tokenInput" class="form-control" value="<?= $safeToken ?>" readonly>
            <button class="btn btn-outline-secondary" id="copyBtn">Copiar</button>
        </div>
        <small class="text-muted">Dominio permitido: <?= ($allowedHost ?: '(no restringido)') ?></small>
    </div>
    <div id="qr"></div>
    <hr>
    <p>Instrucciones:</p>
    <ol>
        <li>Abra la instalación local (instalador Universal) y vaya a Admin → Facturación.</li>
        <li>Pegue el Sync Token en <code>Sync API Token</code> o escanee el QR.</li>
        <li>Opcional: guarde <code>allowed_host</code> en la instalación local para protección adicional.</li>
    </ol>
</div>
<script src="/assets/vendor/qrcode/qrcode.min.js"></script>
<script>
    const token = document.getElementById('tokenInput').value;
    const qrDiv = document.getElementById('qr');
    new QRCode(qrDiv, { text: token, width: 192, height: 192 });
    document.getElementById('copyBtn').addEventListener('click', function(){
        navigator.clipboard.writeText(token).then(()=>{ alert('Token copiado al portapapeles'); });
    });
</script>
</body>
</html>
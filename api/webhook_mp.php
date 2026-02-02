<?php
// api/webhook_mp.php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Billing.php';

$db = Database::getInstance()->getConnection();

// Mercado Pago webhooks may arrive as GET with 'topic' and 'id' or POST with data
$input = file_get_contents('php://input');
$raw = json_decode($input, true);

// Optional HMAC verification if mp_webhook_secret is configured
$mpWebhookSecret = Billing::getSetting('mp_webhook_secret');
$headers = getallheaders();
$sigHeader = $headers['X-Hub-Signature'] ?? $headers['x-hub-signature'] ?? null;
if ($mpWebhookSecret && $sigHeader) {
    $computed = 'sha256=' . hash_hmac('sha256', $input, $mpWebhookSecret);
    if (!hash_equals($computed, $sigHeader)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid signature']);
        exit;
    }
}

$id = null;
$topic = $_GET['topic'] ?? $_GET['type'] ?? null;
if (!empty($raw['data']['id'])) {
    $id = $raw['data']['id'];
}
// Some MP webhooks send 'id' as GET param
if (empty($id) && !empty($_GET['id'])) $id = $_GET['id'];

if (!$id) {
    // Nothing to do
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No id provided']);
    exit;
}

try {
    $payment = Billing::getPayment($id);
    if (!$payment) {
        throw new Exception('No se obtuvo pago desde MP');
    }

    // Buscar external_reference (we stored subscriptionId there when creating preference)
    $externalRef = null;
    if (!empty($payment['order']) && !empty($payment['order']['external_reference'])) {
        $externalRef = $payment['order']['external_reference'];
    }
    if (!$externalRef && !empty($payment['external_reference'])) {
        $externalRef = $payment['external_reference'];
    }

    if (!$externalRef) {
        // Log and ignore
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")
           ->execute(['Webhook sin external_reference', json_encode($payment)]);
        echo json_encode(['ok' => false, 'message' => 'No external_reference']);
        exit;
    }

    // Encontrar la suscripción local
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ? LIMIT 1");
    $stmt->execute([$externalRef]);
    $sub = $stmt->fetch();

    if (!$sub) {
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")
           ->execute(['Suscripción no encontrada', json_encode(['externalRef' => $externalRef, 'payment' => $payment])]);
        echo json_encode(['ok' => false, 'message' => 'Subscription not found']);
        exit;
    }

    // Verificar estado del pago
    $status = $payment['status'] ?? ($payment['status_detail'] ?? null);
    if (strtolower($status) === 'approved' || strtolower($status) === 'authorized') {
        // Activar la suscripción
        $db->prepare("UPDATE subscriptions SET status = 'active', external_id = ?, started_at = " . \Database::nowSql() . " WHERE id = ?")
           ->execute([$id, $sub['id']]);

        // Provisionar tenant (crear tenant + licencia)
        // Implement inline provisioning for now
        require_once __DIR__ . '/provision.php';
        $tenant = provision_tenant_for_subscription($sub['id']);

        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'success', ?)")
           ->execute(['Subscripción activada y tenant provisionado', json_encode($tenant)]);

        echo json_encode(['ok' => true]);
        exit;
    } else {
        // Otros estados: pending, in_process, rejected -> registrar
        $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")
           ->execute(['Pago no aprobado: ' . ($status ?? 'unknown'), json_encode($payment)]);
        echo json_encode(['ok' => false, 'message' => 'Payment not approved']);
        exit;
    }

} catch (Exception $e) {
    $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")
       ->execute(['Webhook handler error: ' . $e->getMessage(), json_encode(['id' => $id])]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

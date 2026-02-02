<?php
// api/subscribe.php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Billing.php';

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /landing.php');
    exit;
}

$plan_id = $_POST['plan_id'] ?? null;
if (!$plan_id) {
    die('Plan inválido');
}

$stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) die('Plan no encontrado');

try {
    // Crear registro de suscripción (pending)
    $stmt = $db->prepare("INSERT INTO subscriptions (plan_id, amount, period, status, created_at) VALUES (?, ?, ?, 'pending', " . \Database::nowSql() . ")");
    $stmt->execute([$plan_id, $plan['price'], $plan['period']]);
    $subscriptionId = $db->lastInsertId();

    // Crear Preference en Mercado Pago
    $pref = Billing::createPreference($subscriptionId, $plan['name'], $plan['price'], (string)$subscriptionId);

    // MP devuelve 'sandbox_init_point' y 'init_point'
    $init = $pref['sandbox_init_point'] ?? $pref['init_point'] ?? null;

    if (!$init) throw new Exception('No se pudo obtener URL de pago');

    // Store pending subscription id in session so landing can detect provisioning
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['pending_subscription_id'] = $subscriptionId;

    header('Location: ' . $init);
    exit; 

} catch (Exception $e) {
    // Log error y mostrar mensaje
    $db->prepare("INSERT INTO sync_logs (last_sync, details, status, meta) VALUES (" . \Database::nowSql() . ", ?, 'error', ?)")->execute([
        'Subscribe error: ' . $e->getMessage(), json_encode(['subscription_id' => $subscriptionId ?? null])
    ]);
    die('Error creando suscripción: ' . $e->getMessage());
}

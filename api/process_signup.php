<?php
// api/process_signup.php
require_once __DIR__ . '/../src/Database.php';

// Habilitar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../landing.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// --- AUTO-MIGRATION PARA PENDING SIGNUPS UPDATED ---
// Aseguramos que la tabla pending pueda guardar JSON grande (ya es JSON, ok)

// Recibir Datos
$plan_id = $_POST['plan_id'];
$business_name = $_POST['business_name'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$subdomain = $_POST['subdomain'] ?? '';

// Extras
$addMp = isset($_POST['add_mp']) && $_POST['add_mp'] == 1;
$addModo = isset($_POST['add_modo']) && $_POST['add_modo'] == 1;
$extraPos = isset($_POST['extra_pos']) ? (int)$_POST['extra_pos'] : 0;
if ($extraPos < 0) $extraPos = 0;

// Validaciones
if (empty($business_name) || empty($username) || empty($password)) die("Faltan datos requeridos.");
if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) die("Subdominio inválido");

// ... (verificaciones unicidad omitidas para brevedad, no cambian)

// Integración Mercado Pago (Solo si el precio es > 0)
if ($totalPrice > 0) {
    if (!$saasToken) die("Error de Configuración: Token SaaS no configurado.");

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];

    $currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
    $basePath = preg_replace('#/api$#', '', $currentDir); 
    $baseUrl = $protocol . $host . $basePath;

    $url = "https://api.mercadopago.com/checkout/preferences";
    $data = [
        "items" => [[
            "title" => $description,
            "quantity" => 1,
            "currency_id" => "ARS",
            "unit_price" => $totalPrice
        ]],
        "payer" => [
            "email" => $email,
            "name" => $username
        ],
        "back_urls" => [
            "success" => $baseUrl . "/api/payment_success.php",
            "failure" => $baseUrl . "/signup.php?error=payment",
            "pending" => $baseUrl . "/signup.php?warning=pending"
        ],
        "external_reference" => "REG-" . $pendingId,
        "notification_url" => $baseUrl . "/api/webhook_mp.php"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . trim($saasToken),
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 201 || $httpCode == 200) {
        $mpData = json_decode($response, true);
        $isSandbox = strpos(trim($saasToken), 'TEST') === 0;
        $checkoutUrl = ($isSandbox && !empty($mpData['sandbox_init_point'])) 
                       ? $mpData['sandbox_init_point'] 
                       : $mpData['init_point'];

        header("Location: " . $checkoutUrl);
        exit;
    } else {
        $errorMsg = "Error MP ($httpCode): " . $response;
        $errorMsg .= "<br>Payload Enviado: <pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
        die($errorMsg);
    }
} else {
    // PRECIO ZERO: Bypass Mercado Pago
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
    $basePath = preg_replace('#/api$#', '', $currentDir); 
    $baseUrl = $protocol . $host . $basePath;

    $successUrl = $baseUrl . "/api/payment_success.php?collection_status=approved&external_reference=REG-" . $pendingId;
    header("Location: " . $successUrl);
    exit;
}

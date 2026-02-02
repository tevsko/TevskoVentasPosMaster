<?php
// src/Billing.php
require_once __DIR__ . '/Database.php';

class Billing {
    public static function getSetting($key) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $r = $stmt->fetch();
        return $r ? $r['setting_value'] : null;
    }

    public static function getSiteUrl() {
        $url = self::getSetting('site_url');
        if ($url) return rtrim($url, '/');
        // Fall back to detection
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    // Create a Mercado Pago preference for one-time payments (or use as redirect target for pending subscriptions)
    public static function createPreference($subscriptionId, $title, $price, $external_reference = null) {
        $accessToken = self::getSetting('mp_access_token');
        if (!$accessToken) throw new Exception('MP_ACCESS_TOKEN no configurado en settings');

        $back_url = self::getSiteUrl() . '/landing.php';
        $notification_url = self::getSiteUrl() . '/api/webhook_mp.php';

        $payload = [
            'items' => [
                [
                    'title' => $title,
                    'quantity' => 1,
                    'currency_id' => 'ARS',
                    'unit_price' => (float)$price
                ]
            ],
            'external_reference' => $external_reference ?? (string)$subscriptionId,
            'back_urls' => [
                'success' => $back_url . '?status=success',
                'failure' => $back_url . '?status=failure',
                'pending' => $back_url . '?status=pending'
            ],
            'auto_return' => 'approved',
            'notification_url' => $notification_url
        ];

        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http >= 200 && $http < 300) {
            $data = json_decode($resp, true);
            return $data; // contains 'init_point' and 'sandbox_init_point' (sandbox)
        }

        throw new Exception('Error creando preference MP: ' . $resp);
    }

    public static function getPayment($paymentId) {
        $accessToken = self::getSetting('mp_access_token');
        if (!$accessToken) throw new Exception('MP_ACCESS_TOKEN no configurado en settings');
        $url = "https://api.mercadopago.com/v1/payments/" . urlencode($paymentId) . "?access_token=" . urlencode($accessToken);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http >= 200 && $http < 300) {
            return json_decode($resp, true);
        }
        return null;
    }
}

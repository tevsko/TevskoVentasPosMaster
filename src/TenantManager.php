<?php
// src/TenantManager.php

class TenantManager {
    private static $currentTenant = null;
    private static $isGlobal = false;

    public static function init($pdo) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // 1. Detección por parámetro (GET/POST/JSON)
        $subdomain = $_GET['tenant'] ?? $_POST['tenant'] ?? null;
        
        if (!$subdomain) {
            // Check JSON input
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            if (isset($input['tenant'])) {
                $subdomain = $input['tenant'];
            }
        }

        if (!$subdomain) {
            // 2. Detección por Host (Subdominio)
            $hostPart = explode(':', $host)[0];
            $parts = explode('.', $hostPart);
            
            if (count($parts) > 2) {
                // Case: nike.spacepark.com -> subdomain 'nike'
                $subdomain = $parts[0];
            } else if (count($parts) == 2 && $parts[1] === 'localhost') {
                 // Case: nike.localhost -> subdomain 'nike'
                 $subdomain = $parts[0];
            }
        }

        if (!$subdomain) {
            // 3. Detección por Referer (Útil para PWA en dominio principal)
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if ($referer && strpos($referer, 'tenant=') !== false) {
                $query = parse_url($referer, PHP_URL_QUERY);
                if ($query) {
                    parse_str($query, $refParams);
                    if (isset($refParams['tenant'])) {
                        $subdomain = $refParams['tenant'];
                    }
                }
            }
        }

        // Check for 'www' or no subdomain -> Global Master
        if (!$subdomain || $subdomain === 'www' || $subdomain === 'localhost' || $hostPart === '127.0.0.1') {
            self::$isGlobal = true;
            return;
        }

        // Fetch Tenant
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE subdomain = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$subdomain]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            self::$currentTenant = $tenant;
        } else {
            // Tenant not found or inactive
            // In a real app, maybe redirect to 404 or signup page
            // For now, we can just leave currentTenant null, or throw exception
            // die("Tenant not found: " . htmlspecialchars($subdomain));
        }
    }

    public static function getTenant() {
        return self::$currentTenant;
    }

    public static function getTenantId() {
        return self::$currentTenant ? self::$currentTenant['id'] : null;
    }

    public static function isGlobal() {
        return self::$isGlobal;
    }
}

<?php
// src/TenantManager.php

class TenantManager {
    private static $currentTenant = null;
    private static $isGlobal = false;

    public static function init($pdo) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Remove port number if present
        $hostPart = explode(':', $host)[0];
        
        // Basic subdomain extraction logic
        // Assumes format: subdomain.domain.com or subdomain.localhost
        // You might need to adjust this based on your actual domain structure (e.g. how many parts the root domain has)
        
        // For development like 'nike.localhost', parts are ['nike', 'localhost']
        // For production 'nike.spacepark.com', parts are ['nike', 'spacepark', 'com']
        
        $parts = explode('.', $hostPart);
        $subdomain = null;

        if (count($parts) > 2) {
            // Case: nike.spacepark.com -> subdomain 'nike'
            $subdomain = $parts[0];
        } else if (count($parts) == 2 && $parts[1] === 'localhost') {
             // Case: nike.localhost -> subdomain 'nike'
             $subdomain = $parts[0];
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

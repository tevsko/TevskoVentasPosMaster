<?php
// test_tenant.php
// Run this from command line: php test_tenant.php

require_once 'config/db.php';

echo "--- Tenant Resolution Test ---\n";

// 1. Test Global Context (no subdomain)
$_SERVER['HTTP_HOST'] = 'localhost';
TenantManager::init($pdo);
echo "Host: localhost -> Is Global: " . (TenantManager::isGlobal() ? 'YES' : 'NO') . " (Expected: YES)\n";

// 2. Test Tenant Context (Simulated)
// First we need to insert a tenant to test success
// But since we can't easily rely on DB state in this script without setup, we rely on the migration having run.
// Let's try to fetch a tenant calling the real init.
// We will manually insert a tenant for this test if it doesn't exist? 
// Or just checking the logic with a non-existent one.

$_SERVER['HTTP_HOST'] = 'nike.localhost';
TenantManager::init($pdo);
$tenant = TenantManager::getTenant();
echo "Host: nike.localhost -> Tenant Found: " . ($tenant ? $tenant['business_name'] : 'NO') . "\n";

if (!$tenant) {
    echo "NOTE: If 'nike' tenant is not in DB, this is expected to be NO.\n";
}

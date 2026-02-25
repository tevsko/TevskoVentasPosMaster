<?php
// migrations/run_maintenance_mode_migration.php
// Auto-migration script to add maintenance_mode setting

require_once __DIR__ . '/../src/Database.php';

$db = Database::getInstance()->getConnection();
$driver = Database::getInstance()->getDriver();

try {
    // Check if maintenance_mode already exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        // Insert maintenance_mode setting
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
        $stmt->execute();
        echo "✅ Migration successful: maintenance_mode setting added with default value '0' (disabled)\n";
    } else {
        echo "ℹ️ Migration skipped: maintenance_mode setting already exists\n";
    }
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

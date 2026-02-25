<?php
// src/DeviceFingerprint.php
// Generate unique device fingerprint for license control

class DeviceFingerprint {
    
    /**
     * Generate a unique device fingerprint based on hardware/system info
     * @return string 32-character unique device ID
     */
    public static function generate() {
        $factors = [];
        
        // Basic system info
        $factors[] = php_uname('n');  // Hostname
        $factors[] = php_uname('m');  // Machine type
        $factors[] = gethostname();   // Hostname (alternative)
        
        // Windows-specific
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $factors[] = getenv('COMPUTERNAME');
            $factors[] = getenv('USERNAME');
            $factors[] = getenv('USERDOMAIN');
            
            // Try to get hardware info via WMIC
            if (function_exists('shell_exec')) {
                try {
                    // Motherboard serial
                    $wmic = @shell_exec('wmic baseboard get serialnumber 2>nul');
                    if ($wmic) {
                        $lines = explode("\n", trim($wmic));
                        if (isset($lines[1])) {
                            $factors[] = trim($lines[1]);
                        }
                    }
                    
                    // System UUID
                    $uuid = @shell_exec('wmic csproduct get uuid 2>nul');
                    if ($uuid) {
                        $lines = explode("\n", trim($uuid));
                        if (isset($lines[1])) {
                            $factors[] = trim($lines[1]);
                        }
                    }
                    
                    // CPU ID
                    $cpu = @shell_exec('wmic cpu get processorid 2>nul');
                    if ($cpu) {
                        $lines = explode("\n", trim($cpu));
                        if (isset($lines[1])) {
                            $factors[] = trim($lines[1]);
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail if WMIC not available
                }
            }
        }
        
        // Filter empty values
        $factors = array_filter($factors, function($v) {
            return !empty(trim($v));
        });
        
        // Generate hash
        $fingerprint = hash('sha256', implode('|', $factors));
        
        return substr($fingerprint, 0, 32);
    }
    
    /**
     * Get stored device ID from local database
     * @return string|false Device ID or false if not found
     */
    public static function getStoredDeviceId() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'device_id' LIMIT 1");
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Store device ID in local database
     * @param string $deviceId Device ID to store
     * @return bool Success
     */
    public static function storeDeviceId($deviceId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('device_id', ?)");
            return $stmt->execute([$deviceId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get or generate device ID
     * @return string Device ID
     */
    public static function getOrCreate() {
        $deviceId = self::getStoredDeviceId();
        
        if (!$deviceId) {
            $deviceId = self::generate();
            self::storeDeviceId($deviceId);
        }
        
        return $deviceId;
    }
    
    /**
     * Get device name (computer name)
     * @return string Device name
     */
    public static function getDeviceName() {
        $name = gethostname();
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $computerName = getenv('COMPUTERNAME');
            if ($computerName) {
                $name = $computerName;
            }
        }
        
        return $name ?: 'Unknown PC';
    }
}

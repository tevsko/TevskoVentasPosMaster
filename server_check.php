<?php
// server_check.php - Diagnostic Tool v3 (Runtime & Instantiation)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>SpacePark Server Diagnostic v3</h1>";

try {
    // 1. Define BASE_PATH like index.php does
    define('BASE_PATH', __DIR__);
    echo "1. BASE_PATH defined: " . BASE_PATH . "<br>";

    // 2. Test Session Start
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "2. Session start: OK<br>";

    // 3. Test Database Instantiation
    echo "3. Testing Database Connection...<br>";
    if (!file_exists(BASE_PATH . '/src/Database.php')) {
        throw new Exception("Missing src/Database.php");
    }
    require_once BASE_PATH . '/src/Database.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    if ($conn) {
        echo "<strong style='color:green'>Database Connected Successfully!</strong><br>";
    } else {
        throw new Exception("Database::getInstance() returned null or invalid object");
    }

    // 4. Test Auth Instantiation
    echo "4. Testing Auth Class...<br>";
    if (!file_exists(BASE_PATH . '/src/Auth.php')) {
        throw new Exception("Missing src/Auth.php");
    }
    require_once BASE_PATH . '/src/Auth.php';
    
    $auth = new Auth();
    echo "<strong style='color:green'>Auth Class Instantiated Successfully!</strong><br>";
    
    // 5. Test Login Method (Dry Run)
    echo "5. Testing Login Method (Existence only)...<br>";
    if (method_exists($auth, 'login')) {
        echo "Method Auth::login exists. OK.<br>";
    } else {
        echo "Method Auth::login MISSING.<br>";
    }

    echo "<h3>DIAGNOSTIC COMPLETE: No Fatal Errors detected in Core Logic.</h3>";
    echo "<p>If this script runs but your site still gives 500 Error, the problem is likely:</p>";
    echo "<ul>
            <li><strong>.htaccess file:</strong> Try renaming <code>.htaccess</code> to <code>.htaccess_bak</code> via cPanel File Manager.</li>
            <li><strong>index.php permissions:</strong> Ensure file permissions are 644 (not 777).</li>
          </ul>";

} catch (Throwable $e) {
    echo "<div style='background:#fee; border:2px solid red; padding:15px; margin:20px;'>";
    echo "<h2 style='color:red;'>FATAL ERROR CAUGHT</h2>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>

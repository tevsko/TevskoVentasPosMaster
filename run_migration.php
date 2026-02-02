<?php
// run_migration.php
// Executes all SQL files in migrations/ in lexicographic order and stores a migration history

// Prefer using config/db.php if available
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'spacepark_db');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database.\n";

    // Ensure migrations history table
    $pdo->exec("CREATE TABLE IF NOT EXISTS migration_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // If using SQLite, initialize via a PHP script that creates compatible schema
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        echo "Detected SQLite driver; running SQLite init script...\n";
        require_once __DIR__ . '/scripts/init_sqlite.php';
        // Mark migrations as applied conceptually
        $stm = $pdo->prepare("INSERT OR REPLACE INTO migration_history (filename) VALUES (?)");
        $stm->execute(['sqlite_init']);
    } else {
        $files = glob(__DIR__ . '/migrations/*.sql');
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $fname = basename($file);
            // Check if already applied
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM migration_history WHERE filename = ?");
            $stmt->execute([$fname]);
            if ($stmt->fetchColumn() > 0) {
                echo "Skipping already applied migration: $fname\n";
                continue;
            }

            echo "Applying migration: $fname ... ";
            $sql = file_get_contents($file);
            try {
                // Some migrations use DDL which causes implicit commits; execute directly and avoid wrapping in a transaction
                $pdo->exec($sql);
                $pdo->prepare("INSERT INTO migration_history (filename) VALUES (?)")->execute([$fname]);
                echo "OK\n";
            } catch (PDOException $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                // Stop on first failure
                exit(1);
            }
        }
    }

    echo "All migrations processed.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}

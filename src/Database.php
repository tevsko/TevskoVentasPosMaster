<?php
// src/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Cargar configuración siempre (require_once se encarga de no duplicar)
        require_once __DIR__ . '/../config/db.php';

        // If a global $pdo exists via a direct include of config/db.php, we CAN use it 
        // BUT it's better to let the singleton own the lifecycle.
        // We only reuse it if we are sure it's valid.
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $this->pdo = $pdo;
            return;
        }

        // Detectar carpeta de datos de usuario en Windows de forma segura
        $appData = getenv('APPDATA');
        if (!$appData && isset($_SERVER['APPDATA'])) $appData = $_SERVER['APPDATA'];
        if (!$appData && isset($_SERVER['USERPROFILE'])) $appData = $_SERVER['USERPROFILE'] . '\AppData\Roaming';
        
        $defaultPath = ($appData ? $appData . '/SpacePark/data/data.sqlite' : __DIR__ . '/../data/data.sqlite');
        
        $sqliteFile = defined('DB_SQLITE_FILE') ? DB_SQLITE_FILE : $defaultPath;
        // Si no se define driver, POR DEFECTO usamos sqlite para que el cliente local no falle
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

        try {
            if ($driver === 'sqlite') {
                $dir = dirname($sqliteFile);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                
                $this->pdo = new PDO('sqlite:' . $sqliteFile);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');

                // AUTO-HEAL: Verificar si las tablas existen. Si no, inicializarlas.
                // Consultamos si existe la tabla 'branches' como testigo.
                $check = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='branches'")->fetch();
                if (!$check) {
                    $this->initSqliteSchema();
                } else {
                    // MIGRATIONS: Verificar si falta la columna license_modo_expiry
                    $cols = $this->pdo->query("PRAGMA table_info(branches)")->fetchAll();
                    $hasModo = false;
                    foreach ($cols as $c) {
                        if ($c['name'] === 'license_modo_expiry') { $hasModo = true; break; }
                    }
                    if (!$hasModo) {
                        $this->pdo->exec("ALTER TABLE branches ADD COLUMN license_modo_expiry TEXT");
                    }

                    // MIGRATIONS: Arcade Module
                    $hasArcade = false;
                    foreach ($cols as $c) {
                        if ($c['name'] === 'license_arcade_expiry') { $hasArcade = true; break; }
                    }
                    if (!$hasArcade) {
                        $this->pdo->exec("ALTER TABLE branches ADD COLUMN license_arcade_expiry TEXT");
                    }
                    
                    // ADD tenant_id to tables if missing
                    $tablesToFix = ['branches', 'machines', 'sales'];
                    foreach ($tablesToFix as $table) {
                        $cols = $this->pdo->query("PRAGMA table_info($table)")->fetchAll();
                        $hasTenant = false;
                        foreach ($cols as $c) {
                            if ($c['name'] === 'tenant_id') { $hasTenant = true; break; }
                        }
                        if (!$hasTenant) {
                            $type = ($table === 'branches' || $table === 'machines') ? "INTEGER" : "INTEGER"; 
                            // Using INTEGER for all tenant_id for consistency
                            $this->pdo->exec("ALTER TABLE $table ADD COLUMN tenant_id INTEGER");
                        }
                    }

                    // Plans table columns in SQLite
                    $planCols = $this->pdo->query("PRAGMA table_info(plans)")->fetchAll();
                    $hasMob = false;
                    foreach ($planCols as $pc) {
                        if ($pc['name'] === 'mobile_module_enabled') { $hasMob = true; break; }
                    }
                    if (!$hasMob) {
                        $this->pdo->exec("ALTER TABLE plans ADD COLUMN mobile_module_enabled INTEGER DEFAULT 0");
                    }
                }
            } else {
                $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
                $name = defined('DB_NAME') ? DB_NAME : 'spacepark_db';
                $user = defined('DB_USER') ? DB_USER : 'root';
                $pass = defined('DB_PASS') ? DB_PASS : 'root';
                
                $this->pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec("SET NAMES 'utf8mb4'");

                // MIGRATIONS MySQL - Only run for Admin or when specifically requested
                // This reduces the overhead for every mobile API call
                if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'branch_manager')) {
                    try {
                        // Check Modo
                        $res = $this->pdo->query("SHOW COLUMNS FROM branches LIKE 'license_modo_expiry'")->fetch();
                        if (!$res) {
                            $this->pdo->exec("ALTER TABLE branches ADD COLUMN license_modo_expiry DATE NULL AFTER license_mp_expiry");
                        }
                        // Check Arcade
                        $resA = $this->pdo->query("SHOW COLUMNS FROM branches LIKE 'license_arcade_expiry'")->fetch();
                        if (!$resA) {
                            $this->pdo->exec("ALTER TABLE branches ADD COLUMN license_arcade_expiry DATE NULL AFTER license_modo_expiry");
                        }
                        // Check Plans Mobile
                        $resP = $this->pdo->query("SHOW COLUMNS FROM plans LIKE 'mobile_module_enabled'")->fetch();
                        if (!$resP) {
                            $this->pdo->exec("ALTER TABLE plans ADD COLUMN mobile_module_enabled TINYINT DEFAULT 0 AFTER allow_modo_integration");
                        }
                    } catch (Exception $e) {
                        // Fail silently
                    }
                }
            }
        } catch (PDOException $e) {
            die("Error de conexión (Modo: $driver): " . $e->getMessage());
        }
    }

    private function initSqliteSchema() {
        // Tenants
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subdomain TEXT NOT NULL UNIQUE,
            business_name TEXT NOT NULL,
            db_name TEXT,
            status TEXT DEFAULT 'active',
            sync_token TEXT,
            allowed_host TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Users
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            tenant_id INTEGER NULL,
            username TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            emp_name TEXT,
            emp_email TEXT,
            branch_id TEXT,
            active INTEGER DEFAULT 1,
            last_activity TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Branches
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS branches (
            id TEXT PRIMARY KEY,
            tenant_id INTEGER,
            name TEXT NOT NULL,
            address TEXT,
            phone TEXT,
            cuit TEXT,
            fiscal_data TEXT,
            license_expiry TEXT,
            license_pos_expiry TEXT,
            license_mp_expiry TEXT,
            license_modo_expiry TEXT,
            license_cloud_expiry TEXT,
            pos_license_limit INTEGER DEFAULT 1,
            pos_title TEXT DEFAULT 'SpacePark POS',
            cloud_host TEXT,
            cloud_db TEXT,
            cloud_user TEXT,
            cloud_pass TEXT,
            mp_token TEXT,
            mp_collector_id TEXT,
            mp_status INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            license_arcade_expiry TEXT
        )");

        // Machines
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS machines (
            id TEXT PRIMARY KEY,
            tenant_id INTEGER,
            name TEXT NOT NULL,
            price REAL NOT NULL,
            branch_id TEXT,
            active INTEGER DEFAULT 1
        )");

        // Sales
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id TEXT PRIMARY KEY,
            tenant_id INTEGER,
            user_id TEXT NOT NULL,
            branch_id TEXT,
            machine_id TEXT NOT NULL,
            amount REAL NOT NULL,
            payment_method TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            sync_status INTEGER DEFAULT 0,
            last_synced_at TEXT NULL
        )");

        // Licenses
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
            license_key TEXT PRIMARY KEY,
            branch_id TEXT NOT NULL,
            status TEXT DEFAULT 'inactive',
            device_id TEXT
        )");

        // Sync Queue
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS sync_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            resource_type TEXT NOT NULL,
            resource_uuid TEXT NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER DEFAULT 0,
            locked INTEGER DEFAULT 0,
            locked_at TEXT,
            next_attempt TEXT DEFAULT (datetime('now', 'localtime')),
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Sync Logs
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS sync_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            last_sync TEXT,
            details TEXT,
            status TEXT,
            attempts INTEGER DEFAULT 0,
            meta TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Settings
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT
        )");

        // Plans
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE,
            name TEXT,
            price REAL,
            period TEXT,
            features TEXT,
            active INTEGER DEFAULT 1,
            pos_included INTEGER DEFAULT 1,
            pos_extra_monthly_fee REAL DEFAULT 500.00,
            pos_extra_annual_fee REAL DEFAULT 5000.00,
            mobile_module_enabled INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Tenant Plans (Mapping)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tenant_plans (
            tenant_id INTEGER,
            plan_id INTEGER,
            PRIMARY KEY (tenant_id, plan_id)
        )");

        // Subscriptions
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER,
            plan_id INTEGER NOT NULL,
            external_id TEXT,
            status TEXT DEFAULT 'pending',
            amount REAL,
            period TEXT,
            started_at TEXT,
            ended_at TEXT,
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Device Licenses
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS device_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER NOT NULL,
            device_id TEXT NOT NULL UNIQUE,
            device_name TEXT,
            device_role TEXT DEFAULT 'master',
            license_type TEXT DEFAULT 'included',
            activated_at TEXT,
            expires_at TEXT,
            last_payment_date TEXT,
            status TEXT DEFAULT 'active',
            ip_address TEXT,
            last_seen_at TEXT,
            monthly_fee REAL DEFAULT 0.00,
            payment_period TEXT DEFAULT 'monthly',
            payment_status TEXT DEFAULT 'paid',
            created_at TEXT DEFAULT (datetime('now', 'localtime')),
            updated_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Device Payments
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS device_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_license_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            payment_method TEXT,
            external_payment_id TEXT,
            period_start TEXT NOT NULL,
            period_end TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at TEXT DEFAULT (datetime('now', 'localtime'))
        )");

        // Outbox
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS outbox_emails (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            `to` TEXT NOT NULL,
            subject TEXT,
            body TEXT,
            headers TEXT,
            status TEXT DEFAULT 'pending',
            attempts INTEGER DEFAULT 0,
            last_attempt TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        )");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function getDriver() {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    // Return a SQL expression for "now" compatible with driver
    public static function nowSql() {
        return self::getInstance()->getDriver() === 'sqlite' ? "datetime('now', 'localtime')" : 'NOW()';
    }
}

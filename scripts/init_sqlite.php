<?php
// scripts/init_sqlite.php
// Creates schema for SQLite client installations (idempotent)
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->exec("PRAGMA foreign_keys = ON;");

    // Tenants
    $db->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subdomain TEXT NOT NULL UNIQUE,
        business_name TEXT NOT NULL,
        db_name TEXT,
        status TEXT DEFAULT 'active',
        sync_token TEXT,
        allowed_host TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now'))
    )");

    // Users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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
        created_at TEXT DEFAULT (datetime('now'))
    )");

    // Branches
    $db->exec("CREATE TABLE IF NOT EXISTS branches (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        address TEXT,
        phone TEXT,
        cuit TEXT,
        fiscal_data TEXT,
        license_expiry TEXT,
        license_pos_expiry TEXT,
        license_mp_expiry TEXT,
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
        status INTEGER DEFAULT 1
    )");

    // Machines
    $db->exec("CREATE TABLE IF NOT EXISTS machines (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        price REAL NOT NULL,
        branch_id TEXT,
        active INTEGER DEFAULT 1
    )");

    // Sales
    $db->exec("CREATE TABLE IF NOT EXISTS sales (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        branch_id TEXT,
        machine_id TEXT NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now')),
        sync_status INTEGER DEFAULT 0,
        last_synced_at TEXT NULL
    )");

    // Licenses
    $db->exec("CREATE TABLE IF NOT EXISTS licenses (
        license_key TEXT PRIMARY KEY,
        branch_id TEXT NOT NULL,
        status TEXT DEFAULT 'inactive',
        device_id TEXT
    )");

    // Sync Queue
    $db->exec("CREATE TABLE IF NOT EXISTS sync_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        resource_type TEXT NOT NULL,
        resource_uuid TEXT NOT NULL,
        payload TEXT NOT NULL,
        attempts INTEGER DEFAULT 0,
        locked INTEGER DEFAULT 0,
        locked_at TEXT,
        next_attempt TEXT DEFAULT (datetime('now')),
        created_at TEXT DEFAULT (datetime('now'))
    )");

    // Sync Logs
    $db->exec("CREATE TABLE IF NOT EXISTS sync_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        last_sync TEXT,
        details TEXT,
        status TEXT,
        attempts INTEGER DEFAULT 0,
        meta TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    // Settings
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");

    // Plans and subscriptions
    $db->exec("CREATE TABLE IF NOT EXISTS plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE,
        name TEXT,
        price REAL,
        period TEXT,
        features TEXT,
        active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenant_id INTEGER,
        plan_id INTEGER NOT NULL,
        external_id TEXT,
        status TEXT DEFAULT 'pending',
        amount REAL,
        period TEXT,
        started_at TEXT,
        ended_at TEXT,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    // Outbox
    $db->exec("CREATE TABLE IF NOT EXISTS outbox_emails (
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

    echo "SQLite schema initialized.\n";
} catch (PDOException $e) {
    echo "SQLite init error: " . $e->getMessage() . "\n";
    exit(1);
}

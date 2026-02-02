<?php
// src/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Incluir la configuración si no está definida
        if (!defined('DB_HOST') && !defined('DB_DRIVER')) {
            require_once __DIR__ . '/../config/db.php';
        }

        // If config/db.php created a $pdo variable, reuse it (supports sqlite)
        if (isset($pdo) && $pdo instanceof PDO) {
            $this->pdo = $pdo;
            return;
        }

        try {
            // Fallback: build PDO from constants (mostly for MySQL)
            if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
                $sqliteFile = defined('DB_SQLITE_FILE') ? DB_SQLITE_FILE : __DIR__ . '/../data/data.sqlite';
                if (!is_dir(dirname($sqliteFile))) mkdir(dirname($sqliteFile), 0755, true);
                $this->pdo = new PDO('sqlite:' . $sqliteFile);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            } else {
                $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec("SET NAMES 'utf8mb4'");
            }
        } catch (PDOException $e) {
            die("Error de conexión en Clase Database: " . $e->getMessage());
        }
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
        return self::getInstance()->getDriver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}

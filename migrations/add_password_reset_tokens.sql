-- Migration: Add password_reset_tokens table
-- Run this once on your MySQL server via phpMyAdmin

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    VARCHAR(64) NOT NULL,
    `token`      VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT DEFAULT 0,
    INDEX(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: 004_outbox.sql
-- Adds outbox_emails table for queued outbound emails

CREATE TABLE IF NOT EXISTS outbox_emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `to` VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  headers TEXT NULL,
  status ENUM('pending','sent','failed') DEFAULT 'pending',
  attempts INT DEFAULT 0,
  last_attempt DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

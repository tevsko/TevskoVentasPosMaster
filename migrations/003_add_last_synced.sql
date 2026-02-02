-- Migration: 003_add_last_synced.sql
-- Adds last_synced_at column to sales

ALTER TABLE sales
  ADD COLUMN last_synced_at DATETIME NULL;

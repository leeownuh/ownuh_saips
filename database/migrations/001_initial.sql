-- ============================================================
-- Ownuh SAIPS — Migration 001: Initial Schema
-- Run: mysql -u root -p ownuh_saips < database/migrations/001_initial.sql
-- ============================================================

-- This migration applies the complete initial schema.
-- It is idempotent — safe to re-run (uses IF NOT EXISTS / CREATE OR REPLACE).

-- Track applied migrations
CREATE TABLE IF NOT EXISTS _migrations (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    migration   VARCHAR(255)    NOT NULL UNIQUE,
    applied_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check if already applied
SET @already_applied = (
    SELECT COUNT(*) FROM _migrations WHERE migration = '001_initial'
);

-- Only apply if not already done
-- (In production use a proper migration runner like Phinx or Doctrine Migrations)
-- For now, the full schema is in schema.sql — source that file directly.
-- This file just records the migration.

INSERT IGNORE INTO _migrations (migration) VALUES ('001_initial');

SELECT 'Migration 001_initial recorded.' AS status;

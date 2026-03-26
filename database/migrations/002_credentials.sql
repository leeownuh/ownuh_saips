-- ============================================================
-- Ownuh SAIPS — Credentials Database Schema
-- Separate database for password storage (SRS §2.3)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ownuh_credentials
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ownuh_credentials;

-- Credentials table (isolated from main application database)
CREATE TABLE IF NOT EXISTS credentials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         CHAR(36)       NOT NULL,
    password_hash   VARCHAR(72)    NOT NULL,  -- bcrypt hash
    bcrypt_cost     TINYINT UNSIGNED NOT NULL DEFAULT 12,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_id (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Isolated password storage. AES-256 encrypted at rest in production.';

-- Password history for reuse prevention (SRS §2.2)
CREATE TABLE IF NOT EXISTS password_history (
    id              CHAR(36)       NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id         CHAR(36)       NOT NULL,
    password_hash   VARCHAR(72)    NOT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Last 12 password hashes for reuse prevention (SRS §2.2).';
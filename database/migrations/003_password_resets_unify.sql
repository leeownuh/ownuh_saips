-- ============================================================
-- Ownuh SAIPS - Unify password reset storage
-- Canonical table: password_resets
-- ============================================================

USE ownuh_saips;

CREATE TABLE IF NOT EXISTS password_resets (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id                 CHAR(36)        NOT NULL,
    token_hash              VARCHAR(64)     NOT NULL,
    created_by              CHAR(36)        NULL DEFAULT NULL,
    reason                  VARCHAR(255)    NULL DEFAULT NULL,
    requested_ip            VARCHAR(45)     NULL DEFAULT NULL,
    requested_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              DATETIME        NOT NULL,
    used_at                 DATETIME        NULL,

    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_user_expires (user_id, expires_at)
);

ALTER TABLE password_resets
    ADD COLUMN IF NOT EXISTS created_by CHAR(36) NULL DEFAULT NULL AFTER token_hash,
    ADD COLUMN IF NOT EXISTS reason VARCHAR(255) NULL DEFAULT NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS requested_ip VARCHAR(45) NULL DEFAULT NULL AFTER reason,
    ADD COLUMN IF NOT EXISTS requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER requested_ip;

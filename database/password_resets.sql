-- ============================================================
-- Ownuh SAIPS - Password Resets Table
-- Canonical reset registry used by self-service and admin resets
-- ============================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id                 CHAR(36)        NOT NULL,
    token_hash              VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hash of the reset token',
    created_by              CHAR(36)        NULL DEFAULT NULL COMMENT 'Admin user who initiated the reset',
    reason                  VARCHAR(255)    NULL DEFAULT NULL,
    requested_ip            VARCHAR(45)     NULL DEFAULT NULL,
    requested_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              DATETIME        NOT NULL,
    used_at                 DATETIME        NULL,

    FOREIGN KEY fk_pr_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_pr_admin (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset registry for self-service and admin-initiated resets.';

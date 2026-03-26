-- ────────────────────────────────────────────────────────────────────────────────────────────────────────
-- PASSWORD RESET TOKENS
-- ────────────────────────────────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    token_hash              VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hash of the reset token',
    created_by              CHAR(36)        NOT NULL COMMENT 'Admin user who initiated the reset',
    expires_at              DATETIME        NOT NULL,
    used_at                 DATETIME        NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY fk_prt_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_prt_admin (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Admin-initiated password reset tokens (SRS §2.3).';
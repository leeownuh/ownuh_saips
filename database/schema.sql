-- ============================================================
-- Ownuh SAIPS — Full Database Schema
-- Version: 1.0.0 | Engine: MySQL 8.0+ / MariaDB 10.11+
-- Run this against ownuh_saips database as root
-- See DATABASE.md for architecture notes
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ─── USERS ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    display_name            VARCHAR(120)    NOT NULL,
    email                   VARCHAR(254)    NOT NULL,
    role                    ENUM('superadmin','admin','manager','user') NOT NULL DEFAULT 'user',
    status                  ENUM('active','locked','suspended','pending') NOT NULL DEFAULT 'pending',
    mfa_enrolled            TINYINT(1)      NOT NULL DEFAULT 0,
    mfa_factor              ENUM('fido2','totp','email_otp','sms','none') NOT NULL DEFAULT 'none',
    mfa_bypass_token        VARCHAR(128)    NULL DEFAULT NULL,
    mfa_bypass_expiry       DATETIME        NULL DEFAULT NULL,
    failed_attempts         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_failed_at          DATETIME        NULL DEFAULT NULL,
    last_login_at           DATETIME        NULL DEFAULT NULL,
    last_login_ip           VARCHAR(45)     NULL DEFAULT NULL,
    last_login_country      CHAR(2)         NULL DEFAULT NULL,
    password_changed_at     DATETIME        NULL DEFAULT NULL,
    email_verified          TINYINT(1)      NOT NULL DEFAULT 0,
    email_verified_at       DATETIME        NULL DEFAULT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME        NULL DEFAULT NULL,

    UNIQUE KEY uq_email (email),
    INDEX idx_status (status),
    INDEX idx_role (role),
    INDEX idx_deleted (deleted_at),
    INDEX idx_mfa_enrolled (mfa_enrolled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User accounts. Passwords stored separately in ownuh_credentials.';

-- ─── SESSIONS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    refresh_token_hash      VARCHAR(128)    NOT NULL,
    ip_address              VARCHAR(45)     NOT NULL,
    user_agent              TEXT            NULL,
    device_fingerprint      VARCHAR(128)    NULL DEFAULT NULL,
    mfa_method              VARCHAR(20)     NULL DEFAULT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              DATETIME        NOT NULL,
    last_used_at            DATETIME        NULL DEFAULT NULL,
    invalidated_at          DATETIME        NULL DEFAULT NULL,
    invalidated_by          CHAR(36)        NULL DEFAULT NULL,
    invalidation_reason     VARCHAR(255)    NULL DEFAULT NULL,

    UNIQUE KEY uq_token_hash (refresh_token_hash),
    FOREIGN KEY fk_sessions_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_invalidated (invalidated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='JWT refresh token registry. Access tokens are stateless.';

-- ─── AUDIT LOG ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_code              VARCHAR(20)     NOT NULL,
    event_name              VARCHAR(100)    NOT NULL,
    user_id                 CHAR(36)        NULL DEFAULT NULL,
    source_ip               VARCHAR(45)     NULL DEFAULT NULL,
    user_agent              TEXT            NULL,
    country_code            CHAR(2)         NULL DEFAULT NULL,
    device_fingerprint      VARCHAR(128)    NULL DEFAULT NULL,
    mfa_method              VARCHAR(20)     NULL DEFAULT NULL,
    risk_score              TINYINT UNSIGNED NULL DEFAULT NULL,
    details                 JSON            NULL,
    admin_id                CHAR(36)        NULL DEFAULT NULL,
    target_user_id          CHAR(36)        NULL DEFAULT NULL,
    created_at              DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    entry_hash              CHAR(64)        NOT NULL,
    prev_hash               CHAR(64)        NULL DEFAULT NULL,

    INDEX idx_event_code (event_code),
    INDEX idx_user_id (user_id),
    INDEX idx_source_ip (source_ip),
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id),
    INDEX idx_target_user (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tamper-evident SHA-256 chained audit log. INSERT only — no UPDATE/DELETE grants.';

-- ─── MFA — TOTP SECRETS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mfa_totp_secrets (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    secret_encrypted        TEXT            NOT NULL,
    algorithm               VARCHAR(10)     NOT NULL DEFAULT 'SHA1',
    digits                  TINYINT UNSIGNED NOT NULL DEFAULT 6,
    period                  TINYINT UNSIGNED NOT NULL DEFAULT 30,
    enrolled_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at            DATETIME        NULL DEFAULT NULL,

    UNIQUE KEY uq_user_id (user_id),
    FOREIGN KEY fk_totp_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='AES-256-GCM encrypted TOTP secrets (RFC 6238).';

-- ─── MFA — BACKUP CODES ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mfa_backup_codes (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    code_hash               VARCHAR(72)     NOT NULL,
    used_at                 DATETIME        NULL DEFAULT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY fk_backup_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='10 single-use bcrypt-hashed backup codes per user.';

-- ─── MFA — FIDO2 / WEBAUTHN ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mfa_fido2_credentials (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    credential_id           VARBINARY(1024) NOT NULL,
    public_key              TEXT            NOT NULL,
    sign_count              INT UNSIGNED    NOT NULL DEFAULT 0,
    device_description      VARCHAR(255)    NULL DEFAULT NULL,
    aaguid                  VARCHAR(36)     NULL DEFAULT NULL,
    enrolled_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at            DATETIME        NULL DEFAULT NULL,

    UNIQUE KEY uq_credential_id (credential_id(255)),
    FOREIGN KEY fk_fido2_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FIDO2/WebAuthn hardware key registrations. Required for Admin+ roles.';

-- ─── LOGIN ATTEMPTS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username                VARCHAR(254)    NULL DEFAULT NULL,
    ip_address              VARCHAR(45)     NOT NULL,
    success                 TINYINT(1)      NOT NULL DEFAULT 0,
    failure_reason          VARCHAR(100)    NULL DEFAULT NULL,
    risk_score              TINYINT UNSIGNED NULL DEFAULT NULL,
    attempted_at            DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_user_time (username(100), attempted_at),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rolling brute-force detection window. Auto-purged after 25 hours.';

-- ─── BLOCKED IPS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blocked_ips (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    ip_address              VARCHAR(45)     NOT NULL,
    block_type              ENUM('brute_force','geo_block','threat_feed','tor_exit','manual') NOT NULL,
    trigger_rule            VARCHAR(255)    NULL DEFAULT NULL,
    country_code            CHAR(2)         NULL DEFAULT NULL,
    threat_feed             VARCHAR(50)     NULL DEFAULT NULL,
    blocked_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at              DATETIME        NULL DEFAULT NULL,
    unblocked_at            DATETIME        NULL DEFAULT NULL,
    unblocked_by            CHAR(36)        NULL DEFAULT NULL,

    INDEX idx_ip (ip_address),
    INDEX idx_expires (expires_at),
    INDEX idx_active (unblocked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── GEO RULES ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS geo_rules (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    country_code            CHAR(2)         NOT NULL,
    country_name            VARCHAR(100)    NOT NULL,
    rule_type               ENUM('deny','allow') NOT NULL DEFAULT 'deny',
    created_by              CHAR(36)        NOT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_country (country_code),
    FOREIGN KEY fk_geo_user (created_by) REFERENCES users(id),
    INDEX idx_type (rule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── INCIDENTS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS incidents (
    id                          CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    incident_ref                VARCHAR(20)     NOT NULL,
    severity                    ENUM('sev1','sev2','sev3','sev4') NOT NULL,
    status                      ENUM('open','in_progress','under_review','resolved','closed') NOT NULL DEFAULT 'open',
    trigger_summary             TEXT            NOT NULL,
    affected_user_id            CHAR(36)        NULL DEFAULT NULL,
    source_ip                   VARCHAR(45)     NULL DEFAULT NULL,
    detected_at                 DATETIME        NOT NULL,
    acknowledged_at             DATETIME        NULL DEFAULT NULL,
    resolved_at                 DATETIME        NULL DEFAULT NULL,
    assigned_to                 CHAR(36)        NULL DEFAULT NULL,
    reported_by                 CHAR(36)        NOT NULL,
    description                 TEXT            NOT NULL,
    actions_taken               TEXT            NULL DEFAULT NULL,
    personal_data_involved      TINYINT(1)      NOT NULL DEFAULT 0,
    gdpr_notification_required  TINYINT(1)      NOT NULL DEFAULT 0,
    gdpr_notified_at            DATETIME        NULL DEFAULT NULL,
    related_audit_entries       JSON            NULL DEFAULT NULL,
    created_at                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_ref (incident_ref),
    FOREIGN KEY fk_incident_reporter (reported_by) REFERENCES users(id),
    FOREIGN KEY fk_incident_assignee (assigned_to) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_detected (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── RATE LIMIT CONFIG ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limit_config (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    endpoint                VARCHAR(100)    NOT NULL,
    requests_limit          SMALLINT UNSIGNED NOT NULL,
    window_seconds          INT UNSIGNED    NOT NULL,
    scope                   ENUM('per_ip','per_user','per_token') NOT NULL DEFAULT 'per_ip',
    action_on_breach        ENUM('block_temp','block_perm','soft_lock','rate_429') NOT NULL DEFAULT 'rate_429',
    is_active               TINYINT(1)      NOT NULL DEFAULT 1,
    updated_by              CHAR(36)        NULL DEFAULT NULL,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PASSWORD HISTORY ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_history (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    password_hash           VARCHAR(72)     NOT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY fk_pwhistory_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Last 12 password hashes for reuse prevention (SRS §2.2).';


-- ────────────────────────────────────────────────────────────────────────────────────────────────────────
-- PASSWORD RESETS
-- ────────────────────────────────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
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
  COMMENT='Admin-initiated password reset tokens (SRS §2.3).';

-- ─── DEFAULT RATE LIMIT RULES ─────────────────────────────────────────────────
INSERT INTO rate_limit_config (endpoint, requests_limit, window_seconds, scope, action_on_breach) VALUES
('/auth/login',         60,  60,   'per_ip',    'block_temp'),
('/auth/token',         60,  60,   'per_ip',    'block_temp'),
('/auth/mfa/verify',    5,   900,  'per_user',  'soft_lock'),
('/auth/mfa/email-otp', 5,   3600, 'per_user',  'block_temp'),
('/api/*',              300, 60,   'per_token', 'rate_429');

-- ─── DEFAULT GEO DENY RULES ───────────────────────────────────────────────────
-- Populated by admin — no defaults inserted here.
-- See ips-geo-block.html for the admin interface.

SET FOREIGN_KEY_CHECKS = 1;

-- ─── VIEWS ────────────────────────────────────────────────────────────────────

-- Active sessions view (non-invalidated, non-expired)
CREATE OR REPLACE VIEW v_active_sessions AS
SELECT
    s.id,
    s.user_id,
    u.display_name,
    u.email,
    u.role,
    s.ip_address,
    s.mfa_method,
    s.created_at,
    s.expires_at,
    s.last_used_at,
    TIMESTAMPDIFF(MINUTE, s.last_used_at, NOW()) AS idle_minutes
FROM sessions s
JOIN users u ON u.id = s.user_id
WHERE s.invalidated_at IS NULL
  AND s.expires_at > NOW();

-- Failed login attempts in last 15 minutes per username (for brute-force detection)
CREATE OR REPLACE VIEW v_recent_failures_per_user AS
SELECT
    username,
    COUNT(*) AS failure_count,
    MIN(attempted_at) AS window_start,
    MAX(attempted_at) AS last_attempt
FROM login_attempts
WHERE success = 0
  AND attempted_at > NOW() - INTERVAL 15 MINUTE
GROUP BY username;

-- Failed login attempts in last 10 minutes per IP
CREATE OR REPLACE VIEW v_recent_failures_per_ip AS
SELECT
    ip_address,
    COUNT(*) AS failure_count,
    MIN(attempted_at) AS window_start,
    MAX(attempted_at) AS last_attempt
FROM login_attempts
WHERE success = 0
  AND attempted_at > NOW() - INTERVAL 10 MINUTE
GROUP BY ip_address;

-- ─── STORED PROCEDURES ────────────────────────────────────────────────────────

DELIMITER //

-- Insert an audit log entry with SHA-256 chain hash
CREATE PROCEDURE sp_insert_audit_log(
    IN p_event_code     VARCHAR(20),
    IN p_event_name     VARCHAR(100),
    IN p_user_id        CHAR(36),
    IN p_source_ip      VARCHAR(45),
    IN p_user_agent     TEXT,
    IN p_country_code   CHAR(2),
    IN p_mfa_method     VARCHAR(20),
    IN p_risk_score     TINYINT UNSIGNED,
    IN p_details        JSON,
    IN p_admin_id       CHAR(36),
    IN p_target_user_id CHAR(36)
)
BEGIN
    DECLARE v_prev_hash     CHAR(64) DEFAULT NULL;
    DECLARE v_entry_hash    CHAR(64);
    DECLARE v_created_at    VARCHAR(30);

    -- Get previous entry hash for chain
    SELECT entry_hash INTO v_prev_hash
    FROM audit_log
    ORDER BY id DESC
    LIMIT 1;

    SET v_created_at = DATE_FORMAT(NOW(3), '%Y-%m-%d %H:%i:%s.%f');

    -- Compute SHA-256 chain hash
    SET v_entry_hash = SHA2(
        CONCAT_WS('|',
            COALESCE(v_prev_hash, 'GENESIS'),
            p_event_code,
            COALESCE(p_user_id, ''),
            v_created_at,
            COALESCE(CAST(p_details AS CHAR), '')
        ),
        256
    );

    INSERT INTO audit_log (
        event_code, event_name, user_id, source_ip, user_agent,
        country_code, mfa_method, risk_score, details,
        admin_id, target_user_id, entry_hash, prev_hash
    ) VALUES (
        p_event_code, p_event_name, p_user_id, p_source_ip, p_user_agent,
        p_country_code, p_mfa_method, p_risk_score, p_details,
        p_admin_id, p_target_user_id, v_entry_hash, v_prev_hash
    );
END //

-- Purge expired login attempts (run via cron every hour)
CREATE PROCEDURE sp_purge_login_attempts()
BEGIN
    DELETE FROM login_attempts
    WHERE attempted_at < NOW() - INTERVAL 25 HOUR;

    SELECT ROW_COUNT() AS rows_purged;
END //

-- Purge expired sessions (run via cron every hour)
CREATE PROCEDURE sp_purge_expired_sessions()
BEGIN
    DELETE FROM sessions
    WHERE expires_at < NOW() - INTERVAL 30 DAY
      AND invalidated_at IS NOT NULL;

    SELECT ROW_COUNT() AS rows_purged;
END //

-- Check if IP is currently blocked
CREATE PROCEDURE sp_is_ip_blocked(IN p_ip VARCHAR(45), OUT p_blocked TINYINT(1))
BEGIN
    SELECT COUNT(*) > 0 INTO p_blocked
    FROM blocked_ips
    WHERE ip_address = p_ip
      AND unblocked_at IS NULL
      AND (expires_at IS NULL OR expires_at > NOW());
END //

DELIMITER ;

-- ─── ALERT RULES ─────────────────────────────────────────────────────────────
-- Used by settings-alert-rules.php — SRS §5.2 Webhook / Alert Dispatcher
CREATE TABLE IF NOT EXISTS alert_rules (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    rule_name               VARCHAR(120)    NOT NULL,
    event_type              VARCHAR(20)     NOT NULL
                                COMMENT 'Audit event code, e.g. AUTH-002, IPS-001',
    channel                 ENUM('email','slack','webhook','sms') NOT NULL DEFAULT 'email',
    threshold_count         SMALLINT UNSIGNED NOT NULL DEFAULT 1
                                COMMENT 'Number of matching events to trigger alert',
    window_minutes          SMALLINT UNSIGNED NOT NULL DEFAULT 5
                                COMMENT 'Rolling window in which threshold is evaluated',
    destination             VARCHAR(500)    NOT NULL
                                COMMENT 'Email address, Slack webhook URL, or HTTP endpoint',
    is_active               TINYINT(1)      NOT NULL DEFAULT 1,
    last_triggered_at       DATETIME        NULL DEFAULT NULL,
    created_by              CHAR(36)        NOT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_event_type  (event_type),
    INDEX idx_active      (is_active),
    FOREIGN KEY fk_alert_creator (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alert rules for webhook/email/Slack notifications on security events.';

-- ─── SYSTEM SETTINGS ─────────────────────────────────────────────────────────
-- Key-value store for runtime configuration (MFA policy, thresholds, etc.)
-- Used by settings-mfa.php, settings-policy.php — SRS §7.1
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key             VARCHAR(100)    NOT NULL PRIMARY KEY,
    setting_value           TEXT            NOT NULL,
    updated_by              CHAR(36)        NULL DEFAULT NULL,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Runtime system configuration. Keys namespaced e.g. mfa.admin_required.';

-- ─── CREDENTIALS ALIAS (main DB) ─────────────────────────────────────────────
-- register.php falls back to this table when ownuh_credentials DB is unreachable.
-- Mirrors the structure of ownuh_credentials.credentials for portability.
CREATE TABLE IF NOT EXISTS user_credentials (
    id                      CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id                 CHAR(36)        NOT NULL,
    password_hash           VARCHAR(72)     NOT NULL COMMENT 'bcrypt hash, cost 12+',
    bcrypt_cost             TINYINT UNSIGNED NOT NULL DEFAULT 12,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_id (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY fk_ucred_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fallback credential store in main DB. Primary store is ownuh_credentials DB (SRS §2.3).';

-- Default seed data for alert_rules
INSERT IGNORE INTO alert_rules (id, rule_name, event_type, channel, threshold_count, window_minutes, destination, created_by)
SELECT
    UUID(),
    'Brute-Force Alert',
    'AUTH-002',
    'email',
    5,
    15,
    'security@ownuh.local',
    id
FROM users WHERE role = 'superadmin' LIMIT 1;

INSERT IGNORE INTO alert_rules (id, rule_name, event_type, channel, threshold_count, window_minutes, destination, created_by)
SELECT
    UUID(),
    'IP Block Notification',
    'IPS-001',
    'email',
    1,
    5,
    'security@ownuh.local',
    id
FROM users WHERE role = 'superadmin' LIMIT 1;

INSERT IGNORE INTO alert_rules (id, rule_name, event_type, channel, threshold_count, window_minutes, destination, created_by)
SELECT
    UUID(),
    'Incident Escalation Alert',
    'INC-002',
    'email',
    1,
    1,
    'security@ownuh.local',
    id
FROM users WHERE role = 'superadmin' LIMIT 1;

-- Default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('mfa.admin_required',  '1'),
    ('mfa.user_required',   '0'),
    ('mfa.allow_totp',      '1'),
    ('mfa.allow_email_otp', '1'),
    ('mfa.allow_sms',       '0'),
    ('mfa.allow_fido2',     '1'),
    ('mfa.totp_window',     '1'),
    ('mfa.email_otp_ttl',   '600'),
    ('mfa.email_otp_rate',  '5');

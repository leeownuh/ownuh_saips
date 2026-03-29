-- ============================================================
-- Ownuh SAIPS — Stored Procedure Fix Migration
-- ============================================================
-- Run this against ownuh_saips in phpMyAdmin (or CLI) if you
-- get the error:
--   PROCEDURE ownuh_saips.sp_insert_audit_log does not exist
--
-- This file is safe to run multiple times (DROP IF EXISTS).
-- It adds the audit_log region column if missing, but does not modify rows.
-- ============================================================

USE ownuh_saips;

-- Ensure audit_log has region column for geo lookups
ALTER TABLE audit_log
    ADD COLUMN IF NOT EXISTS region VARCHAR(100) NULL DEFAULT NULL
    AFTER country_code;

-- ── Drop existing procedures so we can recreate cleanly ───────────────────────
DROP PROCEDURE IF EXISTS sp_insert_audit_log;
DROP PROCEDURE IF EXISTS sp_purge_login_attempts;
DROP PROCEDURE IF EXISTS sp_purge_expired_sessions;
DROP PROCEDURE IF EXISTS sp_is_ip_blocked;

DELIMITER //

-- ─────────────────────────────────────────────────────────────────────────────
-- sp_insert_audit_log
-- Writes a tamper-evident, SHA-256 chained audit log entry.
-- Called by AuditMiddleware::log() via PDO CALL.
-- SRS §4 — Audit Logging
-- ─────────────────────────────────────────────────────────────────────────────
CREATE PROCEDURE sp_insert_audit_log(
    IN p_event_code     VARCHAR(20),
    IN p_event_name     VARCHAR(100),
    IN p_user_id        CHAR(36),
    IN p_source_ip      VARCHAR(45),
    IN p_user_agent     TEXT,
    IN p_country_code   CHAR(2),
    IN p_region         VARCHAR(100),
    IN p_mfa_method     VARCHAR(20),
    IN p_risk_score     TINYINT UNSIGNED,
    IN p_details        JSON,
    IN p_admin_id       CHAR(36),
    IN p_target_user_id CHAR(36)
)
BEGIN
    DECLARE v_prev_hash  CHAR(64) DEFAULT NULL;
    DECLARE v_entry_hash CHAR(64);
    DECLARE v_created_at VARCHAR(30);

    -- Fetch most recent entry hash to form the chain
    SELECT entry_hash INTO v_prev_hash
    FROM audit_log
    ORDER BY id DESC
    LIMIT 1;

    SET v_created_at = DATE_FORMAT(NOW(3), '%Y-%m-%d %H:%i:%s.%f');

    -- SHA-256 chain: each entry commits to the previous one
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
        country_code, region, mfa_method, risk_score, details,
        admin_id, target_user_id, entry_hash, prev_hash
    ) VALUES (
        p_event_code, p_event_name, p_user_id, p_source_ip, p_user_agent,
        p_country_code, p_region, p_mfa_method, p_risk_score, p_details,
        p_admin_id, p_target_user_id, v_entry_hash, v_prev_hash
    );
END //

-- ─────────────────────────────────────────────────────────────────────────────
-- sp_purge_login_attempts
-- Deletes login_attempts older than 25 hours. Run hourly via cron.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE PROCEDURE sp_purge_login_attempts()
BEGIN
    DELETE FROM login_attempts
    WHERE attempted_at < NOW() - INTERVAL 25 HOUR;
    SELECT ROW_COUNT() AS rows_purged;
END //

-- ─────────────────────────────────────────────────────────────────────────────
-- sp_purge_expired_sessions
-- Deletes old invalidated sessions. Run hourly via cron.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE PROCEDURE sp_purge_expired_sessions()
BEGIN
    DELETE FROM sessions
    WHERE expires_at < NOW() - INTERVAL 30 DAY
      AND invalidated_at IS NOT NULL;
    SELECT ROW_COUNT() AS rows_purged;
END //

-- ─────────────────────────────────────────────────────────────────────────────
-- sp_is_ip_blocked
-- Returns 1 if the given IP is currently actively blocked, 0 otherwise.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE PROCEDURE sp_is_ip_blocked(IN p_ip VARCHAR(45), OUT p_blocked TINYINT(1))
BEGIN
    SELECT COUNT(*) > 0 INTO p_blocked
    FROM blocked_ips
    WHERE ip_address  = p_ip
      AND unblocked_at IS NULL
      AND (expires_at IS NULL OR expires_at > NOW());
END //

DELIMITER ;

-- ── Verify all four procedures now exist ──────────────────────────────────────
SELECT ROUTINE_NAME, ROUTINE_TYPE, CREATED, LAST_ALTERED
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_TYPE   = 'PROCEDURE'
ORDER BY ROUTINE_NAME;

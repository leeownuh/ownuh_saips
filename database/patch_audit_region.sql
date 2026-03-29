-- ============================================================
-- Ownuh SAIPS — Audit Region Migration
-- Adds audit_log.region and updates sp_insert_audit_log
-- Safe to run multiple times.
-- ============================================================

USE ownuh_saips;

ALTER TABLE audit_log
    ADD COLUMN IF NOT EXISTS region VARCHAR(100) NULL DEFAULT NULL
    AFTER country_code;

DROP PROCEDURE IF EXISTS sp_insert_audit_log;

DELIMITER //

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
    DECLARE v_prev_hash     CHAR(64) DEFAULT NULL;
    DECLARE v_entry_hash    CHAR(64);
    DECLARE v_created_at    VARCHAR(30);

    SELECT entry_hash INTO v_prev_hash
    FROM audit_log
    ORDER BY id DESC
    LIMIT 1;

    SET v_created_at = DATE_FORMAT(NOW(3), '%Y-%m-%d %H:%i:%s.%f');

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

DELIMITER ;

-- ============================================================
-- Ownuh SAIPS â€” Development Seed Data
-- DO NOT USE IN PRODUCTION
-- ============================================================

USE ownuh_saips;

-- Default admin user (password: Admin@SAIPS2025! - CHANGE IMMEDIATELY)
INSERT IGNORE INTO users (id, display_name, email, role, status, mfa_enrolled, mfa_factor, email_verified, email_verified_at) VALUES
('usr-001-0000-0000-0000-000000000001', 'Sophia Johnson',  'sophia.johnson@ownuh-saips.com',  'superadmin', 'active',  1, 'fido2',     1, NOW()),
('usr-002-0000-0000-0000-000000000002', 'Marcus Chen',     'marcus.chen@ownuh-saips.com',     'admin',      'active',  1, 'totp',      1, NOW()),
('usr-003-0000-0000-0000-000000000003', 'Priya Patel',     'priya.patel@ownuh-saips.com',     'manager',    'locked',  1, 'totp',      1, NOW()),
('usr-004-0000-0000-0000-000000000004', 'James Harris',    'james.harris@ownuh-saips.com',    'user',       'active',  1, 'email_otp', 1, NOW()),
('usr-005-0000-0000-0000-000000000005', 'Alex Rivera',     'alex.rivera@ownuh-saips.com',     'user',       'pending', 0, 'none',      0, NULL);

UPDATE users 
SET failed_attempts = 10, last_failed_at = NOW() - INTERVAL 1 HOUR
WHERE id = 'usr-003-0000-0000-0000-000000000003';

INSERT IGNORE INTO geo_rules (country_code, country_name, rule_type, created_by) VALUES
('RU', 'Russia',       'deny', 'usr-001-0000-0000-0000-000000000001'),
('KP', 'North Korea',  'deny', 'usr-001-0000-0000-0000-000000000001'),
('IR', 'Iran',         'deny', 'usr-002-0000-0000-0000-000000000002');

INSERT IGNORE INTO blocked_ips (ip_address, block_type, trigger_rule, country_code, blocked_at, expires_at) VALUES
('185.220.101.47', 'tor_exit',     '20 failures / 10 min',  'NL', NOW() - INTERVAL 5 MINUTE,  NOW() + INTERVAL 55 MINUTE),
('91.108.4.200',   'geo_block',    'Country deny-list: RU', 'RU', NOW() - INTERVAL 3 HOUR,    NULL),
('198.54.117.212', 'brute_force',  'Per-IP threshold',      'US', NOW() - INTERVAL 10 MINUTE, NOW() + INTERVAL 50 MINUTE);

INSERT IGNORE INTO incidents (incident_ref, severity, status, trigger_summary, source_ip, detected_at, assigned_to, reported_by, description, personal_data_involved) VALUES
('INC-2025-021', 'sev2', 'in_progress', 'Successful brute-force on priya.patel', '185.220.101.47', NOW() - INTERVAL 5 MINUTE, 'usr-002-0000-0000-0000-000000000002', 'usr-001-0000-0000-0000-000000000001', 'Automated brute-force attack detected.', 0),
('INC-2025-020', 'sev2', 'in_progress', 'MFA bypass token issued outside standard process', NULL, NOW() - INTERVAL 2 HOUR, 'usr-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', 'MFA bypass issued for alex.rivera.', 0),
('INC-2025-019', 'sev3', 'under_review','Admin login from new device/location', NULL, NOW() - INTERVAL 1 DAY, 'usr-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', 'marcus.chen login from new country.', 0);

INSERT IGNORE INTO rate_limit_config (endpoint, requests_limit, window_seconds, scope, action_on_breach) VALUES
('/auth/login',         60,  60,   'per_ip',    'block_temp'),
('/auth/token',         60,  60,   'per_ip',    'block_temp'),
('/auth/mfa/verify',    5,   900,  'per_user',  'soft_lock'),
('/auth/mfa/email-otp', 5,   3600, 'per_user',  'block_temp'),
('/api/*',              300, 60,   'per_token', 'rate_429');

CALL sp_insert_audit_log('AUTH-001', 'Successful Login', 'usr-001-0000-0000-0000-000000000001', '203.0.113.10', 'Mozilla/5.0', 'AU', 'fido2', 15, '{"session_id":"ses_1"}', NULL, NULL);

-- ================= CREDENTIALS =================

USE ownuh_credentials;

INSERT IGNORE INTO credentials (user_id, password_hash, bcrypt_cost) VALUES
('usr-001-0000-0000-0000-000000000001', '$2y$12$PLACEHOLDER_RUN_SETUP', 12),
('usr-002-0000-0000-0000-000000000002', '$2y$12$PLACEHOLDER_RUN_SETUP', 12),
('usr-003-0000-0000-0000-000000000003', '$2y$12$PLACEHOLDER_RUN_SETUP', 12);
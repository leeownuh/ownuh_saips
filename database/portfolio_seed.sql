-- ============================================================
-- Ownuh SAIPS - Portfolio Demo Seed
-- Purpose: Recruiter-facing demo data for a polished walkthrough
-- Safe to share: all data is fictional
-- Default password for all seeded accounts: Admin@SAIPS2025!
-- ============================================================

USE ownuh_saips;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_log;
TRUNCATE TABLE sessions;
TRUNCATE TABLE incidents;
TRUNCATE TABLE blocked_ips;
TRUNCATE TABLE geo_rules;
TRUNCATE TABLE mfa_backup_codes;
TRUNCATE TABLE mfa_totp_secrets;
TRUNCATE TABLE mfa_fido2_credentials;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE alert_rules;
TRUNCATE TABLE rate_limit_config;
TRUNCATE TABLE system_settings;
TRUNCATE TABLE password_history;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE user_credentials;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- USERS
-- ============================================================

INSERT INTO users (
    id, display_name, email, role, status, mfa_enrolled, mfa_factor,
    failed_attempts, last_failed_at, last_login_at, last_login_ip, last_login_country,
    password_changed_at, email_verified, email_verified_at, created_at, updated_at
) VALUES
('usr-001-0000-0000-0000-000000000001', 'Sophia Johnson',  'sophia.johnson@ownuh-saips.com',  'superadmin', 'active',    1, 'fido2',     0, NULL,                         NOW() - INTERVAL 22 MINUTE, '203.0.113.10',  'SG', NOW() - INTERVAL 14 DAY, 1, NOW() - INTERVAL 120 DAY, NOW() - INTERVAL 180 DAY, NOW() - INTERVAL 22 MINUTE),
('usr-002-0000-0000-0000-000000000002', 'Marcus Chen',     'marcus.chen@ownuh-saips.com',     'admin',      'active',    1, 'totp',      0, NULL,                         NOW() - INTERVAL 48 MINUTE, '198.51.100.22', 'IN', NOW() - INTERVAL 21 DAY, 1, NOW() - INTERVAL 118 DAY, NOW() - INTERVAL 175 DAY, NOW() - INTERVAL 48 MINUTE),
('usr-003-0000-0000-0000-000000000003', 'Priya Patel',     'priya.patel@ownuh-saips.com',     'manager',    'locked',    1, 'totp',      6, NOW() - INTERVAL 45 MINUTE,  NOW() - INTERVAL 2 DAY,     '198.51.100.40', 'IN', NOW() - INTERVAL 35 DAY, 1, NOW() - INTERVAL 110 DAY, NOW() - INTERVAL 170 DAY, NOW() - INTERVAL 40 MINUTE),
('usr-004-0000-0000-0000-000000000004', 'James Harris',    'james.harris@ownuh-saips.com',    'user',       'active',    1, 'email_otp', 0, NULL,                         NOW() - INTERVAL 3 HOUR,    '203.0.113.45',  'AE', NOW() - INTERVAL 30 DAY, 1, NOW() - INTERVAL 100 DAY, NOW() - INTERVAL 165 DAY, NOW() - INTERVAL 3 HOUR),
('usr-005-0000-0000-0000-000000000005', 'Alex Rivera',     'alex.rivera@ownuh-saips.com',     'user',       'pending',   0, 'none',      0, NULL,                         NULL,                       NULL,            NULL, NULL,                    0, NULL,                    NOW() - INTERVAL 2 DAY,   NOW() - INTERVAL 2 DAY),
('usr-006-0000-0000-0000-000000000006', 'Nina Schultz',    'nina.schultz@ownuh-saips.com',    'user',       'active',    1, 'totp',      0, NULL,                         NOW() - INTERVAL 5 HOUR,    '203.0.113.91',  'DE', NOW() - INTERVAL 11 DAY, 1, NOW() - INTERVAL 95 DAY,  NOW() - INTERVAL 160 DAY, NOW() - INTERVAL 5 HOUR),
('usr-007-0000-0000-0000-000000000007', 'Omar Farouk',     'omar.farouk@ownuh-saips.com',     'manager',    'active',    1, 'fido2',     0, NULL,                         NOW() - INTERVAL 9 HOUR,    '198.51.100.54', 'EG', NOW() - INTERVAL 8 DAY,  1, NOW() - INTERVAL 90 DAY,  NOW() - INTERVAL 158 DAY, NOW() - INTERVAL 9 HOUR),
('usr-008-0000-0000-0000-000000000008', 'Ava Thompson',    'ava.thompson@ownuh-saips.com',    'user',       'suspended', 0, 'none',      3, NOW() - INTERVAL 1 DAY,      NOW() - INTERVAL 12 DAY,    '203.0.113.144', 'US', NOW() - INTERVAL 60 DAY, 1, NOW() - INTERVAL 80 DAY,  NOW() - INTERVAL 150 DAY, NOW() - INTERVAL 1 DAY),
('usr-009-0000-0000-0000-000000000009', 'Rahul Mehta',     'rahul.mehta@ownuh-saips.com',     'user',       'active',    1, 'email_otp', 0, NULL,                         NOW() - INTERVAL 36 MINUTE, '198.51.100.88', 'IN', NOW() - INTERVAL 18 DAY, 1, NOW() - INTERVAL 75 DAY,  NOW() - INTERVAL 145 DAY, NOW() - INTERVAL 36 MINUTE),
('usr-010-0000-0000-0000-000000000010', 'Lucia Alvarez',   'lucia.alvarez@ownuh-saips.com',   'admin',      'active',    1, 'email_otp', 0, NULL,                         NOW() - INTERVAL 6 HOUR,    '203.0.113.73',  'ES', NOW() - INTERVAL 12 DAY, 1, NOW() - INTERVAL 70 DAY,  NOW() - INTERVAL 140 DAY, NOW() - INTERVAL 6 HOUR);

-- ============================================================
-- POLICY / SETTINGS
-- ============================================================

INSERT INTO rate_limit_config (
    id, endpoint, requests_limit, window_seconds, scope, action_on_breach, is_active, updated_by
) VALUES
('rlc-001-0000-0000-0000-000000000001', '/auth/login',         60, 60,   'per_ip',    'block_temp', 1, 'usr-001-0000-0000-0000-000000000001'),
('rlc-002-0000-0000-0000-000000000002', '/auth/token',         60, 60,   'per_ip',    'block_temp', 1, 'usr-001-0000-0000-0000-000000000001'),
('rlc-003-0000-0000-0000-000000000003', '/auth/mfa/verify',     5, 900,  'per_user',  'soft_lock',  1, 'usr-002-0000-0000-0000-000000000002'),
('rlc-004-0000-0000-0000-000000000004', '/auth/mfa/email-otp',  5, 3600, 'per_user',  'block_temp', 1, 'usr-002-0000-0000-0000-000000000002'),
('rlc-005-0000-0000-0000-000000000005', '/api/*',             300, 60,   'per_token', 'rate_429',   1, 'usr-001-0000-0000-0000-000000000001');

INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES
('mfa.admin_required', 'true',  'usr-001-0000-0000-0000-000000000001'),
('mfa.manager_required', 'true', 'usr-001-0000-0000-0000-000000000001'),
('mfa.user_required', 'false',  'usr-001-0000-0000-0000-000000000001'),
('mfa.allow_email_otp', 'true', 'usr-002-0000-0000-0000-000000000002'),
('auth.password.min_length', '12', 'usr-002-0000-0000-0000-000000000002'),
('auth.password.history_count', '5', 'usr-002-0000-0000-0000-000000000002'),
('ips.bruteforce.ip_threshold', '10', 'usr-001-0000-0000-0000-000000000001'),
('ips.bruteforce.window_minutes', '10', 'usr-001-0000-0000-0000-000000000001'),
('ips.geo_block.enabled', 'true', 'usr-001-0000-0000-0000-000000000001'),
('executive_reports.email_enabled', '1', 'usr-001-0000-0000-0000-000000000001'),
('executive_reports.cadence', 'weekly', 'usr-001-0000-0000-0000-000000000001'),
('executive_reports.attach_format', 'none', 'usr-001-0000-0000-0000-000000000001'),
('ui.timezone.label', 'IST', 'usr-001-0000-0000-0000-000000000001');

INSERT INTO geo_rules (id, country_code, country_name, rule_type, created_by, created_at) VALUES
('geo-001-0000-0000-0000-000000000001', 'RU', 'Russia',       'deny',  'usr-001-0000-0000-0000-000000000001', NOW() - INTERVAL 45 DAY),
('geo-002-0000-0000-0000-000000000002', 'KP', 'North Korea',  'deny',  'usr-001-0000-0000-0000-000000000001', NOW() - INTERVAL 45 DAY),
('geo-003-0000-0000-0000-000000000003', 'IR', 'Iran',         'deny',  'usr-002-0000-0000-0000-000000000002', NOW() - INTERVAL 38 DAY),
('geo-004-0000-0000-0000-000000000004', 'IN', 'India',        'allow', 'usr-001-0000-0000-0000-000000000001', NOW() - INTERVAL 7 DAY),
('geo-005-0000-0000-0000-000000000005', 'SG', 'Singapore',    'allow', 'usr-002-0000-0000-0000-000000000002', NOW() - INTERVAL 7 DAY);

INSERT INTO blocked_ips (
    id, ip_address, block_type, trigger_rule, country_code, threat_feed, blocked_at, expires_at, unblocked_at, unblocked_by
) VALUES
('blk-001-0000-0000-0000-000000000001', '185.220.101.47', 'tor_exit',    'Known Tor exit node',         'NL', 'tor-exit-list', NOW() - INTERVAL 5 MINUTE,  NOW() + INTERVAL 55 MINUTE, NULL, NULL),
('blk-002-0000-0000-0000-000000000002', '91.108.4.200',   'geo_block',   'Country deny-list: RU',       'RU', NULL,            NOW() - INTERVAL 3 HOUR,    NULL,                      NULL, NULL),
('blk-003-0000-0000-0000-000000000003', '198.54.117.212', 'brute_force', '10 failed logins in 10 min',  'US', NULL,            NOW() - INTERVAL 14 MINUTE, NOW() + INTERVAL 46 MINUTE, NULL, NULL),
('blk-004-0000-0000-0000-000000000004', '45.83.64.19',    'threat_feed', 'AbuseIPDB confidence >= 90',  'DE', 'abuseipdb',     NOW() - INTERVAL 1 DAY,     NOW() + INTERVAL 6 DAY,    NULL, NULL),
('blk-005-0000-0000-0000-000000000005', '203.0.113.201',  'manual',      'Blocked after analyst review', 'US', NULL,            NOW() - INTERVAL 4 DAY,     NULL,                      NULL, NULL);

INSERT INTO alert_rules (
    id, rule_name, event_type, channel, threshold_count, window_minutes,
    destination, is_active, last_triggered_at, created_by, created_at
) VALUES
('alr-001-0000-0000-0000-000000000001', 'Brute-Force Alert',          'AUTH-002', 'email',   5, 15, 'soc@ownuh-saips.com',         1, NOW() - INTERVAL 35 MINUTE, 'usr-001-0000-0000-0000-000000000001', NOW() - INTERVAL 90 DAY),
('alr-002-0000-0000-0000-000000000002', 'IP Block Notification',      'IPS-001',  'slack',   1,  5, 'https://hooks.slack.test/saips', 1, NOW() - INTERVAL 12 MINUTE, 'usr-001-0000-0000-0000-000000000001', NOW() - INTERVAL 88 DAY),
('alr-003-0000-0000-0000-000000000003', 'Admin MFA Bypass Oversight', 'AUTH-017', 'webhook', 1, 10, 'https://alerts.test/mfa-bypass',  1, NOW() - INTERVAL 2 HOUR,   'usr-010-0000-0000-0000-000000000010', NOW() - INTERVAL 41 DAY);

-- ============================================================
-- MFA INVENTORY / ACTIVE SESSIONS
-- ============================================================

INSERT INTO mfa_totp_secrets (
    id, user_id, secret_encrypted, algorithm, digits, period, enrolled_at, last_used_at
) VALUES
('totp-001-0000-0000-0000-000000000001', 'usr-002-0000-0000-0000-000000000002', 'demo_totp_secret_marcus_encrypted', 'SHA1', 6, 30, NOW() - INTERVAL 120 DAY, NOW() - INTERVAL 48 MINUTE),
('totp-002-0000-0000-0000-000000000002', 'usr-003-0000-0000-0000-000000000003', 'demo_totp_secret_priya_encrypted',  'SHA1', 6, 30, NOW() - INTERVAL 119 DAY, NOW() - INTERVAL 2 DAY),
('totp-003-0000-0000-0000-000000000003', 'usr-006-0000-0000-0000-000000000006', 'demo_totp_secret_nina_encrypted',   'SHA1', 6, 30, NOW() - INTERVAL 90 DAY,  NOW() - INTERVAL 5 HOUR);

INSERT INTO mfa_backup_codes (id, user_id, code_hash, used_at, created_at) VALUES
('mbc-001-0000-0000-0000-000000000001', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 120 DAY),
('mbc-002-0000-0000-0000-000000000002', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 120 DAY),
('mbc-003-0000-0000-0000-000000000003', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 120 DAY),
('mbc-004-0000-0000-0000-000000000004', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 30 DAY,  NOW() - INTERVAL 120 DAY),
('mbc-005-0000-0000-0000-000000000005', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 10 DAY,  NOW() - INTERVAL 120 DAY),
('mbc-006-0000-0000-0000-000000000006', 'usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 119 DAY),
('mbc-007-0000-0000-0000-000000000007', 'usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 119 DAY),
('mbc-008-0000-0000-0000-000000000008', 'usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 20 DAY,  NOW() - INTERVAL 119 DAY),
('mbc-009-0000-0000-0000-000000000009', 'usr-006-0000-0000-0000-000000000006', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 90 DAY),
('mbc-010-0000-0000-0000-000000000010', 'usr-006-0000-0000-0000-000000000006', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 90 DAY),
('mbc-011-0000-0000-0000-000000000011', 'usr-009-0000-0000-0000-000000000009', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 75 DAY),
('mbc-012-0000-0000-0000-000000000012', 'usr-009-0000-0000-0000-000000000009', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NULL,                    NOW() - INTERVAL 75 DAY);

INSERT INTO mfa_fido2_credentials (
    id, user_id, credential_id, public_key, sign_count, device_description, aaguid, enrolled_at, last_used_at
) VALUES
('fid-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', X'736F706869615F6669646F325F6B65795F3031', 'demo_public_key_sophia', 42, 'YubiKey 5 NFC',                    'aaguid-demo-0001', NOW() - INTERVAL 150 DAY, NOW() - INTERVAL 22 MINUTE),
('fid-002-0000-0000-0000-000000000002', 'usr-007-0000-0000-0000-000000000007', X'6F6D61725F6669646F325F6B65795F3031',   'demo_public_key_omar',   17, 'Windows Hello - Managed Laptop', 'aaguid-demo-0002', NOW() - INTERVAL 100 DAY, NOW() - INTERVAL 9 HOUR),
('fid-003-0000-0000-0000-000000000003', 'usr-010-0000-0000-0000-000000000010', X'6C756369615F6669646F325F6B65795F3031', 'demo_public_key_lucia',  25, 'Titan Security Key',             'aaguid-demo-0003', NOW() - INTERVAL 92 DAY,  NOW() - INTERVAL 6 HOUR);

INSERT INTO sessions (
    id, user_id, refresh_token_hash, ip_address, user_agent, device_fingerprint, mfa_method,
    created_at, expires_at, last_used_at, invalidated_at, invalidated_by, invalidation_reason
) VALUES
('ses-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', SHA2('session_demo_superadmin_1', 256), '203.0.113.10',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',             'fp_superadmin_laptop', 'fido2',     NOW() - INTERVAL 40 MINUTE, NOW() + INTERVAL 7 HOUR,  NOW() - INTERVAL 3 MINUTE,  NULL, NULL, NULL),
('ses-002-0000-0000-0000-000000000002', 'usr-002-0000-0000-0000-000000000002', SHA2('session_demo_admin_1',      256), '198.51.100.22', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',       'fp_admin_travel_mac',   'totp',      NOW() - INTERVAL 2 HOUR,   NOW() + INTERVAL 6 HOUR,  NOW() - INTERVAL 19 MINUTE, NULL, NULL, NULL),
('ses-003-0000-0000-0000-000000000003', 'usr-004-0000-0000-0000-000000000004', SHA2('session_demo_user_1',       256), '203.0.113.45',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)','fp_james_mobile',       'email_otp', NOW() - INTERVAL 5 HOUR,   NOW() + INTERVAL 2 DAY,   NOW() - INTERVAL 6 MINUTE,  NULL, NULL, NULL),
('ses-004-0000-0000-0000-000000000004', 'usr-006-0000-0000-0000-000000000006', SHA2('session_demo_user_2',       256), '203.0.113.91',  'Mozilla/5.0 (X11; Linux x86_64)',                       'fp_nina_linux',         'totp',      NOW() - INTERVAL 9 HOUR,   NOW() + INTERVAL 1 DAY,    NOW() - INTERVAL 27 MINUTE, NULL, NULL, NULL),
('ses-005-0000-0000-0000-000000000005', 'usr-009-0000-0000-0000-000000000009', SHA2('session_demo_user_3',       256), '198.51.100.88', 'Mozilla/5.0 (Android 14; Mobile)',                      'fp_rahul_phone',        'email_otp', NOW() - INTERVAL 1 HOUR,   NOW() + INTERVAL 1 DAY,    NOW() - INTERVAL 1 MINUTE,  NULL, NULL, NULL),
('ses-006-0000-0000-0000-000000000006', 'usr-010-0000-0000-0000-000000000010', SHA2('session_demo_admin_2',      256), '203.0.113.73',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',             'fp_lucia_ops_desktop',  'email_otp', NOW() - INTERVAL 12 HOUR,  NOW() + INTERVAL 8 HOUR,   NOW() - INTERVAL 32 MINUTE, NULL, NULL, NULL);

-- ============================================================
-- INCIDENTS / OPS SIGNALS
-- ============================================================

INSERT INTO incidents (
    id, incident_ref, severity, status, trigger_summary, affected_user_id, source_ip,
    detected_at, acknowledged_at, resolved_at, assigned_to, reported_by,
    description, actions_taken, personal_data_involved, gdpr_notification_required,
    gdpr_notified_at, related_audit_entries, created_at, updated_at
) VALUES
('inc-001-0000-0000-0000-000000000001', 'INC-2026-031', 'sev2', 'in_progress',  'Credential stuffing against manager account', 'usr-003-0000-0000-0000-000000000003', '198.54.117.212', NOW() - INTERVAL 42 MINUTE, NOW() - INTERVAL 35 MINUTE, NULL, 'usr-002-0000-0000-0000-000000000002', 'usr-001-0000-0000-0000-000000000001', 'Multiple failed logins were detected against Priya Patel from rotating IPs. Account was temporarily locked and the source IP was blocked by the IPS pipeline.', 'Temporary lock applied, IP blocked for 60 minutes, password reset required on next successful recovery.', 0, 0, NULL, JSON_ARRAY('AUTH-002', 'IPS-001'), NOW() - INTERVAL 42 MINUTE, NOW() - INTERVAL 12 MINUTE),
('inc-002-0000-0000-0000-000000000002', 'INC-2026-030', 'sev3', 'under_review', 'Admin login from new geography', 'usr-002-0000-0000-0000-000000000002', '198.51.100.22', NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 5 HOUR, NULL, 'usr-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', 'An admin account completed MFA from a previously unseen country profile. Session was allowed but flagged for analyst review and geo policy verification.', 'Session retained, analyst review opened, geo rule baseline compared against recent travel exception.', 0, 0, NULL, JSON_ARRAY('AUTH-001', 'AUTH-009'), NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 3 HOUR),
('inc-003-0000-0000-0000-000000000003', 'INC-2026-029', 'sev2', 'resolved',     'Single-use MFA bypass token issued', 'usr-005-0000-0000-0000-000000000005', NULL, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 23 HOUR, NOW() - INTERVAL 21 HOUR, 'usr-010-0000-0000-0000-000000000010', 'usr-001-0000-0000-0000-000000000001', 'A one-time admin-issued MFA bypass token was created for a user who had lost access to their TOTP device during onboarding.', 'Recovery reason documented, token consumed once, user moved to pending MFA re-enrollment.', 0, 0, NULL, JSON_ARRAY('AUTH-017', 'AUTH-018'), NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 21 HOUR),
('inc-004-0000-0000-0000-000000000004', 'INC-2026-028', 'sev4', 'closed',       'Dormant suspended user retained for records', 'usr-008-0000-0000-0000-000000000008', NULL, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 10 DAY, 'usr-007-0000-0000-0000-000000000007', 'usr-002-0000-0000-0000-000000000002', 'Legacy user access was suspended after repeated policy violations. Data was retained for audit and HR coordination but access remains disabled.', 'Suspension confirmed, session inventory reviewed, no active tokens present.', 1, 0, NULL, JSON_ARRAY('AUTH-014'), NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 10 DAY);

INSERT INTO login_attempts (username, ip_address, success, failure_reason, risk_score, attempted_at) VALUES
('priya.patel@ownuh-saips.com',   '198.54.117.212', 0, 'bad_password', 92, NOW() - INTERVAL 52 MINUTE),
('priya.patel@ownuh-saips.com',   '198.54.117.212', 0, 'bad_password', 94, NOW() - INTERVAL 50 MINUTE),
('priya.patel@ownuh-saips.com',   '198.54.117.212', 0, 'bad_password', 95, NOW() - INTERVAL 48 MINUTE),
('priya.patel@ownuh-saips.com',   '198.54.117.212', 0, 'bad_password', 97, NOW() - INTERVAL 46 MINUTE),
('priya.patel@ownuh-saips.com',   '198.54.117.212', 0, 'account_locked', 98, NOW() - INTERVAL 44 MINUTE),
('marcus.chen@ownuh-saips.com',   '198.51.100.22', 1, NULL,           18, NOW() - INTERVAL 6 HOUR),
('rahul.mehta@ownuh-saips.com',   '198.51.100.88', 1, NULL,           12, NOW() - INTERVAL 40 MINUTE),
('james.harris@ownuh-saips.com',  '203.0.113.45',  1, NULL,           15, NOW() - INTERVAL 3 HOUR),
('ava.thompson@ownuh-saips.com',  '203.0.113.144', 0, 'suspended',    76, NOW() - INTERVAL 1 DAY),
('alex.rivera@ownuh-saips.com',   '198.51.100.101',0, 'pending_user', 42, NOW() - INTERVAL 2 DAY);

INSERT INTO audit_log (
    event_code, event_name, user_id, source_ip, user_agent, country_code, device_fingerprint,
    mfa_method, risk_score, details, admin_id, target_user_id, created_at, entry_hash, prev_hash
) VALUES
('AUTH-001', 'Successful Login',      'usr-001-0000-0000-0000-000000000001', '203.0.113.10',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'SG', 'device_superadmin_1', 'fido2',        15, JSON_OBJECT('session_id', 'ses_demo_001', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 8 HOUR,  SHA2('portfolio-audit-001', 256), NULL),
('AUTH-009', 'Anomalous Login Reviewed','usr-002-0000-0000-0000-000000000002', '198.51.100.22', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'IN', 'device_admin_travel', 'totp',         63, JSON_OBJECT('reason', 'new_country', 'review_status', 'open'), NULL, NULL, NOW() - INTERVAL 6 HOUR,  SHA2('portfolio-audit-002', 256), SHA2('portfolio-audit-001', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                   '198.54.117.212', 'python-requests/2.31 demo',                         'US', NULL,                  NULL,           94, JSON_OBJECT('username', 'priya.patel@ownuh-saips.com', 'reason', 'bad_password', 'attempt', 4), NULL, NULL, NOW() - INTERVAL 50 MINUTE, SHA2('portfolio-audit-003', 256), SHA2('portfolio-audit-002', 256)),
('IPS-001',  'IP Blocked',            NULL,                                   '198.54.117.212', 'IPS Middleware',                                    'US', NULL,                  NULL,           97, JSON_OBJECT('block_type', 'brute_force', 'duration_minutes', 60, 'threshold', 10), NULL, NULL, NOW() - INTERVAL 44 MINUTE, SHA2('portfolio-audit-004', 256), SHA2('portfolio-audit-003', 256)),
('AUTH-017', 'MFA Bypass Token Issued','usr-010-0000-0000-0000-000000000010', NULL,              'Admin Console',                                      NULL, NULL,                  NULL,           71, JSON_OBJECT('reason', 'device_lost', 'delivery', 'manual'), 'usr-010-0000-0000-0000-000000000010', 'usr-005-0000-0000-0000-000000000005', NOW() - INTERVAL 1 DAY,     SHA2('portfolio-audit-005', 256), SHA2('portfolio-audit-004', 256)),
('AUTH-018', 'MFA Bypass Token Consumed','usr-005-0000-0000-0000-000000000005', '198.51.100.101', 'Mozilla/5.0 (Android 14; Mobile)',                  'IN', 'device_onboarding_5',  'bypass_token', 44, JSON_OBJECT('result', 'success', 'next_step', 're-enroll_mfa'), NULL, NULL, NOW() - INTERVAL 23 HOUR, SHA2('portfolio-audit-006', 256), SHA2('portfolio-audit-005', 256)),
('AUTH-014', 'User Suspended',        'usr-002-0000-0000-0000-000000000002', NULL,              'Admin Console',                                      NULL, NULL,                  NULL,           58, JSON_OBJECT('target', 'usr-008-0000-0000-0000-000000000008', 'reason', 'policy_violation'), 'usr-002-0000-0000-0000-000000000002', 'usr-008-0000-0000-0000-000000000008', NOW() - INTERVAL 12 DAY,   SHA2('portfolio-audit-007', 256), SHA2('portfolio-audit-006', 256)),
('AUTH-001', 'Successful Login',      'usr-007-0000-0000-0000-000000000007', '198.51.100.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',          'EG', 'device_omar_key',      'fido2',        20, JSON_OBJECT('session_id', 'ses_demo_007', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 14 DAY, SHA2('portfolio-audit-008', 256), SHA2('portfolio-audit-007', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '45.83.64.19',   'curl/8.4.0',                                          'DE', NULL,                  NULL,           89, JSON_OBJECT('username', 'admin@ownuh-saips.com', 'reason', 'credential_stuffing'), NULL, NULL, NOW() - INTERVAL 33 DAY, SHA2('portfolio-audit-009', 256), SHA2('portfolio-audit-008', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '45.83.64.19',   'Threat Feed Sync',                                    'DE', NULL,                  NULL,           93, JSON_OBJECT('block_type', 'threat_feed', 'feed', 'abuseipdb'), NULL, NULL, NOW() - INTERVAL 33 DAY, SHA2('portfolio-audit-010', 256), SHA2('portfolio-audit-009', 256)),
('AUTH-001', 'Successful Login',      'usr-004-0000-0000-0000-000000000004', '203.0.113.45',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'AE', 'device_james_phone', 'email_otp',    14, JSON_OBJECT('session_id', 'ses_demo_004', 'trust_level', 'new_device_verified'), NULL, NULL, NOW() - INTERVAL 52 DAY, SHA2('portfolio-audit-011', 256), SHA2('portfolio-audit-010', 256)),
('AUTH-001', 'Successful Login',      'usr-006-0000-0000-0000-000000000006', '203.0.113.91',  'Mozilla/5.0 (X11; Linux x86_64)',                     'DE', 'device_nina_linux',    'totp',         16, JSON_OBJECT('session_id', 'ses_demo_006', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 78 DAY, SHA2('portfolio-audit-012', 256), SHA2('portfolio-audit-011', 256)),
('AUTH-001', 'Successful Login',      'usr-009-0000-0000-0000-000000000009', '198.51.100.88', 'Mozilla/5.0 (Android 14; Mobile)',                    'IN', 'device_rahul_phone',   'email_otp',    11, JSON_OBJECT('session_id', 'ses_demo_009', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 109 DAY, SHA2('portfolio-audit-013', 256), SHA2('portfolio-audit-012', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '91.108.4.200',  'python-requests/2.31',                                'RU', NULL,                  NULL,           91, JSON_OBJECT('username', 'marcus.chen@ownuh-saips.com', 'reason', 'geo_denied'), NULL, NULL, NOW() - INTERVAL 141 DAY, SHA2('portfolio-audit-014', 256), SHA2('portfolio-audit-013', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '91.108.4.200',  'Geo Policy Engine',                                   'RU', NULL,                  NULL,           95, JSON_OBJECT('block_type', 'geo_block', 'country_code', 'RU'), NULL, NULL, NOW() - INTERVAL 141 DAY, SHA2('portfolio-audit-015', 256), SHA2('portfolio-audit-014', 256)),
('AUTH-001', 'Successful Login',      'usr-010-0000-0000-0000-000000000010', '203.0.113.73',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'ES', 'device_lucia_desktop', 'email_otp',    19, JSON_OBJECT('session_id', 'ses_demo_010', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 170 DAY, SHA2('portfolio-audit-016', 256), SHA2('portfolio-audit-015', 256)),
('AUTH-001', 'Successful Login',      'usr-010-0000-0000-0000-000000000010', '198.51.100.144','Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'IN', 'device_lucia_travel',  'email_otp',    18, JSON_OBJECT('session_id', 'ses_demo_017', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 2 DAY,  SHA2('portfolio-audit-017', 256), SHA2('portfolio-audit-016', 256)),
('AUTH-001', 'Successful Login',      'usr-002-0000-0000-0000-000000000002', '203.0.113.131', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',       'US', 'device_marcus_mac',    'totp',         17, JSON_OBJECT('session_id', 'ses_demo_018', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 4 DAY,  SHA2('portfolio-audit-018', 256), SHA2('portfolio-audit-017', 256)),
('AUTH-001', 'Successful Login',      'usr-001-0000-0000-0000-000000000001', '203.0.113.125', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'SG', 'device_sophia_ops',    'fido2',        12, JSON_OBJECT('session_id', 'ses_demo_019', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 6 DAY,  SHA2('portfolio-audit-019', 256), SHA2('portfolio-audit-018', 256)),
('AUTH-001', 'Successful Login',      'usr-004-0000-0000-0000-000000000004', '198.51.100.63', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)','AE', 'device_james_trip',    'email_otp',    22, JSON_OBJECT('session_id', 'ses_demo_020', 'trust_level', 'new_device_verified'), NULL, NULL, NOW() - INTERVAL 9 DAY,  SHA2('portfolio-audit-020', 256), SHA2('portfolio-audit-019', 256)),
('AUTH-001', 'Successful Login',      'usr-006-0000-0000-0000-000000000006', '203.0.113.91',  'Mozilla/5.0 (X11; Linux x86_64)',                     'DE', 'device_nina_linux',   'totp',         16, JSON_OBJECT('session_id', 'ses_demo_021', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 12 DAY, SHA2('portfolio-audit-021', 256), SHA2('portfolio-audit-020', 256)),
('AUTH-001', 'Successful Login',      'usr-009-0000-0000-0000-000000000009', '198.51.100.88', 'Mozilla/5.0 (Android 14; Mobile)',                    'AU', 'device_rahul_phone',  'email_otp',    13, JSON_OBJECT('session_id', 'ses_demo_022', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 15 DAY, SHA2('portfolio-audit-022', 256), SHA2('portfolio-audit-021', 256)),
('AUTH-001', 'Successful Login',      'usr-007-0000-0000-0000-000000000007', '198.51.100.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'GB', 'device_omar_key',     'fido2',        20, JSON_OBJECT('session_id', 'ses_demo_023', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 18 DAY, SHA2('portfolio-audit-023', 256), SHA2('portfolio-audit-022', 256)),
('AUTH-001', 'Successful Login',      'usr-005-0000-0000-0000-000000000005', '198.51.100.101','Mozilla/5.0 (Android 14; Mobile)',                    'CA', 'device_alex_mobile',  'bypass_token', 28, JSON_OBJECT('session_id', 'ses_demo_024', 'trust_level', 'recovery_flow'), NULL, NULL, NOW() - INTERVAL 21 DAY, SHA2('portfolio-audit-024', 256), SHA2('portfolio-audit-023', 256)),
('AUTH-001', 'Successful Login',      'usr-003-0000-0000-0000-000000000003', '203.0.113.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'JP', 'device_priya_laptop', 'totp',         21, JSON_OBJECT('session_id', 'ses_demo_025', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 25 DAY, SHA2('portfolio-audit-025', 256), SHA2('portfolio-audit-024', 256));

INSERT INTO audit_log (
    event_code, event_name, user_id, source_ip, user_agent, country_code, device_fingerprint,
    mfa_method, risk_score, details, admin_id, target_user_id, created_at, entry_hash, prev_hash
) VALUES
('AUTH-001', 'Successful Login',      'usr-002-0000-0000-0000-000000000002', '198.51.100.24', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',     'IN', 'device_marcus_q1',    'totp',         18, JSON_OBJECT('session_id', 'ses_demo_026', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 35 DAY, SHA2('portfolio-audit-026', 256), SHA2('portfolio-audit-025', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '185.76.9.31',   'python-requests/2.31 demo',                           'NL', NULL,                  NULL,           88, JSON_OBJECT('username', 'sophia.johnson@ownuh-saips.com', 'reason', 'password_spray'), NULL, NULL, NOW() - INTERVAL 35 DAY, SHA2('portfolio-audit-027', 256), SHA2('portfolio-audit-026', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '185.76.9.31',   'IPS Middleware',                                      'NL', NULL,                  NULL,           93, JSON_OBJECT('block_type', 'threat_feed', 'duration_minutes', 1440), NULL, NULL, NOW() - INTERVAL 35 DAY, SHA2('portfolio-audit-028', 256), SHA2('portfolio-audit-027', 256)),
('AUTH-001', 'Successful Login',      'usr-001-0000-0000-0000-000000000001', '203.0.113.118', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'SG', 'device_sophia_q2',    'fido2',        14, JSON_OBJECT('session_id', 'ses_demo_029', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 42 DAY, SHA2('portfolio-audit-029', 256), SHA2('portfolio-audit-028', 256)),
('AUTH-001', 'Successful Login',      'usr-010-0000-0000-0000-000000000010', '203.0.113.74',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'ES', 'device_lucia_q2',     'email_otp',    16, JSON_OBJECT('session_id', 'ses_demo_030', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 58 DAY, SHA2('portfolio-audit-030', 256), SHA2('portfolio-audit-029', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '45.92.18.204',  'curl/8.4.0',                                          'DE', NULL,                  NULL,           84, JSON_OBJECT('username', 'lucia.alvarez@ownuh-saips.com', 'reason', 'credential_stuffing'), NULL, NULL, NOW() - INTERVAL 58 DAY, SHA2('portfolio-audit-031', 256), SHA2('portfolio-audit-030', 256)),
('AUTH-001', 'Successful Login',      'usr-006-0000-0000-0000-000000000006', '203.0.113.92',  'Mozilla/5.0 (X11; Linux x86_64)',                     'DE', 'device_nina_q2',      'totp',         15, JSON_OBJECT('session_id', 'ses_demo_032', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 67 DAY, SHA2('portfolio-audit-032', 256), SHA2('portfolio-audit-031', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '102.129.153.11','Geo Policy Engine',                                   'ZA', NULL,                  NULL,           91, JSON_OBJECT('block_type', 'geo_block', 'country_code', 'ZA'), NULL, NULL, NOW() - INTERVAL 67 DAY, SHA2('portfolio-audit-033', 256), SHA2('portfolio-audit-032', 256)),
('AUTH-001', 'Successful Login',      'usr-004-0000-0000-0000-000000000004', '203.0.113.49',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'AE', 'device_james_q3', 'email_otp',    17, JSON_OBJECT('session_id', 'ses_demo_034', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 84 DAY, SHA2('portfolio-audit-034', 256), SHA2('portfolio-audit-033', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '198.54.117.30', 'python-requests/2.31 demo',                           'US', NULL,                  NULL,           87, JSON_OBJECT('username', 'marcus.chen@ownuh-saips.com', 'reason', 'bad_password'), NULL, NULL, NOW() - INTERVAL 84 DAY, SHA2('portfolio-audit-035', 256), SHA2('portfolio-audit-034', 256)),
('AUTH-001', 'Successful Login',      'usr-009-0000-0000-0000-000000000009', '198.51.100.90', 'Mozilla/5.0 (Android 14; Mobile)',                    'AU', 'device_rahul_q3',     'email_otp',    12, JSON_OBJECT('session_id', 'ses_demo_036', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 96 DAY, SHA2('portfolio-audit-036', 256), SHA2('portfolio-audit-035', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '91.240.118.66', 'Threat Feed Sync',                                    'PL', NULL,                  NULL,           94, JSON_OBJECT('block_type', 'threat_feed', 'feed', 'abuseipdb'), NULL, NULL, NOW() - INTERVAL 96 DAY, SHA2('portfolio-audit-037', 256), SHA2('portfolio-audit-036', 256)),
('AUTH-001', 'Successful Login',      'usr-007-0000-0000-0000-000000000007', '198.51.100.56', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'GB', 'device_omar_q4',      'fido2',        19, JSON_OBJECT('session_id', 'ses_demo_038', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 118 DAY, SHA2('portfolio-audit-038', 256), SHA2('portfolio-audit-037', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '154.73.12.8',   'python-requests/2.31 demo',                           'NG', NULL,                  NULL,           90, JSON_OBJECT('username', 'rahul.mehta@ownuh-saips.com', 'reason', 'password_spray'), NULL, NULL, NOW() - INTERVAL 118 DAY, SHA2('portfolio-audit-039', 256), SHA2('portfolio-audit-038', 256)),
('AUTH-001', 'Successful Login',      'usr-001-0000-0000-0000-000000000001', '203.0.113.127', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'SG', 'device_sophia_q4',    'fido2',        13, JSON_OBJECT('session_id', 'ses_demo_040', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 128 DAY, SHA2('portfolio-audit-040', 256), SHA2('portfolio-audit-039', 256)),
('AUTH-001', 'Successful Login',      'usr-010-0000-0000-0000-000000000010', '203.0.113.75',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',           'ES', 'device_lucia_q4',     'email_otp',    17, JSON_OBJECT('session_id', 'ses_demo_041', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 152 DAY, SHA2('portfolio-audit-041', 256), SHA2('portfolio-audit-040', 256)),
('AUTH-002', 'Failed Login Attempt',  NULL,                                  '203.17.55.18',  'curl/8.4.0',                                          'AU', NULL,                  NULL,           86, JSON_OBJECT('username', 'james.harris@ownuh-saips.com', 'reason', 'bad_password'), NULL, NULL, NOW() - INTERVAL 152 DAY, SHA2('portfolio-audit-042', 256), SHA2('portfolio-audit-041', 256)),
('IPS-001',  'IP Blocked',            NULL,                                  '203.17.55.18',  'IPS Middleware',                                      'AU', NULL,                  NULL,           92, JSON_OBJECT('block_type', 'brute_force', 'duration_minutes', 60), NULL, NULL, NOW() - INTERVAL 152 DAY, SHA2('portfolio-audit-043', 256), SHA2('portfolio-audit-042', 256)),
('AUTH-001', 'Successful Login',      'usr-006-0000-0000-0000-000000000006', '203.0.113.93',  'Mozilla/5.0 (X11; Linux x86_64)',                     'DE', 'device_nina_q5',      'totp',         14, JSON_OBJECT('session_id', 'ses_demo_044', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 176 DAY, SHA2('portfolio-audit-044', 256), SHA2('portfolio-audit-043', 256)),
('AUTH-001', 'Successful Login',      'usr-004-0000-0000-0000-000000000004', '203.0.113.46',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'AE', 'device_james_q5', 'email_otp',    16, JSON_OBJECT('session_id', 'ses_demo_045', 'trust_level', 'known_device'), NULL, NULL, NOW() - INTERVAL 176 DAY, SHA2('portfolio-audit-045', 256), SHA2('portfolio-audit-044', 256));

-- ============================================================
-- PASSWORDS
-- ============================================================

INSERT INTO password_history (id, user_id, password_hash, created_at) VALUES
('pwh-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 45 DAY),
('pwh-002-0000-0000-0000-000000000002', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 40 DAY),
('pwh-003-0000-0000-0000-000000000003', 'usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', NOW() - INTERVAL 39 DAY);

INSERT INTO user_credentials (id, user_id, password_hash, bcrypt_cost, created_at, updated_at) VALUES
('ucr-001-0000-0000-0000-000000000001', 'usr-001-0000-0000-0000-000000000001', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 180 DAY, NOW() - INTERVAL 14 DAY),
('ucr-002-0000-0000-0000-000000000002', 'usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 175 DAY, NOW() - INTERVAL 21 DAY),
('ucr-003-0000-0000-0000-000000000003', 'usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 170 DAY, NOW() - INTERVAL 35 DAY),
('ucr-004-0000-0000-0000-000000000004', 'usr-004-0000-0000-0000-000000000004', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 165 DAY, NOW() - INTERVAL 30 DAY),
('ucr-005-0000-0000-0000-000000000005', 'usr-005-0000-0000-0000-000000000005', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 2 DAY,   NOW() - INTERVAL 2 DAY),
('ucr-006-0000-0000-0000-000000000006', 'usr-006-0000-0000-0000-000000000006', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 160 DAY, NOW() - INTERVAL 11 DAY),
('ucr-007-0000-0000-0000-000000000007', 'usr-007-0000-0000-0000-000000000007', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 158 DAY, NOW() - INTERVAL 8 DAY),
('ucr-008-0000-0000-0000-000000000008', 'usr-008-0000-0000-0000-000000000008', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 150 DAY, NOW() - INTERVAL 60 DAY),
('ucr-009-0000-0000-0000-000000000009', 'usr-009-0000-0000-0000-000000000009', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 145 DAY, NOW() - INTERVAL 18 DAY),
('ucr-010-0000-0000-0000-000000000010', 'usr-010-0000-0000-0000-000000000010', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12, NOW() - INTERVAL 140 DAY, NOW() - INTERVAL 12 DAY);

USE ownuh_credentials;

TRUNCATE TABLE credentials;

INSERT INTO credentials (user_id, password_hash, bcrypt_cost) VALUES
('usr-001-0000-0000-0000-000000000001', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-004-0000-0000-0000-000000000004', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-005-0000-0000-0000-000000000005', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-006-0000-0000-0000-000000000006', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-007-0000-0000-0000-000000000007', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-008-0000-0000-0000-000000000008', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-009-0000-0000-0000-000000000009', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-010-0000-0000-0000-000000000010', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12);

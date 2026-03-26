-- ============================================================
-- Ownuh SAIPS — Test Seed Data
-- Password for all users: Admin@SAIPS2025!
-- ============================================================

USE ownuh_saips;

-- Clear existing data
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
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Default users (password: Admin@SAIPS2025!)
INSERT IGNORE INTO users (id, display_name, email, role, status, mfa_enrolled, mfa_factor, email_verified, email_verified_at) VALUES
('usr-001-0000-0000-0000-000000000001', 'Sophia Johnson',  'sophia.johnson@acme.com',  'superadmin', 'active',  1, 'fido2',     1, NOW()),
('usr-002-0000-0000-0000-000000000002', 'Marcus Chen',     'marcus.chen@acme.com',     'admin',      'active',  1, 'totp',      1, NOW()),
('usr-003-0000-0000-0000-000000000003', 'Priya Patel',     'priya.patel@acme.com',     'manager',    'active',  1, 'totp',      1, NOW()),
('usr-004-0000-0000-0000-000000000004', 'James Harris',    'james.harris@acme.com',    'user',       'active',  1, 'email_otp', 1, NOW()),
('usr-005-0000-0000-0000-000000000005', 'Alex Rivera',     'alex.rivera@acme.com',     'user',       'pending', 0, 'none',      0, NULL),
('usr-006-0000-0000-0000-000000000006', 'Test User',       'test@test.com',            'user',       'active',  0, 'none',      1, NOW());

-- Sample geo deny rules
INSERT IGNORE INTO geo_rules (country_code, country_name, rule_type, created_by) VALUES
('RU', 'Russia',       'deny', 'usr-001-0000-0000-0000-000000000001'),
('KP', 'North Korea',  'deny', 'usr-001-0000-0000-0000-000000000001'),
('IR', 'Iran',         'deny', 'usr-002-0000-0000-0000-000000000002');

-- Sample blocked IPs
INSERT IGNORE INTO blocked_ips (ip_address, block_type, trigger_rule, country_code, blocked_at, expires_at) VALUES
('185.220.101.47', 'tor_exit',     '20 failures / 10 min',  'NL', NOW() - INTERVAL 5 MINUTE,  NOW() + INTERVAL 55 MINUTE),
('91.108.4.200',   'geo_block',    'Country deny-list: RU', 'RU', NOW() - INTERVAL 3 HOUR,    NULL);

-- Sample incidents
INSERT IGNORE INTO incidents (incident_ref, severity, status, trigger_summary, source_ip, detected_at, assigned_to, reported_by, description, personal_data_involved) VALUES
('INC-2025-021', 'sev2', 'in_progress', 'Successful brute-force attempt detected', '185.220.101.47', NOW() - INTERVAL 5 MINUTE, 'usr-002-0000-0000-0000-000000000002', 'usr-001-0000-0000-0000-000000000001', 'Automated brute-force attack detected. IP blocked 60 min.', 0),
('INC-2025-020', 'sev3', 'open', 'Suspicious login pattern', '198.51.100.23', NOW() - INTERVAL 2 HOUR, NULL, 'usr-001-0000-0000-0000-000000000001', 'Multiple failed login attempts from unusual location.', 0);

-- Sample audit log entries
INSERT IGNORE INTO audit_log (event_code, event_name, user_id, source_ip, country_code, mfa_method, risk_score, details, created_at, entry_hash, prev_hash) VALUES
('AUTH-001', 'Successful Login', 'usr-001-0000-0000-0000-000000000001', '203.0.113.10', 'AU', 'fido2', 15, '{"session_id": "ses_8f3a2c1"}', NOW() - INTERVAL 1 HOUR, SHA2(CONCAT('GENESIS', '|AUTH-001|usr-001-0000-0000-0000-000000000001|', DATE_FORMAT(NOW() - INTERVAL 1 HOUR, '%Y-%m-%d %H:%i:%s.000')), 256), NULL),
('AUTH-002', 'Failed Login Attempt', NULL, '185.220.101.47', 'NL', NULL, 90, '{"username": "admin", "reason": "bad_password", "attempt": 4}', NOW() - INTERVAL 30 MINUTE, SHA2(CONCAT('prev_hash_placeholder', '|AUTH-002||', DATE_FORMAT(NOW() - INTERVAL 30 MINUTE, '%Y-%m-%d %H:%i:%s.000')), 256), NULL),
('IPS-001', 'IP Blocked', NULL, '185.220.101.47', 'NL', NULL, 95, '{"rule": "20 failures/10min", "duration_min": 60, "tor_exit": true}', NOW() - INTERVAL 25 MINUTE, SHA2(CONCAT('prev_hash_placeholder2', '|IPS-001||', DATE_FORMAT(NOW() - INTERVAL 25 MINUTE, '%Y-%m-%d %H:%i:%s.000')), 256), NULL);

USE ownuh_credentials;

-- Clear existing credentials
TRUNCATE TABLE credentials;

-- Insert password hashes (password: Admin@SAIPS2025!)
-- Hash generated with: password_hash('Admin@SAIPS2025!', PASSWORD_BCRYPT, ['cost' => 12])
INSERT IGNORE INTO credentials (user_id, password_hash, bcrypt_cost) VALUES
('usr-001-0000-0000-0000-000000000001', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-002-0000-0000-0000-000000000002', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-003-0000-0000-0000-000000000003', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-004-0000-0000-0000-000000000004', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-005-0000-0000-0000-000000000005', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12),
('usr-006-0000-0000-0000-000000000006', '$2y$12$6LOAfUF3/dq.RLzTCPE8EOfyVzbNjgGlODBEuP9lxveKskSZ8X2.2', 12);
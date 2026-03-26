-- ============================================================
-- Ownuh SAIPS — Audit Log Seed Patch
-- Run this in phpMyAdmin against ownuh_saips if the map is blank.
-- Inserts realistic audit entries with country codes for the heatmap.
-- ============================================================
-- ============================================================
-- Ownuh SAIPS — Audit Log Seed Patch (FIXED)
-- ============================================================

USE ownuh_saips;

-- Clean table for repeatable runs
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_log;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO audit_log
(event_code, event_name, user_id, source_ip, user_agent, country_code, mfa_method, risk_score, details, created_at)
VALUES

('AUTH-001','Successful Login','usr-001-0000-0000-0000-000000000001','203.0.113.10','Mozilla/5.0','AU','fido2',15,'{"session":"s1"}',NOW()-INTERVAL 2 HOUR),
('AUTH-001','Successful Login','usr-002-0000-0000-0000-000000000002','198.51.100.20','Mozilla/5.0','US','totp',45,'{"session":"s2"}',NOW()-INTERVAL 4 HOUR),
('AUTH-001','Successful Login','usr-004-0000-0000-0000-000000000004','103.21.244.10','Mozilla/5.0','IN','email_otp',50,'{"session":"s3"}',NOW()-INTERVAL 5 HOUR),
('AUTH-001','Successful Login','usr-004-0000-0000-0000-000000000004','192.0.2.50','Mozilla/5.0','GB','email_otp',35,'{"session":"s4"}',NOW()-INTERVAL 1 DAY),

('AUTH-002','Failed Login Attempt',NULL,'185.220.101.47','curl/7.88','NL',NULL,90,'{"attempt":1}',NOW()-INTERVAL 30 MINUTE),
('AUTH-002','Failed Login Attempt',NULL,'185.220.101.47','curl/7.88','NL',NULL,90,'{"attempt":2}',NOW()-INTERVAL 25 MINUTE);
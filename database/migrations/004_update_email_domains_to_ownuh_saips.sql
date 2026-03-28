-- ============================================================
-- Ownuh SAIPS
-- Migration: Update live/demo emails to @ownuh-saips.com
-- Target DB: ownuh_saips
-- ============================================================
--
-- Purpose:
-- - Update seeded/demo user email addresses in live tables
-- - Update historical login_attempt usernames that store emails
-- - Update alert rule destinations for email channels
--
-- Important:
-- - This migration intentionally does NOT modify audit_log.
--   audit_log is hash-chained for tamper evidence, so changing
--   historical JSON/email values would invalidate the chain.
--
-- Run this against the ownuh_saips database.
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS email_migration_map;
CREATE TEMPORARY TABLE email_migration_map (
    old_email VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
    new_email VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO email_migration_map (old_email, new_email) VALUES
('lucia.alvarez@acme.com', 'lucia.alvarez@ownuh-saips.com'),
('sophia.johnson@acme.com', 'sophia.johnson@ownuh-saips.com'),
('marcus.chen@acme.com', 'marcus.chen@ownuh-saips.com'),
('priya.patel@acme.com', 'priya.patel@ownuh-saips.com'),
('james.harris@acme.com', 'james.harris@ownuh-saips.com'),
('alex.rivera@acme.com', 'alex.rivera@ownuh-saips.com'),
('ava.thompson@acme.com', 'ava.thompson@ownuh-saips.com'),
('nina.schultz@acme.com', 'nina.schultz@ownuh-saips.com'),
('rahul.mehta@acme.com', 'rahul.mehta@ownuh-saips.com'),
('omar.farouk@acme.com', 'omar.farouk@ownuh-saips.com'),
('test@test.com', 'test@ownuh-saips.com'),
('admin@legacy.local', 'admin@ownuh-saips.com'),
('security@acme.com', 'security@ownuh-saips.com'),
('security@ownuh.local', 'security@ownuh-saips.com'),
('security@example.com', 'security@ownuh-saips.com'),
('user@example.com', 'user@ownuh-saips.com'),
('jane.doe@acme.com', 'jane.doe@ownuh-saips.com'),
('soc@acme-demo.local', 'soc@ownuh-saips.com');

-- Preview rows that will change
SELECT 'users' AS table_name, COUNT(*) AS rows_to_update
FROM users u
JOIN email_migration_map m ON m.old_email = u.email COLLATE utf8mb4_unicode_ci
UNION ALL
SELECT 'login_attempts', COUNT(*)
FROM login_attempts la
JOIN email_migration_map m ON m.old_email = la.username COLLATE utf8mb4_unicode_ci
UNION ALL
SELECT 'alert_rules', COUNT(*)
FROM alert_rules ar
JOIN email_migration_map m ON m.old_email = ar.destination COLLATE utf8mb4_unicode_ci
WHERE ar.channel = 'email';

-- Update live user emails
UPDATE users u
JOIN email_migration_map m ON m.old_email = u.email COLLATE utf8mb4_unicode_ci
SET u.email = m.new_email;

-- Update login-attempt usernames where an email address was stored
UPDATE login_attempts la
JOIN email_migration_map m ON m.old_email = la.username COLLATE utf8mb4_unicode_ci
SET la.username = m.new_email;

-- Update email-based alert destinations
UPDATE alert_rules ar
JOIN email_migration_map m ON m.old_email = ar.destination COLLATE utf8mb4_unicode_ci
SET ar.destination = m.new_email
WHERE ar.channel = 'email';

-- Optional spot-check after update
SELECT id, display_name, email
FROM users
WHERE email LIKE '%@ownuh-saips.com'
ORDER BY id;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS email_migration_map;

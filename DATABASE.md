# DATABASE.md — Ownuh SAIPS Database Schema Reference

**Engine:** MySQL 8.0+ / MariaDB 10.11+  
**Character Set:** utf8mb4 / utf8mb4_unicode_ci  
**Credentials Database:** Isolated from application database (separate access credentials per SRS §2.3)

---

## Architecture

The SAIPS uses **two separate databases** as required by SRS §2.3:

| Database | Purpose | Access User |
|----------|---------|------------|
| `ownuh_saips` | Application data: users, sessions, audit, incidents, IPS | `saips_app` |
| `ownuh_credentials` | Password hashes only — isolated store | `saips_auth` |

The `saips_app` user has **no access** to `ownuh_credentials`. Authentication queries use a dedicated `saips_auth` connection that can only SELECT/UPDATE the credentials table.

---

## Tables — `ownuh_saips`

### `users`

Stores all user account metadata. Passwords are never stored here.

```sql
CREATE TABLE users (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    display_name    VARCHAR(120)    NOT NULL,
    email           VARCHAR(254)    NOT NULL UNIQUE,
    role            ENUM('superadmin','admin','manager','user') NOT NULL DEFAULT 'user',
    status          ENUM('active','locked','suspended','pending') NOT NULL DEFAULT 'pending',
    mfa_enrolled    TINYINT(1)      NOT NULL DEFAULT 0,
    mfa_factor      ENUM('fido2','totp','email_otp','sms','none') NOT NULL DEFAULT 'none',
    mfa_bypass_token VARCHAR(128)   NULL,
    mfa_bypass_expiry DATETIME      NULL,
    failed_attempts  TINYINT        NOT NULL DEFAULT 0,
    last_failed_at   DATETIME       NULL,
    last_login_at    DATETIME       NULL,
    last_login_ip    VARCHAR(45)    NULL,
    last_login_country CHAR(2)      NULL,
    password_changed_at DATETIME    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,  -- soft delete, 30-day recovery window

    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_role (role),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `sessions`

All active JWT refresh tokens. Access tokens are stateless (JWT); only refresh tokens are stored server-side in Redis AND this table for forensic purposes.

```sql
CREATE TABLE sessions (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id         CHAR(36)        NOT NULL,
    refresh_token_hash VARCHAR(128) NOT NULL UNIQUE,  -- SHA-256 of token
    ip_address      VARCHAR(45)     NOT NULL,
    user_agent      TEXT            NULL,
    device_fingerprint VARCHAR(128) NULL,
    mfa_method      VARCHAR(20)     NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME        NOT NULL,
    last_used_at    DATETIME        NULL,
    invalidated_at  DATETIME        NULL,
    invalidated_by  CHAR(36)        NULL,  -- admin user_id if force-revoked
    invalidation_reason VARCHAR(255) NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_invalidated (invalidated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `audit_log`

Tamper-evident, append-only audit log. SHA-256 chained entries. No DELETE privilege granted to `saips_app`.

```sql
CREATE TABLE audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_code      VARCHAR(20)     NOT NULL,   -- AUTH-001, IPS-001, ADM-001, SES-001 etc.
    event_name      VARCHAR(100)    NOT NULL,
    user_id         CHAR(36)        NULL,        -- NULL for IP-only events
    source_ip       VARCHAR(45)     NULL,
    user_agent      TEXT            NULL,
    country_code    CHAR(2)         NULL,
    device_fingerprint VARCHAR(128) NULL,
    mfa_method      VARCHAR(20)     NULL,
    risk_score      TINYINT         NULL,        -- 0-100
    details         JSON            NULL,        -- event-specific data per SRS §4.1
    admin_id        CHAR(36)        NULL,        -- for ADM-* events
    target_user_id  CHAR(36)        NULL,        -- for ADM-* events
    created_at      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    -- SHA-256 chain: hash of (prev_hash || event_code || user_id || created_at || details)
    entry_hash      CHAR(64)        NOT NULL,
    prev_hash       CHAR(64)        NULL,        -- NULL for first entry

    INDEX idx_event_code (event_code),
    INDEX idx_user_id (user_id),
    INDEX idx_source_ip (source_ip),
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- IMPORTANT: saips_app has INSERT only — no UPDATE or DELETE on this table
```

**Event codes per SRS §4.1:**

| Code | Event |
|------|-------|
| AUTH-001 | Successful login |
| AUTH-002 | Failed login attempt |
| AUTH-003 | Account locked |
| AUTH-004 | Account unlocked |
| AUTH-005 | Password changed |
| AUTH-006 | MFA enrolled |
| AUTH-007 | MFA bypass issued |
| SES-001 | Session created |
| SES-002 | Session expired |
| SES-003 | Session invalidated |
| IPS-001 | IP blocked |
| IPS-002 | Brute-force detected |
| IPS-003 | Geo-block triggered |
| ADM-001 | Admin login |
| ADM-002 | User record modified |
| ADM-003 | Role/permission changed |

---

### `mfa_totp_secrets`

TOTP shared secrets. Encrypted at rest (AES-256).

```sql
CREATE TABLE mfa_totp_secrets (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id         CHAR(36)        NOT NULL UNIQUE,
    secret_encrypted TEXT           NOT NULL,   -- AES-256-GCM encrypted TOTP secret
    algorithm       VARCHAR(10)     NOT NULL DEFAULT 'SHA1',
    digits          TINYINT         NOT NULL DEFAULT 6,
    period          TINYINT         NOT NULL DEFAULT 30,
    enrolled_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    DATETIME        NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `mfa_backup_codes`

Ten single-use recovery codes per user. Stored as bcrypt hashes.

```sql
CREATE TABLE mfa_backup_codes (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id         CHAR(36)        NOT NULL,
    code_hash       VARCHAR(72)     NOT NULL,   -- bcrypt hash
    used_at         DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `mfa_fido2_credentials`

FIDO2/WebAuthn hardware key registrations.

```sql
CREATE TABLE mfa_fido2_credentials (
    id                  CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id             CHAR(36)        NOT NULL,
    credential_id       VARBINARY(1024) NOT NULL UNIQUE,
    public_key          TEXT            NOT NULL,
    sign_count          INT UNSIGNED    NOT NULL DEFAULT 0,
    device_description  VARCHAR(255)    NULL,
    enrolled_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at        DATETIME        NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `blocked_ips`

IP addresses blocked by the IPS. Auto-expiry via scheduled cleanup.

```sql
CREATE TABLE blocked_ips (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    ip_address      VARCHAR(45)     NOT NULL,
    block_type      ENUM('brute_force','geo_block','threat_feed','tor_exit','manual') NOT NULL,
    trigger_rule    VARCHAR(255)    NULL,
    country_code    CHAR(2)         NULL,
    threat_feed     VARCHAR(50)     NULL,       -- AbuseIPDB, Spamhaus, etc.
    blocked_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME        NULL,       -- NULL = permanent
    unblocked_at    DATETIME        NULL,
    unblocked_by    CHAR(36)        NULL,       -- admin user_id

    INDEX idx_ip (ip_address),
    INDEX idx_expires (expires_at),
    INDEX idx_active (unblocked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `login_attempts`

Rolling window table for brute-force detection. Purged automatically after 24 hours.

```sql
CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(254)    NULL,
    ip_address      VARCHAR(45)     NOT NULL,
    success         TINYINT(1)      NOT NULL DEFAULT 0,
    failure_reason  VARCHAR(100)    NULL,
    attempted_at    DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_user_time (username, attempted_at),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Partitioned by day in production; purge job runs every hour
```

---

### `geo_rules`

Country allow/deny list for geo-blocking.

```sql
CREATE TABLE geo_rules (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    country_code    CHAR(2)         NOT NULL UNIQUE,
    country_name    VARCHAR(100)    NOT NULL,
    rule_type       ENUM('deny','allow') NOT NULL DEFAULT 'deny',
    created_by      CHAR(36)        NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_country (country_code),
    INDEX idx_type (rule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `incidents`

Security incident records per SRS §5.

```sql
CREATE TABLE incidents (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    incident_ref    VARCHAR(20)     NOT NULL UNIQUE,  -- INC-2025-001 format
    severity        ENUM('sev1','sev2','sev3','sev4') NOT NULL,
    status          ENUM('open','in_progress','under_review','resolved','closed') NOT NULL DEFAULT 'open',
    trigger_summary TEXT            NOT NULL,
    affected_user_id CHAR(36)       NULL,
    source_ip       VARCHAR(45)     NULL,
    detected_at     DATETIME        NOT NULL,
    acknowledged_at DATETIME        NULL,
    resolved_at     DATETIME        NULL,
    assigned_to     CHAR(36)        NULL,
    reported_by     CHAR(36)        NOT NULL,
    description     TEXT            NOT NULL,
    actions_taken   TEXT            NULL,
    personal_data_involved TINYINT(1) NOT NULL DEFAULT 0,
    gdpr_notification_required TINYINT(1) NOT NULL DEFAULT 0,
    gdpr_notified_at DATETIME       NULL,
    related_audit_entries JSON      NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_detected (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `rate_limit_config`

Configurable rate limit rules, editable via admin panel.

```sql
CREATE TABLE rate_limit_config (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    endpoint        VARCHAR(100)    NOT NULL UNIQUE,
    requests_limit  SMALLINT        NOT NULL,
    window_seconds  INT             NOT NULL,
    scope           ENUM('per_ip','per_user','per_token') NOT NULL DEFAULT 'per_ip',
    action_on_breach ENUM('block_temp','block_perm','soft_lock','rate_429') NOT NULL DEFAULT 'rate_429',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    updated_by      CHAR(36)        NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `password_history`

Stores hashes of last 12 passwords to prevent cyclic reuse (SRS §2.2).

```sql
CREATE TABLE password_history (
    id              CHAR(36)        NOT NULL PRIMARY KEY DEFAULT (UUID()),
    user_id         CHAR(36)        NOT NULL,
    password_hash   VARCHAR(72)     NOT NULL,   -- bcrypt
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## `ownuh_credentials` (Isolated Database)

```sql
-- Run as root, NOT as saips_app
CREATE DATABASE ownuh_credentials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'saips_auth'@'localhost' IDENTIFIED BY 'separate_strong_password';
GRANT SELECT, UPDATE ON ownuh_credentials.credentials TO 'saips_auth'@'localhost';

CREATE TABLE credentials (
    user_id         CHAR(36)        NOT NULL PRIMARY KEY,
    password_hash   VARCHAR(72)     NOT NULL,   -- bcrypt cost 12
    bcrypt_cost     TINYINT         NOT NULL DEFAULT 12,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    -- No foreign key — this DB has no knowledge of ownuh_saips
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entire database encrypted at rest via AES-256 (InnoDB tablespace encryption)
ALTER INSTANCE ROTATE INNODB MASTER KEY;
```

---

## Indexes & Performance Notes

- `audit_log` is high-write. In production, use InnoDB partitioning by month on `created_at`.
- `login_attempts` should be purged hourly: `DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 25 HOUR;`
- `sessions` expiry cleanup: `DELETE FROM sessions WHERE expires_at < NOW() AND invalidated_at IS NULL;` (run hourly via cron).
- Redis is the primary session store (fast path). MySQL sessions table is the forensic backup.

---

## Backup & Retention

| Table | Online Retention | Archive Retention |
|-------|-----------------|-------------------|
| `audit_log` (AUTH/SES/IPS events) | 90 days | 2 years |
| `audit_log` (ADM events) | 1 year | 7 years |
| `sessions` | 30 days post-expiry | 90 days |
| `incidents` | 2 years | 7 years |
| `credentials` | Current only (versioned via `password_history`) | N/A |

---

*See `schema.sql` for the complete runnable DDL.*

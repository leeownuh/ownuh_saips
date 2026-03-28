# Ownuh SAIPS Architecture

## Overview

Ownuh SAIPS is a PHP security administration platform centered around four core areas:

- authentication and session management
- MFA and account recovery
- IPS / threat-control visibility
- audit, incident, and compliance reporting

## Main Components

### Application layer

- Public pages live at the project root, for example [login.php](/c:/xampp/htdocs/ownuh_saips_fixed/login.php), [dashboard.php](/c:/xampp/htdocs/ownuh_saips_fixed/dashboard.php), and [users.php](/c:/xampp/htdocs/ownuh_saips_fixed/users.php).
- API handlers live under [backend/api](/c:/xampp/htdocs/ownuh_saips_fixed/backend/api).
- Shared bootstrap helpers, DB access, and dashboard queries live in [backend/bootstrap.php](/c:/xampp/htdocs/ownuh_saips_fixed/backend/bootstrap.php).

### Data stores

- `ownuh_saips` stores users, sessions, incidents, audit entries, IPS data, and password reset state.
- `ownuh_credentials` stores password hashes in a separate credentials table.
- Redis is used as an accelerator for sessions, MFA state, queues, and rate limiting when available.

## Authentication Flow

1. A user submits credentials to the login flow.
2. The app checks the isolated credentials store.
3. If the risk and policy checks pass, the app either:
   - completes sign-in directly, or
   - moves the user into MFA verification.
4. A JWT access token and refresh-token registry entry are created for active sessions.

## MFA Flow

Supported factors include:

- FIDO2 / WebAuthn
- TOTP
- Email OTP
- backup codes
- admin-issued bypass token for recovery

Important boundary:

- `otp-verify.php` can consume a pre-issued bypass token
- bypass tokens are issued by admin-controlled flows, not self-created by the MFA page

## Password Recovery Flow

There are two password-reset paths:

- self-service reset for standard users
- admin-initiated reset for recovery/support scenarios

Both now write to the same canonical `password_resets` table, which stores:

- token hash
- target user
- optional initiating admin
- requested IP
- requested time
- expiry
- used state

## Audit Design

- Authentication and security events are written to `audit_log`
- Entries are chained with hashes for tamper-evidence
- Local development can fall back to direct table inserts when the old stored procedure is unavailable

## IPS Design

IPS views and APIs use:

- `login_attempts` for brute-force evidence
- `blocked_ips` for active enforcement state
- `geo_rules` for country allow/deny rules
- `rate_limit_config` for endpoint throttling rules

## Demo vs Production Boundary

This repository is portfolio-ready, not production-hardened end to end.

The recruiter-facing setup uses:

- [database/portfolio_seed.sql](/c:/xampp/htdocs/ownuh_saips_fixed/database/portfolio_seed.sql)
- realistic but fictional users, incidents, audit events, and IPS data

Some MFA records are seeded for dashboard realism rather than full device usability.

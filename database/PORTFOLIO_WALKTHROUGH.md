# Portfolio Demo Walkthrough

This dataset is designed for recruiter demos and portfolio walkthroughs.

## Primary Login

Use this account for a reliable admin demo:

- Email: `lucia.alvarez@acme.com`
- Password: `Admin@SAIPS2025!`
- Role: `admin`
- MFA: `email_otp`

This account is the best default because it can reach the admin experience without requiring real TOTP or hardware security keys.

## Strong Demo Storylines

### 1. Security Dashboard

Show:

- active users and MFA coverage
- blocked IP totals
- open incidents
- recent audit events
- geographic login spread

Why it works:

- the seed includes multi-country successful logins, recent brute-force activity, and active sessions

### 2. User Management

Show:

- role distribution across superadmin, admin, manager, and user
- mixed account states: active, locked, pending, suspended
- realistic last login and password-change timestamps

Best records:

- `priya.patel@acme.com` for locked-account handling
- `alex.rivera@acme.com` for pending onboarding
- `ava.thompson@acme.com` for suspended-user review

### 3. MFA Operations

Show:

- factor distribution across FIDO2, TOTP, Email OTP, and no MFA
- backup-code inventory
- TOTP-enrollment dates for selected users

Best records:

- `sophia.johnson@acme.com` for superadmin with strong MFA posture
- `marcus.chen@acme.com` and `nina.schultz@acme.com` for TOTP coverage
- `lucia.alvarez@acme.com` and `rahul.mehta@acme.com` for Email OTP

### 4. IPS / Threat Controls

Show:

- blocked IPs across `tor_exit`, `geo_block`, `brute_force`, `threat_feed`, and `manual`
- deny and allow geo rules
- brute-force evidence tied to a locked account

Best records:

- IP `198.54.117.212` for brute-force
- IP `91.108.4.200` for geo-block
- IP `45.83.64.19` for threat-feed action

### 5. Incident Response

Show:

- incident lifecycle across `in_progress`, `under_review`, `resolved`, and `closed`
- assignment and reporting relationships
- linked audit evidence

Best records:

- `INC-2026-031` for credential stuffing response
- `INC-2026-029` for MFA bypass recovery

## Notes

- All identities and records are fictional.
- The seed is optimized for presentation, not for integration testing.
- Some users are marked as TOTP or FIDO2 enrolled for realism, but the seed does not include real usable device secrets.

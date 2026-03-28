# SECURITY.md â€” Ownuh SAIPS Security Policy & Hardening Guide

**Classification:** CONFIDENTIAL  
**Owner:** Security Officer  
**Review Cycle:** Quarterly

---

## Security Architecture Summary

The Ownuh SAIPS implements a layered defence model:

```
Layer 1: Network       â€” TLS 1.3, HSTS, WAF, geo-blocking
Layer 2: Transport     â€” CSP, CSRF tokens, secure cookies
Layer 3: Authentication â€” bcrypt/TOTP/FIDO2/JWT RS256
Layer 4: Authorisation  â€” RBAC, session limits, idle timeouts
Layer 5: Detection      â€” Brute-force, IP reputation, risk scoring
Layer 6: Response       â€” Auto-lockout, IP block, alert dispatch
Layer 7: Audit          â€” SHA-256 chained, append-only, tamper-evident
```

AI-assisted executive reporting sits on top of these layers as a read-mostly decision-support feature. It summarizes posture data for leadership review, but it does not directly enforce blocks, revoke sessions, or change security state without human action.

---

## Password Security (SRS Â§2.2)

| Control | Value | Standard |
|---------|-------|---------|
| Hashing algorithm | bcrypt | OWASP |
| Work factor | 12 (min), auto-upgrade to 14 | OWASP |
| Min length | 12 characters | NIST SP 800-63B |
| Max length | 128 characters | OWASP |
| Complexity | 3-of-4 character classes | ISO 27001 |
| Blacklist | HIBP API + custom dictionary | OWASP |
| History | Last 12 passwords blocked | PCI DSS |
| Expiry (standard) | 180 days | â€” |
| Expiry (privileged) | 90 days | ISO 27001 |
| Similarity check | Levenshtein distance vs email/username | â€” |
| Plaintext logging | **Never** | All |
| Storage | Isolated credentials DB, AES-256 at rest | ISO 27001 |

---

## MFA Requirements (SRS Â§2.4)

| Role | Required Factor | Notes |
|------|----------------|-------|
| Superadmin | FIDO2/WebAuthn only | Hardware key mandatory |
| Admin | FIDO2/WebAuthn only | Hardware key mandatory |
| Manager | Any enrolled factor | TOTP recommended |
| User | Optional (encouraged) | Email OTP minimum if enrolled |

### TOTP Parameters
- Algorithm: HMAC-SHA1 (RFC 6238)
- Digits: 6
- Period: 30 seconds
- Tolerance: Â±1 step (prevents clock skew failures)

### Backup Codes
- Count: 10 per user
- Storage: bcrypt hashes (cost 12)
- Usage: Single-use only
- Regeneration: Required after full set consumed

---

## Session Management (SRS Â§3.4)

| Parameter | Standard Users | Admin Accounts |
|-----------|---------------|----------------|
| Access token TTL | 15 minutes | 15 minutes |
| Refresh token TTL | 7 days | 8 hours |
| Concurrent sessions | 3 | 1 |
| Idle timeout | 60 minutes | 15 minutes |
| Algorithm | RS256 | RS256 |
| Token rotation | Every refresh | Every refresh |

**Session fixation prevention:** New session ID issued on every privilege elevation.

---

## Brute-Force Thresholds (SRS Â§3.1 & Â§3.2)

| Trigger | Threshold | Response |
|---------|-----------|---------|
| Per-username failures | 5 in 15 min | Soft-lock 30 min + CAPTCHA |
| Per-username failures | 10 in 24h | Hard-lock (admin review required) |
| Per-IP failures | 20 in 10 min | IP block 60 min |
| Distributed attack | 100 across IPs in 5 min | WAF rule deployed + security team paged |
| Credential stuffing | Unusual success rate from new IPs | Sessions invalidated + user email |
| Progressive delays | After 3 failures: 5s, doubles per failure, cap 60s | â€” |

---

## IP Reputation & Threat Intelligence (SRS Â§3.3)

Feeds checked on every authentication request (cached locally, updated every 6 hours):

- **AbuseIPDB** â€” reported abuse history
- **Spamhaus** â€” SBL/XBL/PBL blocklists
- **Emerging Threats** â€” active threat IPs
- **Tor Exit Node List** â€” updated daily
- **Internal reputation cache** â€” SAIPS-generated blocks

**Tor policy:**
- Standard accounts: Enhanced MFA required
- Admin accounts: Tor access **blocked entirely**

---

## Audit Log Integrity (SRS Â§4.2)

Each log entry stores:
- `entry_hash`: SHA-256 of `prev_hash || event_code || user_id || timestamp || details`
- `prev_hash`: Hash of previous entry (chain)

Verification command:
```bash
php backend/scripts/verify-audit-chain.php --from="2025-03-01" --to="2025-03-21"
```

A broken chain indicates tampering. Alert immediately if chain verification fails.

Important operational note:
- Email-domain migrations intentionally do not rewrite historical `audit_log` records, because mutating old entries would break hash-chain verification.

**Access controls:**
- `saips_app` DB user: INSERT only on `audit_log` (no UPDATE/DELETE)
- Physical backup replica: read-only, offsite

---

## Incident Response SLAs (SRS Â§5.1)

| Severity | Trigger | Required Response Time |
|----------|---------|----------------------|
| SEV-1 Critical | Active breach, credential theft, data exfiltration | Immediate: isolate + CSIRT + DPO within 1 hour |
| SEV-2 High | Successful brute-force, privilege escalation, MFA bypass suspected | 15 minutes: lock accounts + preserve logs |
| SEV-3 Medium | Repeated failures, IP reputation hit, new admin location | 2 hours: review + assess + notify user |
| SEV-4 Low | Single failure, minor violation, anomalous user-agent | Next daily report |

**GDPR Art. 33:** If personal data may be involved in a SEV-1, the Data Protection Officer must be notified within **72 hours** of detection. Prepare for regulatory notification if required.

---

## Admin Account Controls (SRS Â§6.1)

- FIDO2/WebAuthn hardware key mandatory for all Admin/Superadmin accounts
- Sessions expire after **15 minutes idle** and **8 hours absolute**
- Only **1 concurrent session** permitted per admin account
- New device/location login triggers immediate email + SMS notification
- **No self-service password reset** â€” requires dual-authorisation from a second admin
- All admin actions logged to `ADM-001`, `ADM-002`, `ADM-003`

---

## OWASP Top 10 Controls

| # | Risk | Control Implemented |
|---|------|-------------------|
| A01 | Broken Access Control | RBAC with role enforcement on every endpoint, JWT validation middleware |
| A02 | Cryptographic Failures | TLS 1.3 only, bcrypt cost 12, AES-256 at rest, RS256 JWT |
| A03 | Injection | Prepared statements throughout, parameterised queries, input validation |
| A04 | Insecure Design | Threat modelling, defence-in-depth, zero-trust session management |
| A05 | Security Misconfiguration | HSTS, CSP, X-Frame-Options, no directory listing, no error disclosure |
| A06 | Vulnerable Components | Composer audit in CI/CD, monthly dependency review |
| A07 | Auth & Session Failures | bcrypt, MFA, JWT rotation, brute-force detection, idle timeout |
| A08 | Software & Data Integrity | CSRF tokens, SRI for CDN assets (none used â€” self-hosted) |
| A09 | Logging & Monitoring | SHA-256 chained audit log, real-time alerting, 90-day online retention |
| A10 | Server-Side Request Forgery | No user-controlled URL fetching; allowlist for outbound requests |

---

## AI Reporting Guardrails

- Executive-report generation is advisory only and requires human review
- Only posture snapshot data needed for summary generation should be sent to the model
- Structured output is preferred over free-form generation for predictable rendering
- If `OPENAI_API_KEY` is absent, the app falls back to a deterministic local summary instead of failing open
- Weekly executive-report emails are sent only to active `admin` and `superadmin` accounts

---

## Compliance Targets

| Standard | Status | Notes |
|----------|--------|-------|
| NIST SP 800-63B AAL2 | âœ… Implemented | MFA required for medium/high risk logins |
| OWASP Top 10 2021 | âœ… Addressed | See table above |
| ISO/IEC 27001 Access Control | âœ… Implemented | RBAC, audit trail, access reviews |
| GDPR Article 32 | âœ… Implemented | Encryption, access control, pseudonymisation |
| GDPR Article 33 | âœ… Procedure documented | 72h notification SLA in incident response |
| SOC 2 Type II â€” CC6 | âœ… Implemented | Logical access, MFA, session management |
| PCI DSS (where applicable) | âœ… Aligned | Password history, lockout policy |

---

## Security Contact

- **Security Officer:** sophia.johnson@ownuh-saips.com
- **Incident Hotline:** +XX-XXX-XXX-XXXX (24/7)
- **Out-of-band channel:** Encrypted Signal group "SAIPS-SecOps"

---

## Responsible Disclosure

To report a vulnerability, contact security@ownuh-saips.com with:
1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact assessment
4. Suggested remediation (if known)

We aim to acknowledge reports within 24 hours and provide a fix timeline within 5 business days.

---

*Ownuh Â© 2025 â€” All Rights Reserved. CONFIDENTIAL.*

# API.md — Ownuh SAIPS REST API Reference

**Base URL:** `https://saips.your-domain.com/api/v1`  
**Auth:** Bearer JWT (RS256) in `Authorization` header  
**Transport:** TLS 1.3 only — HTTP connections are rejected  
**Rate limits:** See `ips-rate-limits.html` or `rate_limit_config` table

---

## Authentication

All endpoints except `/auth/login` and `/auth/password/reset` require a valid JWT access token:

```
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
```

JWT payload structure:
```json
{
  "sub": "usr-001-...",
  "email": "sophia.johnson@acme.com",
  "role": "superadmin",
  "mfa_method": "fido2",
  "iat": 1742400000,
  "exp": 1742400900,
  "iss": "ownuh-saips",
  "jti": "unique-token-id"
}
```

Access tokens expire in **15 minutes**. Use `/auth/token/refresh` with your refresh token to obtain a new pair.

---

## Auth Endpoints

### `POST /auth/login`

Initiates authentication. Returns JWT directly (low risk) or triggers MFA flow (medium/high risk).

**Rate limit:** 60 req/min per IP — 429 + temp block on breach

**Request:**
```json
{
  "email": "sophia.johnson@acme.com",
  "password": "YourPassword123!",
  "device_fingerprint": "optional-client-fp-hash"
}
```

**Response — Low risk (MFA not required):**
```json
{
  "status": "success",
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "expires_in": 900,
  "user": { "id": "usr-001...", "role": "superadmin", "mfa_method": "fido2" }
}
```

**Response — Medium risk (MFA required):**
```json
{
  "status": "mfa_required",
  "mfa_token": "temp-session-token",
  "mfa_factors": ["fido2", "backup_code"],
  "risk_level": "medium"
}
```

**Response — High risk (blocked):**
```json
{
  "status": "blocked",
  "message": "Login denied. Contact your administrator."
}
```
> Note: Geo-blocked responses intentionally return a generic error with no geo-block information.

**Audit:** `AUTH-001` (success) or `AUTH-002` (failure)

---

### `POST /auth/mfa/verify`

Verifies the MFA code after a `mfa_required` response.

**Rate limit:** 5 req/15 min per user — soft-lock on breach

**Request:**
```json
{
  "mfa_token": "temp-session-token",
  "factor": "totp",
  "code": "482913"
}
```

**Response:**
```json
{
  "status": "success",
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "expires_in": 900
}
```

---

### `POST /auth/token/refresh`

Exchanges a refresh token for a new access + refresh token pair (sliding window rotation).

**Request:**
```json
{ "refresh_token": "eyJ..." }
```

**Response:**
```json
{
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "expires_in": 900
}
```

> The old refresh token is immediately invalidated. Reuse of a revoked token triggers a security alert and full session revocation.

---

### `POST /auth/logout`

Invalidates the current session's refresh token.

**Headers:** `Authorization: Bearer <access_token>`

**Response:**
```json
{ "status": "success" }
```

**Audit:** `SES-002`

---

### `POST /auth/password/reset`

Requests a password reset link. Always returns success to prevent username enumeration.

**Request:**
```json
{ "email": "user@example.com" }
```

---

### `POST /auth/password/change`

Changes the authenticated user's password.

**Request:**
```json
{
  "current_password": "OldPass123!",
  "new_password": "NewPass456#",
  "new_password_confirm": "NewPass456#"
}
```

**Validation enforced:**
- Min 12 chars, max 128
- 3-of-4 character classes
- Not in HIBP + custom dictionary
- Not in last 12 passwords
- Not resembling email or username (Levenshtein check)

**Audit:** `AUTH-005`

---

## IPS Endpoints (Admin+)

### `GET /ips/blocked`

Returns all currently active IP blocks.

**Query params:** `?type=brute_force|geo_block|tor_exit|threat_feed|manual&page=1&per_page=50`

### `POST /ips/blocked`

Manually block an IP address.

```json
{
  "ip_address": "1.2.3.4",
  "block_type": "manual",
  "duration_minutes": 60,
  "reason": "Suspicious scraping activity"
}
```

### `DELETE /ips/blocked/{id}`

Unblock an IP. Requires Admin role. Logged to `IPS-001` audit.

### `GET /ips/geo-rules`

Returns country allow/deny list.

### `POST /ips/geo-rules`

Add a country block.

```json
{ "country_code": "CN", "rule_type": "deny" }
```

---

## Sessions Endpoints (Admin+)

### `GET /sessions`

List active sessions. Admins see all; users see only their own.

**Query params:** `?user_id=...&role=admin&page=1`

### `DELETE /sessions/{id}`

Revoke a specific session.

```json
{ "reason": "Suspicious activity reported by user" }
```

**Audit:** `SES-003`

### `DELETE /sessions/user/{user_id}`

Revoke all sessions for a user.

```json
{ "reason": "Account compromise suspected" }
```

---

## Audit Log (Admin+)

### `GET /audit/log`

Retrieve audit log entries with filtering.

**Query params:**
- `event_code=AUTH-001`
- `user_id=usr-001...`
- `source_ip=185.220.101.47`
- `status=failed|completed|blocked`
- `from=2025-03-21T00:00:00Z`
- `to=2025-03-21T23:59:59Z`
- `page=1&per_page=100`

**Response:**
```json
{
  "total": 847,
  "page": 1,
  "data": [
    {
      "id": 1042,
      "event_code": "AUTH-001",
      "event_name": "Successful Login",
      "user_id": "usr-001...",
      "source_ip": "203.0.113.10",
      "country_code": "AU",
      "mfa_method": "fido2",
      "risk_score": 15,
      "created_at": "2025-03-21T14:23:07.000Z",
      "entry_hash": "abc123..."
    }
  ],
  "integrity_verified": true
}
```

### `GET /audit/log/export`

Export log as CSV. Superadmin only. Export action itself is logged.

---

## User Management (Admin+)

### `GET /users`

List users with filtering.

### `POST /users`

Create a new user account.

```json
{
  "display_name": "Jane Doe",
  "email": "jane.doe@acme.com",
  "role": "user"
}
```

**Audit:** `ADM-002`

### `PUT /users/{id}`

Update user profile fields.

**Audit:** `ADM-002`

### `POST /users/{id}/lock`

Lock a user account.

```json
{ "reason": "Policy violation", "lock_type": "hard" }
```

**Audit:** `AUTH-003`

### `POST /users/{id}/unlock`

Unlock a user account. Requires Admin role.

**Audit:** `AUTH-004`

### `PUT /users/{id}/role`

Change user role. Logged to `ADM-003` with old and new role values.

### `DELETE /users/{id}/sessions`

Revoke all sessions for a user. Equivalent to `DELETE /sessions/user/{id}`.

---

## Incidents (Admin+)

### `GET /incidents`

List incidents with filtering by severity and status.

### `POST /incidents`

File a new incident report.

```json
{
  "severity": "sev2",
  "trigger_summary": "Brute-force on priya.patel",
  "source_ip": "185.220.101.47",
  "detected_at": "2025-03-21T14:18:35Z",
  "description": "10 failures in 24h window...",
  "personal_data_involved": false
}
```

### `PUT /incidents/{id}`

Update incident status or add notes.

---

## Error Responses

All errors follow a consistent format:

```json
{
  "status": "error",
  "code": "RATE_LIMITED",
  "message": "Too many requests. Try again in 47 seconds.",
  "retry_after": 47
}
```

Common error codes: `UNAUTHORIZED`, `FORBIDDEN`, `RATE_LIMITED`, `ACCOUNT_LOCKED`, `MFA_REQUIRED`, `INVALID_TOKEN`, `VALIDATION_ERROR`, `NOT_FOUND`, `SERVER_ERROR`

---

## Security Headers (All Responses)

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
Content-Security-Policy: default-src 'self'; script-src 'self'; ...
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

*See `DEPLOYMENT.md` for Nginx configuration to set these headers at the server layer.*

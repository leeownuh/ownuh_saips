# Known Limitations

## Portfolio positioning

This repository is intentionally tuned as a portfolio-grade, read-only demo environment.
The defaults are optimized for fast local spin-up and a confident walkthrough, not for
full production hardening.

What that means in practice:

- rapid local installation and repeatable demos
- strong visual/admin narrative flow
- realistic fictional operational data that looks alive

## Current limitations

### 1. Demo seed vs live-security guarantees

The default setup imports `database/portfolio_seed.sql`, which is crafted for presentation quality.
Some seeded MFA records exist to support admin views and dashboards, not to fully emulate
hardware-backed enrollments end to end.

The guest-facing demo lane prioritizes safe storytelling over strict operational parity:

- some values are intentionally masked or tokenised
- some admin controls are intentionally read-only
- the goal is to protect the environment while still showing the product honestly

### 2. Redis is optional in local setup

Core flows can run without Redis locally, but these areas are stronger with Redis enabled:

- rate limiting
- async queue behavior
- fast session invalidation
- MFA pending state

### 3. Test coverage is lightweight

The repo includes lightweight guard checks, but it does not yet ship with a full PHPUnit/integration
suite across all auth, IPS, and reporting flows.

### 4. Local setup is prioritized

The Windows and Linux setup scripts are tuned for local demos first. Production deployment still
requires a tighter operational pass on:

- secrets management
- HTTPS termination
- Redis hardening
- email delivery
- queue workers
- monitoring and alert routing

### 5. Recent iteration and consolidation

The repository has gone through active iteration, including older reset/setup paths. The current
setup and password-reset flow are now aligned around the canonical scripts and the
`password_resets` table, but the project should still be treated as an actively refined
portfolio artifact.

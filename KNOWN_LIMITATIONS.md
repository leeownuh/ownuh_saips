# Known Limitations

## Portfolio positioning

This repository is curated as a portfolio project and recruiter demo environment.

That means the setup defaults favor:

- fast local installation
- strong visual/admin walkthroughs
- realistic fictional operational data

over full production hardening.

## Current limitations

### 1. Demo seed vs live-security guarantees

The default setup imports `database/portfolio_seed.sql`, which is designed for presentation quality. Some seeded MFA-related records exist to improve admin views and dashboards, not to model every hardware-backed enrollment path end to end.

### 2. Redis is optional in local setup

Core flows work without Redis in some local scenarios, but certain features are stronger when Redis is present:

- rate limiting
- async queue behavior
- fast session invalidation
- MFA pending state

### 3. Test coverage is lightweight

The repository includes lightweight repo-guard checks, but it does not yet ship with a full PHPUnit/integration suite for all auth and IPS flows.

### 4. Local setup is prioritized

The Windows and Linux setup scripts are optimized for local demo environments first. Production deployment still requires a tighter operational pass on:

- secrets management
- HTTPS termination
- Redis hardening
- email delivery
- queue workers
- monitoring and alert routing

### 5. Some legacy drift was recently normalized

The repository has gone through active iteration, including older reset/setup paths. The current setup and password-reset flow are now aligned around canonical scripts and the `password_resets` table, but the project should still be treated as an actively refined portfolio artifact.

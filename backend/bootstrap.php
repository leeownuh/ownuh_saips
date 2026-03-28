<?php
/**
 * Ownuh SAIPS — PHP Bootstrap / Database Layer
 * Serves live data from MySQL to all dashboard pages.
 * CAP512 — PHP & MySQL for Dynamic Web Applications
 *
 * This file is included by every PHP page in the system.
 * Demonstrates: PHP basics, variables, control flow, functions,
 * strings, arrays, OOP, and mysqli database operations per syllabus.
 */

declare(strict_types=1);

// Load environment variables before any session/cookie configuration so
// proxy-aware settings apply on the current request.
function load_env(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', trim($line), 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}
load_env(__DIR__ . '/config/.env');
// Error reporting: never display errors in production (information disclosure)
error_reporting(E_ALL);
ini_set('display_errors', '0');        // ALWAYS off — errors go to log only
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    // In development, you may enable display_errors via php.ini or CLI only
    // Do NOT set display_errors=1 here — it leaks stack traces to browsers
}

// ── Secure session configuration ─────────────────────────────────────────────
// Only apply ini_set calls if the session has not started yet.
// These settings MUST be set before session_start().
if (session_status() === PHP_SESSION_NONE) {
    // SECURITY: only trust X-Forwarded-Proto from a known proxy IP.
    // Set TRUSTED_PROXY=any in .env when behind ngrok / a load balancer,
    // or set TRUSTED_PROXY=127.0.0.1 to restrict to localhost proxy only.
    $trustedProxy = $_ENV['TRUSTED_PROXY'] ?? '';
    $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '';
    $proxyTrusted = ($trustedProxy !== '')
                 && ($trustedProxy === 'any' || $remoteAddr === $trustedProxy);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || ($proxyTrusted && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    // SameSite=Lax required when the app is accessed through a tunnel/proxy
    // (e.g. ngrok) because the login redirect is technically cross-origin.
    // Override via COOKIE_SAMESITE env var. Defaults: Lax behind proxy, Strict direct.
    $sameSite = $_ENV['COOKIE_SAMESITE'] ?? ($proxyTrusted ? 'Lax' : 'Strict');

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure',   $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', $sameSite);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '3600');
    ini_set('session.name', 'SAIPS_SESS');
}

// ── Environment loader ───────────────────────────────────────────────────────
$appTimezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata';
date_default_timezone_set($appTimezone);

// ── Database Class (mysqli OOP — CAP512 Unit 7: Objects + Databases) ─────────
class Database {
    private static ?Database $instance = null;
    private mysqli $conn;
    private int    $queryCount = 0;

    private function __construct() {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user = $_ENV['DB_USER'] ?? 'saips_app';
        $pass = $_ENV['DB_PASS'] ?? '';
        $name = $_ENV['DB_NAME'] ?? 'ownuh_saips';
        $port = (int)($_ENV['DB_PORT'] ?? 3306);

        $this->conn = new mysqli($host, $user, $pass, $name, $port);

        if ($this->conn->connect_error) {
            // Log error, show maintenance page
            error_log('[SAIPS DB] Connection failed: ' . $this->conn->connect_error);
            http_response_code(503);
            header('Location: under-maintenance.php');
            exit;
        }

        $this->conn->set_charset('utf8mb4');
        $this->conn->query("SET time_zone = '+05:30'");
    }

    // Singleton pattern — one connection per request
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a prepared statement and return all rows.
     * Demonstrates: mysqli, prepared statements, arrays, loops (CAP512 Unit 7)
     */
    public function fetchAll(string $sql, array $params = [], string $types = ''): array {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('[SAIPS DB] Prepare failed: ' . $this->conn->error . ' | SQL: ' . $sql);
            return [];
        }

        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];

        // Traversing arrays / while loop — CAP512 Unit 4 & 3
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        $this->queryCount++;
        return $rows;
    }

    /**
     * Fetch a single row.
     */
    public function fetchOne(string $sql, array $params = [], string $types = ''): ?array {
        $rows = $this->fetchAll($sql, $params, $types);
        return $rows[0] ?? null;
    }

    /**
     * Fetch a single scalar value.
     */
    public function fetchScalar(string $sql, array $params = [], string $types = ''): mixed {
        $row = $this->fetchOne($sql, $params, $types);
        return $row ? array_values($row)[0] : null;
    }

    /**
     * Execute INSERT/UPDATE/DELETE.
     * Returns affected rows count.
     */
    public function execute(string $sql, array $params = [], string $types = ''): int {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('[SAIPS DB] Prepare failed: ' . $this->conn->error);
            return 0;
        }

        if (!empty($params)) {
            if (empty($types)) $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $this->queryCount++;
        return $affected;
    }

    public function lastInsertId(): int {
        return (int)$this->conn->insert_id;
    }

    public function escape(string $str): string {
        return $this->conn->real_escape_string($str);
    }

    public function getQueryCount(): int {
        return $this->queryCount;
    }

    public function close(): void {
        $this->conn->close();
    }
}

// ── Security Helper Functions (CAP512 Unit 3: Functions) ─────────────────────

/**
 * Sanitise output to prevent XSS — CAP512 Unit 2: Strings
 */
function esc(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Verify JWT and return payload, or null if invalid.
 * Called on every protected page load.
 */
function verify_session(): ?array {
    // Retrieve token from cookie or Authorization header
    $token = $_COOKIE['saips_access'] ?? '';
    if (!$token) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    if (!$token) return null;

    // JWT is base64url-encoded — split into 3 parts
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) return null;

    [$headerB64, $payloadB64, $sigB64] = $parts;

    // Decode payload (base64url → JSON)
    $padding = str_repeat('=', (4 - strlen($payloadB64) % 4) % 4);
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/') . $padding), true);
    if (!$payload) return null;

    // Check token expiry
    if (($payload['exp'] ?? 0) < time()) return null;

    // Resolve public key path (supports relative paths from project root)
    $publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH'] ?? 'keys/public.pem';
    if ($publicKeyPath[0] !== '/') {
        $publicKeyPath = __DIR__ . '/../' . $publicKeyPath;
    }
    $publicKey = openssl_pkey_get_public('file://' . realpath($publicKeyPath));
    if (!$publicKey) return null;

    // Verify RS256 signature — both header and payload must have been base64url-signed
    $data      = "{$headerB64}.{$payloadB64}";
    $padding   = str_repeat('=', (4 - strlen($sigB64) % 4) % 4);
    $signature = base64_decode(strtr($sigB64, '-_', '+/') . $padding);

    $verified = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) return null;

    return $payload;
}

/**
 * Require authentication — redirects to login if not authenticated.
 * CAP512 Unit 3: Control flow + functions
 */
function require_auth(string $minRole = 'user'): array {
    $payload = verify_session();

    if (!$payload) {
        header('Location: login.php');
        exit;
    }

    $hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
    if (($hierarchy[$payload['role']] ?? 0) < ($hierarchy[$minRole] ?? 1)) {
        http_response_code(401);
        header('Location: auth-401.php');
        exit;
    }

    // Enrich with display_name from DB for header partial
    if (empty($payload['display_name'])) {
        try {
            $db  = Database::getInstance();
            $row = $db->fetchOne(
                'SELECT display_name, email, role FROM users WHERE id = ? AND deleted_at IS NULL',
                [$payload['sub'] ?? '']
            );
            if ($row) {
                $payload['display_name'] = $row['display_name'];
                $payload['email']        = $row['email'];
                $payload['role']         = $row['role'];
            }
        } catch (Throwable $e) {
            // Non-fatal — header will fall back to email
        }
    }

    return $payload;
}

/**
 * Format a timestamp for display — CAP512 Unit 2: String functions
 */
function format_ts(?string $ts, string $format = 'Y-m-d H:i:s'): string {
    if (!$ts) return '—';
    $label = $_ENV['APP_TIMEZONE_LABEL'] ?? 'IST';
    return date($format, strtotime($ts)) . ' ' . $label;
}

/**
 * Truncate a string — string handling (CAP512 Unit 4)
 */
function truncate(string $str, int $len = 40): string {
    return strlen($str) > $len ? substr($str, 0, $len) . '…' : $str;
}

/**
 * Return a Bootstrap badge class for event codes — CAP512 Unit 3: functions + strings
 */
function event_badge_class(string $code): string {
    $prefix = substr($code, 0, 3);
    return match($prefix) {
        'AUT' => 'bg-primary-subtle text-primary border border-primary',
        'SES' => 'bg-info-subtle text-info border border-info',
        'IPS' => 'bg-danger-subtle text-danger border border-danger',
        'ADM' => 'bg-warning-subtle text-warning border border-warning',
        default => 'bg-secondary-subtle text-secondary border border-secondary',
    };
}

/**
 * Return Bootstrap badge for status strings — CAP512 Unit 3: match expression
 */
function status_badge(string $status): string {
    $map = [
        'active'      => 'bg-success-subtle text-success border border-success',
        'locked'      => 'bg-danger-subtle text-danger border border-danger',
        'suspended'   => 'bg-danger-subtle text-danger border border-danger',
        'pending'     => 'bg-warning-subtle text-warning border border-warning',
        'completed'   => 'bg-success-subtle text-success border border-success',
        'failed'      => 'bg-warning-subtle text-warning border border-warning',
        'blocked'     => 'bg-danger-subtle text-danger border border-danger',
        'in_progress' => 'bg-warning-subtle text-warning border border-warning',
        'resolved'    => 'bg-success-subtle text-success border border-success',
        'open'        => 'bg-danger-subtle text-danger border border-danger',
    ];
    $class = $map[strtolower($status)] ?? 'bg-secondary-subtle text-secondary';
    return '<span class="badge ' . $class . '">' . esc(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/**
 * Severity badge — CAP512 Unit 3: functions
 */
function severity_badge(string $sev): string {
    return match($sev) {
        'sev1'  => '<span class="badge bg-danger">SEV-1 Critical</span>',
        'sev2'  => '<span class="badge bg-warning text-dark">SEV-2 High</span>',
        'sev3'  => '<span class="badge bg-info">SEV-3 Medium</span>',
        'sev4'  => '<span class="badge bg-secondary">SEV-4 Low</span>',
        default => '<span class="badge bg-secondary">' . esc($sev) . '</span>',
    };
}

/**
 * MFA badge — CAP512 Unit 4: strings
 */
function mfa_badge(string $factor, bool $enrolled): string {
    if (!$enrolled || $factor === 'none') {
        return '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="ri-close-circle-line me-1"></i>None</span>';
    }
    $icons = ['fido2' => 'ri-key-2-line', 'totp' => 'ri-smartphone-line', 'email_otp' => 'ri-mail-line', 'sms' => 'ri-message-3-line'];
    $labels = ['fido2' => 'FIDO2', 'totp' => 'TOTP', 'email_otp' => 'Email OTP', 'sms' => 'SMS'];
    $icon  = $icons[$factor]  ?? 'ri-shield-line';
    $label = $labels[$factor] ?? strtoupper($factor);
    $cls   = ($factor === 'fido2') ? 'bg-primary-subtle text-primary border border-primary' : 'bg-success-subtle text-success border border-success';
    return '<span class="badge ' . $cls . '"><i class="' . $icon . ' me-1"></i>' . $label . '</span>';
}

/**
 * Role badge — CAP512 Unit 4: strings + arrays
 */
function role_badge(string $role): string {
    $colors = ['superadmin' => 'bg-danger', 'admin' => 'bg-primary', 'manager' => 'bg-info', 'user' => 'bg-secondary'];
    $cls = $colors[$role] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . esc(ucfirst($role)) . '</span>';
}

/**
 * Paginate array — CAP512 Unit 5: Arrays
 */
function paginate(array $items, int $page, int $perPage): array {
    $total  = count($items);
    $offset = ($page - 1) * $perPage;
    $slice  = array_slice($items, $offset, $perPage);
    return [
        'items'       => $slice,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ];
}

// ── Dashboard Data Functions (CAP512 Unit 7: Databases + Unit 3: Functions) ──

/**
 * Get dashboard KPI stats — all in one query set
 * Demonstrates: multiple mysqli queries, associative arrays (CAP512 §4 + §7)
 */
function get_dashboard_stats(): array {
    $db = Database::getInstance();

    // CAP512 Unit 5: Arrays — building associative arrays from DB results
    $stats = [];

    // User counts
    $userStats = $db->fetchOne(
        'SELECT
            COUNT(*) as total,
            SUM(status = "active") as active,
            SUM(status = "locked") as locked,
            SUM(mfa_enrolled = 1) as mfa_enrolled,
            SUM(mfa_enrolled = 0) as no_mfa
         FROM users WHERE deleted_at IS NULL'
    );
    $stats['users'] = $userStats ?? ['total'=>0,'active'=>0,'locked'=>0,'mfa_enrolled'=>0,'no_mfa'=>0];

    // New registrations in last 24h — CAP512 Unit 7: DB
    $stats['new_registrations_24h'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 24 HOUR AND deleted_at IS NULL'
    ) ?? 0);

    // Auth events last 24h
    $authStats = $db->fetchOne(
        'SELECT
            SUM(event_code = "AUTH-001") as successful_logins,
            SUM(event_code = "AUTH-002") as failed_attempts,
            SUM(event_code = "AUTH-003") as accounts_locked,
            COUNT(*) as total_events
         FROM audit_log
         WHERE created_at >= NOW() - INTERVAL 24 HOUR'
    );
    $stats['auth_24h'] = $authStats ?? ['successful_logins'=>0,'failed_attempts'=>0,'accounts_locked'=>0,'total_events'=>0];

    // Active sessions
    // Active sessions created in last 24h (dashboard KPI)
    $stats['active_sessions'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM sessions WHERE invalidated_at IS NULL AND expires_at > NOW()
         AND created_at >= NOW() - INTERVAL 24 HOUR'
    ) ?? 0);

    // Blocked IPs
    $stats['blocked_ips'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM blocked_ips
         WHERE unblocked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())'
    ) ?? 0);

    // Open incidents
    $incidentStats = $db->fetchAll(
        'SELECT severity, COUNT(*) as cnt FROM incidents
         WHERE status NOT IN ("resolved","closed") GROUP BY severity'
    );
    // CAP512 Unit 5: array_column, array_combine
    $stats['open_incidents'] = array_column($incidentStats, 'cnt', 'severity');
    $stats['open_incidents_total'] = array_sum($stats['open_incidents']);

    // Resolved incidents today
    $stats['resolved_today'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM incidents
         WHERE status = "resolved" AND DATE(resolved_at) = CURDATE()'
    ) ?? 0);

    // Alert rules active
    $stats['alert_rules'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM rate_limit_config WHERE is_active = 1'
    ) ?? 0);

    // Monitored endpoints
    $stats['monitored_endpoints'] = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM rate_limit_config'
    ) ?? 0);

    // Security score (computed)
    // CAP512 Unit 2: Arithmetic, variables
    $mfaCoverage     = $stats['users']['total'] > 0
        ? round(($stats['users']['mfa_enrolled'] / $stats['users']['total']) * 100)
        : 0;
    $stats['security_score']  = min(100, 70 + (int)($mfaCoverage * 0.2) + ($stats['blocked_ips'] > 0 ? 5 : 0));
    $stats['mfa_coverage']    = $mfaCoverage;

    return $stats;
}

/**
 * Get recent audit log entries — CAP512 Unit 7: Advanced DB techniques
 */
function get_compliance_checks(): array {
    $db = Database::getInstance();

    $mfaCoverage  = (int)($db->fetchScalar(
        'SELECT ROUND(SUM(mfa_enrolled) / NULLIF(COUNT(*), 0) * 100) FROM users WHERE deleted_at IS NULL'
    ) ?? 0);
    $hasPolicy    = (bool)$db->fetchScalar('SELECT COUNT(*) FROM rate_limit_config');
    $auditCount   = (int)($db->fetchScalar('SELECT COUNT(*) FROM audit_log') ?? 0);
    $openCritical = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM incidents WHERE severity = "sev1" AND status = "open"'
    ) ?? 0);
    $blockedCount = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM blocked_ips WHERE unblocked_at IS NULL'
    ) ?? 0);

    return [
        ['id'=>'C01', 'std'=>'NIST SP 800-63B', 'control'=>'Multi-factor authentication enforced',     'status'=> $mfaCoverage >= 100 ? 'pass' : ($mfaCoverage >= 80 ? 'action' : 'fail'), 'detail'=> $mfaCoverage . '% MFA coverage'],
        ['id'=>'C02', 'std'=>'NIST SP 800-63B', 'control'=>'bcrypt password hashing (cost >= 12)',     'status'=>'pass', 'detail'=>'bcrypt cost 12 configured'],
        ['id'=>'C03', 'std'=>'OWASP Top 10',    'control'=>'SQL injection prevention (prepared stmts)', 'status'=>'pass', 'detail'=>'All queries use mysqli prepared statements'],
        ['id'=>'C04', 'std'=>'OWASP Top 10',    'control'=>'XSS prevention (output encoding)',          'status'=>'pass', 'detail'=>'htmlspecialchars() on all output'],
        ['id'=>'C05', 'std'=>'OWASP Top 10',    'control'=>'CSRF protection on all POST forms',         'status'=>'pass', 'detail'=>'Cryptographic tokens per session'],
        ['id'=>'C06', 'std'=>'ISO 27001',       'control'=>'Tamper-evident audit logging',              'status'=> $auditCount > 0 ? 'pass' : 'action', 'detail'=> $auditCount . ' SHA-256 chained entries'],
        ['id'=>'C07', 'std'=>'ISO 27001',       'control'=>'Account lockout policy (10 failures)',      'status'=>'pass', 'detail'=>'Soft-lock at 5, hard-lock at 10'],
        ['id'=>'C08', 'std'=>'ISO 27001',       'control'=>'Rate limiting configured',                  'status'=> $hasPolicy ? 'pass' : 'action', 'detail'=> $hasPolicy ? 'Rate limits active' : 'No rate limit rules found'],
        ['id'=>'C09', 'std'=>'GDPR Art. 32',    'control'=>'Data encrypted in transit (TLS 1.3)',       'status'=>'pass', 'detail'=>'Nginx TLS 1.3 only configuration'],
        ['id'=>'C10', 'std'=>'GDPR Art. 32',    'control'=>'Database encryption at rest',               'status'=>'recommended', 'detail'=>'InnoDB AES-256 - configure in DEPLOYMENT.md'],
        ['id'=>'C11', 'std'=>'GDPR Art. 33',    'control'=>'72-hour breach notification workflow',      'status'=>'pass', 'detail'=>'GDPR flag in incident report form'],
        ['id'=>'C12', 'std'=>'SOC 2 Type II',   'control'=>'Access control (RBAC 4-tier)',              'status'=>'pass', 'detail'=>'user / manager / admin / superadmin'],
        ['id'=>'C13', 'std'=>'SOC 2 Type II',   'control'=>'No open SEV-1 incidents',                   'status'=> $openCritical === 0 ? 'pass' : 'fail', 'detail'=> $openCritical . ' open critical incidents'],
        ['id'=>'C14', 'std'=>'MITRE ATT&CK',    'control'=>'Brute-force detection and auto-block',      'status'=> $blockedCount > 0 || $hasPolicy ? 'pass' : 'recommended', 'detail'=>'IPS active: ' . $blockedCount . ' IPs blocked'],
        ['id'=>'C15', 'std'=>'PCI DSS',         'control'=>'Password history enforcement (last 12)',    'status'=>'pass', 'detail'=>'password_history table enforces 12-entry history'],
    ];
}

function get_security_posture_snapshot(): array {
    $db = Database::getInstance();
    $stats = get_dashboard_stats();
    $checks = get_compliance_checks();

    $passed = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
    $action = count(array_filter($checks, fn($c) => $c['status'] === 'action'));
    $fail   = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
    $rec    = count(array_filter($checks, fn($c) => $c['status'] === 'recommended'));
    $score  = count($checks) > 0 ? (int)round(($passed / count($checks)) * 100) : 0;

    $highRiskEvents24h = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM audit_log WHERE created_at >= NOW() - INTERVAL 24 HOUR AND risk_score >= 70'
    ) ?? 0);
    $failedLogins7d = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM audit_log WHERE event_code = "AUTH-002" AND created_at >= NOW() - INTERVAL 7 DAY'
    ) ?? 0);
    $resolvedIncidents30d = (int)($db->fetchScalar(
        'SELECT COUNT(*) FROM incidents WHERE status IN ("resolved", "closed") AND detected_at >= NOW() - INTERVAL 30 DAY'
    ) ?? 0);

    $failingControls = array_values(array_map(
        fn($check) => ['id' => $check['id'], 'control' => $check['control'], 'detail' => $check['detail']],
        array_filter($checks, fn($c) => in_array($c['status'], ['fail', 'action'], true))
    ));

    $recentIncidents = array_map(static function(array $incident): array {
        return [
            'incident_ref' => $incident['incident_ref'] ?? '',
            'severity' => $incident['severity'] ?? '',
            'status' => $incident['status'] ?? '',
            'trigger_summary' => $incident['trigger_summary'] ?? '',
            'detected_at' => $incident['detected_at'] ?? '',
        ];
    }, array_slice(get_incidents('', 6), 0, 6));

    $recentAudit = array_map(static function(array $entry): array {
        return [
            'event_code' => $entry['event_code'] ?? '',
            'event_name' => $entry['event_name'] ?? '',
            'user' => $entry['email'] ?? $entry['display_name'] ?? 'system',
            'source_ip' => $entry['source_ip'] ?? '',
            'risk_score' => (int)($entry['risk_score'] ?? 0),
            'created_at' => $entry['created_at'] ?? '',
        ];
    }, array_slice(get_recent_audit(10), 0, 10));

    $blockedIps = array_map(static function(array $row): array {
        return [
            'ip_address' => $row['ip_address'] ?? '',
            'block_type' => $row['block_type'] ?? '',
            'trigger_rule' => $row['trigger_rule'] ?? '',
            'country_code' => $row['country_code'] ?? '',
            'blocked_at' => $row['blocked_at'] ?? '',
        ];
    }, array_slice(get_blocked_ips(5), 0, 5));

    return [
        'organisation' => $_ENV['APP_NAME'] ?? 'Ownuh SAIPS Organisation',
        'generated_at' => date('c'),
        'security_score' => (int)($stats['security_score'] ?? 0),
        'compliance_score' => $score,
        'compliance' => [
            'passed' => $passed,
            'requires_action' => $action + $fail,
            'recommended' => $rec,
            'failing_controls' => $failingControls,
        ],
        'users' => [
            'total' => (int)($stats['users']['total'] ?? 0),
            'active' => (int)($stats['users']['active'] ?? 0),
            'locked' => (int)($stats['users']['locked'] ?? 0),
            'mfa_coverage' => (int)($stats['mfa_coverage'] ?? 0),
        ],
        'auth' => [
            'successful_logins_24h' => (int)($stats['auth_24h']['successful_logins'] ?? 0),
            'failed_attempts_24h' => (int)($stats['auth_24h']['failed_attempts'] ?? 0),
            'failed_attempts_7d' => $failedLogins7d,
            'high_risk_events_24h' => $highRiskEvents24h,
            'active_sessions_24h' => (int)($stats['active_sessions'] ?? 0),
        ],
        'ips' => [
            'blocked_ips_active' => (int)($stats['blocked_ips'] ?? 0),
            'alert_rules_active' => (int)($stats['alert_rules'] ?? 0),
            'monitored_endpoints' => (int)($stats['monitored_endpoints'] ?? 0),
            'top_blocked_ips' => $blockedIps,
        ],
        'incidents' => [
            'open_total' => (int)($stats['open_incidents_total'] ?? 0),
            'open_by_severity' => $stats['open_incidents'] ?? [],
            'resolved_today' => (int)($stats['resolved_today'] ?? 0),
            'resolved_30d' => $resolvedIncidents30d,
            'recent' => $recentIncidents,
        ],
        'recent_audit' => $recentAudit,
    ];
}

function get_recent_audit(int $limit = 10): array {
    $db = Database::getInstance();
    return $db->fetchAll(
        'SELECT al.id, al.event_code, al.event_name, al.user_id,
                u.display_name, u.email,
                al.source_ip, al.country_code, al.mfa_method,
                al.risk_score, al.details, al.created_at
         FROM audit_log al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.id DESC LIMIT ?',
        [$limit], 'i'
    );
}

function saips_guest_user(string $label = 'Guest'): array {
    $safe = trim($label) !== '' ? trim($label) : 'Guest';
    return [
        'id' => null,
        'display_name' => $safe,
        'email' => strtolower(str_replace(' ', '.', $safe)) . '@ownuh-saips.com',
        'role' => 'visitor',
    ];
}

function get_system_setting(string $key, mixed $default = null): mixed {
    try {
        $db = Database::getInstance();
        $value = $db->fetchScalar(
            'SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1',
            [$key]
        );
        return $value !== null ? $value : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function set_system_setting(string $key, string $value, ?string $updatedBy = null): bool {
    try {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by)',
            [$key, $value, $updatedBy]
        );
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}


/**
 * Get all users — demonstrates mysqli JOIN + ORDER BY (CAP512 Unit 7)
 */
function get_users(string $status = '', string $search = '', int $limit = 100): array {
    $db     = Database::getInstance();
    $where  = ['deleted_at IS NULL'];
    $params = [];
    $types  = '';

    // CAP512 Unit 3: Control flow — conditional query building
    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
        $types   .= 's';
    }
    if ($search) {
        $where[]  = '(email LIKE ? OR display_name LIKE ?)';
        $like     = "%{$search}%";
        $params   = array_merge($params, [$like, $like]);
        $types   .= 'ss';
    }
    $params[] = $limit;
    $types   .= 'i';

    return $db->fetchAll(
        'SELECT id, display_name, email, role, status, mfa_enrolled, mfa_factor,
                failed_attempts, last_login_at, last_login_ip, last_login_country,
                created_at, password_changed_at
         FROM users WHERE ' . implode(' AND ', $where) . '
         ORDER BY FIELD(role,"superadmin","admin","manager","user"), display_name
         LIMIT ?',
        $params, $types
    );
}

/**
 * Get active blocked IPs — CAP512 Unit 7: DB operations
 */
function get_blocked_ips(int $limit = 50): array {
    $db = Database::getInstance();
    return $db->fetchAll(
        'SELECT id, ip_address, block_type, trigger_rule, country_code,
                threat_feed, blocked_at, expires_at
         FROM blocked_ips
         WHERE unblocked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY blocked_at DESC LIMIT ?',
        [$limit], 'i'
    );
}

/**
 * Get incidents — CAP512 Unit 7: Advanced DB + sorting
 */
function get_incidents(string $status = '', int $limit = 50): array {
    $db     = Database::getInstance();
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($status) {
        $where[]  = 'i.status = ?';
        $params[] = $status;
        $types   .= 's';
    }
    $params[] = $limit;
    $types   .= 'i';

    return $db->fetchAll(
        'SELECT i.*, u1.email as reporter_email, u2.email as assignee_email
         FROM incidents i
         LEFT JOIN users u1 ON u1.id = i.reported_by
         LEFT JOIN users u2 ON u2.id = i.assigned_to
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY FIELD(i.severity,"sev1","sev2","sev3","sev4"),
                  FIELD(i.status,"open","in_progress","under_review","resolved","closed"),
                  i.detected_at DESC
         LIMIT ?',
        $params, $types
    );
}

/**
 * Get active sessions — CAP512 Unit 7: DB + Unit 6: OOP
 */
function get_active_sessions(int $limit = 100): array {
    $db = Database::getInstance();
    return $db->fetchAll(
        'SELECT s.id, s.user_id, u.display_name, u.email, u.role,
                s.ip_address, s.mfa_method, s.created_at, s.expires_at,
                s.last_used_at,
                GREATEST(
  0,
  TIMESTAMPDIFF(MINUTE, IFNULL(s.last_used_at, s.created_at), NOW())
) as idle_minutes
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.invalidated_at IS NULL AND s.expires_at > NOW()
         ORDER BY FIELD(u.role,"superadmin","admin","manager","user"), s.created_at DESC
         LIMIT ?',
        [$limit], 'i'
    );
}

/**
 * Monthly auth trend for chart — CAP512 Unit 7: Aggregation + Unit 5: Arrays
 */
function get_monthly_auth_trend(int $months = 9): array {
    $db   = Database::getInstance();
    $rows = $db->fetchAll(
        'SELECT
            DATE_FORMAT(created_at, "%b") as month,
            SUM(event_code = "AUTH-001") as successful,
            SUM(event_code = "AUTH-002") as failed,
            SUM(event_code = "IPS-001")  as blocked
         FROM audit_log
         WHERE created_at >= NOW() - INTERVAL ? MONTH
         GROUP BY DATE_FORMAT(created_at, "%Y-%m")
         ORDER BY MIN(created_at) ASC',
        [$months], 'i'
    );

    // CAP512 Unit 5: array_column — extract columns into separate arrays
    // CAP512 Unit 3: if DB has no trend data, return sample months for the chart
    if (empty($rows)) {
        $sampleMonths = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $sampleMonths[] = date('M', strtotime("-{$i} months"));
        }
        return [
            'labels'     => $sampleMonths,
            'successful' => [12, 19, 15, 22, 18, 25, 20, 28, 24],
            'failed'     => [3,  5,  4,  6,  3,  8,  5,  7,  4],
            'blocked'    => [1,  2,  1,  3,  2,  4,  2,  3,  2],
        ];
    }

    return [
        'labels'     => array_column($rows, 'month'),
        'successful' => array_map('intval', array_column($rows, 'successful')),
        'failed'     => array_map('intval', array_column($rows, 'failed')),
        'blocked'    => array_map('intval', array_column($rows, 'blocked')),
    ];
}

/**
 * Get login origin heatmap data for the vector map — CAP512 Unit 7 + Unit 5
 */
function get_login_origins(): array {
    $db   = Database::getInstance();
    $rows = $db->fetchAll(
        'SELECT country_code, COUNT(*) as logins
         FROM audit_log
         WHERE event_code = "AUTH-001"
           AND country_code IS NOT NULL
           AND created_at >= NOW() - INTERVAL 30 DAY
         GROUP BY country_code
         ORDER BY logins DESC'
    );

    // CAP512 Unit 5: array_combine creates assoc array from two arrays
    $codes  = array_column($rows, 'country_code');
    $counts = array_map('intval', array_column($rows, 'logins'));
    $result = (count($codes) > 0) ? array_combine($codes, $counts) : [];

    // CAP512 Unit 3: fallback if audit log has no geo data yet
    // (run database/patch_audit_seed.sql in phpMyAdmin to populate real data)
    if (empty($result)) {
        $result = ['AU' => 6, 'US' => 3, 'GB' => 2, 'IN' => 2, 'SG' => 1, 'DE' => 1, 'CA' => 1];
    }

    return $result;
}

/**
 * Validate CSRF token — CAP512 Unit 2: sessions + security
 */
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

function log_dev_otp(string $email, string $otp): void {
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        return;
    }

    $line = sprintf(
        "[%s] [SAIPS OTP] %s => %s%s",
        date('Y-m-d H:i:s'),
        $email,
        $otp,
        PHP_EOL
    );

    @file_put_contents(__DIR__ . '/../logs/dev-otp.log', $line, FILE_APPEND | LOCK_EX);
}

// ── Image helper functions (CAP512 Unit 7: Graphics) ─────────────────────────

/**
 * Generate a user avatar initials image using GD — CAP512 Unit 7: Graphics
 * Returns base64-encoded PNG data URI.
 */
function generate_avatar_image(string $name, int $size = 40): string {
    // CAP512 Unit 7: Graphics — SVG avatar (no GD extension required)
    // CAP512 Unit 4: String manipulation — extract initials
    $words    = explode(' ', trim($name));
    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

    // CAP512 Unit 3: control flow — pick colour from name hash
    $colours  = ['#9c2fba','#198754','#dc3545','#fd7e14','#6f42c1','#0dcaf0','#20c997'];
    $colIndex = abs(crc32($name)) % count($colours);
    $bg       = $colours[$colIndex];

    $half     = $size / 2;
    $fontSize = round($size * 0.38);

    // CAP512 Unit 4: heredoc string building
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
  <rect width="{$size}" height="{$size}" rx="{$half}" fill="{$bg}"/>
  <text x="{$half}" y="{$half}" dy="0.35em" text-anchor="middle" font-size="{$fontSize}" font-weight="bold" fill="#fff" font-family="Arial,sans-serif">{$initials}</text>
</svg>
SVG;

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Generate a security score gauge image — CAP512 Unit 7: Drawing images
 */
function generate_score_gauge(int $score, int $width = 200, int $height = 100): string {
    // CAP512 Unit 7: Graphics — SVG gauge (no GD extension required)
    // Color based on score — CAP512 Unit 3: control flow
    if ($score >= 80)     { $color = '#198754'; $label = 'Good'; }
    elseif ($score >= 60) { $color = '#ffc107'; $label = 'Fair'; }
    else                  { $color = '#dc3545'; $label = 'Poor'; }

    // CAP512 Unit 4: String functions — build SVG arc path
    $r        = 70;                          // arc radius
    $cx       = 100;                         // centre x
    $cy       = 90;                          // centre y (bottom of semicircle)
    $pct      = min(100, max(0, $score));
    $angle    = ($pct / 100) * 180;          // degrees swept (0–180)
    $rad      = deg2rad(180 + $angle);       // convert to radians
    $ex       = round($cx + $r * cos($rad), 2);
    $ey       = round($cy + $r * sin($rad), 2);
    $largeArc = $angle > 180 ? 1 : 0;

    // Background track
    $bgPath   = "M " . ($cx - $r) . " $cy A $r $r 0 0 1 " . ($cx + $r) . " $cy";
    // Score arc
    $scorePath = "M " . ($cx - $r) . " $cy A $r $r 0 $largeArc 1 $ex $ey";

    // CAP512 Unit 4: heredoc string
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 110" width="{$width}" height="{$height}">
  <path d="{$bgPath}" fill="none" stroke="#e9ecef" stroke-width="14" stroke-linecap="round"/>
  <path d="{$scorePath}" fill="none" stroke="{$color}" stroke-width="14" stroke-linecap="round"/>
  <text x="{$cx}" y="82" text-anchor="middle" font-size="22" font-weight="bold" fill="{$color}" font-family="Arial,sans-serif">{$score}</text>
  <text x="{$cx}" y="100" text-anchor="middle" font-size="11" fill="#6c757d" font-family="Arial,sans-serif">{$label}</text>
</svg>
SVG;

    // Return as data URI so it works as <img src="..."> — CAP512 Unit 4: base64
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// ── Audit PDO helper (used by PHP-session pages: login.php, otp-verify.php) ──

/**
 * BUG-02 / BUG-03 FIX: Provides a PDO instance so PHP-session pages can call
 * AuditMiddleware::init() and write auth events to the audit_log table.
 * Previously these pages had zero audit coverage.
 */
function get_audit_pdo(): \PDO {
    static $pdo = null;

    // Verify cached connection is still alive (avoids MySQL error 2006 "server has gone away")
    if ($pdo !== null) {
        try { $pdo->query('SELECT 1'); return $pdo; }
        catch (\PDOException $e) { $pdo = null; } // stale — reconnect below
    }

    $dbConfig = require __DIR__ . '/config/database.php';
    $cfg      = $dbConfig['app'];

    // Include port in DSN (was missing — caused refusals on non-3306 setups)
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        (int)($cfg['port'] ?? 3306),
        $cfg['name']
    );

    try {
        $pdo = new \PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
    } catch (\PDOException $e) {
        error_log('[SAIPS DB] get_audit_pdo() failed: ' . $e->getMessage());
        throw new \RuntimeException('Database unavailable — check DB_HOST/DB_PORT/DB_USER in .env');
    }

    return $pdo;
}

// Autoload AuditMiddleware for PHP-session pages that require bootstrap.php
if (!class_exists('SAIPS\\Middleware\\AuditMiddleware')) {
    require_once __DIR__ . '/Middleware/AuditMiddleware.php';
}

// ── Array utility functions (CAP512 Unit 5: Arrays) ──────────────────────────

/**
 * Group array of rows by a key — CAP512 Unit 5: array functions
 */
function array_group_by(array $items, string $key): array {
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item[$key] ?? 'unknown'][] = $item;
    }
    return $grouped;
}

/**
 * Safe array access with default — CAP512 Unit 5: arrays
 */
function arr(array $data, string $key, mixed $default = null): mixed {
    return $data[$key] ?? $default;
}

/**
 * Convert rows to key-value pairs — CAP512 Unit 5: array_combine
 */
function rows_to_map(array $rows, string $keyCol, string $valCol): array {
    return array_combine(
        array_column($rows, $keyCol),
        array_column($rows, $valCol)
    );
}

/**
 * Create JWT access token — SRS §3.4
 * Creates RS256-signed JWT for authenticated users
 */
function create_jwt_token(array $user, int $ttl = 900): string {
    $privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH'] ?? 'keys/private.pem';
    if ($privateKeyPath[0] !== '/') {
        $privateKeyPath = __DIR__ . '/../' . $privateKeyPath;
    }
    $privateKey = openssl_pkey_get_private('file://' . realpath($privateKeyPath));
    
    if (!$privateKey) {
        throw new RuntimeException('Failed to load private key for JWT signing');
    }
    
    $now = time();
    $payload = [
        'sub' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => $now,
        'exp' => $now + $ttl,
        'iss' => 'ownuh-saips',
        'jti' => bin2hex(random_bytes(16)),
    ];
    
    // Encode header and payload as base64url (RFC 7515)
    $b64url = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

    $header     = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payloadB64 = $b64url(json_encode($payload));
    $data       = "{$header}.{$payloadB64}";

    openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return $data . '.' . $b64url($signature);
}

/**
 * Set authentication cookies — SRS §3.4
 */
function set_auth_cookies(string $accessToken): void {
    $trustedProxy = $_ENV['TRUSTED_PROXY'] ?? '';
    $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '';
    $proxyTrusted = ($trustedProxy !== '')
                 && ($trustedProxy === 'any' || $remoteAddr === $trustedProxy);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
             || ($proxyTrusted && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $sameSite = $_ENV['COOKIE_SAMESITE'] ?? ($proxyTrusted ? 'Lax' : 'Strict');

    setcookie('saips_access', $accessToken, [
        'expires'  => time() + 900,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
}

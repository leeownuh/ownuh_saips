<?php
/**
 * Ownuh SAIPS — /ips/alert-rules endpoint
 *
 * Handles CRUD for alert_rules from the dashboard modal AND the
 * settings-alert-rules.php page.
 *
 * Accepts both:
 *   • JSON API calls  (Content-Type: application/json, Bearer token auth)
 *   • PHP-session POST (form submit from dashboard modal, session auth)
 *
 * Routes:
 *   GET    — list all alert rules          (admin+)
 *   POST   — create a new rule             (admin+)
 *   PUT    — toggle / update a rule        (admin+)
 *   DELETE — delete a rule                 (admin+)
 *
 * Dashboard modal fields mapped to DB columns:
 *   condition  → rule_name   (free-text description)
 *   severity   → sev1–sev4   stored in details JSON
 *   action     → destination (e.g. "Account locked + admin email")
 *
 * SRS §5.2 — Webhook / Alert Dispatcher
 */

declare(strict_types=1);

$bootstrapPath = dirname(__DIR__, 3) . '/backend/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    $bootstrapPath = dirname(__DIR__, 2) . '/bootstrap.php';
}
require_once $bootstrapPath;

// ── Determine auth mode: JWT API vs PHP session ───────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$hasSessionCookie = !empty($_COOKIE[session_name() ?: 'PHPSESSID']);
$isApiCall = str_starts_with($authHeader, 'Bearer ')
          || (str_contains($contentType, 'application/json') && !$hasSessionCookie);
$wantsJson = str_contains($acceptHeader, 'application/json') || $isApiCall;

header('Content-Type: application/json');

$adminId = null;
$adminRole = null;

if ($isApiCall) {
    // JWT path — used by settings-alert-rules.php XHR or external API consumers
    require_once dirname(__DIR__, 2) . '/Middleware/AuthMiddleware.php';
    $secConfig = require dirname(__DIR__, 2) . '/config/security.php';
    $auth      = new SAIPS\Middleware\AuthMiddleware($secConfig);
    $payload   = $auth->validate();
    if (!in_array($payload['role'], ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN']);
        exit;
    }
    $adminId   = $payload['sub'];
    $adminRole = $payload['role'];
} else {
    // PHP-session path — dashboard modal POST
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sessionUser = verify_session();
    if (!$sessionUser || !in_array($sessionUser['role'], ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Admin access required.']);
        exit;
    }
    $adminId   = $sessionUser['id'];
    $adminRole = $sessionUser['role'];
}

// ── Database ──────────────────────────────────────────────────────────────────
$db = Database::getInstance();

$method = $_SERVER['REQUEST_METHOD'];

// Decode body: JSON or form-encoded
$body = [];
if ($method !== 'GET') {
    $raw = file_get_contents('php://input');
    if (!empty($raw) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $body = json_decode($raw, true) ?? [];
    } else {
        $body = $_POST;
    }
}

// ── CSRF check for session-based (non-API) POSTs ──────────────────────────────
if (!$isApiCall && $method === 'POST') {
    if (!verify_csrf($body['csrf_token'] ?? null)) {
        http_response_code(403);
        // Redirect back to dashboard with error if it was a direct form submit
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
            header('Location: ../../../dashboard.php?alert_error=csrf');
            exit;
        }
        echo json_encode(['status' => 'error', 'code' => 'CSRF_INVALID']);
        exit;
    }
    // Rotate token after use
    unset($_SESSION['csrf_token']);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET — list all rules
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $rules = $db->fetchAll(
        'SELECT ar.id, ar.rule_name, ar.event_type, ar.channel,
                ar.threshold_count, ar.window_minutes, ar.destination,
                ar.is_active, ar.created_at,
                u.display_name AS created_by_name
         FROM alert_rules ar
         LEFT JOIN users u ON u.id = ar.created_by
         ORDER BY ar.is_active DESC, ar.created_at DESC'
    );
    echo json_encode(['status' => 'ok', 'data' => $rules]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST — create new rule
// Accepts both the full settings-page fields AND the simplified dashboard modal fields.
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && empty($body['_method'])) {
    $action = $body['action'] ?? 'add';

    // Toggle / delete actions tunnelled via dashboard POST (legacy)
    if ($action === 'toggle') {
        $ruleId = trim($body['rule_id'] ?? '');
        if (!$ruleId) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'rule_id is required.']);
            exit;
        }
        $db->execute('UPDATE alert_rules SET is_active = NOT is_active WHERE id = ?', [$ruleId]);
        _redirect_or_json(['status' => 'ok', 'message' => 'Alert rule toggled.'], $wantsJson);
        exit;
    }

    if ($action === 'delete') {
        $ruleId = trim($body['rule_id'] ?? '');
        if (!$ruleId) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'rule_id is required.']);
            exit;
        }
        $db->execute('DELETE FROM alert_rules WHERE id = ?', [$ruleId]);
        _redirect_or_json(['status' => 'ok', 'message' => 'Alert rule deleted.'], $wantsJson);
        exit;
    }

    // ── Map dashboard modal fields → DB columns ──────────────────────────────
    // Settings page: rule_name, event_type, channel, threshold, window_min, destination
    // Dashboard modal: condition (→ rule_name), severity, automated_action (→ destination)

    if (!empty($body['condition'])) {
        // Dashboard modal simplified format
        $name      = trim($body['condition']);
        $eventType = trim($body['event_type'] ?? 'AUTH-002');
        $channel   = 'email';
        $threshold = 5;
        $window    = 5;
        // Map automated_action label → destination value stored in DB
        $actionMap = [
            'Account locked + admin email' => 'admin@localhost',
            'IP blocked 60 min'            => 'ip_block:60',
            'WAF rule deployed'            => 'waf:deploy',
            'Alert only'                   => 'log_only',
        ];
        $rawAction   = trim($body['automated_action'] ?? $body['action'] ?? 'Alert only');
        $destination = $actionMap[$rawAction] ?? $rawAction;
        $details     = ['severity' => $body['severity'] ?? 'sev2', 'source' => 'dashboard_modal'];
    } else {
        // Full settings-page format
        $name        = trim($body['rule_name']   ?? '');
        $eventType   = trim($body['event_type']  ?? '');
        $channel     = trim($body['channel']     ?? 'email');
        $threshold   = max(1, (int)($body['threshold']  ?? $body['threshold_count'] ?? 1));
        $window      = max(1, (int)($body['window_min'] ?? $body['window_minutes']  ?? 5));
        $destination = trim($body['destination'] ?? '');
        $details     = null;
    }

    if (!$name || !$destination) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Rule name/condition and destination/action are required.']);
        exit;
    }

    try {
        $affected = $db->execute(
            'INSERT INTO alert_rules
                (id, rule_name, event_type, channel, threshold_count,
                 window_minutes, destination, is_active, created_by)
             VALUES (UUID(), ?, ?, ?, ?, ?, ?, 1, ?)',
            [$name, $eventType, $channel, $threshold, $window, $destination, $adminId]
        );
        if ($affected < 1) {
            throw new \RuntimeException('The alert rule was not saved.');
        }
    } catch (\Exception $e) {
        error_log('[SAIPS Alert Rules] Insert failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not save rule: ' . $e->getMessage()]);
        exit;
    }

    _redirect_or_json(
        ['status' => 'ok', 'message' => "Alert rule '{$name}' created successfully."],
        $wantsJson,
        'dashboard.php?alert_ok=1'
    );
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PUT — update / toggle
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'PUT' || ($method === 'POST' && ($body['_method'] ?? '') === 'PUT')) {
    $ruleId = trim($body['id'] ?? $body['rule_id'] ?? '');
    if (!$ruleId) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'id is required.']);
        exit;
    }

    if (isset($body['is_active'])) {
        $db->execute(
            'UPDATE alert_rules SET is_active = ? WHERE id = ?',
            [(int)(bool)$body['is_active'], $ruleId]
        );
    }

    if (!empty($body['rule_name'])) {
        $db->execute(
            'UPDATE alert_rules SET rule_name=?, event_type=?, channel=?,
             threshold_count=?, window_minutes=?, destination=? WHERE id=?',
            [
                $body['rule_name'],
                $body['event_type']  ?? 'AUTH-002',
                $body['channel']     ?? 'email',
                max(1, (int)($body['threshold_count'] ?? 1)),
                max(1, (int)($body['window_minutes']  ?? 5)),
                $body['destination'] ?? '',
                $ruleId,
            ]
        );
    }

    echo json_encode(['status' => 'ok', 'message' => 'Alert rule updated.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && ($body['_method'] ?? '') === 'DELETE')) {
    $ruleId = trim($body['id'] ?? $body['rule_id'] ?? '');
    if (!$ruleId) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'id is required.']);
        exit;
    }
    $db->execute('DELETE FROM alert_rules WHERE id = ?', [$ruleId]);
    echo json_encode(['status' => 'ok', 'message' => 'Alert rule deleted.']);
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);

// ─────────────────────────────────────────────────────────────────────────────
// Helper: redirect for session form submits, JSON for API calls
// ─────────────────────────────────────────────────────────────────────────────
function _redirect_or_json(array $payload, bool $isApi, string $redirectTo = 'dashboard.php'): void {
    if ($isApi) {
        echo json_encode($payload);
        return;
    }
    // Session form submit from dashboard — redirect back
    if (!headers_sent()) {
        $param = $payload['status'] === 'ok' ? 'alert_ok' : 'alert_error';
        $msg   = urlencode($payload['message'] ?? '');
        header("Location: {$redirectTo}?{$param}={$msg}");
    }
}

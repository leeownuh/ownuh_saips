<?php
/**
 * Ownuh SAIPS — POST /incidents
 * Create a new security incident.
 * SRS §5.3 — Incident Management
 * 
 * POST /incidents
 * Request body: {
 *   "severity": "sev1|sev2|sev3|sev4",
 *   "trigger_summary": "Brute force attack detected",
 *   "description": "Detailed description...",
 *   "affected_user_id": "uuid" (optional),
 *   "source_ip": "1.2.3.4" (optional),
 *   "personal_data_involved": true/false
 * }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo       = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) {
    $redis->auth($dbConfig['redis']['pass']);
}

AuditMiddleware::init($pdo);
$rateLimit = new RateLimitMiddleware($redis, $secConfig);

// ── Authenticate user via JWT ─────────────────────────────────────────────────
$auth = new AuthMiddleware($secConfig);
$user = $auth->validate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']);
    exit;
}

// Only managers and above can create incidents
$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
if (($hierarchy[$user['role']] ?? 0) < 2) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Manager access required to create incidents.']);
    exit;
}

$reporterId = $user['sub'];

// ── Rate limit ────────────────────────────────────────────────────────────────
try {
    $rateLimit->check('/incidents/create', $reporterId, 'per_user');
} catch (Throwable $e) {
    exit;
}

// ── Parse and validate input ──────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$severity = strtolower(trim((string)($body['severity'] ?? '')));
$triggerSummary = trim((string)($body['trigger_summary'] ?? ''));
$description = trim((string)($body['description'] ?? ''));
$affectedUserId = $body['affected_user_id'] ?? null;
$sourceIp = $body['source_ip'] ?? null;
$personalDataInvolved = (bool)($body['personal_data_involved'] ?? false);
$relatedAuditEntries = $body['related_audit_entries'] ?? null;

// Validation
if (!in_array($severity, ['sev1', 'sev2', 'sev3', 'sev4'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Severity must be sev1, sev2, sev3, or sev4.']);
    exit;
}

if (strlen($triggerSummary) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Trigger summary must be at least 10 characters.']);
    exit;
}

if (strlen($description) < 20) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Description must be at least 20 characters.']);
    exit;
}

// Validate affected user if provided
if ($affectedUserId) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$affectedUserId]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Affected user not found.']);
        exit;
    }
}

// ── Generate incident reference ───────────────────────────────────────────────
$year = date('Y');
$month = date('m');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM incidents WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?');
$stmt->execute([$year, $month]);
$seq = (int)$stmt->fetchColumn() + 1;
$incidentRef = sprintf('INC-%s%s-%04d', $year, $month, $seq);

// ── Insert incident ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare('
    INSERT INTO incidents (
        incident_ref, severity, status, trigger_summary, affected_user_id,
        source_ip, detected_at, reported_by, description, personal_data_involved,
        gdpr_notification_required, related_audit_entries
    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
');

$gdprRequired = $personalDataInvolved ? 1 : 0;
$relatedAuditJson = $relatedAuditEntries ? json_encode($relatedAuditEntries) : null;

$stmt->execute([
    $incidentRef,
    $severity,
    'open',
    $triggerSummary,
    $affectedUserId,
    $sourceIp,
    $reporterId,
    $description,
    $personalDataInvolved ? 1 : 0,
    $gdprRequired,
    $relatedAuditJson,
]);

$incidentId = $pdo->lastInsertId();

// ── Audit log ────────────────────────────────────────────────────────────────
AuditMiddleware::incidentCreated($incidentId, $incidentRef, $severity, $reporterId);

// ── Send alerts for critical incidents ────────────────────────────────────────
if ($severity === 'sev1') {
    // Queue immediate alert for SEV-1
    $redis->lpush('saips:alerts:queue', json_encode([
        'type' => 'incident_critical',
        'incident_ref' => $incidentRef,
        'severity' => $severity,
        'trigger_summary' => $triggerSummary,
        'created_at' => date('c'),
    ]));
}

// ── Return response ──────────────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'status'        => 'success',
    'message'       => 'Incident created successfully.',
    'incident'      => [
        'id'                => $incidentId,
        'incident_ref'      => $incidentRef,
        'severity'          => $severity,
        'status'            => 'open',
        'trigger_summary'   => $triggerSummary,
        'detected_at'       => date('c'),
        'reported_by'       => $reporterId,
        'gdpr_notification_required' => $gdprRequired,
    ],
]);
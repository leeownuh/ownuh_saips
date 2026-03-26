<?php
/**
 * Ownuh SAIPS — PUT /incidents/{id}
 * Update an existing security incident.
 * SRS §5.3 — Incident Management
 * 
 * PUT /incidents/{id}
 * Request body: {
 *   "status": "in_progress|under_review|resolved|closed",
 *   "assigned_to": "uuid" (optional),
 *   "actions_taken": "Description of actions taken...",
 *   "resolution_notes": "How the incident was resolved..." (optional)
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
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
$auth = new AuthMiddleware($pdo, $redis, $secConfig);
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']);
    exit;
}

// Only managers and above can update incidents
$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
if (($hierarchy[$user['role']] ?? 0) < 2) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Manager access required to update incidents.']);
    exit;
}

$userId = $user['sub'];

// ── Parse input ───────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Get incident ID from path or body
$pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$incidentId = $body['incident_id'] ?? ($pathParts[0] ?? null);

if (!$incidentId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Incident ID is required.']);
    exit;
}

// ── Fetch incident ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
$stmt->execute([$incidentId]);
$incident = $stmt->fetch();

if (!$incident) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'Incident not found.']);
    exit;
}

// ── Parse update fields ──────────────────────────────────────────────────────
$status = $body['status'] ?? null;
$assignedTo = $body['assigned_to'] ?? null;
$actionsTaken = $body['actions_taken'] ?? null;
$resolutionNotes = $body['resolution_notes'] ?? null;
$gdprNotifiedAt = $body['gdpr_notified'] ?? null;

// Build update query dynamically
$updateFields = [];
$updateParams = [];

if ($status !== null) {
    $validStatuses = ['open', 'in_progress', 'under_review', 'resolved', 'closed'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Invalid status.']);
        exit;
    }
    $updateFields[] = 'status = ?';
    $updateParams[] = $status;
    
    // Set timestamps based on status
    if ($status === 'in_progress' && !$incident['acknowledged_at']) {
        $updateFields[] = 'acknowledged_at = NOW()';
    }
    if ($status === 'resolved' && !$incident['resolved_at']) {
        $updateFields[] = 'resolved_at = NOW()';
    }
}

if ($assignedTo !== null) {
    // Validate assignee
    if ($assignedTo) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$assignedTo]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Assignee not found.']);
            exit;
        }
    }
    $updateFields[] = 'assigned_to = ?';
    $updateParams[] = $assignedTo ?: null;
}

if ($actionsTaken !== null) {
    $updateFields[] = 'actions_taken = ?';
    $updateParams[] = $actionsTaken;
}

if ($resolutionNotes !== null) {
    $updateFields[] = 'resolution_notes = ?';
    $updateParams[] = $resolutionNotes;
}

if ($gdprNotifiedAt !== null && $incident['gdpr_notification_required']) {
    $updateFields[] = 'gdpr_notified_at = NOW()';
}

if (empty($updateFields)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'NO_CHANGES', 'message' => 'No valid update fields provided.']);
    exit;
}

// ── Execute update ───────────────────────────────────────────────────────────
$updateParams[] = $incidentId;
$sql = 'UPDATE incidents SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
$pdo->prepare($sql)->execute($updateParams);

// ── Audit log ────────────────────────────────────────────────────────────────
AuditMiddleware::incidentUpdated($incidentId, $incident['incident_ref'], $userId, $body);

// ── Return response ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
$stmt->execute([$incidentId]);
$updated = $stmt->fetch();

echo json_encode([
    'status'        => 'success',
    'message'       => 'Incident updated successfully.',
    'incident'      => [
        'id'                => $updated['id'],
        'incident_ref'      => $updated['incident_ref'],
        'severity'          => $updated['severity'],
        'status'            => $updated['status'],
        'assigned_to'       => $updated['assigned_to'],
        'acknowledged_at'   => $updated['acknowledged_at'],
        'resolved_at'       => $updated['resolved_at'],
        'actions_taken'     => $updated['actions_taken'],
    ],
]);
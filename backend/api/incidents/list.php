<?php
/**
 * Ownuh SAIPS — /incidents endpoints
 * GET  /incidents        — list incidents (Admin+)
 * POST /incidents        — create incident report (Admin+)
 * PUT  /incidents/{id}   — update status/notes (Admin+)
 * SRS §5 — Incident Response Procedures
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;

$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

AuditMiddleware::init($pdo);
$auth    = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$adminId = $payload['sub'];
$role    = $payload['role'];

if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /incidents ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = ['1=1'];
    $params = [];

    if ($sev = $_GET['severity'] ?? null) {
        $where[]  = 'severity = ?';
        $params[] = $sev;
    }
    if ($status = $_GET['status'] ?? null) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, (int)($_GET['per_page'] ?? 50));
    $offset  = ($page - 1) * $perPage;
    $ws      = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT i.*, u1.email as reporter_email, u2.email as assignee_email
         FROM incidents i
         LEFT JOIN users u1 ON u1.id = i.reported_by
         LEFT JOIN users u2 ON u2.id = i.assigned_to
         WHERE {$ws}
         ORDER BY
           FIELD(i.severity,'sev1','sev2','sev3','sev4'),
           FIELD(i.status,'open','in_progress','under_review','resolved','closed'),
           i.detected_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([...$params, $perPage, $offset]);

    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// ── POST /incidents ───────────────────────────────────────────────────────────
if ($method === 'POST' && !preg_match('#/incidents/[^/]+#', $uri)) {
    $required = ['severity', 'trigger_summary', 'detected_at', 'description'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => "{$field} is required."]);
            exit;
        }
    }

    // Generate incident ref: INC-YYYY-NNN
    $year     = date('Y');
    // SECURITY FIX: use prepared statement — never interpolate vars into query()
    $lastStmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(incident_ref, '-', -1) AS UNSIGNED)) FROM incidents WHERE incident_ref LIKE ?"
    );
    $lastStmt->execute(['INC-' . $year . '-%']);
    $lastNum  = (int)$lastStmt->fetchColumn();
    $ref      = sprintf('INC-%s-%03d', $year, $lastNum + 1);

    $pdo->prepare(
        'INSERT INTO incidents (incident_ref, severity, trigger_summary, affected_user_id,
            source_ip, detected_at, assigned_to, reported_by, description, actions_taken,
            personal_data_involved, gdpr_notification_required)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ref,
        $body['severity'],
        $body['trigger_summary'],
        $body['affected_user_id'] ?? null,
        $body['source_ip']        ?? null,
        date('Y-m-d H:i:s', strtotime($body['detected_at'])),
        $body['assigned_to']      ?? $adminId,
        $adminId,
        $body['description'],
        $body['actions_taken']    ?? null,
        (int)($body['personal_data_involved'] ?? 0),
        (int)($body['gdpr_notification_required'] ?? 0),
    ]);

    // If GDPR notification required, log reminder
    if (!empty($body['gdpr_notification_required'])) {
        AuditMiddleware::log('ADM-002', 'Incident Filed — GDPR Art.33 Notification Required', null, null, null, null, null, null, [
            'incident_ref' => $ref,
            'notification_deadline_hours' => 72,
        ], $adminId);
    }

    http_response_code(201);
    echo json_encode(['status' => 'success', 'incident_ref' => $ref]);
    exit;
}

// ── PUT /incidents/{id} ───────────────────────────────────────────────────────
if ($method === 'PUT' && preg_match('#/incidents/([a-zA-Z0-9-]+)$#', $uri, $m)) {
    $ref     = $m[1];
    $updates = [];
    $params  = [];

    $allowed = ['status', 'actions_taken', 'assigned_to', 'gdpr_notified_at'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $updates[] = "{$f} = ?";
            $params[]  = $body[$f];
        }
    }

    // Auto-set timestamps
    if (($body['status'] ?? null) === 'resolved' && !isset($body['resolved_at'])) {
        $updates[] = 'resolved_at = NOW()';
    }
    if (($body['status'] ?? null) === 'in_progress' && !isset($body['acknowledged_at'])) {
        $updates[] = 'acknowledged_at = NOW()';
    }

    if (!empty($updates)) {
        $params[] = $ref;
        $pdo->prepare('UPDATE incidents SET ' . implode(', ', $updates) . ' WHERE incident_ref = ?')
            ->execute($params);
    }

    echo json_encode(['status' => 'success', 'message' => 'Incident updated.']);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);

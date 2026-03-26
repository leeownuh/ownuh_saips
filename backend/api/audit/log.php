<?php
/**
 * Ownuh SAIPS — GET /audit/log
 * Returns filtered audit log entries with integrity verification.
 * Requires Admin role. Superadmin can export.
 * SRS §4 — Audit Logging and Monitoring
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');

use SAIPS\Middleware\AuthMiddleware;

$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

$auth    = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$role    = $payload['role'];

// Admin+ required
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN']);
    exit;
}

// Build dynamic query
$where  = ['1=1'];
$params = [];

if ($code = $_GET['event_code'] ?? null) {
    $where[]  = 'event_code = ?';
    $params[] = $code;
}
if ($uid = $_GET['user_id'] ?? null) {
    $where[]  = 'user_id = ?';
    $params[] = $uid;
}
if ($ip = $_GET['source_ip'] ?? null) {
    $where[]  = 'source_ip = ?';
    $params[] = $ip;
}
if ($from = $_GET['from'] ?? null) {
    $where[]  = 'created_at >= ?';
    $params[] = date('Y-m-d H:i:s', strtotime($from));
}
if ($to = $_GET['to'] ?? null) {
    $where[]  = 'created_at <= ?';
    $params[] = date('Y-m-d H:i:s', strtotime($to));
}

// Category filter (maps to event code prefix)
if ($cat = $_GET['category'] ?? null) {
    $prefix   = strtoupper(substr($cat, 0, 3));
    $where[]  = 'event_code LIKE ?';
    $params[] = "{$prefix}-%";
}

$page    = max(1, (int)($_GET['page']     ?? 1));
$perPage = min(500, (int)($_GET['per_page'] ?? 100));
$offset  = ($page - 1) * $perPage;

$whereStr = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE {$whereStr}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch entries
$stmt = $pdo->prepare(
    "SELECT al.id, al.event_code, al.event_name, al.user_id,
            u.display_name, u.email as user_email,
            al.source_ip, al.country_code, al.mfa_method,
            al.risk_score, al.details, al.admin_id,
            al.target_user_id, al.created_at, al.entry_hash, al.prev_hash
     FROM audit_log al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereStr}
     ORDER BY al.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute([...$params, $perPage, $offset]);
$entries = $stmt->fetchAll();

// Verify chain integrity for returned window
$integrityOk = _verifyChainWindow($entries);

// Export (Superadmin only)
if (isset($_GET['export']) && $role === 'superadmin') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Event Code', 'Event Name', 'User', 'Source IP', 'Country', 'MFA Method', 'Risk Score', 'Details', 'Timestamp', 'Hash']);
    // SECURITY FIX: sanitize cells to prevent CSV/formula injection
    $csvSanitize = function(mixed $v): string {
        $s = (string)($v ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $s = "'" . $s; // Prefix with apostrophe to neutralize formula
        }
        return $s;
    };
    foreach ($entries as $e) {
        fputcsv($out, array_map($csvSanitize, [
            $e['id'], $e['event_code'], $e['event_name'],
            $e['user_email'] ?? $e['source_ip'],
            $e['source_ip'], $e['country_code'], $e['mfa_method'],
            $e['risk_score'], $e['details'], $e['created_at'], $e['entry_hash'],
        ]));
    }
    fclose($out);
    exit;
}

// Parse JSON details
foreach ($entries as &$entry) {
    if ($entry['details']) {
        $entry['details'] = json_decode($entry['details'], true);
    }
}

echo json_encode([
    'status'             => 'success',
    'total'              => $total,
    'page'               => $page,
    'per_page'           => $perPage,
    'integrity_verified' => $integrityOk,
    'data'               => $entries,
]);

// ── Chain integrity verification ─────────────────────────────────────────────
function _verifyChainWindow(array $entries): bool
{
    // Verify SHA-256 chain for returned subset
    // Full verification: backend/scripts/verify-audit-chain.php
    $entries = array_reverse($entries); // oldest first for chain check
    $prevHash = null;

    foreach ($entries as $e) {
        if ($prevHash !== null && $e['prev_hash'] !== $prevHash) {
            return false; // Chain broken
        }
        $expected = hash('sha256', implode('|', [
            $e['prev_hash'] ?? 'GENESIS',
            $e['event_code'],
            $e['user_id'] ?? '',
            $e['created_at'],
            $e['details'] ?? '',
        ]));
        if ($expected !== $e['entry_hash']) {
            return false;
        }
        $prevHash = $e['entry_hash'];
    }
    return true;
}

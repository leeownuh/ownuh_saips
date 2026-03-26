#!/usr/bin/env php
<?php
/**
 * Ownuh SAIPS — Audit Chain Verification Script
 * Verifies the SHA-256 chain integrity of the entire audit log.
 * Run daily via cron or after any suspected incident.
 *
 * Usage:
 *   php verify-audit-chain.php
 *   php verify-audit-chain.php --from=2025-03-01 --to=2025-03-21
 *   php verify-audit-chain.php --fix-report   (outputs broken entries only)
 *
 * Exit codes:
 *   0 = chain intact
 *   1 = chain broken (tampering detected)
 *   2 = configuration/DB error
 */

declare(strict_types=1);

$opts = getopt('', ['from:', 'to:', 'fix-report']);
$from = $opts['from'] ?? null;
$to   = $opts['to']   ?? null;
$fixReport = isset($opts['fix-report']);

// Load config
$envFile = __DIR__ . '/../../backend/config/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'], $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

// Build query
$where  = ['1=1'];
$params = [];
if ($from) { $where[] = 'created_at >= ?'; $params[] = $from; }
if ($to)   { $where[] = 'created_at <= ?'; $params[] = $to; }

$stmt = $pdo->prepare(
    'SELECT id, event_code, user_id, created_at, details, entry_hash, prev_hash
     FROM audit_log WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC'
);
$stmt->execute($params);

$total      = 0;
$broken     = 0;
$prevHash   = null;
$brokenList = [];

echo "Ownuh SAIPS — Audit Chain Verification\n";
echo str_repeat('─', 60) . "\n";
if ($from || $to) {
    echo "Range: " . ($from ?? 'beginning') . " → " . ($to ?? 'now') . "\n\n";
}

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $total++;

    // Verify prev_hash pointer
    if ($total === 1 && $row['prev_hash'] !== null) {
        // First entry in range may have a prev_hash pointing to earlier entry
        // Only flag if this is truly the first entry in the table
        $firstId = $pdo->query('SELECT MIN(id) FROM audit_log')->fetchColumn();
        if ($row['id'] == $firstId && $row['prev_hash'] !== null) {
            $broken++;
            $brokenList[] = ['id' => $row['id'], 'issue' => 'Genesis entry has non-null prev_hash'];
        }
    } elseif ($prevHash !== null && $row['prev_hash'] !== $prevHash) {
        $broken++;
        $brokenList[] = [
            'id'       => $row['id'],
            'issue'    => 'prev_hash mismatch',
            'expected' => $prevHash,
            'actual'   => $row['prev_hash'],
        ];
    }

    // Verify entry_hash
    $expected = hash('sha256', implode('|', [
        $row['prev_hash'] ?? 'GENESIS',
        $row['event_code'],
        $row['user_id'] ?? '',
        $row['created_at'],
        $row['details'] ?? '',
    ]));

    if ($expected !== $row['entry_hash']) {
        $broken++;
        $brokenList[] = [
            'id'       => $row['id'],
            'issue'    => 'entry_hash mismatch (possible tampering)',
            'expected' => $expected,
            'actual'   => $row['entry_hash'],
        ];
    }

    $prevHash = $row['entry_hash'];
}

echo "Entries verified : {$total}\n";
echo "Chain breaks     : {$broken}\n";
echo str_repeat('─', 60) . "\n";

if ($broken === 0) {
    echo "✅  CHAIN INTEGRITY VERIFIED — No tampering detected.\n";
    exit(0);
} else {
    echo "🚨  CHAIN INTEGRITY FAILURE — {$broken} broken link(s) detected!\n\n";
    echo "This may indicate log tampering. Escalate immediately to Security Officer.\n\n";

    if ($fixReport || true) {
        echo "Broken entries:\n";
        foreach ($brokenList as $b) {
            echo "  Entry ID {$b['id']}: {$b['issue']}\n";
        }
    }

    // Write to security alert log
    error_log("[SAIPS CRITICAL] Audit chain integrity failure: {$broken} broken link(s). Run verify-audit-chain.php for details.");

    exit(1);
}

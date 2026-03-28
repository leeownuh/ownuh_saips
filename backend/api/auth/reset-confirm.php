<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 1) . '/../../vendor/autoload.php';

session_start();

use SAIPS\Middleware\AuditMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

AuditMiddleware::init(get_audit_pdo());

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$plainToken = trim((string)($body['token'] ?? ''));
$password   = (string)($body['password'] ?? '');
$confirm    = (string)($body['confirm_password'] ?? '');
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($plainToken === '' || $password === '' || $confirm === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
    exit;
}

function validate_password_strength(string $password): ?string {
    if (strlen($password) < 12 || strlen($password) > 128) {
        return 'Password must be between 12 and 128 characters.';
    }

    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
    $classes += preg_match('/\d/', $password) ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z\d]/', $password) ? 1 : 0;

    if ($classes < 3) {
        return 'Password must include at least 3 of 4 character classes.';
    }

    return null;
}

$policyError = validate_password_strength($password);
if ($policyError !== null) {
    echo json_encode(['status' => 'error', 'message' => $policyError]);
    exit;
}

$db = Database::getInstance();

try {
    $tokenHash = hash('sha256', $plainToken);

    $reset = $db->fetchOne(
        'SELECT user_id, token_hash, expires_at, used_at
         FROM password_resets
         WHERE token_hash = ?',
        [$tokenHash]
    );

    if (!$reset) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid reset link.']);
        exit;
    }

    $user = $db->fetchOne(
        'SELECT email, role, display_name
         FROM users
         WHERE id = ?',
        [$reset['user_id']]
    );

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid reset link.']);
        exit;
    }

    if ($reset['used_at'] !== null) {
        echo json_encode(['status' => 'error', 'message' => 'This reset link has already been used.']);
        exit;
    }

    if (strtotime($reset['expires_at']) < time()) {
        echo json_encode(['status' => 'error', 'message' => 'This reset link has expired.']);
        exit;
    }

    if (in_array($user['role'], ['admin', 'superadmin'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Admin accounts cannot use self-service reset.']);
        exit;
    }

    // stop simple email resemblance
    $emailLocal = strtolower((string)strtok($user['email'], '@'));
    if ($emailLocal !== '' && str_contains(strtolower($password), $emailLocal)) {
        echo json_encode(['status' => 'error', 'message' => 'Password must not resemble your email address.']);
        exit;
    }

    $authDbConfig = require dirname(__DIR__, 2) . '/config/database.php';
    $authHost  = $_ENV['DB_AUTH_HOST'] ?? $authDbConfig['auth']['host'] ?? '127.0.0.1';
    $authUser  = $_ENV['DB_AUTH_USER'] ?? $authDbConfig['auth']['user'] ?? 'root';
    $authPass  = $_ENV['DB_AUTH_PASS'] ?? $authDbConfig['auth']['pass'] ?? '';
    $authPort  = (int)($_ENV['DB_AUTH_PORT'] ?? $authDbConfig['auth']['port'] ?? 3306);

    $authConn = new mysqli($authHost, $authUser, $authPass, 'ownuh_credentials', $authPort);

    if ($authConn->connect_error) {
        throw new RuntimeException('Credentials database connection failed.');
    }

    $authConn->set_charset('utf8mb4');

    // optional recent password history check
    $historyStmt = $authConn->prepare(
        'SELECT password_hash
         FROM password_history
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 12'
    );

    if ($historyStmt) {
        $historyStmt->bind_param('s', $reset['user_id']);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();

        while ($row = $historyResult->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $historyStmt->close();
                $authConn->close();
                echo json_encode(['status' => 'error', 'message' => 'You cannot reuse one of your recent passwords.']);
                exit;
            }
        }
        $historyStmt->close();
    }

    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $updateStmt = $authConn->prepare(
        'UPDATE credentials
         SET password_hash = ?
         WHERE user_id = ?'
    );
    if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare credentials update.');
    }

    $updateStmt->bind_param('ss', $newHash, $reset['user_id']);
    $updateStmt->execute();
    $updateStmt->close();

    $insertHistory = $authConn->prepare(
        'INSERT INTO password_history (user_id, password_hash, changed_at)
         VALUES (?, ?, NOW())'
    );
    if ($insertHistory) {
        $insertHistory->bind_param('ss', $reset['user_id'], $newHash);
        $insertHistory->execute();
        $insertHistory->close();
    }

    $authConn->close();

    $db->execute(
        'UPDATE users
         SET password_changed_at = NOW(),
             failed_attempts = 0,
             last_failed_at = NULL,
             status = CASE WHEN status = "locked" THEN "active" ELSE status END
         WHERE id = ?',
        [$reset['user_id']]
    );

    $db->execute(
        'UPDATE password_resets
         SET used_at = NOW()
         WHERE token_hash = ?',
        [$tokenHash]
    );

    // revoke all active sessions after password change
    $sessions = $db->fetchAll(
        'SELECT refresh_token_hash
         FROM sessions
         WHERE user_id = ? AND invalidated_at IS NULL',
        [$reset['user_id']]
    );

    foreach ($sessions as $s) {
        try {
            if (class_exists('Redis')) {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->del('saips:session:' . $s['refresh_token_hash']);
            }
        } catch (Throwable $e) {
            error_log('[SAIPS] Redis revoke failed after password reset: ' . $e->getMessage());
        }
    }

    $db->execute(
        'UPDATE sessions
         SET invalidated_at = NOW(),
             invalidation_reason = ?
         WHERE user_id = ? AND invalidated_at IS NULL',
        ['Password reset completed', $reset['user_id']]
    );

    AuditMiddleware::passwordChanged($reset['user_id'], 'self_service');

    echo json_encode([
        'status'  => 'success',
        'message' => 'Password updated successfully. Please sign in again.'
    ]);
} catch (Throwable $e) {
    error_log('[SAIPS] Password reset confirm failed: ' . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unable to reset password right now.'
    ]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
session_start();

use SAIPS\Middleware\AuditMiddleware;

$authUser = require_auth('admin');
$db       = Database::getInstance();
$csrf     = csrf_token();

AuditMiddleware::init(get_audit_pdo());

$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
$flash     = null;
$flashType = 'success';

function clear_current_auth_session(): void {
    $trustedProxy = $_ENV['TRUSTED_PROXY'] ?? '';
    $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '';
    $proxyTrusted = ($trustedProxy !== '')
                 && ($trustedProxy === 'any' || $remoteAddr === $trustedProxy);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
             || ($proxyTrusted && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $sameSite = $_ENV['COOKIE_SAMESITE'] ?? ($proxyTrusted ? 'Lax' : 'Strict');

    setcookie('saips_access', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite,
    ]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }

        session_destroy();
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $flash = 'Invalid request.';
        $flashType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $reason = trim((string)($_POST['reason'] ?? 'Session revoked by administrator'));
        $currentUserId = $authUser['sub'] ?? $authUser['id'] ?? null;

        try {
            if ($action === 'revoke_user') {
                $targetUserId = trim((string)($_POST['user_id'] ?? ''));

                if ($targetUserId === '') {
                    throw new RuntimeException('Please select a user.');
                }

                $targetUser = $db->fetchOne(
                    'SELECT id, email, display_name, role
                     FROM users
                     WHERE id = ? AND deleted_at IS NULL',
                    [$targetUserId]
                );

                if (!$targetUser) {
                    throw new RuntimeException('User not found.');
                }

                if (
                    ($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$authUser['role']] ?? 0)
                    && ($authUser['role'] ?? '') !== 'superadmin'
                ) {
                    throw new RuntimeException('You cannot revoke sessions for a user with equal or higher privileges.');
                }

                $sessions = $db->fetchAll(
                    'SELECT id, refresh_token_hash
                     FROM sessions
                     WHERE user_id = ? AND invalidated_at IS NULL',
                    [$targetUserId]
                );

                foreach ($sessions as $s) {
                    try {
                        $redis = new Redis();
                        $redis->connect('127.0.0.1', 6379);
                        $redis->del('saips:session:' . $s['refresh_token_hash']);
                    } catch (Throwable $e) {
                        error_log('[SAIPS] Redis revoke failed: ' . $e->getMessage());
                    }
                }

                $count = $db->execute(
                    'UPDATE sessions
                     SET invalidated_at = NOW(),
                         invalidated_by = ?,
                         invalidation_reason = ?
                     WHERE user_id = ? AND invalidated_at IS NULL',
                    [$currentUserId, $reason, $targetUserId]
                );

                AuditMiddleware::log(
                    'SES-003',
                    'All Sessions Revoked',
                    $currentUserId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    null,
                    json_encode([
                        'target_user_id' => $targetUserId,
                        'target_email'   => $targetUser['email'],
                        'revoked_count'  => $count,
                        'reason'         => $reason,
                    ], JSON_UNESCAPED_SLASHES)
                );

                // If revoking your own sessions, log out immediately
                if ($targetUserId === $currentUserId) {
                    clear_current_auth_session();
                    header('Location: login.php?revoked=1');
                    exit;
                }

                $flash = $count > 0
                    ? "Revoked {$count} active session(s) for {$targetUser['display_name']}."
                    : 'No active sessions found for that user.';
            }

            elseif ($action === 'revoke_session') {
                $sessionId = trim((string)($_POST['session_id'] ?? ''));

                if ($sessionId === '') {
                    throw new RuntimeException('Please enter a session ID.');
                }

                $session = $db->fetchOne(
                    'SELECT s.id, s.user_id, s.refresh_token_hash, u.role, u.display_name, u.email
                     FROM sessions s
                     JOIN users u ON u.id = s.user_id
                     WHERE s.id = ? AND s.invalidated_at IS NULL',
                    [$sessionId]
                );

                if (!$session) {
                    throw new RuntimeException('Session not found or already revoked.');
                }

                if (
                    ($hierarchy[$session['role']] ?? 0) >= ($hierarchy[$authUser['role']] ?? 0)
                    && ($authUser['role'] ?? '') !== 'superadmin'
                ) {
                    throw new RuntimeException('You cannot revoke a session for a user with equal or higher privileges.');
                }

                try {
                    $redis = new Redis();
                    $redis->connect('127.0.0.1', 6379);
                    $redis->del('saips:session:' . $session['refresh_token_hash']);
                } catch (Throwable $e) {
                    error_log('[SAIPS] Redis revoke failed: ' . $e->getMessage());
                }

                $db->execute(
                    'UPDATE sessions
                     SET invalidated_at = NOW(),
                         invalidated_by = ?,
                         invalidation_reason = ?
                     WHERE id = ? AND invalidated_at IS NULL',
                    [$currentUserId, $reason, $sessionId]
                );

                AuditMiddleware::log(
                    'SES-002',
                    'Session Revoked',
                    $currentUserId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    null,
                    json_encode([
                        'session_id'      => $sessionId,
                        'target_user_id'  => $session['user_id'],
                        'target_email'    => $session['email'],
                        'reason'          => $reason,
                    ], JSON_UNESCAPED_SLASHES)
                );

                // If revoking one of your own sessions, log out immediately
                if ($session['user_id'] === $currentUserId) {
                    clear_current_auth_session();
                    header('Location: login.php?revoked=1');
                    exit;
                }

                $flash = "Session {$sessionId} revoked successfully.";
            }

            elseif ($action === 'revoke_all_system') {
                if (($authUser['role'] ?? '') !== 'superadmin') {
                    throw new RuntimeException('Only a superadmin can revoke all system sessions.');
                }

                $confirm = trim((string)($_POST['confirm_text'] ?? ''));
                if ($confirm !== 'CONFIRM REVOKE ALL') {
                    throw new RuntimeException('Confirmation text does not match.');
                }

                $sessions = $db->fetchAll(
                    'SELECT refresh_token_hash
                     FROM sessions
                     WHERE invalidated_at IS NULL'
                );

                foreach ($sessions as $s) {
                    try {
                        $redis = new Redis();
                        $redis->connect('127.0.0.1', 6379);
                        $redis->del('saips:session:' . $s['refresh_token_hash']);
                    } catch (Throwable $e) {
                        error_log('[SAIPS] Redis revoke failed: ' . $e->getMessage());
                    }
                }

                $count = $db->execute(
                    'UPDATE sessions
                     SET invalidated_at = NOW(),
                         invalidated_by = ?,
                         invalidation_reason = ?
                     WHERE invalidated_at IS NULL',
                    [$currentUserId, 'SYSTEM-WIDE: ' . $reason]
                );

                AuditMiddleware::log(
                    'SES-004',
                    'All System Sessions Revoked',
                    $currentUserId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    null,
                    json_encode([
                        'revoked_count' => $count,
                        'reason'        => $reason,
                    ], JSON_UNESCAPED_SLASHES)
                );

                // System-wide revoke should also kick out the current superadmin
                clear_current_auth_session();
                header('Location: login.php?revoked=1');
                exit;
            }

            else {
                throw new RuntimeException('Invalid action.');
            }
        } catch (Throwable $e) {
            $flash = $e->getMessage();
            $flashType = 'danger';
        }
    }
}

$users = $db->fetchAll(
    'SELECT id, email, display_name, role, status
     FROM users
     WHERE deleted_at IS NULL
     ORDER BY FIELD(role,"superadmin","admin","manager","user"), display_name'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Revoke Sessions | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>

<main class="app-wrapper">
    <div class="app-container">

        <div class="hstack flex-wrap gap-3 mb-5">
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-semibold">
                    <i class="ri-logout-circle-line me-2 text-danger"></i>Revoke Sessions
                </h4>
                <nav>
                    <ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">Revoke Sessions</li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= esc($flashType) ?> mb-4">
                <?= esc($flash) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0 text-danger">
                            <i class="ri-user-unfollow-line me-2"></i>Revoke All Sessions for a User
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13">
                            Immediately invalidates all active sessions and refresh tokens for the selected user.
                            The user will be logged out of all devices.
                        </p>

                        <form method="POST" action="sessions-revoke.php">
                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                            <input type="hidden" name="action" value="revoke_user">

                            <div class="mb-3">
                                <label class="form-label">Select User</label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">— Select user —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= esc($u['id']) ?>">
                                            <?= esc($u['email']) ?> (<?= esc(ucfirst($u['role'])) ?>)<?= $u['status'] !== 'active' ? ' — ' . esc(strtoupper($u['status'])) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason / Justification</label>
                                <textarea class="form-control" name="reason" rows="3" required placeholder="Required for audit log"></textarea>
                            </div>

                            <button class="btn btn-danger w-100" type="submit">
                                <i class="ri-logout-circle-line me-2"></i>Revoke All Sessions for User
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0 text-warning">
                            <i class="ri-global-line me-2"></i>Revoke Session by ID
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13">
                            Revoke a specific session token by its Session ID without affecting the user's other active sessions.
                        </p>

                        <form method="POST" action="sessions-revoke.php">
                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                            <input type="hidden" name="action" value="revoke_session">

                            <div class="mb-3">
                                <label class="form-label">Session ID</label>
                                <input type="text" class="form-control fw-mono" name="session_id" placeholder="Session ID" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason / Justification</label>
                                <textarea class="form-control" name="reason" rows="3" required placeholder="Required for audit log"></textarea>
                            </div>

                            <button class="btn btn-warning w-100" type="submit">
                                <i class="ri-close-circle-line me-2"></i>Revoke Specific Session
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger-subtle">
                        <h5 class="card-title mb-0 text-danger">
                            <i class="ri-alarm-warning-line me-2"></i>Emergency — Revoke ALL Active Sessions (System-Wide)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Nuclear option: immediately invalidates every active session across all users.
                            Use only during confirmed active breach. Superadmin only.
                        </p>

                        <form method="POST" action="sessions-revoke.php">
                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                            <input type="hidden" name="action" value="revoke_all_system">

                            <div class="mb-3">
                                <label class="form-label">Type <strong>CONFIRM REVOKE ALL</strong> to proceed</label>
                                <input type="text" class="form-control border-danger" name="confirm_text" placeholder="CONFIRM REVOKE ALL" <?= ($authUser['role'] ?? '') !== 'superadmin' ? 'disabled' : '' ?>>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason / Justification</label>
                                <textarea class="form-control" name="reason" rows="3" required <?= ($authUser['role'] ?? '') !== 'superadmin' ? 'disabled' : '' ?> placeholder="Required for audit log"></textarea>
                            </div>

                            <button class="btn btn-outline-danger w-100" type="submit" <?= ($authUser['role'] ?? '') !== 'superadmin' ? 'disabled' : '' ?>>
                                <i class="ri-delete-bin-line me-2"></i>Revoke ALL System Sessions — Superadmin Only
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script src="assets/js/pages/scroll-top.init.js"></script>
<script src="assets/js/app.js" type="module"></script>
</body>
</html>
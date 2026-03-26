<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Session Revocation (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

// POST: revoke session(s)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403); die('CSRF token mismatch.');
    }
    $action    = $_POST['action']    ?? '';
    $sessionId = $_POST['session_id'] ?? '';
    $targetUid = $_POST['user_id']    ?? '';
    $reason    = $_POST['reason']    ?? 'Admin revocation';

    if ($action === 'revoke_one' && $sessionId) {
        // CAP512 Unit 7: parameterised UPDATE
        $db->execute(
            'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?,
             invalidation_reason = ? WHERE id = ?',
            [$user['id'], $reason, $sessionId]
        );
    } elseif ($action === 'revoke_user' && $targetUid) {
        $db->execute(
            'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?,
             invalidation_reason = ?
             WHERE user_id = ? AND invalidated_at IS NULL AND expires_at > NOW()',
            [$user['id'], $reason, $targetUid]
        );
    } elseif ($action === 'revoke_all') {
        // Superadmin only
        if ($user['role'] === 'superadmin') {
            $db->execute(
                'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?,
                 invalidation_reason = ?
                 WHERE user_id != ? AND invalidated_at IS NULL AND expires_at > NOW()',
                [$user['id'], 'Mass revocation by superadmin', $user['id']]
            );
        }
    }
    header('Location: sessions-revoke.php?done=1');
    exit;
}

// CAP512 Unit 7: load data
$sessions = get_active_sessions(200);

// CAP512 Unit 5: array_filter + grouping
$adminSessions = array_filter($sessions, fn($s) => in_array($s['role'], ['admin','superadmin']));
$userSessions  = array_filter($sessions, fn($s) => !in_array($s['role'], ['admin','superadmin']));

// Users with multiple sessions
$sessionCounts = [];
foreach ($sessions as $s) {
    $sessionCounts[$s['user_id']] = ($sessionCounts[$s['user_id']] ?? 0) + 1;
}
arsort($sessionCounts);
$multiSessionUsers = array_filter($sessionCounts, fn($c) => $c > 1);

// Recently revoked
$recentRevoked = $db->fetchAll(
    'SELECT s.id, u.display_name, u.email, s.ip_address, s.invalidated_at,
            s.invalidation_reason, a.display_name as revoked_by_name
     FROM sessions s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN users a ON a.id = s.invalidated_by
     WHERE s.invalidated_at IS NOT NULL
     ORDER BY s.invalidated_at DESC LIMIT 20'
);

$done = isset($_GET['done']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Session Revocation | Ownuh SAIPS</title>
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
                <h4 class="mb-1 fw-semibold"><i class="ri-logout-box-r-line me-2 text-danger"></i>Session Revocation</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="sessions-active.php">Sessions</a></li>
                    <li class="breadcrumb-item active">Revoke</li>
                </ol></nav>
            </div>
            <?php if ($user['role'] === 'superadmin'): ?>
            <form method="POST" onsubmit="return confirm('Revoke ALL active sessions (except yours)? This will force re-login for all users.')">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="revoke_all">
                <button type="submit" class="btn btn-danger">
                    <i class="ri-shield-keyhole-line me-1"></i>Revoke All Sessions
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($done): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i>Session(s) revoked successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-primary"><?= count($sessions) ?></div>
                    <div class="text-muted small">Active Sessions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-danger"><?= count($adminSessions) ?></div>
                    <div class="text-muted small">Admin Sessions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-warning"><?= count($multiSessionUsers) ?></div>
                    <div class="text-muted small">Multi-Session Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-secondary"><?= count($recentRevoked) ?></div>
                    <div class="text-muted small">Recently Revoked</div>
                </div>
            </div>
        </div>

        <!-- Active Sessions Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 py-3 hstack">
                <h5 class="mb-0 fw-semibold flex-grow-1">Active Sessions</h5>
                <span class="badge bg-primary-subtle text-primary"><?= count($sessions) ?> total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>IP Address</th>
                                <th>MFA Method</th>
                                <th>Created</th>
                                <th>Idle (min)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No active sessions.</td></tr>
                        <?php else: foreach ($sessions as $s):
                            $isMe    = $s['user_id'] === $user['id'];
                            $isAdmin = in_array($s['role'], ['admin','superadmin']);
                        ?>
                            <tr class="<?= $isAdmin ? 'table-warning' : '' ?>">
                                <td>
                                    <div class="fw-semibold"><?= esc($s['display_name']) ?></div>
                                    <div class="text-muted small"><?= esc($s['email']) ?></div>
                                </td>
                                <td><span class="badge bg-secondary"><?= esc($s['role']) ?></span></td>
                                <td><code><?= esc($s['ip_address']) ?></code></td>
                                <td><?= esc($s['mfa_method'] ?? 'none') ?></td>
                                <td class="small text-muted"><?= esc(substr($s['created_at'], 0, 16)) ?></td>
                                <td>
                                    <?php $idle = (int)$s['idle_minutes']; ?>
                                    <span class="badge <?= $idle > 30 ? 'bg-danger-subtle text-danger' : ($idle > 10 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success') ?>">
                                        <?= $idle ?>m
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$isMe): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this session?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="revoke_one">
                                        <input type="hidden" name="session_id" value="<?= esc($s['id']) ?>">
                                        <input type="hidden" name="reason" value="Admin single revocation">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="ri-logout-box-r-line"></i> Revoke
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Revoke ALL sessions for <?= esc($s['display_name']) ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="revoke_user">
                                        <input type="hidden" name="user_id" value="<?= esc($s['user_id']) ?>">
                                        <input type="hidden" name="reason" value="Admin revoked all user sessions">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Revoke all sessions for this user">
                                            <i class="ri-shield-off-line"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted small"><i class="ri-user-line me-1"></i>Your session</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recently Revoked -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="mb-0 fw-semibold">Recently Revoked Sessions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>User</th><th>IP</th><th>Revoked At</th><th>Reason</th><th>Revoked By</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentRevoked)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No revocation history.</td></tr>
                        <?php else: foreach ($recentRevoked as $r): ?>
                            <tr>
                                <td>
                                    <div><?= esc($r['display_name']) ?></div>
                                    <div class="text-muted small"><?= esc($r['email']) ?></div>
                                </td>
                                <td><code><?= esc($r['ip_address']) ?></code></td>
                                <td class="small text-muted"><?= esc(substr($r['invalidated_at'], 0, 16)) ?></td>
                                <td class="small"><?= esc($r['invalidation_reason'] ?? '—') ?></td>
                                <td class="small"><?= esc($r['revoked_by_name'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
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
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"],[title]').forEach(el => {
    try { new bootstrap.Tooltip(el, {trigger:'hover'}); } catch(e){}
});
</script>
</body>
</html>

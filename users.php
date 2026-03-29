<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — User Management (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, GD graphics
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$csrf = csrf_token();

$db     = Database::getInstance();
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$demoReadOnly = app_is_demo_mode();
$flashBypass = $_SESSION['flash_mfa_bypass'] ?? null;
$flashUser = $_SESSION['flash_user_created'] ?? null;
$flashDemo = $_SESSION['flash_demo_read_only'] ?? null;
unset($_SESSION['flash_mfa_bypass']);
unset($_SESSION['flash_user_created']);
unset($_SESSION['flash_demo_read_only']);

if ($demoReadOnly && is_array($flashBypass)) {
    $flashIdentity = app_demo_safe_identity(
        (string)($flashBypass['email'] ?? ''),
        (string)($flashBypass['display_name'] ?? ''),
        'admin'
    );
    $flashBypass['display_name'] = $flashIdentity['display_name'];
    $flashBypass['email'] = $flashIdentity['email'];
    if (!empty($flashBypass['token'])) {
        $flashBypass['token'] = app_demo_safe_identifier((string)$flashBypass['token'], 'TOK');
    }
}

if ($demoReadOnly && is_array($flashUser)) {
    $flashIdentity = app_demo_safe_identity(
        (string)($flashUser['email'] ?? ''),
        (string)($flashUser['display_name'] ?? ''),
        (string)($flashUser['role'] ?? 'user')
    );
    $flashUser['display_name'] = $flashIdentity['display_name'];
    $flashUser['email'] = $flashIdentity['email'];
}

// POST: handle quick actions (lock/unlock/reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($demoReadOnly) {
        $_SESSION['flash_demo_read_only'] = 'Demo experience is view-only. User lifecycle controls stay visible for storytelling, but live changes are disabled.';
        header('Location: users.php?status=' . urlencode($status) . '&search=' . urlencode($search) . '&page=' . $page);
        exit;
    }

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
    $currentUser = verify_session();
    // SECURITY FIX: validate action against strict allowlist before executing
    $action   = $_POST['action']    ?? '';
    $targetId = $_POST['user_id']   ?? '';
    $reason   = $_POST['reason']    ?? 'Admin action';

    if ($action === 'create_user') {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $newRole = (string)($_POST['role'] ?? 'user');
        $newStatus = (string)($_POST['status'] ?? 'pending');

        if ($displayName === '' || $email === '') {
            http_response_code(400);
            die('Display name and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            die('Invalid email address.');
        }

        if (!in_array($newRole, ['user', 'manager', 'admin', 'superadmin'], true)) {
            http_response_code(422);
            die('Invalid role.');
        }

        if (!in_array($newStatus, ['active', 'pending', 'locked', 'suspended'], true)) {
            http_response_code(422);
            die('Invalid status.');
        }

        $currentRole = $currentUser['role'] ?? 'user';
        if (in_array($newRole, ['admin', 'superadmin'], true) && $currentRole !== 'superadmin') {
            http_response_code(403);
            die('Only superadmins can create admin or superadmin users.');
        }

        $existingUser = $db->fetchOne(
            'SELECT id FROM users WHERE email = ? AND deleted_at IS NULL',
            [$email]
        );
        if ($existingUser) {
            http_response_code(409);
            die('A user with that email already exists.');
        }

        $newUserId = 'usr-' . substr(bin2hex(random_bytes(16)), 0, 33);
        $defaultPassword = 'Welcome@SAIPS2026!';
        $bcryptCost = max(10, min(14, (int)($_ENV['BCRYPT_COST'] ?? 12)));
        $db->execute(
            'INSERT INTO users (id, display_name, email, role, status, mfa_enrolled, mfa_factor, email_verified)
             VALUES (?, ?, ?, ?, ?, 0, "none", 0)',
            [$newUserId, $displayName, $email, $newRole, $newStatus]
        );

        try {
            provision_user_credentials($newUserId, $defaultPassword, null, $bcryptCost);
        } catch (Throwable $e) {
            $db->execute('DELETE FROM users WHERE id = ?', [$newUserId]);
            error_log('[SAIPS] Failed to create default credentials for new user: ' . $e->getMessage());
            http_response_code(500);
            die('User record created, but default credentials could not be provisioned.');
        }

        $_SESSION['flash_user_created'] = [
            'display_name' => $displayName,
            'email' => $email,
            'role' => $newRole,
            'status' => $newStatus,
            'default_password' => $defaultPassword,
        ];

        unset($_SESSION['csrf_token']);
        header('Location: users.php?status=' . urlencode($status) . '&search=' . urlencode($search) . '&page=' . $page);
        exit;
    }

    $allowedActions = ['lock', 'unlock', 'delete', 'issue_bypass'];
    if (!in_array($action, $allowedActions, true)) {
        http_response_code(400);
        die('Invalid action.');
    }

    // Accept the UUID-like and prefixed user IDs used across the app.
    if (!$targetId || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $targetId)) {
        http_response_code(400);
        die('Invalid user ID.');
    }

    // Prevent admin from acting on their own account (accidental self-lockout)
    if ($currentUser && $currentUser['sub'] === $targetId && $action !== 'unlock') {
        http_response_code(400);
        die('You cannot perform this action on your own account.');
    }

    if ($action === 'issue_bypass') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $durationHours = max(1, min(4, (int)($_POST['duration_hours'] ?? 4)));

        if (strlen($reason) < 10) {
            http_response_code(400);
            die('A detailed reason of at least 10 characters is required.');
        }

        $targetUser = $db->fetchOne(
            'SELECT id, email, display_name, role, mfa_enrolled, mfa_factor, deleted_at
             FROM users
             WHERE id = ? AND deleted_at IS NULL',
            [$targetId]
        );

        if (!$targetUser) {
            http_response_code(404);
            die('User not found.');
        }

        $hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
        $currentRole = $currentUser['role'] ?? 'user';
        if (($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$currentRole] ?? 0) && $currentRole !== 'superadmin') {
            http_response_code(403);
            die('You cannot issue a bypass token for a user with equal or higher privileges.');
        }

        $bypassToken = bin2hex(random_bytes(32));
        $bypassTokenHash = hash('sha256', $bypassToken);
        $expiresAt = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

        $db->execute(
            'UPDATE users
             SET mfa_bypass_token = ?, mfa_bypass_expiry = ?
             WHERE id = ?',
            [$bypassTokenHash, $expiresAt, $targetId]
        );

        try {
            $dbConfig = require __DIR__ . '/backend/config/database.php';
            $redis = new Redis();
            $redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
            if (!empty($dbConfig['redis']['pass'])) {
                $redis->auth($dbConfig['redis']['pass']);
            }
            $redis->setex("saips:mfa_bypass:{$bypassTokenHash}", $durationHours * 3600, json_encode([
                'user_id' => $targetId,
                'admin_id' => $currentUser['sub'],
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ], JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            error_log('[SAIPS] Redis store failed for MFA bypass token: ' . $e->getMessage());
        }

        if (class_exists('SAIPS\\Middleware\\AuditMiddleware')) {
            \SAIPS\Middleware\AuditMiddleware::init(get_audit_pdo());
            \SAIPS\Middleware\AuditMiddleware::mfaBypassIssued($targetId, $currentUser['sub'], $reason);
        }

        $_SESSION['flash_mfa_bypass'] = [
            'user_id' => $targetUser['id'],
            'display_name' => $targetUser['display_name'],
            'email' => $targetUser['email'],
            'token' => $bypassToken,
            'expires_at' => $expiresAt,
            'duration_hours' => $durationHours,
            'reason' => $reason,
        ];

        unset($_SESSION['csrf_token']);
        header('Location: users.php?status=' . urlencode($status) . '&search=' . urlencode($search) . '&page=' . $page);
        exit;
    }

    if ($action === 'lock') {
        $db->execute("UPDATE users SET status = 'locked' WHERE id = ?", [$targetId]);
    } elseif ($action === 'unlock') {
        $db->execute("UPDATE users SET status = 'active', failed_attempts = 0, last_failed_at = NULL WHERE id = ?", [$targetId]);
    } elseif ($action === 'delete') {
        $db->execute("UPDATE users SET deleted_at = NOW() WHERE id = ?", [$targetId]);
    }
    // Rotate CSRF after use
    unset($_SESSION['csrf_token']);
    header('Location: users.php?status=' . urlencode($status));
    exit;
}

// Fetch users — CAP512 Unit 7: DB + Unit 5: arrays
$users = get_users($status, $search, 100);

// User stats — CAP512 Unit 7: aggregation
$statsSql = 'SELECT COUNT(*) as total,
            SUM(status="active") as active,
            SUM(status="locked") as locked,
            SUM(status="pending") as pending,
            SUM(mfa_enrolled=0) as no_mfa
     FROM users WHERE deleted_at IS NULL';
$statsParams = [];
$statsTypes = '';

if ($demoReadOnly) {
    $seedUserIds = app_demo_seed_user_ids();
    $statsSql .= ' AND id IN (' . implode(',', array_fill(0, count($seedUserIds), '?')) . ')';
    $statsParams = $seedUserIds;
    $statsTypes = str_repeat('s', count($seedUserIds));
}

$stats = $db->fetchOne($statsSql, $statsParams, $statsTypes);

// Paginate — CAP512 Unit 5: array functions
$paginated = paginate($users, $page, 20);
$users = $paginated['items'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>User Management | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-group-line me-2 text-primary"></i>User Account Management</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">User Management</li>
                    </ol></nav>
                </div>
                <?php if ($demoReadOnly): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" disabled>
                    <i class="ri-eye-line me-1"></i>Demo Read-Only
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="ri-user-add-line me-1"></i>Add User
                </button>
                <?php endif; ?>
            </div>

            <?php if ($demoReadOnly): ?>
            <div class="alert border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,rgba(15,39,64,0.96) 0%, rgba(43,91,68,0.94) 100%); color:#f4f7fb;">
                <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                    <div>
                        <div class="text-uppercase text-white text-opacity-75 fw-semibold fs-12 mb-1">Demo-safe User Story</div>
                        <div class="fw-semibold text-white mb-1">Identity data is tokenised and controls are view-only.</div>
                        <div class="text-white text-opacity-75 small">Walk visitors through roles, MFA readiness, login history, and audit pivots without exposing your live team or letting anyone mutate the environment.</div>
                    </div>
                    <a href="settings-compliance.php" class="btn btn-light btn-sm"><i class="ri-file-chart-line me-1"></i>Next: Compliance</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($flashDemo): ?>
            <div class="alert alert-warning d-flex gap-2 mb-4" role="alert">
                <i class="ri-information-line flex-shrink-0"></i>
                <span><?= esc($flashDemo) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($flashBypass): ?>
            <div class="alert alert-warning d-flex flex-column gap-2 mb-4" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="ri-key-2-line fs-4 flex-shrink-0"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">MFA bypass token issued for <?= esc($flashBypass['display_name']) ?>.</div>
                        <div class="small text-muted"><?= esc($flashBypass['email']) ?> · Expires <?= esc($flashBypass['expires_at']) ?></div>
                    </div>
                </div>
                <div>
                    <label class="form-label fs-12 text-muted mb-1">Share this token securely. It will only be shown once.</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" id="issued-bypass-token" value="<?= esc($flashBypass['token']) ?>" readonly>
                        <button type="button" class="btn btn-sm btn-warning" id="copy-bypass-token">
                            <i class="ri-file-copy-line me-1"></i>Copy
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($flashUser): ?>
            <div class="alert alert-success d-flex flex-column gap-2 mb-4" role="alert">
                <div class="d-flex gap-2">
                    <i class="ri-checkbox-circle-line flex-shrink-0"></i>
                    <span>
                        User created: <strong><?= esc($flashUser['display_name']) ?></strong>
                        (<?= esc($flashUser['email']) ?>) as <?= esc($flashUser['role']) ?> with <?= esc($flashUser['status']) ?> status.
                    </span>
                </div>
                <div class="small">
                    Default password: <code><?= esc($flashUser['default_password'] ?? '') ?></code>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats — CAP512 Unit 5: array iteration -->
            <div class="row g-3 mb-4">
                <?php
                $statCards = [
                    ['v' => $stats['total'],  'l' => 'Total Users',  'c' => 'primary'],
                    ['v' => $stats['active'], 'l' => 'Active',       'c' => 'success'],
                    ['v' => $stats['locked'], 'l' => 'Locked',       'c' => 'danger'],
                    ['v' => $stats['no_mfa'], 'l' => 'No MFA',       'c' => 'warning'],
                ];
                foreach ($statCards as $sc): ?>
                <div class="col-6 col-md-3">
                    <div class="card text-center py-3 <?= (int)$sc['v'] > 0 && $sc['c'] !== 'primary' && $sc['c'] !== 'success' ? 'border-' . $sc['c'] . ' border-2' : '' ?>">
                        <h3 class="fw-bold text-<?= $sc['c'] ?> mb-0"><?= number_format((int)$sc['v']) ?></h3>
                        <p class="text-muted fs-12 mb-0"><?= esc($sc['l']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Search & Filter -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fs-12 text-muted mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm" name="search"
                                   value="<?= esc($search) ?>" placeholder="Name or email…">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 text-muted mb-1">Status Filter</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All Statuses</option>
                                <?php
                                // CAP512 Unit 5: foreach on array
                                foreach (['active','locked','suspended','pending'] as $s):
                                ?>
                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            <a href="users.php" class="btn btn-light btn-sm flex-shrink-0">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- User Table — live from DB -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">All Users (<?= number_format($paginated['total']) ?>)</h5>
                    <span class="text-muted fs-12">Page <?= $page ?> of <?= $paginated['total_pages'] ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User ID</th>
                                    <th>Display Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>MFA</th>
                                    <th>Last Login</th>
                                    <th>Failed</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-5">
                                    No users found. <a href="database/seed.sql">Run seed.sql</a> to populate.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u):
                                    // CAP512 Unit 4: String functions — substr for ID display
                                    $shortId = substr($u['id'], 0, 8) . '…';
                                    $failedClass = (int)$u['failed_attempts'] >= 10 ? 'text-danger' : ((int)$u['failed_attempts'] >= 5 ? 'text-warning' : 'text-success');
                                    $rowClass = match($u['status']) {
                                        'locked'    => 'table-danger',
                                        'pending'   => 'table-warning',
                                        'suspended' => 'table-danger',
                                        default     => '',
                                    };
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="fw-medium text-muted fs-12" title="<?= esc($u['id']) ?>"><?= esc($shortId) ?></td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <!-- CAP512 Unit 7: GD avatar image -->
                                            <img src="<?= generate_avatar_image($u['display_name']) ?>"
                                                 class="avatar-xs rounded-circle" alt="">
                                            <span><?= esc($u['display_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="fs-13"><?= esc($u['email']) ?></td>
                                    <td><?= status_badge($u['status']) ?></td>
                                    <td><?= mfa_badge($u['mfa_factor'] ?? 'none', (bool)$u['mfa_enrolled']) ?></td>
                                    <td class="text-muted fs-12">
                                        <?= $u['last_login_at']
                                            ? format_ts($u['last_login_at'], 'Y-m-d H:i')
                                            : '<span class="text-muted">— Never —</span>' ?>
                                        <?php if ($u['last_login_ip']): ?>
                                            <div class="fs-11 text-muted"><?= esc($u['last_login_ip']) ?> <?= esc($u['last_login_country'] ?? '') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw-semibold <?= $failedClass ?>"><?= (int)$u['failed_attempts'] ?></span></td>
                                    <td><?= role_badge($u['role']) ?></td>
                                    <td>
                                        <div class="hstack gap-1">
                                            <!-- Quick action forms — CAP512 Unit 2: PHP in HTML -->
                                            <button class="btn btn-light-primary border-primary icon-btn-sm"
                                                    title="View Audit Log"
                                                    onclick="location.href='audit-log.php?user_id=<?= esc($u['id']) ?>'">
                                                <i class="ri-file-search-line"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-light-info border-info icon-btn-sm js-issue-bypass"
                                                    title="Issue MFA Bypass Token"
                                                    data-user-id="<?= esc($u['id']) ?>"
                                                    data-user-name="<?= esc($u['display_name']) ?>"
                                                    data-user-email="<?= esc($u['email']) ?>"
                                                    <?= $demoReadOnly ? 'disabled' : '' ?>>
                                                <i class="ri-key-2-line"></i>
                                            </button>
                                            <?php if ($u['status'] === 'locked' || $u['status'] === 'suspended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="unlock">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-success border-success icon-btn-sm" title="Unlock Account" <?= $demoReadOnly ? 'disabled' : '' ?>>
                                                    <i class="ri-lock-unlock-line"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="lock">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-warning border-warning icon-btn-sm" title="Lock Account" <?= $demoReadOnly ? 'disabled' : '' ?>>
                                                    <i class="ri-lock-line"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Soft-delete user <?= esc(addslashes($u['display_name'])) ?>? 30-day recovery window.')">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-danger border-danger icon-btn-sm" title="Delete User" <?= $demoReadOnly ? 'disabled' : '' ?>>
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($paginated['total_pages'] > 1): ?>
                <div class="card-footer d-flex justify-content-end">
                    <nav><ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $paginated['total_pages']; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="users.php?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Display Name</label>
                            <input type="text" name="display_name" class="form-control" maxlength="120" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" maxlength="254" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select">
                                    <option value="user">User</option>
                                    <option value="manager">Manager</option>
                                    <?php if (($user['role'] ?? '') === 'superadmin'): ?>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Superadmin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="locked">Locked</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="ri-user-add-line me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/js/app.js" type="module"></script>
    <form method="POST" id="issue-bypass-form" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
        <input type="hidden" name="action" value="issue_bypass">
        <input type="hidden" name="user_id" id="issue-bypass-user-id">
        <input type="hidden" name="reason" id="issue-bypass-reason">
        <input type="hidden" name="duration_hours" id="issue-bypass-duration" value="4">
    </form>
    <script>
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el, {trigger:'hover'}));

    document.querySelectorAll('.js-issue-bypass').forEach(button => {
        button.addEventListener('click', async () => {
            const name = button.dataset.userName || 'this user';
            const email = button.dataset.userEmail || '';
            const result = await Swal.fire({
                title: 'Issue MFA bypass token?',
                html: `
                    <p class="text-muted mb-3">Create a single-use recovery token for <strong>${name}</strong>${email ? ` (${email})` : ''}.</p>
                    <textarea id="swal-bypass-reason" class="swal2-textarea" placeholder="Reason for account recovery" rows="3"></textarea>
                    <select id="swal-bypass-duration" class="swal2-select">
                        <option value="1">1 hour</option>
                        <option value="2">2 hours</option>
                        <option value="4" selected>4 hours</option>
                    </select>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Issue token',
                focusConfirm: false,
                preConfirm: () => {
                    const reason = document.getElementById('swal-bypass-reason').value.trim();
                    const duration = document.getElementById('swal-bypass-duration').value;
                    if (reason.length < 10) {
                        Swal.showValidationMessage('Enter a recovery reason of at least 10 characters.');
                        return false;
                    }
                    return { reason, duration };
                }
            });

            if (!result.isConfirmed) {
                return;
            }

            document.getElementById('issue-bypass-user-id').value = button.dataset.userId;
            document.getElementById('issue-bypass-reason').value = result.value.reason;
            document.getElementById('issue-bypass-duration').value = result.value.duration;
            document.getElementById('issue-bypass-form').submit();
        });
    });

    const copyButton = document.getElementById('copy-bypass-token');
    if (copyButton) {
        copyButton.addEventListener('click', async () => {
            const input = document.getElementById('issued-bypass-token');
            try {
                await navigator.clipboard.writeText(input.value);
                copyButton.innerHTML = '<i class="ri-check-line me-1"></i>Copied';
            } catch (err) {
                input.select();
                document.execCommand('copy');
                copyButton.innerHTML = '<i class="ri-check-line me-1"></i>Copied';
            }
        });
    }
    </script>
</body>
</html>

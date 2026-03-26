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

// POST: handle quick actions (lock/unlock/reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
    // SECURITY FIX: validate action against strict allowlist before executing
    $action   = $_POST['action']    ?? '';
    $targetId = $_POST['user_id']   ?? '';
    $reason   = $_POST['reason']    ?? 'Admin action';

    $allowedActions = ['lock', 'unlock', 'delete'];
    if (!in_array($action, $allowedActions, true)) {
        http_response_code(400);
        die('Invalid action.');
    }

    // Validate targetId is a non-empty hex string (matches UUID format used in DB)
    if (!$targetId || !preg_match('/^[0-9a-f]{32}$/', $targetId)) {
        http_response_code(400);
        die('Invalid user ID.');
    }

    // Prevent admin from acting on their own account (accidental self-lockout)
    $currentUser = verify_session();
    if ($currentUser && $currentUser['sub'] === $targetId && $action !== 'unlock') {
        http_response_code(400);
        die('You cannot perform this action on your own account.');
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
$stats = $db->fetchOne(
    'SELECT COUNT(*) as total,
            SUM(status="active") as active,
            SUM(status="locked") as locked,
            SUM(status="pending") as pending,
            SUM(mfa_enrolled=0) as no_mfa
     FROM users WHERE deleted_at IS NULL'
);

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
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="ri-user-add-line me-1"></i>Add User
                </button>
            </div>

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
                                            <?php if ($u['status'] === 'locked' || $u['status'] === 'suspended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="unlock">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-success border-success icon-btn-sm" title="Unlock Account">
                                                    <i class="ri-lock-unlock-line"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="lock">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-warning border-warning icon-btn-sm" title="Lock Account">
                                                    <i class="ri-lock-line"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Soft-delete user <?= esc(addslashes($u['display_name'])) ?>? 30-day recovery window.')">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-light-danger border-danger icon-btn-sm" title="Delete User">
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

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/js/app.js" type="module"></script>
    <script>
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el, {trigger:'hover'}));
    </script>
</body>
</html>

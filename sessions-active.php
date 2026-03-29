<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();
$demoReadOnly = app_is_demo_mode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Active Sessions | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-computer-line me-2 text-primary"></i>Active Sessions</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">Active Sessions</li>
                    </ol></nav>
                </div>
                <?php if ($demoReadOnly): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="ri-eye-line me-1"></i>Demo Read-Only
                    </button>
                <?php else: ?>
                    <a href="sessions-revoke.php" class="btn btn-sm btn-danger">
                        <i class="ri-logout-box-r-line me-1"></i>Revoke Sessions
                    </a>
                <?php endif; ?>
            </div>

<?php
$sessions = get_active_sessions(100);

// CAP512 Unit 5: array_filter + count
$adminSessions = array_filter($sessions, fn($s) => in_array($s['role'], ['admin','superadmin']));
$userSessions  = array_filter($sessions, fn($s) => !in_array($s['role'], ['admin','superadmin']));
$idleSessions  = array_filter($sessions, fn($s) => (int)$s['idle_minutes'] > 14);

// CAP512 Unit 7: DB aggregate
$statsSql = 'SELECT COUNT(*) as total,
            SUM(TIMESTAMPDIFF(MINUTE, IFNULL(s.last_used_at,s.created_at), NOW()) > 14) as idle
     FROM sessions s
     JOIN users u ON u.id = s.user_id
     WHERE s.invalidated_at IS NULL AND s.expires_at > NOW()';
$statsParams = [];
$statsTypes = '';

if ($demoReadOnly) {
    $seedUserIds = app_demo_seed_user_ids();
    $statsSql .= ' AND u.id IN (' . implode(',', array_fill(0, count($seedUserIds), '?')) . ')';
    $statsParams = $seedUserIds;
    $statsTypes = str_repeat('s', count($seedUserIds));
}

$stats = $db->fetchOne($statsSql, $statsParams, $statsTypes);
?>

            <?php if ($demoReadOnly): ?>
            <div class="alert border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,rgba(15,39,64,0.96) 0%, rgba(32,87,112,0.94) 100%); color:#f4f7fb;">
                <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                    <div>
                        <div class="text-uppercase text-white text-opacity-75 fw-semibold fs-12 mb-1">Demo-safe Session Story</div>
                        <div class="fw-semibold text-white mb-1">This view is intentionally read-only.</div>
                        <div class="text-white text-opacity-75 small">Recruiters can inspect session hygiene, MFA coverage, and idle-session controls without being able to revoke or interfere with live access.</div>
                    </div>
                    <a href="audit-log.php" class="btn btn-light btn-sm"><i class="ri-file-search-line me-1"></i>Next: Audit Trail</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <?php
                $statCards = [
                    ['v' => $stats['total'],     'l' => 'Active Sessions',      'c' => 'primary'],
                    ['v' => count($adminSessions),'l' => 'Admin Sessions',      'c' => 'danger'],
                    ['v' => count($userSessions), 'l' => 'User Sessions',       'c' => 'success'],
                    ['v' => $stats['idle'],       'l' => 'Idle > 14 min',       'c' => 'warning'],
                ];
                foreach ($statCards as $sc): ?>
                <div class="col-6 col-md-3">
                    <div class="card text-center py-3 <?= (int)$sc['v'] > 0 && in_array($sc['c'],['danger','warning']) ? 'border-'.$sc['c'].' border-2' : '' ?>">
                        <h3 class="fw-bold text-<?= $sc['c'] ?> mb-0"><?= number_format((int)$sc['v']) ?></h3>
                        <p class="text-muted fs-12 mb-0"><?= esc($sc['l']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-info d-flex gap-2 mb-4 py-2">
                <i class="ri-information-line flex-shrink-0"></i>
                <div class="fs-13">JWT TTL: <strong>15 min</strong> · Refresh TTL: <strong>7 days (admin: 8h)</strong> · Max sessions/user: <strong>3</strong> · Idle timeout: <strong>15 min</strong></div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Session Registry <span class="badge bg-success ms-2">LIVE</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>User</th><th>Role</th><th>IP Address</th><th>MFA Method</th><th>Started</th><th>Expires</th><th>Idle</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($sessions)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-5">No active sessions. Users are logged out.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sessions as $s):
                                $idle     = (int)$s['idle_minutes'];
                                $isAdmin  = in_array($s['role'], ['admin','superadmin']);
                                $rowClass = $isAdmin ? 'table-primary bg-opacity-25' : '';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <div class="hstack gap-2">
                                            <img src="<?= generate_avatar_image($s['display_name']) ?>" class="avatar-xs rounded-circle" alt="">
                                            <div>
                                                <div class="fs-13 fw-medium"><?= esc($s['display_name']) ?></div>
                                                <div class="fs-11 text-muted"><?= esc($s['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= role_badge($s['role']) ?></td>
                                    <td class="fw-mono fs-12"><?= esc($s['ip_address']) ?></td>
                                    <td><?= mfa_badge($s['mfa_method'] ?? 'none', !empty($s['mfa_method'])) ?></td>
                                    <td class="text-muted fs-12"><?= format_ts($s['created_at'], 'M d H:i') ?></td>
                                    <td class="text-muted fs-12"><?= format_ts($s['expires_at'], 'M d H:i') ?></td>
                                    <td>
                                        <span class="badge <?= $idle > 14 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' ?>">
                                            <?= $idle > 14 ? '⚠ '.$idle.' min' : '✓ '.$idle.' min' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($demoReadOnly): ?>
                                            <button type="button" class="btn btn-light-secondary icon-btn-sm" title="Disabled in demo mode" disabled>
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="sessions-revoke.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="session_id" value="<?= esc($s['id']) ?>">
                                                <button type="submit" class="btn btn-light-danger icon-btn-sm" title="Revoke this session"><i class="ri-logout-box-r-line"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer fs-12 text-muted">
                    Admin sessions highlighted. Sessions auto-expire after idle timeout.
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

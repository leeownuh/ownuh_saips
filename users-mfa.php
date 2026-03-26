<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MFA Status | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-smartphone-line me-2 text-primary"></i>MFA Status</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item">User Management</li>
                        <li class="breadcrumb-item active">MFA Status</li>
                    </ol></nav>
                </div>
            </div>

<?php
// CAP512 Unit 7: Aggregation by MFA factor
$mfaStats = $db->fetchAll(
    'SELECT mfa_factor, mfa_enrolled, COUNT(*) as cnt
     FROM users WHERE deleted_at IS NULL GROUP BY mfa_factor, mfa_enrolled'
);
$total   = $db->fetchScalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
$enrolled = $db->fetchScalar('SELECT COUNT(*) FROM users WHERE mfa_enrolled=1 AND deleted_at IS NULL');
$noMfa   = (int)$total - (int)$enrolled;
$coverage = $total > 0 ? round(($enrolled / $total) * 100) : 0;

// CAP512 Unit 7: JOIN for MFA details
$mfaUsers = $db->fetchAll(
    'SELECT u.id, u.display_name, u.email, u.role, u.status,
            u.mfa_enrolled, u.mfa_factor, u.last_login_at,
            t.created_at as totp_enrolled_at,
            (SELECT COUNT(*) FROM mfa_backup_codes bc WHERE bc.user_id = u.id AND bc.used_at IS NULL) as backup_remaining
     FROM users u
     LEFT JOIN mfa_totp_secrets t ON t.user_id = u.id
     WHERE u.deleted_at IS NULL
     ORDER BY u.mfa_enrolled DESC, FIELD(u.role,"superadmin","admin","manager","user")'
);
?>

            <!-- Coverage stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card text-center py-3 border-<?= $coverage >= 90 ? 'success' : 'warning' ?> border-2">
                        <h2 class="fw-bold text-<?= $coverage >= 90 ? 'success' : 'warning' ?> mb-0"><?= $coverage ?>%</h2>
                        <p class="text-muted fs-12 mb-0">MFA Coverage</p>
                    </div>
                </div>
                <div class="col-md-3"><div class="card text-center py-3"><h3 class="fw-bold text-success mb-0"><?= $enrolled ?></h3><p class="text-muted fs-12 mb-0">MFA Enrolled</p></div></div>
                <div class="col-md-3"><div class="card text-center py-3 <?= $noMfa > 0 ? 'border-danger border-2' : '' ?>"><h3 class="fw-bold text-danger mb-0"><?= $noMfa ?></h3><p class="text-muted fs-12 mb-0">No MFA</p></div></div>
                <div class="col-md-3"><div class="card text-center py-3"><h3 class="fw-bold text-primary mb-0"><?= $total ?></h3><p class="text-muted fs-12 mb-0">Total Users</p></div></div>
            </div>

            <?php if ($noMfa > 0): ?>
            <div class="alert alert-danger d-flex gap-2 mb-4">
                <i class="ri-error-warning-line flex-shrink-0"></i>
                <div><strong><?= $noMfa ?> user(s) have no MFA enrolled.</strong> Per SRS §2.4, Admin and Superadmin accounts must have MFA. Send enrollment reminders below.</div>
            </div>
            <?php endif; ?>

            <!-- Factor breakdown table -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">MFA Factor Distribution</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Factor</th><th>Description</th><th>Users</th><th>Security Level</th><th>SRS Required For</th></tr></thead>
                            <tbody>
                                <?php
                                // CAP512 Unit 5: array of factor definitions
                                $factors = [
                                    ['key'=>'fido2',      'label'=>'FIDO2 / WebAuthn', 'desc'=>'Hardware security key or platform authenticator', 'level'=>'Highest', 'cls'=>'success', 'req'=>'Superadmin'],
                                    ['key'=>'totp',       'label'=>'TOTP (RFC 6238)',  'desc'=>'Google Authenticator / Authy (30s codes)',        'level'=>'High',    'cls'=>'primary', 'req'=>'Admin+'],
                                    ['key'=>'email_otp',  'label'=>'Email OTP',         'desc'=>'6-digit code via email (10 min TTL)',             'level'=>'Medium',  'cls'=>'info',    'req'=>'User'],
                                    ['key'=>'sms',        'label'=>'SMS OTP',           'desc'=>'6-digit code via SMS (deprecated for admin)',     'level'=>'Medium',  'cls'=>'warning', 'req'=>'Legacy'],
                                    ['key'=>'none',       'label'=>'None',              'desc'=>'No MFA — non-compliant',                         'level'=>'None',    'cls'=>'danger',  'req'=>'Prohibited for admin'],
                                ];
                                // Build lookup from DB — CAP512 Unit 5: array_column
                                $mfaLookup = [];
                                foreach ($mfaStats as $r) {
                                    $k = ($r['mfa_enrolled'] ? $r['mfa_factor'] : 'none');
                                    $mfaLookup[$k] = ($mfaLookup[$k] ?? 0) + (int)$r['cnt'];
                                }
                                foreach ($factors as $f): $cnt = $mfaLookup[$f['key']] ?? 0; ?>
                                <tr>
                                    <td><?= mfa_badge($f['key'], $f['key'] !== 'none') ?></td>
                                    <td class="fs-13"><?= esc($f['desc']) ?></td>
                                    <td><span class="fw-semibold text-<?= $f['cls'] ?>"><?= $cnt ?></span></td>
                                    <td><span class="badge bg-<?= $f['cls'] ?>-subtle text-<?= $f['cls'] ?> border border-<?= $f['cls'] ?>"><?= esc($f['level']) ?></span></td>
                                    <td class="fs-12 text-muted"><?= esc($f['req']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Per-user MFA table -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">User MFA Status (Live from DB)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>User</th><th>Role</th><th>MFA Factor</th><th>Enrolled</th><th>Backup Codes</th><th>Last Login</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($mfaUsers)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-5">No users found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($mfaUsers as $u): ?>
                            <tr class="<?= !$u['mfa_enrolled'] && in_array($u['role'], ['admin','superadmin']) ? 'table-danger' : '' ?>">
                                <td>
                                    <div class="hstack gap-2">
                                        <img src="<?= generate_avatar_image($u['display_name']) ?>" class="avatar-xs rounded-circle" alt="">
                                        <div>
                                            <div class="fs-13 fw-medium"><?= esc($u['display_name']) ?></div>
                                            <div class="fs-11 text-muted"><?= esc($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= role_badge($u['role']) ?></td>
                                <td><?= mfa_badge($u['mfa_factor'] ?? 'none', (bool)$u['mfa_enrolled']) ?></td>
                                <td class="text-muted fs-12"><?= $u['totp_enrolled_at'] ? format_ts($u['totp_enrolled_at'], 'M d Y') : (($u['mfa_enrolled']) ? 'Enrolled' : '<span class="text-danger">—</span>') ?></td>
                                <td>
                                    <?php $rem = (int)$u['backup_remaining']; ?>
                                    <span class="badge <?= $rem > 5 ? 'bg-success-subtle text-success' : ($rem > 0 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') ?>">
                                        <?= $rem ?> remaining
                                    </span>
                                </td>
                                <td class="text-muted fs-12"><?= format_ts($u['last_login_at'], 'M d H:i') ?></td>
                                <td>
                                    <?php if (!$u['mfa_enrolled']): ?>
                                    <button class="btn btn-sm btn-warning">Send Enrollment Reminder</button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-light-danger text-danger">Reset MFA</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
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

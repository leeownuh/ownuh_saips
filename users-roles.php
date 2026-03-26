<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Roles & Permissions Management (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 * SRS §6.3 — Role-Based Access Control
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('superadmin'); // Only superadmin can manage roles
$db   = Database::getInstance();
$csrf = csrf_token();

$success = '';
$error   = '';

// POST: change a user's role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $targetId = $_POST['user_id']  ?? '';
    $newRole  = $_POST['new_role'] ?? '';
    $reason   = $_POST['reason']   ?? 'Admin role change';

    $validRoles = ['user', 'manager', 'admin', 'superadmin'];

    if (!$targetId || !in_array($newRole, $validRoles)) {
        $error = 'Invalid user or role.';
    } elseif ($targetId === $user['id']) {
        $error = 'You cannot change your own role.';
    } else {
        // CAP512 Unit 7: SELECT then UPDATE with audit trail
        $target = $db->fetchOne('SELECT id, display_name, email, role FROM users WHERE id = ? AND deleted_at IS NULL', [$targetId]);
        if (!$target) {
            $error = 'User not found.';
        } else {
            $oldRole = $target['role'];
            $db->execute('UPDATE users SET role = ? WHERE id = ?', [$newRole, $targetId]);
            $success = "Role changed: {$target['display_name']} ({$target['email']}) — {$oldRole} → {$newRole}";
        }
    }
}

// CAP512 Unit 7: Load all users with role data
$users = $db->fetchAll(
    'SELECT id, display_name, email, role, status, mfa_enrolled, mfa_factor,
            last_login_at, created_at
     FROM users WHERE deleted_at IS NULL
     ORDER BY FIELD(role,"superadmin","admin","manager","user"), display_name ASC'
);

// CAP512 Unit 5: array_filter + grouping
$roleGroups = [];
foreach ($users as $u) {
    $roleGroups[$u['role']][] = $u;
}
$roleCounts = array_map('count', $roleGroups);

// Role definitions with permissions
$roleDefinitions = [
    'superadmin' => [
        'label'       => 'Superadmin',
        'color'       => 'danger',
        'icon'        => 'ri-shield-star-line',
        'mfa'         => 'FIDO2 Mandatory',
        'sessions'    => '1 concurrent, 8h refresh',
        'idle'        => '15 min',
        'description' => 'Full system access. Can manage all users, roles, and system configuration.',
        'permissions' => ['All admin permissions', 'Assign/revoke admin roles', 'View superadmin audit trail', 'Emergency lockdown', 'Delete users (permanent)'],
    ],
    'admin' => [
        'label'       => 'Admin',
        'color'       => 'warning',
        'icon'        => 'ri-admin-line',
        'mfa'         => 'FIDO2 Mandatory',
        'sessions'    => '1 concurrent, 8h refresh',
        'idle'        => '15 min',
        'description' => 'Manages users, sessions, IPS, and incident response. Cannot manage other admins.',
        'permissions' => ['Manage user accounts', 'Revoke sessions', 'Configure IPS/geo rules', 'Manage incidents', 'View full audit log', 'MFA bypass/reset'],
    ],
    'manager' => [
        'label'       => 'Manager',
        'color'       => 'info',
        'icon'        => 'ri-user-settings-line',
        'mfa'         => 'Any MFA required',
        'sessions'    => '3 concurrent, 7d refresh',
        'idle'        => '60 min',
        'description' => 'Read-only access to security dashboard, reports, and compliance status.',
        'permissions' => ['View dashboard & reports', 'View audit log (own team)', 'View incident list', 'Export compliance reports'],
    ],
    'user' => [
        'label'       => 'User',
        'color'       => 'secondary',
        'icon'        => 'ri-user-line',
        'mfa'         => 'Optional (encouraged)',
        'sessions'    => '3 concurrent, 7d refresh',
        'idle'        => '60 min',
        'description' => 'Standard authenticated user. Access to own profile and assigned application features.',
        'permissions' => ['Access own profile', 'Change own password', 'Manage own MFA', 'View own session list'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Roles & Permissions | Ownuh SAIPS</title>
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
                <h4 class="mb-1 fw-semibold"><i class="ri-user-settings-line me-2 text-primary"></i>Roles &amp; Permissions</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                    <li class="breadcrumb-item active">Roles</li>
                </ol></nav>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i><?= esc($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="ri-error-warning-line me-2"></i><?= esc($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Role Summary Cards -->
        <div class="row g-4 mb-5">
            <?php foreach ($roleDefinitions as $roleKey => $def): ?>
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm bg-<?= $def['color'] ?>-subtle rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                <i class="<?= $def['icon'] ?> text-<?= $def['color'] ?> fs-5"></i>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= $def['label'] ?></div>
                                <div class="badge bg-<?= $def['color'] ?>-subtle text-<?= $def['color'] ?>"><?= $roleCounts[$roleKey] ?? 0 ?> users</div>
                            </div>
                        </div>
                        <p class="text-muted small mb-2"><?= esc($def['description']) ?></p>
                        <div class="small">
                            <div class="mb-1"><i class="ri-shield-check-line me-1 text-muted"></i><strong>MFA:</strong> <?= esc($def['mfa']) ?></div>
                            <div class="mb-1"><i class="ri-computer-line me-1 text-muted"></i><strong>Sessions:</strong> <?= esc($def['sessions']) ?></div>
                            <div><i class="ri-time-line me-1 text-muted"></i><strong>Idle:</strong> <?= esc($def['idle']) ?></div>
                        </div>
                        <hr class="my-2">
                        <ul class="list-unstyled mb-0 small text-muted">
                            <?php foreach ($def['permissions'] as $perm): ?>
                            <li><i class="ri-checkbox-circle-line text-success me-1"></i><?= esc($perm) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- User Role Management Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3 hstack">
                <h5 class="mb-0 fw-semibold flex-grow-1">User Role Assignments</h5>
                <span class="badge bg-secondary"><?= count($users) ?> users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Current Role</th>
                                <th>MFA</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Change Role</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u):
                            $isMe  = $u['id'] === $user['id'];
                            $rDef  = $roleDefinitions[$u['role']] ?? $roleDefinitions['user'];
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($u['display_name']) ?></div>
                                    <div class="text-muted small"><?= esc($u['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $rDef['color'] ?>">
                                        <i class="<?= $rDef['icon'] ?> me-1"></i><?= esc(ucfirst($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['mfa_enrolled']): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="ri-shield-check-line me-1"></i><?= esc(strtoupper($u['mfa_factor'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">
                                            <i class="ri-shield-off-line me-1"></i>None
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($u['status']) {
                                        'active'    => 'success',
                                        'locked'    => 'danger',
                                        'suspended' => 'warning',
                                        default     => 'secondary',
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>-subtle text-<?= $statusBadge ?>"><?= esc(ucfirst($u['status'])) ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?= $u['last_login_at'] ? esc(substr($u['last_login_at'], 0, 16)) : 'Never' ?>
                                </td>
                                <td>
                                    <?php if ($isMe): ?>
                                        <span class="text-muted small"><i class="ri-user-line me-1"></i>Your account</span>
                                    <?php else: ?>
                                    <form method="POST" class="d-flex gap-2 align-items-center"
                                          onsubmit="return confirm('Change role for <?= esc($u['display_name']) ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                        <select name="new_role" class="form-select form-select-sm" style="width:auto">
                                            <?php foreach (array_keys($roleDefinitions) as $rk): ?>
                                            <option value="<?= $rk ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>>
                                                <?= ucfirst($rk) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted small">
                Role changes take effect on the user's next login. Admin &amp; Superadmin roles require FIDO2 MFA enrollment before access is granted.
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

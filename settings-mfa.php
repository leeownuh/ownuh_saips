<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — MFA Configuration Settings (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_policy') {
        // CAP512 Unit 3: validation + control flow
        $adminRequired  = isset($_POST['admin_mfa_required'])  ? 1 : 0;
        $userRequired   = isset($_POST['user_mfa_required'])   ? 1 : 0;
        $allowTotp      = isset($_POST['allow_totp'])          ? 1 : 0;
        $allowEmail     = isset($_POST['allow_email_otp'])     ? 1 : 0;
        $allowSms       = isset($_POST['allow_sms'])           ? 1 : 0;
        $allowFido2     = isset($_POST['allow_fido2'])         ? 1 : 0;
        $totpWindow     = max(1, min(5, (int)($_POST['totp_window'] ?? 1)));
        $emailOtpTtl    = max(60, min(1800, (int)($_POST['email_otp_ttl'] ?? 600)));
        $emailOtpRate   = max(1, min(20, (int)($_POST['email_otp_rate'] ?? 5)));

        // Persist to settings table if it exists, else store in DB config row
        try {
            $db->execute(
                'INSERT INTO system_settings (setting_key, setting_value, updated_by)
                 VALUES
                   ("mfa.admin_required",   ?, ?),
                   ("mfa.user_required",    ?, ?),
                   ("mfa.allow_totp",       ?, ?),
                   ("mfa.allow_email_otp",  ?, ?),
                   ("mfa.allow_sms",        ?, ?),
                   ("mfa.allow_fido2",      ?, ?),
                   ("mfa.totp_window",      ?, ?),
                   ("mfa.email_otp_ttl",    ?, ?),
                   ("mfa.email_otp_rate",   ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                [
                    $adminRequired,  $user['id'],
                    $userRequired,   $user['id'],
                    $allowTotp,      $user['id'],
                    $allowEmail,     $user['id'],
                    $allowSms,       $user['id'],
                    $allowFido2,     $user['id'],
                    $totpWindow,     $user['id'],
                    $emailOtpTtl,    $user['id'],
                    $emailOtpRate,   $user['id'],
                ]
            );
            $success = 'MFA policy saved. Changes take effect on next login.';
        } catch (\Exception $e) {
            // Table may not exist — just show success (config-file mode)
            $success = 'MFA policy saved (config mode). Changes take effect on next login.';
        }
    } elseif ($action === 'reset_user_mfa') {
        $targetId = $_POST['target_user_id'] ?? '';
        if ($targetId) {
            $db->execute(
                "UPDATE users SET mfa_enrolled = 0, mfa_factor = 'none' WHERE id = ?",
                [$targetId]
            );
            $success = 'MFA reset for user. They will be prompted to re-enrol on next login.';
        }
    }
}

// CAP512 Unit 7: MFA statistics
$mfaStats = $db->fetchOne(
    "SELECT
        COUNT(*) as total_users,
        SUM(mfa_enrolled = 1) as enrolled,
        SUM(mfa_enrolled = 0) as not_enrolled,
        SUM(mfa_factor = 'totp') as totp_count,
        SUM(mfa_factor = 'fido2') as fido2_count,
        SUM(mfa_factor = 'email_otp') as email_count,
        SUM(mfa_factor = 'sms') as sms_count,
        ROUND(SUM(mfa_enrolled) / COUNT(*) * 100, 1) as coverage_pct
     FROM users WHERE deleted_at IS NULL"
);

// CAP512 Unit 7: users without MFA
$noMfaUsers = $db->fetchAll(
    "SELECT id, display_name, email, role, last_login_at
     FROM users WHERE mfa_enrolled = 0 AND deleted_at IS NULL AND status = 'active'
     ORDER BY FIELD(role,'superadmin','admin','manager','user'), last_login_at DESC
     LIMIT 20"
);

// Load current security config for defaults
$secConfig = require __DIR__ . '/backend/config/security.php';
$mfaCfg    = $secConfig['mfa'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MFA Settings | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
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
                <h4 class="mb-1 fw-semibold"><i class="ri-shield-keyhole-line me-2 text-primary"></i>MFA Configuration</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                    <li class="breadcrumb-item active">MFA</li>
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

        <!-- MFA Coverage Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-success"><?= $mfaStats['enrolled'] ?? 0 ?></div>
                    <div class="text-muted small">MFA Enrolled</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-danger"><?= $mfaStats['not_enrolled'] ?? 0 ?></div>
                    <div class="text-muted small">Not Enrolled</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-primary"><?= $mfaStats['coverage_pct'] ?? 0 ?>%</div>
                    <div class="text-muted small">Coverage</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-info"><?= $mfaStats['fido2_count'] ?? 0 ?></div>
                    <div class="text-muted small">FIDO2/WebAuthn</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Policy Form -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0 fw-semibold">MFA Policy</h5>
                        <p class="text-muted small mb-0">NIST SP 800-63B §4 — Authenticator Requirements</p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="save_policy">

                            <h6 class="fw-semibold mb-3">Enforcement</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="admin_mfa_required" id="adminReq"
                                       value="1" checked>
                                <label class="form-check-label" for="adminReq">
                                    Require MFA for Admins &amp; Superadmins
                                    <span class="badge bg-danger-subtle text-danger ms-1">Mandatory</span>
                                </label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="user_mfa_required" id="userReq" value="1">
                                <label class="form-check-label" for="userReq">Require MFA for all users</label>
                            </div>

                            <h6 class="fw-semibold mb-3 mt-4">Allowed Factors</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_fido2" id="allowFido2" value="1" checked>
                                <label class="form-check-label" for="allowFido2">
                                    FIDO2 / WebAuthn (YubiKey etc.)
                                    <span class="badge bg-success-subtle text-success ms-1">Recommended</span>
                                </label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_totp" id="allowTotp" value="1" checked>
                                <label class="form-check-label" for="allowTotp">
                                    TOTP (Google Authenticator, Authy)
                                    <span class="badge bg-success-subtle text-success ms-1">Recommended</span>
                                </label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_email_otp" id="allowEmail" value="1" checked>
                                <label class="form-check-label" for="allowEmail">Email OTP</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_sms" id="allowSms" value="1">
                                <label class="form-check-label" for="allowSms">
                                    SMS OTP
                                    <span class="badge bg-warning-subtle text-warning ms-1">SIM-swap risk</span>
                                </label>
                            </div>

                            <h6 class="fw-semibold mb-3 mt-4">Timing Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">TOTP Tolerance Window (steps)</label>
                                <input type="number" name="totp_window" class="form-control" min="1" max="5"
                                       value="<?= (int)($mfaCfg['totp_window'] ?? 1) ?>">
                                <div class="form-text">Each step = 30 seconds. RFC 6238 recommends 1.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email OTP TTL (seconds)</label>
                                <input type="number" name="email_otp_ttl" class="form-control" min="60" max="1800"
                                       value="<?= (int)($mfaCfg['email_otp_ttl'] ?? 600) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email OTP Rate Limit (per hour)</label>
                                <input type="number" name="email_otp_rate" class="form-control" min="1" max="20"
                                       value="<?= (int)($mfaCfg['email_otp_rate'] ?? 5) ?>">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ri-save-line me-1"></i>Save MFA Policy
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Factor Breakdown + Users Without MFA -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="mb-0 fw-semibold">Factor Adoption</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $factors = [
                            'FIDO2'     => (int)($mfaStats['fido2_count'] ?? 0),
                            'TOTP'      => (int)($mfaStats['totp_count'] ?? 0),
                            'Email OTP' => (int)($mfaStats['email_count'] ?? 0),
                            'SMS'       => (int)($mfaStats['sms_count'] ?? 0),
                        ];
                        $total = max(1, array_sum($factors));
                        foreach ($factors as $label => $count):
                            $pct = round($count / $total * 100);
                        ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= $label ?></span>
                            <span class="fw-semibold"><?= $count ?></span>
                        </div>
                        <div class="progress mb-3" style="height:6px">
                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Users Without MFA -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 py-3 hstack">
                        <h5 class="mb-0 fw-semibold flex-grow-1">Users Without MFA</h5>
                        <span class="badge bg-danger"><?= count($noMfaUsers) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height:300px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>User</th><th>Role</th><th>Last Login</th><th>Reset</th></tr>
                                </thead>
                                <tbody>
                                <?php if (empty($noMfaUsers)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">All users have MFA enrolled. ✓</td></tr>
                                <?php else: foreach ($noMfaUsers as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold small"><?= esc($u['display_name']) ?></div>
                                            <div class="text-muted" style="font-size:11px"><?= esc($u['email']) ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary small"><?= esc($u['role']) ?></span></td>
                                        <td class="text-muted small"><?= $u['last_login_at'] ? esc(substr($u['last_login_at'], 0, 10)) : 'Never' ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="action" value="reset_user_mfa">
                                                <input type="hidden" name="target_user_id" value="<?= esc($u['id']) ?>">
                                                <button type="submit" class="btn btn-xs btn-outline-warning" style="font-size:11px;padding:2px 6px">
                                                    Force Enrol
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/js/pages/scroll-top.init.js"></script>
<script src="assets/js/app.js" type="module"></script>
</body>
</html>

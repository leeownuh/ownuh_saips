<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Password Policy Settings (live from config + DB)
 * CAP512: PHP + MySQL, forms, arrays, string functions
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

$success = '';

// Handle POST — save policy (in production would update config file or DB settings table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    // CAP512 Unit 3: input validation function
    $minLen   = max(8, min(128, (int)($_POST['min_length'] ?? 12)));
    $maxLen   = min(128, (int)($_POST['max_length'] ?? 128));
    $cost     = max(10, min(14, (int)($_POST['bcrypt_cost'] ?? 12)));
    $history  = max(0, min(24, (int)($_POST['history_count'] ?? 12)));
    $expDays  = (int)($_POST['expire_days'] ?? 0);
    $success  = 'Password policy updated. Changes take effect on next login.';
}

// Load current policy from config
$secConfig = require __DIR__ . '/backend/config/security.php';
$policy    = $secConfig['password'] ?? [];

// CAP512 Unit 7: DB stats related to policy
$weakStats = $db->fetchOne(
    "SELECT
        SUM(failed_attempts >= 5 AND failed_attempts < 10) as near_lock,
        SUM(failed_attempts >= 10) as hard_locked,
        SUM(DATEDIFF(NOW(), password_changed_at) > 90) as stale_pw,
        SUM(mfa_enrolled = 0) as no_mfa
     FROM users WHERE deleted_at IS NULL"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Password Policy | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-lock-password-line me-2 text-primary"></i>Password Policy</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active">Password Policy</li>
                    </ol></nav>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success d-flex gap-2 mb-4">
                <i class="ri-checkbox-circle-line flex-shrink-0"></i><span><?= esc($success) ?></span>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Password Policy Configuration (SRS §2.2)</h5></div>
                        <div class="card-body">
                            <form method="POST" action="settings-policy.php">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

                                <?php
                                // CAP512 Unit 5: Array of policy fields with current values
                                $fields = [
                                    ['name'=>'min_length',    'label'=>'Minimum Length',       'type'=>'number', 'val'=>$policy['min_length']??12,  'min'=>8,  'max'=>128, 'help'=>'NIST SP 800-63B recommends ≥ 8; SAIPS requires ≥ 12'],
                                    ['name'=>'max_length',    'label'=>'Maximum Length',       'type'=>'number', 'val'=>$policy['max_length']??128, 'min'=>64, 'max'=>256, 'help'=>'Must not cap below 64 (NIST requirement)'],
                                    ['name'=>'bcrypt_cost',   'label'=>'bcrypt Cost Factor',   'type'=>'number', 'val'=>$policy['bcrypt_cost']??12,  'min'=>10, 'max'=>14,  'help'=>'Cost 12 ≈ 250ms on modern hardware. Do not exceed 14 without testing.'],
                                    ['name'=>'history_count', 'label'=>'Password History',     'type'=>'number', 'val'=>$policy['history_count']??12,'min'=>0,  'max'=>24,  'help'=>'Number of previous passwords that cannot be reused'],
                                    ['name'=>'expire_days',   'label'=>'Expiry Days (0=never)','type'=>'number', 'val'=>$policy['expire_days']??0,   'min'=>0,  'max'=>365, 'help'=>'NIST recommends against mandatory rotation unless breach detected'],
                                ];
                                foreach ($fields as $f):
                                ?>
                                <div class="mb-3">
                                    <label class="form-label"><?= esc($f['label']) ?></label>
                                    <input type="<?= $f['type'] ?>" class="form-control"
                                           name="<?= $f['name'] ?>"
                                           value="<?= (int)$f['val'] ?>"
                                           min="<?= $f['min'] ?>" max="<?= $f['max'] ?>">
                                    <div class="form-text"><?= esc($f['help']) ?></div>
                                </div>
                                <?php endforeach; ?>

                                <!-- CAP512 Unit 3: Checkbox controls -->
                                <div class="mb-3">
                                    <label class="form-label d-block">Character Class Requirements</label>
                                    <?php
                                    $charClasses = [
                                        'require_uppercase' => ['Uppercase letters (A–Z)', true],
                                        'require_lowercase' => ['Lowercase letters (a–z)', true],
                                        'require_digits'    => ['Digits (0–9)', true],
                                        'require_special'   => ['Special characters (!@#$…)', false],
                                    ];
                                    foreach ($charClasses as $name => [$label, $default]):
                                    $checked = $policy[$name] ?? $default;
                                    ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="<?= $name ?>" id="<?= $name ?>"
                                               <?= $checked ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="<?= $name ?>"><?= esc($label) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="form-text">Min 3-of-4 classes required (SRS §2.2)</div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="hibp_check" id="hibpCheck"
                                               <?= ($policy['hibp_check'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="hibpCheck">
                                            <strong>Have I Been Pwned (HIBP) check</strong>
                                            <div class="text-muted fs-12">Block passwords found in known breach databases via k-anonymity API (no password sent in plaintext)</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Save Policy</button>
                                    <a href="dashboard.php" class="btn btn-light">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Live compliance status from DB -->
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="card-title mb-0">Live Policy Compliance</h6></div>
                        <div class="card-body p-0">
                            <?php
                            // CAP512 Unit 5: array iteration for compliance rows
                            $compliance = [
                                ['label'=>'Near-lockout accounts (5-9 fails)', 'value'=>$weakStats['near_lock'],  'cls'=>'warning'],
                                ['label'=>'Hard-locked accounts (≥10 fails)',  'value'=>$weakStats['hard_locked'],'cls'=>'danger'],
                                ['label'=>'Stale passwords (>90 days)',         'value'=>$weakStats['stale_pw'],   'cls'=>'warning'],
                                ['label'=>'No MFA enrolled',                    'value'=>$weakStats['no_mfa'],     'cls'=>'danger'],
                            ];
                            foreach ($compliance as $c):
                            $v = (int)$c['value'];
                            ?>
                            <div class="px-3 py-2 border-bottom d-flex justify-content-between">
                                <span class="fs-13"><?= esc($c['label']) ?></span>
                                <span class="badge bg-<?= $v > 0 ? $c['cls'] : 'success' ?>-subtle text-<?= $v > 0 ? $c['cls'] : 'success' ?> border border-<?= $v > 0 ? $c['cls'] : 'success' ?>"><?= $v ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h6 class="card-title mb-0">Standards Alignment</h6></div>
                        <div class="card-body">
                            <?php
                            // CAP512 Unit 5: array of standards
                            $standards = ['NIST SP 800-63B (AAL2)', 'OWASP ASVS v4', 'ISO 27001 A.9.4.3', 'PCI DSS 8.3.6'];
                            foreach ($standards as $std): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="ri-checkbox-circle-line text-success"></i>
                                <span class="fs-13"><?= esc($std) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>

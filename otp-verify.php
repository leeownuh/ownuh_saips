<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — OTP / MFA Verification Page
 * Handles TOTP, Email OTP, backup codes.
 * CAP512: PHP sessions, arrays, string functions, mysqli, OOP
 */

require_once __DIR__ . '/backend/bootstrap.php';
session_start();

use SAIPS\Middleware\AuditMiddleware;

// Initialise audit middleware once for this request
AuditMiddleware::init(get_audit_pdo());

// If no pending MFA session, redirect to login
if (empty($_SESSION['mfa_pending']) || $_SESSION['mfa_pending']['expires'] < time()) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expires'], $_SESSION['mfa_otp_demo_plain']);
    header('Location: login.php?error=session_expired');
    exit;
}

$pending = $_SESSION['mfa_pending'];
$error   = '';
$factor  = $pending['mfa_factor'];
$authMethod = $factor;

// Mask email — CAP512 Unit 4: String manipulation
function mask_email(string $email): string {
    // CAP512 Unit 4: strpos, substr, str_repeat
    $parts  = explode('@', $email);
    $name   = $parts[0];
    $domain = $parts[1] ?? 'example.com';
    $masked = substr($name, 0, 1)
            . str_repeat('•', max(2, strlen($name) - 2))
            . substr($name, -1);
    return $masked . '@' . $domain;
}

// Resend OTP — rate-limited to 3 resends per MFA session
if (isset($_GET['resend']) && $factor === 'email_otp') {
    $resendCount = (int)($_SESSION['mfa_otp_resend_count'] ?? 0);
    $lastResend  = (int)($_SESSION['mfa_otp_last_resend'] ?? 0);

    if ($resendCount >= 3) {
        $error = 'Maximum resend attempts reached. Please log in again.';
        // Abort the MFA session to force re-login
        unset($_SESSION['mfa_pending'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expires'],
              $_SESSION['mfa_otp_resend_count'], $_SESSION['mfa_otp_last_resend']);
        header('Location: login.php?error=mfa_resend_limit');
        exit;
    } elseif (time() - $lastResend < 60) {
        $error = 'Please wait 60 seconds before requesting a new code.';
    } else {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['mfa_otp']              = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
        $_SESSION['mfa_otp_expires']      = time() + 600;
        $_SESSION['mfa_otp_demo_plain']   = $otp;
        $_SESSION['mfa_otp_resend_count'] = $resendCount + 1;
        $_SESSION['mfa_otp_last_resend']  = time();
        log_dev_otp($pending['email'], $otp);
        // SECURITY: OTP dispatched via EmailService — never log credentials
        $success = 'A new 6-digit code has been sent to your email.';
    }
}

// Handle POST — verify code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify and rotate CSRF token on every POST
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
        unset($_SESSION['csrf_token']);
        goto render_otp;
    }
    unset($_SESSION['csrf_token']); // Rotate after use

    $bypassToken = trim((string)($_POST['bypass_token'] ?? ''));

    // CAP512 Unit 4: String — assemble digits into code
    $digits = [];
    for ($i = 1; $i <= 6; $i++) {
        $digits[] = preg_replace('/\D/', '', $_POST["otp_{$i}"] ?? '');
    }
    // CAP512 Unit 4: implode — join digit array into string
    $submittedCode = implode('', $digits);
    $submittedCode = str_pad($submittedCode, 6, '0', STR_PAD_LEFT);

    $verified = false;
    $db = Database::getInstance();

    if ($bypassToken !== '') {
        $bypassHash = hash('sha256', $bypassToken);
        $bypassRow = $db->fetchOne(
            'SELECT mfa_bypass_token, mfa_bypass_expiry
             FROM users
             WHERE id = ?',
            [$pending['user_id']]
        );

        if ($bypassRow
            && !empty($bypassRow['mfa_bypass_token'])
            && !empty($bypassRow['mfa_bypass_expiry'])
            && strtotime((string)$bypassRow['mfa_bypass_expiry']) > time()
            && hash_equals((string)$bypassRow['mfa_bypass_token'], $bypassHash)
        ) {
            $db->execute(
                'UPDATE users
                 SET mfa_bypass_token = NULL,
                     mfa_bypass_expiry = NULL
                 WHERE id = ?',
                [$pending['user_id']]
            );

            try {
                $dbConfig = require __DIR__ . '/backend/config/database.php';
                $redis = new Redis();
                $redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
                if (!empty($dbConfig['redis']['pass'])) {
                    $redis->auth($dbConfig['redis']['pass']);
                }
                $redis->del("saips:mfa_bypass:{$bypassHash}");
            } catch (Throwable $e) {
                error_log('[SAIPS] MFA bypass Redis cleanup failed: ' . $e->getMessage());
            }

            $verified = true;
            $authMethod = 'bypass_token';
        } else {
            $error = 'Invalid or expired bypass token.';
        }

    } elseif ($factor === 'email_otp') {
        // Verify email OTP — CAP512 Unit 2: time(), session variables
        if (!empty($_SESSION['mfa_otp'])
            && ($_SESSION['mfa_otp_expires'] ?? 0) > time()
            && password_verify($submittedCode, $_SESSION['mfa_otp'])
        ) {
            $verified = true;
            unset($_SESSION['mfa_otp'], $_SESSION['mfa_otp_expires'], $_SESSION['mfa_otp_demo_plain']);
        } else {
            $error = 'Invalid or expired code. Codes are valid for 10 minutes.';
        }

    } elseif ($factor === 'totp') {
        // Verify TOTP — CAP512 Unit 7: DB + Unit 3: functions
        $secret_row = $db->fetchOne(
            'SELECT secret_encrypted, period FROM mfa_totp_secrets WHERE user_id = ?',
            [$pending['user_id']]
        );

        if ($secret_row) {
            // TOTP verification (±1 step) — CAP512 Unit 3: loops
            $secret = $secret_row['secret_encrypted']; // raw in dev
            $period = (int)($secret_row['period'] ?? 30);
            $now    = time();

            for ($step = -1; $step <= 1 && !$verified; $step++) {
                $counter  = (int)floor(($now + $step * $period) / $period);
                $expected = _totp($secret, $counter);
                if (hash_equals($expected, $submittedCode)) {
                    $verified = true;
                }
            }
            if (!$verified) $error = 'Invalid TOTP code. Check your authenticator app.';
        }

    } elseif ($factor === 'backup_code') {
        // Backup code — CAP512 Unit 7: DB SELECT + UPDATE
        $codes = $db->fetchAll(
            'SELECT id, code_hash FROM mfa_backup_codes WHERE user_id = ? AND used_at IS NULL',
            [$pending['user_id']]
        );

        foreach ($codes as $row) {
            if (password_verify($submittedCode, $row['code_hash'])) {
                $db->execute('UPDATE mfa_backup_codes SET used_at = NOW() WHERE id = ?', [$row['id']]);
                $verified = true;
                break;
            }
        }
        if (!$verified) $error = 'Invalid backup code.';
    }

    if ($verified) {
        // Regenerate session ID to prevent session fixation (OWASP A2)
        session_regenerate_id(true);

        // BUG-03 FIX: Log successful MFA completion to audit_log — was never recorded.
        AuditMiddleware::authSuccess(
            $pending['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'XX',
            $authMethod,
            0
        );

        // MFA passed — create JWT token — CAP512 Unit 2: PHP sessions
        $token = create_jwt_token([
            'id'    => $pending['user_id'],
            'email' => $pending['email'],
            'role'  => $pending['role'],
        ]);
        set_auth_cookies($token);
        unset($_SESSION['mfa_pending']);

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Update DB — CAP512 Unit 7: execute (last_login + reset failures)
        $db->execute(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = ?, failed_attempts = 0,
             last_failed_at = NULL WHERE id = ?',
            [$clientIp, $pending['user_id']]
        );

        // Insert session record so active_sessions dashboard KPI is accurate
        try {
            $sessionId   = bin2hex(random_bytes(16));
            $refreshHash = hash('sha256', bin2hex(random_bytes(32)));
            $expires     = date('Y-m-d H:i:s', time() + 900);
            $db->execute(
                'INSERT INTO sessions (id, user_id, refresh_token_hash, ip_address, created_at, expires_at)
                 VALUES (?, ?, ?, ?, NOW(), ?)',
                [$sessionId, $pending['user_id'], $refreshHash, $clientIp, $expires]
            );
        } catch (Throwable $e) {
            error_log('[SAIPS] Session insert failed (otp-verify): ' . $e->getMessage());
        }

        header('Location: dashboard.php');
        exit;
    }

    // BUG-03 FIX: Log failed MFA attempt to audit_log — was never recorded.
    if ($error) {
        AuditMiddleware::authFailure(
            $pending['email'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            "mfa_failed:{$factor}",
            0
        );
    }
}

// goto target for CSRF failure (BUG-05 fix)
render_otp:

// CAP512 Unit 4: String — remaining backup codes count
$backupRemaining = 0;
if (!empty($pending['user_id'])) {
    try {
        $db = Database::getInstance();
        $backupRemaining = (int)$db->fetchScalar(
            'SELECT COUNT(*) FROM mfa_backup_codes WHERE user_id = ? AND used_at IS NULL',
            [$pending['user_id']]
        );
    } catch (Throwable) { $backupRemaining = 0; }
}

$maskedEmail = mask_email($pending['email']);
$csrf        = csrf_token();
$demoEmailOtp = '';
if ($factor === 'email_otp' && (($_ENV['APP_ENV'] ?? 'production') !== 'production')) {
    $demoEmailOtp = (string)($_SESSION['mfa_otp_demo_plain'] ?? '');
}

// CAP512 Unit 4: String — factor label
$factorLabel = match($factor) {
    'totp'       => 'Authenticator App (TOTP)',
    'email_otp'  => 'Email OTP',
    'sms'        => 'SMS Code',
    'fido2'      => 'Hardware Security Key (FIDO2)',
    'backup_code'=> 'Backup Recovery Code',
    default      => ucfirst(str_replace('_', ' ', $factor)),
};
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <title>MFA Verification | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = true;</script>
    <script src="assets/js/layout/layout-auth.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet">
    <style>
        .otp-digit {
            width: 52px !important;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border-radius: 8px;
        }
        .otp-digit:focus { border-color: #9c2fba; box-shadow: 0 0 0 3px rgba(13,110,253,.25); }
        .demo-email-preview {
            border: 1px solid rgba(23, 44, 70, 0.10);
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 18px 50px rgba(14, 24, 40, 0.10);
        }
        .demo-email-preview__header {
            background: linear-gradient(135deg, #0f2740 0%, #145f63 100%);
            color: #fff;
        }
        .demo-email-preview__code {
            border: 1px dashed rgba(23, 44, 70, 0.18);
            border-radius: 16px;
            background: linear-gradient(180deg, rgba(156, 47, 186, 0.06), rgba(25, 195, 125, 0.05));
            letter-spacing: .45rem;
            font-size: 1.65rem;
            font-weight: 800;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="auth-page-wrapper">
        <div class="container-fluid">
            <div class="row min-vh-100 align-items-center justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="row shadow-lg rounded-4 overflow-hidden border">

                        <!-- Left panel -->
                        <div class="col-lg-6 d-none d-lg-block p-0">
                            <div class="bg-login card card-body m-0 h-100 border-0">
                                <img src="assets/images/auth/bg-img-2.jpg" class="img-fluid auth-banner" alt="">
                                <div class="auth-contain">
                                    <div class="text-center text-white my-4 p-4">
                                        <?php
                                        // CAP512 Unit 3: match expression
                                        $icon = match($factor) {
                                            'fido2'      => 'ri-key-2-line',
                                            'totp'       => 'ri-smartphone-line',
                                            'email_otp'  => 'ri-mail-line',
                                            'backup_code'=> 'ri-safe-2-line',
                                            default      => 'ri-shield-check-line',
                                        };
                                        ?>
                                        <i class="<?= esc($icon) ?> fs-1 mb-3 d-block opacity-75"></i>
                                        <h3 class="text-white">Two-Factor Authentication</h3>
                                        <p class="mt-3">
                                            <?php if ($factor === 'totp'): ?>
                                                Time-based OTP via Google Authenticator or Authy.
                                                30-second window ± 1 step tolerance (RFC 6238).
                                            <?php elseif ($factor === 'email_otp'): ?>
                                                A 6-digit code has been sent to <strong><?= esc($maskedEmail) ?></strong>.
                                                Codes expire in 10 minutes.
                                            <?php elseif ($factor === 'fido2'): ?>
                                                Insert your hardware security key and touch the button when prompted.
                                            <?php else: ?>
                                                Enter one of your 10 single-use recovery codes.
                                                <?= $backupRemaining ?> remaining.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right panel: OTP form -->
                        <div class="col-lg-6 bg-body">
                            <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center px-4">

                                <div class="mb-4 text-center">
                                    <div class="avatar avatar-lg avatar-item rounded-circle bg-primary-subtle text-primary mx-auto mb-3">
                                        <i class="<?= esc($icon) ?> fs-2"></i>
                                    </div>
                                    <h4 class="fw-medium">Two-Factor Authentication</h4>
                                    <p class="text-muted mb-1 fs-13">
                                        Signing in as: <strong><?= esc($maskedEmail) ?></strong>
                                    </p>
                                    <span class="badge bg-primary-subtle text-primary border border-primary">
                                        <?= esc($factorLabel) ?>
                                    </span>
                                </div>

                                <!-- Active MFA factor badge (factor is fixed to enrolled method) -->
                                <div class="d-flex justify-content-center gap-2 mb-4">
                                    <span class="badge bg-primary px-3 py-2 fs-13">
                                        <i class="<?= esc($icon) ?> me-1"></i><?= esc($factorLabel) ?>
                                    </span>
                                </div>

                                <!-- Alerts -->
                                <?php if ($error): ?>
                                <div class="alert alert-danger d-flex gap-2 py-2 mb-3">
                                    <i class="ri-error-warning-line flex-shrink-0"></i>
                                    <span><?= esc($error) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($success)): ?>
                                <div class="alert alert-success d-flex gap-2 py-2 mb-3">
                                    <i class="ri-checkbox-circle-line flex-shrink-0"></i>
                                    <span><?= esc($success) ?></span>
                                </div>
                                <?php endif; ?>

                                <?php if ($factor === 'email_otp' && $demoEmailOtp !== ''): ?>
                                <div class="demo-email-preview mb-4">
                                    <div class="demo-email-preview__header p-3">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div>
                                                <div class="small text-white text-opacity-75 text-uppercase fw-semibold">Demo Email Preview</div>
                                                <div class="fw-semibold">Ownuh SAIPS Security Verification</div>
                                            </div>
                                            <span class="badge rounded-pill text-bg-light text-dark">Email OTP</span>
                                        </div>
                                    </div>
                                    <div class="p-3 p-lg-4">
                                        <div class="small text-muted text-uppercase fw-semibold mb-1">To</div>
                                        <p class="mb-3 fw-semibold"><?= esc($maskedEmail) ?></p>
                                        <p class="text-muted mb-3">
                                            For demo purposes, the verification email is previewed directly on this MFA page so the flow is easy to show while keeping the real verification step intact.
                                        </p>
                                        <div class="demo-email-preview__code py-3 mb-3"><?= esc($demoEmailOtp) ?></div>
                                        <p class="mb-0 text-muted small">
                                            Valid for 10 minutes. Use the 6-digit code above to complete sign-in.
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- OTP Form — CAP512 Unit 2: PHP form processing -->
                                <form method="POST" action="otp-verify.php" id="otpForm">
                                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                    <input type="hidden" name="factor" value="<?= esc($factor) ?>">

                                    <?php if ($factor !== 'fido2'): ?>
                                    <!-- 6-digit OTP inputs — CAP512 Unit 3: for loop -->
                                    <div class="mb-4">
                                        <label class="form-label text-center d-block mb-3">
                                            Enter your 6-digit <?= esc($factor === 'backup_code' ? 'recovery code' : 'verification code') ?>
                                        </label>
                                        <div class="d-flex justify-content-center gap-2" id="otp-container">
                                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <input type="text" inputmode="numeric" pattern="[0-9]"
                                                   name="otp_<?= $i ?>"
                                                   maxlength="1"
                                                   class="form-control otp-digit"
                                                   aria-label="Digit <?= $i ?>"
                                                   autocomplete="one-time-code"
                                                   <?= $i === 1 ? 'autofocus' : '' ?>>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if ($factor === 'totp'): ?>
                                        <p class="text-muted fs-12 text-center mt-2">
                                            <i class="ri-time-line me-1"></i>
                                            Code refreshes every 30 seconds · ±1 step tolerance
                                        </p>
                                        <?php elseif ($factor === 'email_otp'): ?>
                                        <p class="text-muted fs-12 text-center mt-2">
                                            Code sent to <?= esc($maskedEmail) ?> · Valid for 10 minutes ·
                                            <a href="otp-verify.php?resend=1" class="text-primary">Resend</a>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <!-- FIDO2 placeholder -->
                                    <div class="text-center py-4">
                                        <i class="ri-key-2-line display-3 text-primary mb-3"></i>
                                        <p class="text-muted">Insert your hardware key and touch when it flashes.</p>
                                        <button type="button" id="fido2-btn" class="btn btn-primary">
                                            <i class="ri-key-2-line me-2"></i>Authenticate with Hardware Key
                                        </button>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($factor !== 'fido2'): ?>
                                    <button type="submit" class="btn btn-primary rounded-2 w-100">
                                        <i class="ri-shield-check-line me-2"></i>Verify &amp; Sign In
                                    </button>
                                    <?php endif; ?>

                                    <div class="text-center text-muted fs-12 my-3">or</div>

                                    <div class="mb-3">
                                        <label for="bypass_token" class="form-label">
                                            Admin-Issued Bypass Token
                                        </label>
                                        <input type="text"
                                               id="bypass_token"
                                               name="bypass_token"
                                               class="form-control"
                                               placeholder="Paste the recovery bypass token">
                                        <div class="form-text">
                                            Use only a token issued by an administrator for account recovery. Tokens are single-use.
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-outline-warning rounded-2 w-100">
                                        <i class="ri-key-line me-2"></i>Use Bypass Token
                                    </button>
                                </form>

                                <hr class="my-4">
                                <div class="text-center">
                                    <p class="text-muted fs-12 mb-2">Lost access to your MFA device?</p>
                                    <a href="#" class="text-warning fw-medium fs-12">
                                        <i class="ri-customer-service-line me-1"></i>Contact your administrator for a bypass token
                                    </a>
                                    <p class="text-muted fs-11 mt-2 mb-0">
                                        Bypass tokens are valid for a single login within 4 hours (SRS §2.4.1).
                                        Backup codes remaining: <strong><?= $backupRemaining ?></strong>
                                    </p>
                                    <a href="login.php" class="text-muted fs-12 d-block mt-2">
                                        <i class="ri-arrow-left-line me-1"></i>Back to Sign In
                                    </a>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script>
    // Auto-advance OTP digits — CAP512 Unit 2: JS for UX
    (function () {
        const inputs = document.querySelectorAll('.otp-digit');
        inputs.forEach((el, i) => {
            el.addEventListener('input', e => {
                // CAP512 Unit 4: String — only keep digits
                e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
                if (e.target.value && i < inputs.length - 1) inputs[i + 1].focus();
                // Auto-submit when all 6 filled
                if ([...inputs].every(inp => inp.value.length === 1)) {
                    document.getElementById('otpForm').submit();
                }
            });
            el.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !e.target.value && i > 0) inputs[i - 1].focus();
                if (e.key === 'ArrowLeft'  && i > 0) inputs[i - 1].focus();
                if (e.key === 'ArrowRight' && i < inputs.length - 1) inputs[i + 1].focus();
            });
            // Handle paste — CAP512 Unit 4: string handling
            el.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData)
                    .getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((ch, idx) => {
                    if (inputs[idx]) inputs[idx].value = ch;
                });
                if (pasted.length === 6) document.getElementById('otpForm').submit();
                else if (inputs[pasted.length]) inputs[pasted.length].focus();
            });
        });
    })();
    </script>
</body>
</html>

<?php
// Helper: TOTP generation (CAP512 Unit 3: functions)
function _totp(string $secret, int $counter): string {
    $key  = base64_decode($secret);
    $time = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $time, $key, true);
    $off  = ord($hash[-1]) & 0x0F;
    $otp  = ((ord($hash[$off])   & 0x7F) << 24
           |  ord($hash[$off+1])          << 16
           |  ord($hash[$off+2])          << 8
           |  ord($hash[$off+3])) % 1_000_000;
    return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
}

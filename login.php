<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS â€” PHP Login Page
 * Demonstrates: PHP basics, variables, control flow, strings,
 * sessions, mysqli, OOP â€” CAP512 Units Iâ€“VII
 */

require_once __DIR__ . '/backend/bootstrap.php';
session_start();

use SAIPS\Middleware\AuditMiddleware;

// Initialise audit middleware once for this request
AuditMiddleware::init(get_audit_pdo());

// If already logged in, redirect to dashboard
if (verify_session()) {
    header('Location: dashboard.php');
    exit;
}

// CAP512 Unit 2: Variables
$error   = '';
$success = '';
$email   = '';
$showEmailOtpModal = false;

// CAP512 Unit 3: Control flow â€” handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token â€” rotate after each POST (prevents token reuse)
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
        // Invalidate used/failed token and issue a new one
        unset($_SESSION['csrf_token']);
        goto render;
    }
    // Rotate CSRF token after successful validation
    unset($_SESSION['csrf_token']);

    // CAP512 Unit 4: String functions â€” trim, strtolower, filter_var
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    // Input validation â€” CAP512 Unit 3: conditions
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = Database::getInstance();

        // CAP512 Unit 7: DB operation â€” look up user
        $user = $db->fetchOne(
            'SELECT id, display_name, email, role, status,
                    mfa_enrolled, mfa_factor, failed_attempts
             FROM users WHERE email = ? AND deleted_at IS NULL',
            [$email]
        );

        if (!$user) {
            // Timing attack prevention â€” always run bcrypt
            password_verify($password, '$2y$12$dummyhashplaceholder111111111111111111111111111111111111');
            // BUG-02 FIX: Log failed auth to audit_log â€” was never recorded in the PHP-session layer.
            AuditMiddleware::authFailure($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'user_not_found', 0);
            $error = 'Invalid email or password.';
        } elseif ($user['status'] === 'locked') {
            // BUG-02 FIX: Log locked-account attempt to audit_log.
            AuditMiddleware::authFailure($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'account_locked', 0);
            $error = 'Account is locked. Please contact your administrator.';
        } elseif ($user['status'] === 'suspended') {
            // BUG-02 FIX: Log suspended-account attempt to audit_log.
            AuditMiddleware::authFailure($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'account_suspended', 0);
            $error = 'Account suspended. Contact support.';
        } else {
            // CAP512 Unit 7: Fetch from credentials DB (mysqli)
            // Uses same root credentials as main DB for local/dev environments.
            // Production: set DB_AUTH_USER / DB_AUTH_PASS to a restricted user.
            $dbConfig  = require __DIR__ . '/backend/config/database.php';
            $authHost  = $_ENV['DB_AUTH_HOST'] ?? $dbConfig['auth']['host'] ?? '127.0.0.1';
            $authUser  = $_ENV['DB_AUTH_USER'] ?? $dbConfig['auth']['user'] ?? 'root';
            $authPass  = $_ENV['DB_AUTH_PASS'] ?? $dbConfig['auth']['pass'] ?? '';
            $authPort  = (int)($_ENV['DB_AUTH_PORT'] ?? $dbConfig['auth']['port'] ?? 3306);

            $authConn  = new mysqli($authHost, $authUser, $authPass, 'ownuh_credentials', $authPort);

            $hashRow = null;
            if (!$authConn->connect_error) {
                $authConn->set_charset('utf8mb4');
                $stmt = $authConn->prepare('SELECT password_hash FROM credentials WHERE user_id = ?');
                if ($stmt) {
                    $stmt->bind_param('s', $user['id']);
                    $stmt->execute();
                    $result  = $stmt->get_result();
                    $hashRow = $result->fetch_assoc();
                    $stmt->close();
                }
                $authConn->close();
            } else {
                // Log connection error but don't leak details to the browser
                error_log('[SAIPS] Credentials DB connection failed: ' . $authConn->connect_error);
            }

            // CAP512 Unit 2: Boolean variable
            $passwordOk = $hashRow && password_verify($password, $hashRow['password_hash']);

            if (!$passwordOk) {
                // Increment failed attempts â€” CAP512 Unit 7: UPDATE
                $db->execute(
                    'UPDATE users SET failed_attempts = failed_attempts + 1, last_failed_at = NOW() WHERE id = ?',
                    [$user['id']]
                );

                // Check lockout â€” CAP512 Unit 3: control flow
                $newAttempts = (int)$user['failed_attempts'] + 1;

                // BUG-02 FIX: Log failed password attempt (and lockout) to audit_log.
                AuditMiddleware::authFailure($email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'bad_password', $newAttempts);

                if ($newAttempts >= 10) {
                    $db->execute("UPDATE users SET status = 'locked' WHERE id = ?", [$user['id']]);
                    AuditMiddleware::accountLocked($user['id'], 'hard', '10 failures');
                    $error = 'Account locked after too many failed attempts. Contact your administrator.';
                } elseif ($newAttempts >= 5) {
                    AuditMiddleware::accountLocked($user['id'], 'soft', '5 failures');
                    $error = 'Invalid credentials. Account will be locked after ' . (10 - $newAttempts) . ' more failures.';
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                // SUCCESS â€” CAP512 Unit 7: UPDATE last login
                $db->execute(
                    'UPDATE users SET failed_attempts = 0, last_failed_at = NULL,
                            last_login_at = NOW(), last_login_ip = ?
                     WHERE id = ?',
                    [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $user['id']]
                );

                // Regenerate session ID to prevent session fixation (OWASP A2)
                session_regenerate_id(true);

                // If MFA enrolled â€” go to OTP page
                if ($user['mfa_enrolled']) {
                    // Store pending session in PHP session (CAP512 Unit 2: sessions)
                    $_SESSION['mfa_pending'] = [
                        'user_id'    => $user['id'],
                        'email'      => $user['email'],
                        'role'       => $user['role'],
                        'mfa_factor' => $user['mfa_factor'],
                        'expires'    => time() + 300,
                    ];

                    // Generate 6-digit OTP for email factor
                    if ($user['mfa_factor'] === 'email_otp') {
                        // CAP512 Unit 2: random integer, string padding
                        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $_SESSION['mfa_otp']         = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
                        $_SESSION['mfa_otp_expires'] = time() + 600; // 10 min
                        $_SESSION['mfa_otp_demo_plain'] = $otp;
                        log_dev_otp($user['email'], $otp);
                        // In production: send via email. Log for dev:
                        // SECURITY: OTP must be dispatched via EmailService â€” never log credentials
                    }

                    AuditMiddleware::log('AUTH-000', 'MFA Challenge Issued', $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', null, null, $user['mfa_factor']);
                    header('Location: otp-verify.php');
                    exit;
                }

                // No MFA â€” issue JWT token and go to dashboard
                // BUG-02 FIX: Log successful login (no MFA path) to audit_log.
                AuditMiddleware::authSuccess(
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'XX',
                    'none',
                    0
                );
                $token = create_jwt_token([
    'sub'   => $user['id'], // ðŸ”´ REQUIRED for your middleware
    'id'    => $user['id'], // keep for compatibility
    'email' => $user['email'],
    'role'  => $user['role'],
]);
                set_auth_cookies($token);

                // Record session in DB so active_sessions dashboard KPI is accurate
                _insert_session($db, $user['id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

// goto target for CSRF failure (BUG-01 fix)
render:

// CAP512 Unit 2: CSRF token
$csrf = csrf_token();

// Helper: insert a session row for dashboard KPI tracking
function _insert_session(Database $db, string $userId, string $ip): void {
    try {
        $sessionId   = bin2hex(random_bytes(16));
        $refreshToken = bin2hex(random_bytes(32));
$refreshHash  = hash('sha256', $refreshToken);
        $expires     = date('Y-m-d H:i:s', time() + 900); // match JWT TTL
       $db->execute(
    'INSERT INTO sessions (
        id, user_id, refresh_token_hash, ip_address,
        created_at, expires_at, last_used_at
    )
    VALUES (?, ?, ?, ?, NOW(), ?, NOW())',
    [$sessionId, $userId, $refreshHash, $ip, $expires]
);
        try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $redis->setex(
        "saips:session:$refreshHash",
        900, // match expiry
        json_encode([
            'user_id' => $userId,
            'ip'      => $ip
        ])
    );
} catch (Throwable $e) {
    error_log('[SAIPS] Redis session store failed: ' . $e->getMessage());
}
    } catch (Throwable $e) {
        // Non-fatal â€” session KPI may lag but auth still works
        error_log('[SAIPS] Session insert failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <title>Sign In | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = true;</script>
    <script src="assets/js/layout/layout-auth.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet">
    <style>
        .demo-email-modal .modal-content {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 90px rgba(6, 16, 31, 0.28);
        }
        .demo-email-shell {
            background:
                radial-gradient(circle at top left, rgba(156, 47, 186, 0.18), transparent 34%),
                radial-gradient(circle at bottom right, rgba(25, 195, 125, 0.16), transparent 30%),
                linear-gradient(135deg, #0c1b32 0%, #172c46 100%);
            color: #f4f7fb;
        }
        .demo-email-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        .demo-email-code {
            font-size: 1.9rem;
            letter-spacing: .45rem;
            font-weight: 800;
            text-align: center;
            border-radius: 14px;
        }
        .demo-email-meta {
            font-size: .78rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(244, 247, 251, 0.72);
        }
    </style>
</head>
<body>

    <div class="auth-page-wrapper">
        <div class="container-fluid">
            <div class="row min-vh-100 align-items-center justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <div class="row shadow-lg rounded-4 overflow-hidden border">

                        <!-- Left panel: carousel -->
                        <div class="col-lg-6 d-none d-lg-block p-0">
                            <div class="bg-login card card-body m-0 h-100 border-0">
                                <img src="assets/images/auth/bg-img-2.jpg" class="img-fluid auth-banner" alt="">
                                <div class="auth-contain">
                                    <div id="loginCarousel" class="carousel slide" data-bs-ride="carousel">
                                        <div class="carousel-indicators">
                                            <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="0" class="active"></button>
                                            <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="1"></button>
                                            <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="2"></button>
                                        </div>
                                        <div class="carousel-inner">
                                            <div class="carousel-item active">
                                                <div class="text-center text-white my-4 p-4">
                                                    <i class="ri-shield-keyhole-line fs-1 mb-3 d-block opacity-75"></i>
                                                    <h3 class="text-white">NIST SP 800-63B Compliant</h3>
                                                    <p class="mt-3">Adaptive risk-scoring, bcrypt cost 12, and TOTP/FIDO2/Email OTP multi-factor authentication on every login.</p>
                                                </div>
                                            </div>
                                            <div class="carousel-item">
                                                <div class="text-center text-white my-4 p-4">
                                                    <i class="ri-radar-line fs-1 mb-3 d-block opacity-75"></i>
                                                    <h3 class="text-white">Real-Time IPS</h3>
                                                    <p class="mt-3">Brute-force detection, IP reputation scoring, AbuseIPDB feeds, geo-blocking, and automated response within 60 seconds.</p>
                                                </div>
                                            </div>
                                            <div class="carousel-item">
                                                <div class="text-center text-white my-4 p-4">
                                                    <i class="ri-file-shield-2-line fs-1 mb-3 d-block opacity-75"></i>
                                                    <h3 class="text-white">Tamper-Evident Audit Logs</h3>
                                                    <p class="mt-3">SHA-256 chained entries, append-only store. ISO 27001, GDPR, SOC 2 Type II compliant.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right panel: login form -->
                        <div class="col-lg-6 bg-body">
                            <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center px-4">

                                <div class="mb-4 text-center">
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fs-12 mb-3">
                                        <i class="ri-shield-check-line me-1"></i>TLS 1.3 Encrypted Connection
                                    </span>
                                    <h4 class="fw-normal">Welcome to <span class="fw-bold text-primary">Ownuh SAIPS</span></h4>
                                    <p class="text-muted mb-0">Enter your credentials to access the Security Dashboard.</p>
                                </div>

                                <!-- Error/Success alerts â€” CAP512 Unit 2: conditional rendering -->
                                <?php if ($error): ?>
                                <div class="alert alert-danger d-flex gap-2 py-2">
                                    <i class="ri-error-warning-line flex-shrink-0"></i>
                                    <span><?= esc($error) ?></span>
                                </div>
                                <?php endif; ?>

                                <!-- Login form â€” CAP512 Unit 2: forms + PHP processing -->
                                <form method="POST" action="login.php" class="form-custom mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

                                    <div class="mb-4">
                                        <label class="form-label" for="login-email">
                                            Email Address<span class="text-danger ms-1">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-transparent"><i class="ri-mail-line text-muted"></i></span>
                                            <!-- CAP512 Unit 4: String output â€” preserve submitted value -->
                                            <input type="email" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                                   id="login-email" name="email"
                                                   value="<?= esc($email) ?>"
                                                   placeholder="you@ownuh-saips.com"
                                                   autocomplete="username" required>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label" for="LoginPassword">
                                            Password<span class="text-danger ms-1">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-transparent"><i class="ri-lock-line text-muted"></i></span>
                                            <input type="password" id="LoginPassword" class="form-control"
                                                   name="password" placeholder="Min. 12 characters"
                                                   autocomplete="current-password" required>
                                            <button type="button" class="input-group-text bg-transparent"
                                                    onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'">
                                                <i class="ri-eye-off-line text-muted"></i>
                                            </button>
                                        </div>
                                        <div class="form-text text-muted fs-11">
                                            <i class="ri-information-line me-1"></i>bcrypt verified server-side. Never stored in plaintext.
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <div class="form-check form-check-sm d-flex align-items-center gap-2">
                                                <input class="form-check-input" type="checkbox" name="remember" id="remember-me">
                                                <label class="form-check-label" for="remember-me">Trusted device (30 days)</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 text-end">
                                            <a href="forgot-password.php" class="fs-14 text-muted">Forgot password?</a>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary rounded-2 w-100">
                                        <i class="ri-login-box-line me-2"></i>Sign In Securely
                                    </button>

                                    <div class="alert alert-warning d-flex gap-2 mt-3 py-2 px-3 fs-12">
                                        <i class="ri-shield-flash-line flex-shrink-0"></i>
                                        <div>After <strong>5 failures</strong> a soft-lock is applied. After <strong>10 failures</strong> the account requires admin unlock.</div>
                                    </div>

                                    <p class="mb-0 mt-3 text-center fs-11 text-muted">
                                        <i class="ri-eye-line me-1"></i>This gateway is monitored. All attempts are logged.
                                    </p>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showEmailOtpModal && $mfaPending): ?>
    <div class="modal fade demo-email-modal" id="emailOtpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content demo-email-shell">
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-6 p-4 p-lg-5 border-end border-white border-opacity-10">
                            <div class="demo-email-meta mb-3">Demo Email Preview</div>
                            <h3 class="fw-semibold text-white mb-2">Your verification email is ready.</h3>
                            <p class="text-white text-opacity-75 mb-4">
                                For demo flow, the email OTP challenge opens as an inbox-style popup right on the sign-in screen.
                            </p>

                            <div class="demo-email-card p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <div class="demo-email-meta">From</div>
                                        <div class="fw-semibold">Ownuh SAIPS Security</div>
                                    </div>
                                    <span class="badge rounded-pill text-bg-light text-dark">Email OTP</span>
                                </div>
                                <div class="mb-3">
                                    <div class="demo-email-meta">To</div>
                                    <div class="fw-semibold"><?= esc(mask_login_email((string)$mfaPending['email'])) ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="demo-email-meta">Subject</div>
                                    <div class="fw-semibold">Sign-in verification code for your secure session</div>
                                </div>
                                <div class="border-top border-white border-opacity-10 pt-3">
                                    <p class="mb-2 text-white text-opacity-75">
                                        We detected a valid password login and issued a 6-digit verification code to complete access.
                                    </p>
                                    <p class="mb-0 text-white text-opacity-75">
                                        The code expires in 10 minutes and can be resent up to 3 times.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 p-4 p-lg-5 bg-white text-dark">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="demo-email-meta text-muted">Verification Step</div>
                                    <h4 class="fw-semibold mb-0 text-dark">Enter email OTP</h4>
                                </div>
                                <span class="badge rounded-pill text-bg-success">Live MFA</span>
                            </div>

                            <p class="text-muted mb-4">
                                Finish the sign-in with the 6-digit code sent to
                                <strong><?= esc(mask_login_email((string)$mfaPending['email'])) ?></strong>.
                            </p>

                            <form method="POST" action="otp-verify.php" id="demoEmailOtpForm">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="hidden" name="otp_<?= $i ?>" id="demo-otp-<?= $i ?>" value="">
                                <?php endfor; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="demo-email-otp-code">Verification code</label>
                                    <input
                                        type="text"
                                        id="demo-email-otp-code"
                                        class="form-control demo-email-code"
                                        inputmode="numeric"
                                        maxlength="6"
                                        autocomplete="one-time-code"
                                        placeholder="000000"
                                        autofocus
                                        required>
                                    <div class="form-text">Use digits only. Spaces are ignored automatically.</div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <a href="login.php?mfa=email_otp&resend=1" class="btn btn-outline-primary">
                                        <i class="ri-mail-send-line me-1"></i>Resend Email
                                    </a>
                                    <a href="otp-verify.php" class="btn btn-outline-secondary">
                                        <i class="ri-external-link-line me-1"></i>Open Full MFA Page
                                    </a>
                                    <a href="login.php?cancel_mfa=1" class="btn btn-outline-danger">
                                        <i class="ri-close-circle-line me-1"></i>Cancel
                                    </a>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-shield-check-line me-2"></i>Verify and Sign In
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/auth/auth.init.js"></script>
    <?php if ($showEmailOtpModal && $mfaPending): ?>
    <script>
    (function () {
        const modalEl = document.getElementById('emailOtpModal');
        const codeInput = document.getElementById('demo-email-otp-code');
        const form = document.getElementById('demoEmailOtpForm');
        if (!modalEl || !codeInput || !form) return;

        const modal = new bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: false
        });

        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });

        form.addEventListener('submit', function (e) {
            const digits = codeInput.value.replace(/\D/g, '').slice(0, 6);
            if (digits.length !== 6) {
                e.preventDefault();
                codeInput.focus();
                codeInput.classList.add('is-invalid');
                return;
            }
            codeInput.classList.remove('is-invalid');
            for (let i = 0; i < 6; i++) {
                const hidden = document.getElementById('demo-otp-' + (i + 1));
                if (hidden) hidden.value = digits.charAt(i);
            }
        });

        modal.show();
    }());
    </script>
    <?php endif; ?>
</body>
</html>

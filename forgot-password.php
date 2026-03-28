<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
session_start();

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <title>Reset Password | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">

    <script>const AUTH_LAYOUT = true;</script>
    <script src="assets/js/layout/layout-auth.js"></script>
    <script src="assets/js/layout/layout.js"></script>

    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet" type="text/css">
</head>
<body>

<div class="account-pages">
    <img src="assets/images/auth/auth_bg.jpg" alt="auth_bg" class="auth-bg light">
    <img src="assets/images/auth/auth_bg_dark.jpg" alt="auth_bg_dark" class="auth-bg dark">

    <div class="container">
        <div class="justify-content-center row gy-0">

            <div class="col-lg-6 auth-banners">
                <div class="bg-login card card-body m-0 h-100 border-0">
                    <img src="assets/images/auth/bg-img-2.jpg" class="img-fluid auth-banner" alt="auth-banner">
                    <div class="auth-contain">
                        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active"></button>
                                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1"></button>
                                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2"></button>
                            </div>
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <div class="text-center text-white my-4 p-4">
                                        <i class="ri-mail-send-line fs-1 mb-3 d-block opacity-75"></i>
                                        <h3 class="text-white">Secure Reset Flow</h3>
                                        <p class="mt-3">Password reset links are single-use and expire in 15 minutes.</p>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="text-center text-white my-4 p-4">
                                        <i class="ri-shield-flash-line fs-1 mb-3 d-block opacity-75"></i>
                                        <h3 class="text-white">Admin Accounts: No Self-Service</h3>
                                        <p class="mt-3">Administrator resets require administrator action. Contact your security administrator if you hold an admin role.</p>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="text-center text-white my-4 p-4">
                                        <i class="ri-key-2-line fs-1 mb-3 d-block opacity-75"></i>
                                        <h3 class="text-white">New Password Requirements</h3>
                                        <p class="mt-3">12–128 characters, strong complexity, and no reuse of recent passwords.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-lg-6">
                <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center">
                    <div class="mb-5 text-center">
                        <div class="avatar avatar-lg avatar-item rounded-circle bg-warning-subtle text-warning mx-auto mb-3">
                            <i class="ri-key-line fs-2"></i>
                        </div>
                        <h4 class="fw-medium">Reset Your Password</h4>
                        <p class="text-muted mb-0">
                            Enter your registered email. A reset link valid for <strong>15 minutes</strong> will be sent if the account is eligible.
                        </p>
                    </div>

                    <form class="form-custom mt-4" id="reset-request-form">
                        <input type="hidden" id="csrf_token" value="<?= esc($csrf) ?>">

                        <div class="mb-4">
                            <label class="form-label" for="login-email">
                                Email Address<span class="text-danger ms-1">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent"><i class="ri-mail-line text-muted"></i></span>
                                <input type="email" class="form-control" id="login-email" placeholder="you@organisation.com" autocomplete="email" required>
                            </div>
                        </div>

                        <div class="alert alert-info d-flex align-items-start gap-2 py-2 px-3 fs-12 mb-4">
                            <i class="ri-information-line flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>Password Policy:</strong> Min 12 chars · 3-of-4 character classes · Cannot resemble username/email · Recent passwords blocked.
                            </div>
                        </div>

                        <div id="reset-alert" class="alert d-none mb-3" role="alert"></div>

                        <button type="button" class="btn btn-primary rounded-2 w-100 btn-loader" id="reset-btn" onclick="handleResetRequest(this)">
                            <span class="indicator-label">
                                <i class="ri-mail-send-line me-2"></i>Send Reset Link
                            </span>
                            <span class="indicator-progress d-none">
                                Sending secure link...
                                <i class="ri-loader-2-fill ms-2"></i>
                            </span>
                        </button>

                        <p class="mb-0 mt-4 text-muted text-center fs-12">
                            <a href="login.php" class="text-primary fw-medium">
                                <i class="ri-arrow-left-line me-1"></i>Back to Sign In
                            </a>
                        </p>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script type="module" src="assets/js/app.js"></script>

<script>
async function handleResetRequest(btn) {
    const emailEl  = document.getElementById('login-email');
    const alertBox = document.getElementById('reset-alert');
    const csrf     = document.getElementById('csrf_token')?.value || '';
    const email    = (emailEl?.value || '').trim();

    function showAlert(msg, type) {
        alertBox.className = 'alert alert-' + type + ' mb-3';
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    if (!email) {
        showAlert('Please enter your email address.', 'warning');
        return;
    }

    btn.disabled = true;
    btn.querySelector('.indicator-label')?.classList.add('d-none');
    btn.querySelector('.indicator-progress')?.classList.remove('d-none');
    alertBox.classList.add('d-none');

    try {
        const res = await fetch('backend/api/auth/password-reset.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ email })
        });

        const data = await res.json();
        showAlert(data.message || 'If that email is registered, a reset link has been sent.', 'success');
    } catch (err) {
        showAlert('Network error. Please try again.', 'danger');
    } finally {
        btn.disabled = false;
        btn.querySelector('.indicator-label')?.classList.remove('d-none');
        btn.querySelector('.indicator-progress')?.classList.add('d-none');
    }
}
</script>
</body>
</html>
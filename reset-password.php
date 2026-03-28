<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

session_start();

$csrf  = csrf_token();
$token = trim((string)($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="utf-8">
    <title>Create New Password | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = true;</script>
    <script src="assets/js/layout/layout-auth.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css">
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
                            <div id="resetPolicyCarousel" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-indicators">
                                    <button type="button" data-bs-target="#resetPolicyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                    <button type="button" data-bs-target="#resetPolicyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                    <button type="button" data-bs-target="#resetPolicyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                </div>
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-lock-password-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Credential Requirements</h3>
                                            <p class="mt-3">Use 12 to 128 characters and satisfy at least 3 of 4 classes: uppercase, lowercase, number, and special character.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-history-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Password History Enforced</h3>
                                            <p class="mt-3">Reset passwords cannot reuse recent credentials and must not resemble the account email address.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-shield-check-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Secure Reset Flow</h3>
                                            <p class="mt-3">A valid reset token is required, and all active sessions will be revoked after the password is changed.</p>
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
                            <div class="mb-3">
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fs-12">
                                    <i class="ri-lock-password-line me-1"></i>Password Reset
                                </span>
                            </div>
                            <h4 class="fw-medium">Create New Password</h4>
                            <p class="text-muted mb-0">Set a new password for your account using the reset link you received.</p>
                        </div>

                        <form class="form-custom mt-10" id="reset-confirm-form">
                            <input type="hidden" id="csrf_token" value="<?= esc($csrf) ?>">
                            <input type="hidden" id="reset_token" value="<?= esc($token) ?>">

                            <div class="mb-5">
                                <label class="form-label" for="new-password">New Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent"><i class="ri-lock-line text-muted"></i></span>
                                    <input type="password" id="new-password" class="form-control" placeholder="Min. 12 characters" autocomplete="new-password">
                                </div>
                                <div class="d-flex align-items-center mb-2 mt-2">
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px"></div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 fs-11 text-muted mt-1">
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>12-128 chars</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>3 of 4 classes</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>No recent reuse</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>Email-safe</span>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label" for="confirm-password">Confirm Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent"><i class="ri-lock-2-line text-muted"></i></span>
                                    <input type="password" id="confirm-password" class="form-control" placeholder="Confirm your new password" autocomplete="new-password">
                                </div>
                            </div>

                            <div id="reset-alert" class="alert d-none mb-3" role="alert"></div>
                            <button type="button" class="btn btn-primary rounded-2 w-100 btn-loader" id="confirm-btn">
                                <span class="indicator-label">Update Password</span>
                                <span class="indicator-progress d-none flex gap-2 justify-content-center w-100">
                                    <span>Please Wait...</span>
                                    <i class="ri-loader-2-fill"></i>
                                </span>
                            </button>

                            <p class="mb-0 mt-10 text-muted text-center">
                                Remembered your password?
                                <a href="login.php" class="text-primary fw-medium text-decoration-underline ms-1">Back to Sign In</a>
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
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script type="module" src="assets/js/app.js"></script>
    <script>
    async function handlePasswordReset(btn) {
        const alertBox = document.getElementById('reset-alert');
        const csrf = document.getElementById('csrf_token').value;
        const token = document.getElementById('reset_token').value;
        const password = document.getElementById('new-password').value;
        const confirm = document.getElementById('confirm-password').value;

        function showAlert(msg, type) {
            alertBox.className = 'alert alert-' + type + ' mb-3';
            alertBox.textContent = msg;
            alertBox.classList.remove('d-none');
        }

        if (!token) {
            showAlert('Invalid or missing reset token.', 'danger');
            return;
        }
        if (!password || !confirm) {
            showAlert('Please complete both password fields.', 'warning');
            return;
        }
        if (password !== confirm) {
            showAlert('Passwords do not match.', 'warning');
            return;
        }

        btn.disabled = true;
        btn.querySelector('.indicator-label')?.classList.add('d-none');
        btn.querySelector('.indicator-progress')?.classList.remove('d-none');
        alertBox.classList.add('d-none');

        try {
            const res = await fetch('backend/api/auth/reset-confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({
                    token,
                    password,
                    confirm_password: confirm
                })
            });

            const data = await res.json();
            showAlert(data.message || 'Password updated.', data.status === 'success' ? 'success' : 'danger');

            if (data.status === 'success') {
                document.getElementById('reset-confirm-form')?.reset();
                setTimeout(() => {
                    window.location.href = 'login.php?reset=1';
                }, 1500);
            }
        } catch (e) {
            showAlert('Network error. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.querySelector('.indicator-label')?.classList.remove('d-none');
            btn.querySelector('.indicator-progress')?.classList.add('d-none');
        }
    }

    document.getElementById('confirm-btn')?.addEventListener('click', function() {
        handlePasswordReset(this);
    });
    </script>
</body>
</html>

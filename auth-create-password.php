<!DOCTYPE html>
<html lang="en" class="h-100">


<!-- auth-create-password.html Ownuh, 16:37 GMT -->
<head>
    <meta charset="utf-8">
    <title>Create Password | Ownuh - Secure Authentication and Intrusion Prevention System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ownuh is the top-selling Bootstrap 5 admin dashboard template. With Dark Mode, multi-demo options, RTL support, and lifetime updates, it's perfect for web developers.">
    <meta name="keywords" content="Ownuh bootstrap dashboard, bootstrap, bootstrap 5, html dashboard, web dashboard, admin themes, web design, figma, web development, fullcalendar, datatables, free templates, free admin themes, bootstrap theme, bootstrap template, bootstrap dashboard, bootstrap dark mode, bootstrap button, frontend dashboard, responsive bootstrap theme">
    <meta content="Ownuh" name="author">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="article">
    <meta property="og:title" content="Ownuh - Secure Authentication and Intrusion Prevention System">
    <meta property="og:description" content="Ownuh is the top-selling Bootstrap 5 admin dashboard template. With Dark Mode, multi-demo options, RTL support, and lifetime updates, it's perfect for web developers.">
    <meta property="og:url" content="https://themeforest.net/user/Ownuh/portfolio">
    <meta property="og:site_name" content="by Ownuhs">
    <script>const AUTH_LAYOUT = true;</script>
    <!-- Layout JS -->
    <script src="assets/js/layout/layout-auth.js"></script>
    
    <script src="assets/js/layout/layout.js"></script>
    
    <!-- Choice Css -->
    <link rel="stylesheet" href="assets/libs/choices.js/public/assets/styles/choices.min.css">
    <!-- Simplebar Css -->
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <!--icons css-->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <!-- Sweet Alert -->
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap Css -->
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    <!-- App Css-->
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">
    
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet" type="text/css">
</head>

<body>
    <?php include __DIR__ . '/backend/partials/header.php'; ?>
    <?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>

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
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                </div>
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-lock-password-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Credential Requirements (SRS Â§2.2)</h3>
                                            <p class="mt-3">Min 12 / max 128 characters. Must use 3 of 4 classes: uppercase, lowercase, digit, special. Cannot resemble your username or email address.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-history-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Password History Enforced</h3>
                                            <p class="mt-3">Your new password cannot match any of your last 12 passwords. Standard accounts expire every 180 days. Privileged accounts every 90 days.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-database-2-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Breached Password Check</h3>
                                            <p class="mt-3">All new passwords are checked against the HIBP database and a custom dictionary of common credentials. Matching passwords are rejected.</p>
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
                            <div class="mb-3"><span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fs-12"><i class="ri-lock-password-line me-1"></i>Set New Password Â· Event AUTH-005</span></div>
                            <h4 class="fw-medium">Create New Password</h4>
                            <p class="text-muted mb-0">Your new password must meet the SAIPS credential requirements in Â§2.2. It will be bcrypt hashed (cost 12) at rest.</p>
                        </div>
                        <form class="form-custom mt-10">

                            <div class="mb-5">
                                <label class="form-label" for="LoginPassword">New Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent"><i class="ri-lock-line text-muted"></i></span>
                                    <input type="password" id="LoginPassword" class="form-control" name="password" placeholder="Min. 12 characters" data-visible="false" autocomplete="new-password">
                                    <a class="input-group-text bg-transparent toggle-password" href="javascript:;" data-target="password">
                                        <i class="ri-eye-off-line text-muted toggle-icon"></i>
                                    </a>
                                </div>
                                <div class="d-flex align-items-center mb-2 mt-2">
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px"></div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 fs-11 text-muted mt-1">
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>12â€“128 chars</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>3 of 4 classes</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>Not in last 12</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>Not in HIBP</span>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label" for="confirmPassword">Confirm Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="confirmPassword" class="form-control" name="password" placeholder="Confirm your password" data-visible="false">
                                    <a class="input-group-text bg-transparent toggle-password" href="javascript:;" data-target="password">
                                        <i class="ri-eye-off-line text-muted toggle-icon"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="form-check form-check-sm d-flex align-items-center gap-2 mb-5">
                                <input class="form-check-input" type="checkbox" value="remember-me" id="remember-me">
                                <label class="form-check-label" for="remember-me">
                                    Remember me
                                </label>
                            </div>

                            <div id="pw-alert" class="alert d-none mb-3" role="alert"></div>
                            <button type="button" id="pw-btn" class="btn btn-primary rounded-2 w-100 btn-loader" onclick="handlePasswordReset(this)">
                                <span class="indicator-label">
                                    Reset Password
                                </span>
                                <span class="indicator-progress flex gap-2 justify-content-center w-100">
                                    <span>Please Wait...</span>
                                    <i class="ri-loader-2-fill"></i>
                                </span>
                            </button>

                            <p class="mb-0 mt-10 text-muted text-center">
                                I Remember!, Let me try once...
                                <a href="auth-signin.html" class="text-primary fw-medium text-decoraton-underline ms-1">
                                    Sign In
                                </a>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include __DIR__ . '/backend/partials/footer-auth-scripts.php'; ?>

<script>
(function() {
    // Read token from URL ?token=...
    const params = new URLSearchParams(window.location.search);
    window.RESET_TOKEN = params.get('token') || '';
    if (!window.RESET_TOKEN) {
        const el = document.getElementById('pw-alert');
        if (el) {
            el.className = 'alert alert-warning mb-3';
            el.textContent = 'No reset token found. Please request a new password reset link.';
            el.classList.remove('d-none');
        }
        const btn = document.getElementById('pw-btn');
        if (btn) btn.disabled = true;
    }
})();

async function handlePasswordReset(btn) {
    const newPass     = (document.getElementById('LoginPassword')?.value  || '');
    const confirmPass = (document.getElementById('confirmPassword')?.value || '');
    const alertBox    = document.getElementById('pw-alert');
    const token       = window.RESET_TOKEN;

    function showAlert(msg, type) {
        if (!alertBox) { alert(msg); return; }
        alertBox.className = 'alert alert-' + type + ' mb-3';
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    if (!newPass || !confirmPass) {
        showAlert('Both password fields are required.', 'warning');
        return;
    }
    if (newPass !== confirmPass) {
        showAlert('Passwords do not match.', 'warning');
        return;
    }
    if (newPass.length < 12) {
        showAlert('Password must be at least 12 characters.', 'warning');
        return;
    }
    if (!token) {
        showAlert('Invalid or missing reset token. Please request a new reset link.', 'danger');
        return;
    }

    btn.disabled = true;
    btn.querySelector('.indicator-label')?.classList.add('d-none');
    btn.querySelector('.indicator-progress')?.classList.remove('d-none');
    if (alertBox) alertBox.classList.add('d-none');

    try {
        const res  = await fetch('backend/api/auth/password-change.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                reset_token:          token,
                new_password:         newPass,
                new_password_confirm: confirmPass,
            }),
        });
        const data = await res.json();

        if (res.ok && data.status === 'success') {
            showAlert('Password changed successfully. Redirecting to sign-in...', 'success');
            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
        } else {
            const errs = data.errors ? data.errors.join(' ') : (data.message || 'Password reset failed.');
            showAlert(errs, 'danger');
            btn.disabled = false;
            btn.querySelector('.indicator-label')?.classList.remove('d-none');
            btn.querySelector('.indicator-progress')?.classList.add('d-none');
        }
    } catch (err) {
        showAlert('Network error. Please try again.', 'danger');
        btn.disabled = false;
        btn.querySelector('.indicator-label')?.classList.remove('d-none');
        btn.querySelector('.indicator-progress')?.classList.add('d-none');
    }
}
</script>
</body>


<!-- auth-create-password.html Ownuh, 16:38 GMT -->
</html>

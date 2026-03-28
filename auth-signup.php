<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('New User');
$pageTitle = 'Sign Up | Ownuh SAIPS';
$authLayout = false;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="row justify-content-center"><div class="col-xl-7"><div class="card border-0 shadow-sm"><div class="card-body p-4"><div class="mb-4 text-center"><span class="badge bg-primary-subtle text-primary border border-primary mb-3">Self-Service Onboarding</span><h2 class="fw-semibold mb-2">Request a new SAIPS account</h2><p class="text-muted mb-0">This signup flow submits through the live registration API while keeping the shared app shell.</p></div><div id="signup-alert" class="alert d-none mb-3" role="alert"></div><form id="signup-form" class="row g-3"><input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>"><div class="col-md-6"><label class="form-label">Display Name</label><input class="form-control" name="display_name" required></div><div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role"><option value="user">User</option><option value="manager">Manager</option></select></div><div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="12" required></div><div class="col-md-6"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="password_confirm" minlength="12" required></div><div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2"><a href="login.php" class="text-decoration-none">Already have an account?</a><button type="submit" class="btn btn-primary" id="signup-btn">Create Account</button></div></form></div></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
<script>
document.getElementById('signup-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const btn = document.getElementById('signup-btn');
    const alertBox = document.getElementById('signup-alert');
    const data = Object.fromEntries(new FormData(form).entries());
    alertBox.className = 'alert d-none mb-3';
    btn.disabled = true;
    try {
        const response = await fetch('backend/api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const payload = await response.json();
        alertBox.textContent = payload.message || (response.ok ? 'Account request submitted.' : 'Signup failed.');
        alertBox.className = 'alert mb-3 ' + (response.ok ? 'alert-success' : 'alert-danger');
        if (response.ok) {
            form.reset();
            setTimeout(() => { window.location.href = 'login.php'; }, 1800);
        }
    } catch (error) {
        alertBox.textContent = 'Network error. Please try again.';
        alertBox.className = 'alert mb-3 alert-danger';
    } finally {
        btn.disabled = false;
    }
});
</script>
</body>
</html>

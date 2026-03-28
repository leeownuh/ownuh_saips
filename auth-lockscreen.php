<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Locked Session');
$pageTitle = 'Lock Screen | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="row justify-content-center"><div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body p-4 text-center"><div class="avatar avatar-xl avatar-title rounded-circle bg-primary text-white mx-auto mb-3">LS</div><h2 class="fw-semibold mb-2">Session locked</h2><p class="text-muted mb-4">Resume work by signing in again. This page now uses the same shared navigation shell as the rest of the platform.</p><div class="d-flex justify-content-center gap-2 flex-wrap"><a href="login.php" class="btn btn-primary">Unlock via Sign In</a><a href="forgot-password.php" class="btn btn-outline-secondary">Need password help?</a></div></div></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

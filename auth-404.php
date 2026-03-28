<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Security Visitor');
$pageTitle = '404 Not Found | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><span class="badge bg-warning-subtle text-warning border border-warning mb-3">404 Not Found</span><h2 class="fw-semibold mb-3">We couldn't find that page</h2><p class="text-muted mb-4">The link may be outdated, moved, or typed incorrectly. Use the shared menu or search to jump back into the right section.</p><div class="d-flex justify-content-center gap-2 flex-wrap"><a href="dashboard.php" class="btn btn-primary">Open Dashboard</a><a href="login.php" class="btn btn-outline-secondary">Sign In</a></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

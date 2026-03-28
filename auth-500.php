<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Security Visitor');
$pageTitle = '500 Server Error | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><span class="badge bg-danger-subtle text-danger border border-danger mb-3">500 Server Error</span><h2 class="fw-semibold mb-3">The platform hit an unexpected error</h2><p class="text-muted mb-4">Ownuh SAIPS is still protecting data, but this request could not be completed. Try again shortly or review the server logs if the issue persists.</p><div class="d-flex justify-content-center gap-2 flex-wrap"><a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a><a href="under-maintenance.php" class="btn btn-outline-secondary">Status Page</a></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

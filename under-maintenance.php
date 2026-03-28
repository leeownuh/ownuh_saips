<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Service Status');
$pageTitle = 'Under Maintenance | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="card border-0 shadow-sm"><div class="card-body py-5 text-center"><span class="badge bg-warning-subtle text-warning border border-warning mb-3">Maintenance Window</span><h2 class="fw-semibold mb-3">Ownuh SAIPS is temporarily in maintenance mode</h2><p class="text-muted mb-4">Core services are paused while configuration or platform updates are completed. Once maintenance ends, the app will return to the normal shared shell automatically.</p><div class="row g-3 justify-content-center"><div class="col-md-3"><div class="border rounded-3 p-3"><div class="fs-12 text-muted">Authentication</div><div class="fw-semibold">Protected</div></div></div><div class="col-md-3"><div class="border rounded-3 p-3"><div class="fs-12 text-muted">Audit Integrity</div><div class="fw-semibold">Preserved</div></div></div><div class="col-md-3"><div class="border rounded-3 p-3"><div class="fs-12 text-muted">Next Step</div><div class="fw-semibold">Retry Later</div></div></div></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

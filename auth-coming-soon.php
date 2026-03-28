<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Security Visitor');
$pageTitle = 'Coming Soon | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="card border-0 shadow-sm"><div class="card-body py-5"><span class="badge bg-primary-subtle text-primary border border-primary mb-3">Coming Soon</span><h2 class="fw-semibold mb-3">Another SAIPS module is on the roadmap</h2><p class="text-muted mb-4">The shared shell is ready, and this placeholder now lives inside the same menu, header, and footer system as the rest of the app. Use it as a launchpad for the next security workflow.</p><div class="row g-3"><div class="col-md-4"><div class="border rounded-3 p-3 h-100"><h6 class="fw-semibold">Planned</h6><p class="text-muted mb-0">Additional security operations pages can drop into this shared shell without duplicating layout code.</p></div></div><div class="col-md-4"><div class="border rounded-3 p-3 h-100"><h6 class="fw-semibold">Ready</h6><p class="text-muted mb-0">Search, theme handling, fullscreen behavior, and navigation already work from the common partials.</p></div></div><div class="col-md-4"><div class="border rounded-3 p-3 h-100"><h6 class="fw-semibold">Next</h6><p class="text-muted mb-0">Attach the new feature to a route and plug it into the sidebar when the implementation is ready.</p></div></div></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

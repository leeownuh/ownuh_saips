<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = saips_guest_user('Offline Visitor');
$pageTitle = 'Offline | Ownuh SAIPS';
$authLayout = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper"><div class="app-container"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><span class="badge bg-secondary-subtle text-secondary border border-secondary mb-3">Offline</span><h2 class="fw-semibold mb-3">Connection unavailable</h2><p class="text-muted mb-4">The browser could not reach the platform or a required service. Reconnect to the network, then retry the action from the shared navigation.</p><div class="d-flex justify-content-center gap-2 flex-wrap"><a href="login.php" class="btn btn-primary">Retry Sign In</a><a href="dashboard.php" class="btn btn-outline-secondary">Open Dashboard</a></div></div></div></div></main>
<?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

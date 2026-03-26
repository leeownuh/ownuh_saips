<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Rate Limit Configuration (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

// POST: update a rate limit config
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403); die('CSRF token mismatch.');
    }
    $id       = $_POST['id'] ?? '';
    $limit    = (int)($_POST['requests_limit'] ?? 0);
    $window   = (int)($_POST['window_seconds'] ?? 0);
    $action   = $_POST['action_on_breach'] ?? 'rate_429';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($id && $limit > 0 && $window > 0) {
        $db->execute(
            'UPDATE rate_limit_config SET requests_limit = ?, window_seconds = ?,
             action_on_breach = ?, is_active = ?, updated_by = ? WHERE id = ?',
            [$limit, $window, $action, $isActive, $user['id'], $id]
        );
    }
    header('Location: ips-rate-limits.php?saved=1');
    exit;
}

// CAP512 Unit 7: DB queries
$rateLimits = $db->fetchAll(
    'SELECT id, endpoint, requests_limit, window_seconds, scope, action_on_breach, is_active, updated_at
     FROM rate_limit_config ORDER BY endpoint ASC'
);

$activeCount = count(array_filter($rateLimits, fn($r) => (int)$r['is_active'] === 1));
$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Rate Limits | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet">
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet">
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>

<main class="app-wrapper">
    <div class="app-container">
        <div class="hstack flex-wrap gap-3 mb-5">
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-semibold"><i class="ri-speed-line me-2 text-warning"></i>Rate Limit Configuration</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="#">IPS</a></li>
                    <li class="breadcrumb-item active">Rate Limits</li>
                </ol></nav>
            </div>
        </div>

        <?php if ($saved): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i>Rate limit configuration saved successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-primary"><?= count($rateLimits) ?></div>
                    <div class="text-muted small">Total Rules</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-success"><?= $activeCount ?></div>
                    <div class="text-muted small">Active Rules</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-warning"><?= count($rateLimits) - $activeCount ?></div>
                    <div class="text-muted small">Disabled Rules</div>
                </div>
            </div>
        </div>

        <!-- Rate Limit Rules -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="mb-0 fw-semibold">Endpoint Rate Limits</h5>
                <p class="text-muted small mb-0">Click edit to update any rule. Changes take effect immediately (Redis cache cleared).</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Endpoint</th>
                                <th>Limit</th>
                                <th>Window</th>
                                <th>Scope</th>
                                <th>On Breach</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rateLimits)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No rate limit rules configured.</td></tr>
                        <?php else: foreach ($rateLimits as $rl): ?>
                            <tr>
                                <td><code><?= esc($rl['endpoint']) ?></code></td>
                                <td><?= (int)$rl['requests_limit'] ?> req</td>
                                <td><?= (int)$rl['window_seconds'] ?>s</td>
                                <td><span class="badge bg-info-subtle text-info"><?= esc($rl['scope']) ?></span></td>
                                <td><span class="badge bg-warning-subtle text-warning"><?= esc($rl['action_on_breach']) ?></span></td>
                                <td>
                                    <?php if ($rl['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= esc(substr($rl['updated_at'], 0, 16)) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        data-id="<?= esc($rl['id']) ?>"
                                        data-endpoint="<?= esc($rl['endpoint']) ?>"
                                        data-limit="<?= (int)$rl['requests_limit'] ?>"
                                        data-window="<?= (int)$rl['window_seconds'] ?>"
                                        data-action="<?= esc($rl['action_on_breach']) ?>"
                                        data-active="<?= (int)$rl['is_active'] ?>">
                                        <i class="ri-edit-line"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Rate Limit — <code id="editEndpoint"></code></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Request Limit</label>
                        <input type="number" name="requests_limit" id="editLimit" class="form-control" min="1" max="10000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Window (seconds)</label>
                        <input type="number" name="window_seconds" id="editWindow" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action on Breach</label>
                        <select name="action_on_breach" id="editAction" class="form-select">
                            <option value="rate_429">Return 429 Too Many Requests</option>
                            <option value="block_temp">Temporarily Block IP</option>
                            <option value="block_perm">Permanently Block IP</option>
                            <option value="soft_lock">Soft-lock Account</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="editActive" class="form-check-input" value="1">
                        <label class="form-check-label" for="editActive">Rule Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/js/pages/scroll-top.init.js"></script>
<script src="assets/js/app.js" type="module"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value       = btn.dataset.id;
    document.getElementById('editEndpoint').textContent = btn.dataset.endpoint;
    document.getElementById('editLimit').value    = btn.dataset.limit;
    document.getElementById('editWindow').value   = btn.dataset.window;
    document.getElementById('editAction').value   = btn.dataset.action;
    document.getElementById('editActive').checked = btn.dataset.active === '1';
});
</script>
</body>
</html>

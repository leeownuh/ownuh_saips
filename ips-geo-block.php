<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Geo-Block Configuration (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

// POST: add or remove geo rule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403); die('CSRF token mismatch.');
    }
    $action  = $_POST['action']       ?? '';
    $country = strtoupper(trim($_POST['country_code'] ?? ''));
    $name    = trim($_POST['country_name'] ?? $country);
    $type    = $_POST['rule_type']    ?? 'deny';

    if ($action === 'add' && preg_match('/^[A-Z]{2}$/', $country)) {
        $db->execute(
            'INSERT INTO geo_rules (id, country_code, country_name, rule_type, created_by)
             VALUES (UUID(), ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rule_type = VALUES(rule_type)',
            [$country, $name, $type, $user['id']]
        );
    } elseif ($action === 'remove' && preg_match('/^[A-Z]{2}$/', $country)) {
        $db->execute('DELETE FROM geo_rules WHERE country_code = ?', [$country]);
    }
    header('Location: ips-geo-block.php');
    exit;
}

// CAP512 Unit 7: DB queries
$geoRules = $db->fetchAll(
    'SELECT gr.id, gr.country_code, gr.country_name, gr.rule_type,
            gr.created_at, u.display_name as created_by_name
     FROM geo_rules gr
     LEFT JOIN users u ON u.id = gr.created_by
     ORDER BY gr.country_code ASC'
);

$denyCount  = count(array_filter($geoRules, fn($r) => $r['rule_type'] === 'deny'));
$allowCount = count(array_filter($geoRules, fn($r) => $r['rule_type'] === 'allow'));

// CAP512 Unit 7: recent geo-blocked attempts
$recentGeoBlocks = $db->fetchAll(
    'SELECT ip_address, country_code, blocked_at, trigger_rule
     FROM blocked_ips
     WHERE block_type = "geo" AND unblocked_at IS NULL
     ORDER BY blocked_at DESC LIMIT 20'
) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Geo-Block Configuration | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
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
                <h4 class="mb-1 fw-semibold"><i class="ri-earth-line me-2 text-primary"></i>Geo-Block Configuration</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="#">IPS</a></li>
                    <li class="breadcrumb-item active">Geo-Block</li>
                </ol></nav>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGeoModal">
                <i class="ri-add-line me-1"></i>Add Country Rule
            </button>
        </div>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-danger"><?= $denyCount ?></div>
                    <div class="text-muted small">Countries Denied</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-success"><?= $allowCount ?></div>
                    <div class="text-muted small">Allowlist Rules</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-warning"><?= count($recentGeoBlocks) ?></div>
                    <div class="text-muted small">Recent Geo Blocks</div>
                </div>
            </div>
        </div>

        <!-- Rules Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="mb-0 fw-semibold">Active Geo Rules</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Country Code</th>
                                <th>Country Name</th>
                                <th>Rule Type</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($geoRules)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No geo rules configured.</td></tr>
                        <?php else: foreach ($geoRules as $rule): ?>
                            <tr>
                                <td><span class="badge bg-secondary fs-6"><?= esc($rule['country_code']) ?></span></td>
                                <td><?= esc($rule['country_name']) ?></td>
                                <td>
                                    <?php if ($rule['rule_type'] === 'deny'): ?>
                                        <span class="badge bg-danger-subtle text-danger">Deny</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Allow</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($rule['created_by_name'] ?? 'System') ?></td>
                                <td><?= esc($rule['created_at']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove geo rule for <?= esc($rule['country_code']) ?>?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="country_code" value="<?= esc($rule['country_code']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Geo Blocks -->
        <?php if (!empty($recentGeoBlocks)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="mb-0 fw-semibold">Recent Geo-Blocked IPs</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>IP Address</th><th>Country</th><th>Trigger</th><th>Blocked At</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentGeoBlocks as $b): ?>
                            <tr>
                                <td><code><?= esc($b['ip_address']) ?></code></td>
                                <td><?= esc($b['country_code'] ?? '—') ?></td>
                                <td><?= esc($b['trigger_rule'] ?? 'geo_block') ?></td>
                                <td><?= esc($b['blocked_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Geo Rule Modal -->
<div class="modal fade" id="addGeoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Country Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Country Code (ISO 3166-1 alpha-2)</label>
                        <input type="text" name="country_code" class="form-control" maxlength="2" placeholder="e.g. CN" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Country Name</label>
                        <input type="text" name="country_name" class="form-control" placeholder="e.g. China">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rule Type</label>
                        <select name="rule_type" class="form-select">
                            <option value="deny">Deny (Block access)</option>
                            <option value="allow">Allow (Explicit allowlist)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script src="assets/js/pages/scroll-top.init.js"></script>
<script src="assets/js/app.js" type="module"></script>
</body>
</html>

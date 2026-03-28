<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Blocked IPs | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-forbid-2-line me-2 text-danger"></i>Blocked IPs</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item">IPS</li>
                        <li class="breadcrumb-item active">Blocked IPs</li>
                    </ol></nav>
                </div>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#blockIpModal">
                    <i class="ri-add-line me-1"></i>Block IP
                </button>
            </div>

<?php
// Handle POST: quick block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $ip  = trim($_POST['ip_address'] ?? '');
    $dur = (int)($_POST['duration'] ?? 60);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $exp = $dur > 0 ? date('Y-m-d H:i:s', time() + $dur * 60) : null;
        $db->execute(
            'INSERT INTO blocked_ips (ip_address, block_type, trigger_rule, expires_at)
             VALUES (?, "manual", ?, ?)
             ON DUPLICATE KEY UPDATE blocked_at=NOW(), expires_at=VALUES(expires_at), unblocked_at=NULL',
            [$ip, $_POST['reason'] ?? 'Manual block', $exp]
        );
    }
    header('Location: ips-blocked-ips.php');
    exit;
}

// Handle GET: unblock
if (isset($_GET['unblock']) && verify_csrf($_GET['t'] ?? null)) {
    $db->execute('UPDATE blocked_ips SET unblocked_at = NOW(), unblocked_by = ? WHERE id = ?',
        [$user['sub'] ?? 'admin', $_GET['unblock']]);
    header('Location: ips-blocked-ips.php');
    exit;
}

$blocked = get_blocked_ips(200);

// CAP512 Unit 5: array grouping
$byType = array_group_by($blocked, 'block_type');
$stats  = $db->fetchOne(
    'SELECT COUNT(*) as total,
            SUM(block_type="brute_force") as brute_force,
            SUM(block_type="manual")     as manual,
            SUM(block_type="geo_block")  as geo,
            SUM(block_type="threat_feed") as feed
     FROM blocked_ips WHERE unblocked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())'
);
?>

            <div class="row g-3 mb-4">
                <?php
                $sc = [
                    ['v'=>$stats['total'],        'l'=>'Total Blocked',    'c'=>'danger'],
                    ['v'=>$stats['brute_force'],   'l'=>'Brute-Force',      'c'=>'warning'],
                    ['v'=>$stats['manual'],        'l'=>'Manual Blocks',    'c'=>'primary'],
                    ['v'=>$stats['feed'],          'l'=>'Threat Feed',      'c'=>'info'],
                ];
                foreach ($sc as $c): ?>
                <div class="col-6 col-md-3">
                    <div class="card text-center py-3">
                        <h3 class="fw-bold text-<?= $c['c'] ?> mb-0"><?= number_format((int)$c['v']) ?></h3>
                        <p class="text-muted fs-12 mb-0"><?= esc($c['l']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Active IP Blocks <span class="badge bg-success ms-2">LIVE</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>IP Address</th><th>Block Type</th><th>Trigger Rule</th><th>Country</th><th>Blocked At</th><th>Expires</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($blocked)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-5">No IPs currently blocked. <a href="#">Run seed.sql</a> to add demo data.</td></tr>
                            <?php else: ?>
                            <?php foreach ($blocked as $b):
                                $typeClass = match($b['block_type']) {
                                    'brute_force'=> 'bg-warning-subtle text-warning border border-warning',
                                    'manual'     => 'bg-primary-subtle text-primary border border-primary',
                                    'geo_block'  => 'bg-info-subtle text-info border border-info',
                                    'threat_feed'=> 'bg-danger-subtle text-danger border border-danger',
                                    'tor_exit'   => 'bg-dark-subtle text-dark border border-dark',
                                    default      => 'bg-secondary-subtle text-secondary',
                                };
                                $permanent = empty($b['expires_at']);
                                ?>
                                <tr>
                                    <td class="fw-mono fw-semibold"><?= esc($b['ip_address']) ?></td>
                                    <td><span class="badge <?= $typeClass ?>"><?= esc(str_replace('_',' ', ucfirst($b['block_type']))) ?></span></td>
                                    <td class="fs-13"><?= esc(truncate($b['trigger_rule'] ?? '—', 35)) ?></td>
                                    <td class="text-muted"><?= esc($b['country_code'] ?? '—') ?></td>
                                    <td class="text-muted fs-12"><?= format_ts($b['blocked_at'], 'M d H:i') ?></td>
                                    <td class="text-muted fs-12"><?= $permanent ? '<span class="badge bg-danger">Permanent</span>' : format_ts($b['expires_at'], 'M d H:i') ?></td>
                                    <td>
                                        <a href="ips-blocked-ips.php?unblock=<?= esc($b['id']) ?>&t=<?= esc($csrf) ?>"
                                           class="btn btn-light-success icon-btn-sm"
                                           title="Unblock IP"
                                           onclick="return confirm('Unblock <?= esc($b['ip_address']) ?>?')">
                                            <i class="ri-lock-unlock-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Block IP Modal -->
            <div class="modal fade" id="blockIpModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Block an IP Address</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <form method="POST" action="ips-blocked-ips.php">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                <div class="mb-3">
                                    <label class="form-label">IP Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ip_address" placeholder="185.220.101.47" required pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reason</label>
                                    <input type="text" class="form-control" name="reason" placeholder="Brute force / suspicious activity">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Duration</label>
                                    <select class="form-select" name="duration">
                                        <option value="60">1 hour</option>
                                        <option value="1440">24 hours</option>
                                        <option value="10080">7 days</option>
                                        <option value="0">Permanent</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-danger">Block IP</button>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/js/app.js" type="module"></script>
    <script>
    document.querySelectorAll('[data-bs-toggle="tooltip"],[title]').forEach(el => {
        try { new bootstrap.Tooltip(el, {trigger:'hover'}); } catch(e){}
    });
    </script>
</body>
</html>

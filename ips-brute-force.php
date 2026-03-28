<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Brute-Force Alerts (live from DB)
 * CAP512: PHP, mysqli, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();

// CAP512 Unit 7: Complex query — aggregate login attempts by IP
$bruteAttempts = $db->fetchAll(
    'SELECT la.ip_address, la.username, u.email, u.display_name,
            COUNT(*) as total_failures,
            MAX(la.attempted_at) as last_attempt,
            MIN(la.attempted_at) as first_attempt,
            TIMESTAMPDIFF(MINUTE, MIN(la.attempted_at), MAX(la.attempted_at)) as window_minutes,
            b.id as block_id
     FROM login_attempts la
     LEFT JOIN users u ON u.email = la.username
     LEFT JOIN blocked_ips b ON b.ip_address = la.ip_address AND b.unblocked_at IS NULL
     WHERE la.attempted_at >= NOW() - INTERVAL 24 HOUR
       AND la.success = 0
     GROUP BY la.ip_address, la.username, u.email, u.display_name, b.id
     HAVING total_failures >= 3
     ORDER BY total_failures DESC
     LIMIT 50'
);

// CAP512 Unit 7: Stats
$statsRow = $db->fetchOne(
    'SELECT
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(*) as total_failures,
        SUM(COUNT(*) >= 10) OVER () as severe_ips
     FROM login_attempts
     WHERE attempted_at >= NOW() - INTERVAL 24 HOUR AND success = 0
     GROUP BY ip_address
     ORDER BY COUNT(*) DESC
     LIMIT 1'
);

// CAP512 Unit 7: accounts currently near-locked
$nearLocked = $db->fetchAll(
    'SELECT id, display_name, email, role, failed_attempts, last_failed_at
     FROM users WHERE failed_attempts >= 5 AND deleted_at IS NULL
     ORDER BY failed_attempts DESC LIMIT 10'
);

// CAP512 Unit 5: array_filter for severe IPs
$severeIps = array_filter($bruteAttempts, fn($r) => (int)$r['total_failures'] >= 10);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Brute-Force Alerts | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-fire-line me-2 text-danger"></i>Brute-Force Alerts</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">IPS</li>
                        <li class="breadcrumb-item active">Brute-Force</li>
                    </ol></nav>
                </div>
            </div>

            <?php if (!empty($severeIps)): ?>
            <div class="alert alert-danger d-flex gap-2 mb-4">
                <i class="ri-error-warning-line flex-shrink-0 fs-4"></i>
                <div>
                    <strong><?= count($severeIps) ?> IP(s) with ≥10 failures in 24h detected.</strong>
                    Thresholds: Soft-lock at 5 failures per user · Hard-lock at 10 · Auto-block IP at 15 failures / 5 min (SRS §3.1)
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats row — CAP512 Unit 5: array + loop -->
            <div class="row g-3 mb-4">
                <?php
                $stats = [
                    ['v' => count($bruteAttempts), 'l' => 'Suspicious IPs (24h)',  'c' => 'warning'],
                    ['v' => count($severeIps),      'l' => 'Severe (≥10 failures)', 'c' => 'danger'],
                    ['v' => count($nearLocked),     'l' => 'Accounts Near-Locked',  'c' => 'warning'],
                    ['v' => (int)$db->fetchScalar('SELECT COUNT(*) FROM blocked_ips WHERE unblocked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())'),
                             'l' => 'Currently Blocked',     'c' => 'danger'],
                ];
                foreach ($stats as $s): ?>
                <div class="col-6 col-md-3">
                    <div class="card text-center py-3 <?= (int)$s['v'] > 0 ? 'border-'.$s['c'].' border-2' : '' ?>">
                        <h3 class="fw-bold text-<?= $s['c'] ?> mb-0"><?= number_format((int)$s['v']) ?></h3>
                        <p class="text-muted fs-12 mb-0"><?= esc($s['l']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Attack table — CAP512 Unit 7: Live DB + Unit 3: Control flow -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Brute-Force Incidents (24h) <span class="badge bg-success ms-2">LIVE</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Source IP</th><th>Target User</th><th>Failures</th><th>Time Window</th><th>Last Attempt</th><th>Severity</th><th>Blocked</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bruteAttempts)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-5">No brute-force attempts detected in the last 24 hours.</td></tr>
                            <?php else: ?>
                            <?php foreach ($bruteAttempts as $a):
                                $fails = (int)$a['total_failures'];
                                $sev   = match(true) { $fails >= 20 => ['sev1','danger'], $fails >= 10 => ['sev2','warning'], default => ['sev3','info'] };
                                $isBlocked = !empty($a['block_id']);
                                ?>
                                <tr class="<?= $fails >= 10 ? 'table-danger bg-opacity-25' : '' ?>">
                                    <td class="fw-mono fw-semibold"><?= esc($a['ip_address']) ?></td>
                                    <td class="fs-13"><?= $a['email'] ? esc($a['email']) : '<span class="text-muted">Multiple / Unknown</span>' ?></td>
                                    <td>
                                        <span class="fw-bold fs-5 text-<?= $sev[1] ?>"><?= $fails ?></span>
                                        <div class="progress mt-1" style="height:4px;width:60px">
                                            <div class="progress-bar bg-<?= $sev[1] ?>" style="width:<?= min(100, $fails * 5) ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="text-muted fs-12">
                                        <?php
                                        // CAP512 Unit 4: String + date functions
                                        $win = (int)$a['window_minutes'];
                                        echo $win < 1 ? '< 1 min' : ($win < 60 ? $win.' min' : intdiv($win,60).'h '.($win%60).'m');
                                        ?>
                                    </td>
                                    <td class="text-muted fs-12"><?= format_ts($a['last_attempt'], 'M d H:i:s') ?></td>
                                    <td><?= severity_badge($sev[0]) ?></td>
                                    <td>
                                        <?php if ($isBlocked): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger"><i class="ri-forbid-2-line me-1"></i>Blocked</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning"><i class="ri-eye-line me-1"></i>Monitoring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$isBlocked): ?>
                                        <form method="POST" action="ips-blocked-ips.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                            <input type="hidden" name="ip_address" value="<?= esc($a['ip_address']) ?>">
                                            <input type="hidden" name="reason" value="Brute-force: <?= $fails ?> failures">
                                            <input type="hidden" name="duration" value="1440">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Block 24h">
                                                <i class="ri-forbid-2-line me-1"></i>Block
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <a href="ips-blocked-ips.php" class="btn btn-sm btn-light-secondary">View Block</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Near-locked accounts -->
            <?php if (!empty($nearLocked)): ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Accounts Near-Locked (≥5 Failed Attempts)</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>User</th><th>Role</th><th>Failed Attempts</th><th>Last Failed</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($nearLocked as $u): ?>
                            <tr class="<?= (int)$u['failed_attempts'] >= 10 ? 'table-danger' : 'table-warning' ?>">
                                <td>
                                    <div class="hstack gap-2">
                                        <img src="<?= generate_avatar_image($u['display_name']) ?>" class="avatar-xs rounded-circle" alt="">
                                        <div>
                                            <div class="fs-13"><?= esc($u['display_name']) ?></div>
                                            <div class="fs-11 text-muted"><?= esc($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= role_badge($u['role']) ?></td>
                                <td>
                                    <span class="fw-bold fs-5 text-<?= (int)$u['failed_attempts'] >= 10 ? 'danger' : 'warning' ?>">
                                        <?= (int)$u['failed_attempts'] ?>/10
                                    </span>
                                </td>
                                <td class="text-muted fs-12"><?= format_ts($u['last_failed_at'], 'M d H:i:s') ?></td>
                                <td>
                                    <form method="POST" action="users.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="user_id" value="<?= esc($u['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="ri-lock-unlock-line me-1"></i>Reset & Unlock
                                        </button>
                                    </form>
                                </td>
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
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>

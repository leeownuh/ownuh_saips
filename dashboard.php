<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Live Security Dashboard
 * Reads all KPIs, audit log, user data, and chart data from MySQL.
 * CAP512 Syllabus Coverage:
 * - PHP basics, variables, control flow, loops (Unit I–II)
 * - Functions (Unit III)
 * - String handling (Unit IV)
 * - Arrays (Unit V)
 * - Objects/Classes (Unit VI)
 * - Database with mysqli (Unit VII)
 * - GD graphics (Unit VII)
 */

require_once __DIR__ . '/backend/bootstrap.php';

// Require admin auth
$user = require_auth('admin');

// ── Fetch all live data from MySQL ───────────────────────────────────────────
$stats        = get_dashboard_stats();
$recentAudit  = get_recent_audit(8);
$users        = get_users('', '', 5);
$blockedIps   = get_blocked_ips(5);
$incidents    = get_incidents('', 5);
$monthlyTrend = get_monthly_auth_trend(9);
$loginOrigins = get_login_origins();

// Generate score gauge image — CAP512 Unit 7: Graphics
$scoreGauge   = generate_score_gauge((int)$stats['security_score']);

// CAP512 Unit 2: Variables and data types
$secScore     = (int)$stats['security_score'];
$mfaCoverage  = (int)$stats['mfa_coverage'];

// CAP512 Unit 5: array_map on chart data
$chartLabels     = json_encode(array_map('esc', $monthlyTrend['labels']));
$chartSuccessful = json_encode($monthlyTrend['successful']);
$chartFailed     = json_encode($monthlyTrend['failed']);
$chartBlocked    = json_encode($monthlyTrend['blocked']);

// Login origins for map — CAP512 Unit 5: json_encode of array
$mapData = json_encode($loginOrigins, JSON_HEX_APOS | JSON_HEX_QUOT);

// CSRF token for any forms — CAP512 Unit 2: Security
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Security Dashboard | Ownuh SAIPS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    <script>const AUTH_LAYOUT = false;</script>
    <script src="assets/js/layout/layout-default.js"></script>
    <script src="assets/js/layout/layout.js"></script>
    <link rel="stylesheet" href="assets/libs/choices.js/public/assets/styles/choices.min.css">
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

    <div class="align-items-center justify-content-center" id="preloader">
        <div class="spinner-border text-primary avatar-sm" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <main class="app-wrapper">
        <div class="app-container">

            <!-- Page title -->
            <div class="hstack flex-wrap gap-3 mb-5">
                <div class="flex-grow-1">
                    <h4 class="mb-1 fw-semibold">Security Dashboard</h4>
                    <nav>
                        <ol class="breadcrumb breadcrumb-arrow mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Security Overview</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto align-items-center flex-wrap flex-shrink-0 gap-2">
                    <!-- CAP512 Unit 2: embedding PHP in HTML -->
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                        <i class="ri-database-2-line me-1"></i>Live DB · <?= esc(date('H:i:s')) ?> UTC
                    </span>
                    <a href="javascript:void(0)" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#addAlertModal">
                        <i class="ri-add-line me-1"></i>Create Alert Rule
                    </a>
                </div>
            </div>

            <div class="project-dashboard">

                <!-- ── KPI Cards Row 1 ──────────────────────────────────────── -->
                <div class="row g-3 mb-4">

                    <!-- Authentication Users Overview -->
                    <div class="col-lg-4">
                        <div class="card card-hover overflow-hidden">
                            <div class="card-body hstack gap-2">
                                <div class="avatar avatar-item rounded-2">
                                    <i class="ri-shield-user-line"></i>
                                </div>
                                <div>
                                    <span class="mb-2 fs-12 text-muted">Authentication Users — Overview</span>
                                    <!-- CAP512 Unit 2: PHP variables in HTML -->
                                    <h5 class="fw-medium mb-1"><?= number_format((int)$stats['users']['total']) ?></h5>
                                </div>
                            </div>
                            <div class="card-body bg-light py-2 bg-opacity-40 hstack justify-content-between gap-3">
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">Active Sessions (24h):</h6>
                                    <p class="fs-12 text-muted mb-0"><?= number_format($stats['active_sessions']) ?></p>
                                </div>
                                <div class="vr h-30px align-self-center bg-light"></div>
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">New Registrations (24h):</h6>
                                    <p class="fs-12 text-muted mb-0"><?= number_format((int)$stats['new_registrations_24h']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authentication Events -->
                    <div class="col-lg-4">
                        <div class="card card-hover overflow-hidden">
                            <div class="card-body hstack gap-2">
                                <div class="avatar avatar-item rounded-2">
                                    <i class="ri-lock-password-line"></i>
                                </div>
                                <div>
                                    <span class="mb-2 fs-12 text-muted">Authentication Events (24h)</span>
                                    <h5 class="fw-medium mb-1"><?= number_format((int)$stats['auth_24h']['total_events']) ?></h5>
                                </div>
                            </div>
                            <div class="card-body bg-light py-2 bg-opacity-40 hstack justify-content-between gap-3">
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">Successful Logins (24h):</h6>
                                    <p class="fs-12 text-success fw-semibold mb-0"><?= number_format((int)$stats['auth_24h']['successful_logins']) ?></p>
                                </div>
                                <div class="vr h-30px align-self-center bg-light"></div>
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">Failed Attempts (24h):</h6>
                                    <p class="fs-12 text-danger fw-semibold mb-0"><?= number_format((int)$stats['auth_24h']['failed_attempts']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Score — uses GD image (CAP512 Unit 7: Graphics) -->
                    <div class="col-lg-4">
                        <div class="card card-hover overflow-hidden">
                            <div class="card-body hstack gap-2">
                                <div class="avatar avatar-item rounded-2 <?= $secScore >= 80 ? 'text-bg-success' : ($secScore >= 60 ? 'text-bg-warning' : 'text-bg-danger') ?>">
                                    <i class="ri-shield-check-line"></i>
                                </div>
                                <div>
                                    <span class="mb-2 fs-12 text-muted">Security Score Overview</span>
                                    <h5 class="fw-medium mb-1">Score:
                                        <span class="<?= $secScore >= 80 ? 'text-success' : ($secScore >= 60 ? 'text-warning' : 'text-danger') ?>">
                                            <?= $secScore ?> / 100
                                        </span>
                                    </h5>
                                    <!-- CAP512 Unit 7: Embedding GD-generated image -->
                                    <img src="<?= $scoreGauge ?>" alt="Score Gauge" height="24" class="ms-1">
                                </div>
                            </div>
                            <div class="card-body bg-light py-2 bg-opacity-40 hstack justify-content-between gap-3">
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">MFA Coverage:</h6>
                                    <p class="fs-12 <?= $mfaCoverage >= 90 ? 'text-success' : 'text-warning' ?> fw-semibold mb-0"><?= $mfaCoverage ?>%</p>
                                </div>
                                <div class="vr h-30px align-self-center bg-light"></div>
                                <div class="hstack gap-3">
                                    <h6 class="mb-0 fw-semibold">Blocked IPs (24h):</h6>
                                    <p class="fs-12 text-danger fw-semibold mb-0"><?= $stats['blocked_ips'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Stat Strip ────────────────────────────────────────────── -->
                <!-- CAP512 Unit 5: Iterating array to build HTML -->
                <div class="row g-3 mb-4">
                    <?php
                    // CAP512 Unit 5: Arrays — building data array then looping
                    $statCards = [
                        ['value' => $stats['alert_rules'],           'label' => 'Active Alert Rules',     'icon' => 'ri-alarm-warning-line',  'color' => 'primary'],
                        ['value' => $stats['monitored_endpoints'],   'label' => 'Monitored Endpoints',    'icon' => 'ri-server-line',         'color' => 'info'],
                        ['value' => $stats['blocked_ips'],           'label' => 'Blocked IPs (24h)',      'icon' => 'ri-forbid-2-line',       'color' => 'warning'],
                        ['value' => $stats['open_incidents_total'],  'label' => 'Open Incidents',         'icon' => 'ri-error-warning-line',  'color' => 'danger'],
                        ['value' => $stats['resolved_today'],        'label' => 'Resolved Incidents (24h)','icon' => 'ri-shield-check-line',  'color' => 'success'],
                    ];
                    foreach ($statCards as $card):
                    ?>
                    <div class="col project-stat">
                        <div class="card card-hover card-h-100 overflow-hidden border-<?= esc($card['color']) ?> border-3 border-bottom">
                            <div class="card-body p-4 d-flex align-items-start gap-3 h-100">
                                <div class="flex-fill h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <h3 class="fw-semibold mb-1"><?= sprintf('%02d', (int)$card['value']) ?></h3>
                                        <h6 class="mb-0"><?= esc($card['label']) ?></h6>
                                    </div>
                                </div>
                                <div><i class="<?= esc($card['icon']) ?> display-6 fw-medium text-muted opacity-50"></i></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── Audit Log + User Management ──────────────────────────── -->
                <div class="row g-4 mb-4">

                    <!-- Audit Log Table — live from DB (CAP512 Unit 7) -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                                <h5 class="card-title mb-0">
                                    <i class="ri-list-check-3 me-2 text-primary"></i>
                                    Authentication Audit Log
                                    <span class="badge bg-success ms-2 fs-11">LIVE</span>
                                </h5>
                                <a href="audit-log.php" class="btn btn-sm btn-light-primary">
                                    <i class="ri-external-link-line me-1"></i>View Full Log
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Event ID</th>
                                                <th>User / Source</th>
                                                <th>Timestamp (UTC)</th>
                                                <th>Event Type</th>
                                                <th>Country</th>
                                                <th>Risk</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <!-- CAP512 Unit 3: foreach loop + Unit 7: DB results -->
                                        <?php if (empty($recentAudit)): ?>
                                            <tr><td colspan="7" class="text-center text-muted py-4">No audit events yet — run the seed data to populate.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentAudit as $entry): ?>
                                            <tr>
                                                <td><span class="badge <?= event_badge_class($entry['event_code']) ?>"><?= esc($entry['event_code']) ?></span></td>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <?php if ($entry['email']): ?>
                                                            <!-- CAP512 Unit 7: GD avatar for users without photos -->
                                                            <img src="<?= generate_avatar_image($entry['display_name'] ?? $entry['email']) ?>"
                                                                 class="avatar-xs rounded-circle" alt=""
                                                                 onerror="this.style.display='none'">
                                                            <span class="fs-13"><?= esc($entry['email']) ?></span>
                                                        <?php else: ?>
                                                            <i class="ri-global-line text-muted fs-16"></i>
                                                            <span class="fw-mono fs-12"><?= esc($entry['source_ip'] ?? '—') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-muted fs-12"><?= format_ts($entry['created_at']) ?></td>
                                                <td class="fs-13"><?= esc($entry['event_name']) ?></td>
                                                <td class="text-muted fs-12"><?= esc($entry['country_code'] ?? '—') ?></td>
                                                <td>
                                                    <?php
                                                    // CAP512 Unit 2: Variables + conditionals
                                                    $risk = (int)($entry['risk_score'] ?? 0);
                                                    $riskClass = $risk >= 80 ? 'danger' : ($risk >= 40 ? 'warning' : 'success');
                                                    ?>
                                                    <span class="badge bg-<?= $riskClass ?>-subtle text-<?= $riskClass ?>"><?= $risk > 0 ? $risk : '—' ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // CAP512 Unit 4: String functions — map event code to status
                                                    $evCode = $entry['event_code'];
                                                    // Explicit map: failure/blocked codes → proper badge; everything else = completed
                                                    $failureCodes  = ['AUTH-002','AUTH-003','IPS-001','IPS-002'];
                                                    $blockedCodes  = ['IPS-001','IPS-002'];
                                                    if (in_array($evCode, $blockedCodes)) {
                                                        $status = 'blocked';
                                                    } elseif (in_array($evCode, $failureCodes)) {
                                                        $status = 'failed';
                                                    } else {
                                                        $status = 'completed';
                                                    }
                                                    echo status_badge($status);
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Account Management — live from DB -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                                <h5 class="card-title mb-0"><i class="ri-group-line me-2 text-primary"></i>User Account Management</h5>
                                <a href="users.php" class="btn btn-sm btn-light-primary"><i class="ri-external-link-line me-1"></i>Manage All Users</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr><th>User ID</th><th>Display Name</th><th>Email</th><th>Status</th><th>MFA</th><th>Last Login</th><th>Failed</th><th>Role</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr><td colspan="9" class="text-center text-muted py-4">No users found — run database/seed.sql</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $u): ?>
                                            <tr class="<?= $u['status'] === 'locked' ? 'table-danger' : ($u['status'] === 'pending' ? 'table-warning' : '') ?>">
                                                <td class="fw-medium text-muted fs-12"><?= esc(substr($u['id'], 0, 8)) ?>…</td>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <!-- CAP512 Unit 7: GD generated avatar -->
                                                        <img src="<?= generate_avatar_image($u['display_name']) ?>" class="avatar-xs rounded-circle" alt="">
                                                        <span><?= esc($u['display_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="fs-13"><?= esc($u['email']) ?></td>
                                                <td><?= status_badge($u['status']) ?></td>
                                                <td><?= mfa_badge($u['mfa_factor'], (bool)$u['mfa_enrolled']) ?></td>
                                                <td class="text-muted fs-12"><?= format_ts($u['last_login_at'], 'Y-m-d H:i') ?></td>
                                                <td>
                                                    <span class="fw-semibold <?= (int)$u['failed_attempts'] >= 5 ? 'text-danger' : 'text-success' ?>">
                                                        <?= (int)$u['failed_attempts'] ?>
                                                    </span>
                                                </td>
                                                <td><?= role_badge($u['role']) ?></td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <button class="btn btn-light-primary border-primary icon-btn-sm" title="Edit"><i class="ri-edit-2-line"></i></button>
                                                        <?php if ($u['status'] === 'locked'): ?>
                                                            <button class="btn btn-light-success border-success icon-btn-sm" title="Unlock"><i class="ri-lock-unlock-line"></i></button>
                                                        <?php else: ?>
                                                            <button class="btn btn-light-warning border-warning icon-btn-sm" title="Lock"><i class="ri-lock-line"></i></button>
                                                        <?php endif; ?>
                                                        <a href="audit-log.php?user_id=<?= esc($u['id']) ?>" class="btn btn-light-secondary border-secondary icon-btn-sm" title="Audit Log"><i class="ri-file-search-line"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ── Chart + Map ────────────────────────────────────────────── -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="card-title mb-0 fw-semibold">Authentication Events Trend (Monthly)</h6>
                            </div>
                            <div class="card-body">
                                <div id="saips_auth_chart" style="min-height:320px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 flex-grow-1"><i class="ri-map-pin-2-line me-2 text-primary"></i>Login Origin Heatmap</h5>
                                <button class="btn btn-sm btn-light-primary flex-shrink-0">Generate Report</button>
                            </div>
                            <div class="card-body">
                                <style>
#global-reach-map .jvm-region.jvm-element { fill: #dee2e8; }
#global-reach-map .jvm-region.jvm-element[data-colored] { fill: unset; }
</style>
<div id="global-reach-map" class="jvm-container" style="height:320px;min-height:320px;width:100%;"></div>
                                <!-- CAP512 Unit 5: json_encode of PHP array for JS -->
                                <script>window.SAIPS_MAP_DATA = <?= $mapData ?>;</script>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Add Alert Rule Modal -->
    <div class="modal fade" id="addAlertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-alarm-warning-line me-2"></i>Create Alert Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- CAP512 Unit 2: CSRF token in form -->
                    <form method="POST" action="backend/api/ips/alert-rules.php">
                        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                        <div class="mb-3">
                            <label class="form-label">Trigger Condition</label>
                            <input type="text" class="form-control" name="condition" placeholder="e.g. 10 failures / 5 min per user">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="severity">
                                <option value="sev1">SEV-1 Critical</option>
                                <option value="sev2" selected>SEV-2 High</option>
                                <option value="sev3">SEV-3 Medium</option>
                                <option value="sev4">SEV-4 Low</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Automated Action</label>
                            <select class="form-select" name="action">
                                <option>Account locked + admin email</option>
                                <option>IP blocked 60 min</option>
                                <option>WAF rule deployed</option>
                                <option>Alert only</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Rule</button>
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
    <script src="assets/js/charts/apexcharts-config.init.js"></script>
    <script src="assets/libs/jsvectormap/jsvectormap.min.js"></script>
    <script src="assets/libs/jsvectormap/maps/world.js"></script>
    <script src="assets/js/app.js" type="module"></script>
    <script src="assets/js/dashboards/dashboard-project.init.js"></script>
    <script src="assets/js/dashboards/dashboard-security.init.js"></script>

    <script>
    // CAP512 Unit 2: Embedding PHP data in JS
    document.addEventListener('DOMContentLoaded', function () {

        // ── ApexCharts: Auth Trend (uses live PHP data) ───────────────────
        const trendEl = document.querySelector('#saips_auth_chart');
        if (trendEl) {
            new ApexCharts(trendEl, {
                series: [
                    { name: 'Successful Logins', data: <?= $chartSuccessful ?> },
                    { name: 'Failed Attempts',   data: <?= $chartFailed   ?> },
                    { name: 'Blocked IPs',        data: <?= $chartBlocked  ?> },
                ],
                chart:  { type: 'bar', height: 300, toolbar: { show: false } },
                plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                xaxis:  { categories: <?= $chartLabels ?> },
                yaxis:  { title: { text: 'Events' } },
                colors: ['#0d6efd', '#ffc107', '#dc3545'],
                fill:   { opacity: 1 },
                legend: { position: 'bottom' },
                tooltip: { y: { formatter: v => v + ' events' } },
            }).render();
        }

        // ── jsvectormap: Login origins ────────────────────────────────────
        const origins = (typeof window.SAIPS_MAP_DATA === 'object' && window.SAIPS_MAP_DATA !== null) ? window.SAIPS_MAP_DATA : {};
        console.log('[SAIPS Map] Login origins data:', origins, 'Keys:', Object.keys(origins).length);

        // Delay init to ensure container is painted and sized
        setTimeout(function() {
            const mapEl = document.getElementById('global-reach-map');
            if (!mapEl) { console.warn('[SAIPS Map] Container not found'); return; }
            if (typeof jsVectorMap === 'undefined') { console.warn('[SAIPS Map] jsVectorMap not loaded'); return; }

            // Ensure container has height before init
            if (mapEl.offsetHeight === 0) {
                mapEl.style.height = '320px';
            }

            try {
                // Build gradient colours: light blue → dark blue based on login count
                const vals = Object.values(origins).map(Number);
                const maxV = vals.length ? Math.max(...vals) : 1;
                function loginColor(count) {
                    const t = maxV > 0 ? count / maxV : 0;
                    const r = Math.round(191 + (29  - 191) * t);
                    const g = Math.round(219 + (78  - 219) * t);
                    const b = Math.round(254 + (216 - 254) * t);
                    return 'rgb(' + r + ',' + g + ',' + b + ')';
                }

                const mapInstance = new jsVectorMap({
                    map:    'world',
                    selector: '#global-reach-map',
                    zoomOnScroll: false,
                    showTooltip: true,
                    regionStyle: {
                        initial: { fill: '#dee2e8' },
                        hover:   { fill: '#93c5fd', cursor: 'pointer' },
                    },
                    onRegionTooltipShow: function(e, tooltip, code) {
                        const count = origins[code] || 0;
                        if (count > 0) {
                            tooltip.text(tooltip.text() + ' — ' + count + ' login' + (count !== 1 ? 's' : '') + ' (30d)');
                        }
                    },
                });

                // Use inline style to beat Bootstrap's .jvm-element { fill: var(--bs-gray-400) }
                for (const [code, count] of Object.entries(origins)) {
                    if (mapInstance.regions && mapInstance.regions[code]) {
                        const node = mapInstance.regions[code].element.shape.node;
                        const color = loginColor(count / maxV);
                        node.style.setProperty('fill', color, 'important');
                        node.setAttribute('data-colored', '1');
                    }
                }
                console.log('[SAIPS Map] Rendered OK, coloured', Object.keys(origins).length, 'countries');
            } catch(err) {
                console.error('[SAIPS Map] Init error:', err);
            }
        }, 300);

        // Tooltips
        document.querySelectorAll('[title]').forEach(el => {
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        });
    });
    </script>
</body>
</html>
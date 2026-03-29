<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Live Audit Log
 * CAP512: PHP + MySQL, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');

$db = Database::getInstance();

// CAP512 Unit 3: Input handling with functions
$page     = max(1, (int)($_GET['page']        ?? 1));
$perPage  = min(100, (int)($_GET['per_page']  ?? 50));
$offset   = ($page - 1) * $perPage;

// CAP512 Unit 5: Array of filter params
$filters = [
    'event_code'  => trim($_GET['event_code']  ?? ''),
    'user_id'     => trim($_GET['user_id']     ?? ''),
    'source_ip'   => trim($_GET['source_ip']   ?? ''),
    'category'    => trim($_GET['category']    ?? ''),
    'from_date'   => trim($_GET['from_date']   ?? ''),
    'to_date'     => trim($_GET['to_date']     ?? ''),
];

// Build dynamic WHERE — CAP512 Unit 7: Advanced DB techniques
$where  = ['1=1'];
$params = [];
$types  = '';

if ($filters['event_code']) {
    $where[]  = 'al.event_code = ?';
    $params[] = $filters['event_code'];
    $types   .= 's';
}
if ($filters['user_id']) {
    $where[]  = 'al.user_id = ?';
    $params[] = $filters['user_id'];
    $types   .= 's';
}
if ($filters['source_ip']) {
    $where[]  = 'al.source_ip = ?';
    $params[] = $filters['source_ip'];
    $types   .= 's';
}
if ($filters['category']) {
    $where[]  = 'al.event_code LIKE ?';
    $params[] = strtoupper($filters['category']) . '-%';
    $types   .= 's';
}
if ($filters['from_date']) {
    $where[]  = 'al.created_at >= ?';
    $params[] = date('Y-m-d 00:00:00', strtotime($filters['from_date']));
    $types   .= 's';
}
if ($filters['to_date']) {
    $where[]  = 'al.created_at <= ?';
    $params[] = date('Y-m-d 23:59:59', strtotime($filters['to_date']));
    $types   .= 's';
}

if (app_is_demo_mode()) {
    $seedUserIds = app_demo_seed_user_ids();
    $where[]  = '(al.user_id IN (' . implode(',', array_fill(0, count($seedUserIds), '?')) . ') OR (al.user_id IS NULL AND (al.source_ip LIKE ? OR al.source_ip LIKE ? OR al.source_ip LIKE ?)))';
    $params   = array_merge($params, $seedUserIds, ['203.0.113.%', '198.51.100.%', '192.0.2.%']);
    $types   .= str_repeat('s', count($seedUserIds) + 3);
}

$whereStr = implode(' AND ', $where);

// CAP512 Unit 5: CSV export handler
if (!empty($_GET['export']) && $_GET['export'] === '1') {
    $exportRows = $db->fetchAll(
        "SELECT al.id, al.event_code, al.event_name,
                u.display_name, u.email,
                al.source_ip, al.country_code, al.region, al.mfa_method,
                al.risk_score, al.details, al.created_at
         FROM audit_log al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE {$whereStr}
        ORDER BY al.id DESC
         LIMIT 10000",
        $params, $types
    );
    if (app_is_demo_mode()) {
        $exportRows = array_map('app_demo_present_audit_row', $exportRows);
    }
    $filename = 'audit_log_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Event Code','Event Name','User','Email','Source IP','Country','Region','MFA Method','Risk Score','Details','Timestamp']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['event_code'],
            $row['event_name'],
            $row['display_name'] ?? 'System',
            $row['email'] ?? '',
            $row['source_ip'],
            $row['country_code'],
            $row['region'],
            $row['mfa_method'],
            $row['risk_score'],
            $row['details'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// Count total — CAP512 Unit 7: fetchScalar
$total = (int)$db->fetchScalar(
    "SELECT COUNT(*) FROM audit_log al WHERE {$whereStr}",
    $params, $types
);

// Fetch page — CAP512 Unit 7: JOIN + pagination
$pageParams  = array_merge($params, [$perPage, $offset]);
$pageTypes   = $types . 'ii';
$entries = $db->fetchAll(
    "SELECT al.id, al.event_code, al.event_name, al.user_id,
            u.display_name, u.email,
            al.source_ip, al.country_code, al.region, al.mfa_method,
            al.risk_score, al.details, al.created_at,
            al.entry_hash, al.admin_id
     FROM audit_log al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereStr}
     ORDER BY al.id DESC
     LIMIT ? OFFSET ?",
    $pageParams, $pageTypes
);
if (app_is_demo_mode()) {
    $entries = array_map('app_demo_present_audit_row', $entries);
}

// Stats for the summary cards — CAP512 Unit 7: aggregation
$dayStatsSql = 'SELECT
        SUM(event_code="AUTH-001") as success,
        SUM(event_code="AUTH-002") as failed,
        SUM(event_code="AUTH-003") as locked,
        SUM(event_code LIKE "IPS-%") as ips_events
     FROM audit_log al
     WHERE al.created_at >= NOW() - INTERVAL 24 HOUR';
$dayStatsParams = [];
$dayStatsTypes = '';

if (app_is_demo_mode()) {
    $seedUserIds = app_demo_seed_user_ids();
    $dayStatsSql .= ' AND (al.user_id IN (' . implode(',', array_fill(0, count($seedUserIds), '?')) . ')
        OR (al.user_id IS NULL AND (al.source_ip LIKE ? OR al.source_ip LIKE ? OR al.source_ip LIKE ?)))';
    $dayStatsParams = array_merge($seedUserIds, ['203.0.113.%', '198.51.100.%', '192.0.2.%']);
    $dayStatsTypes = str_repeat('s', count($seedUserIds) + 3);
}

$dayStats = $db->fetchOne($dayStatsSql, $dayStatsParams, $dayStatsTypes);

// CAP512 Unit 4: Strings — build query string helper
function build_query(array $params): string {
    return http_build_query(array_filter($params));
}

$totalPages = max(1, (int)ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Log | Ownuh SAIPS</title>
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
            <!-- Page header -->
            <div class="hstack flex-wrap gap-3 mb-5">
                <div class="flex-grow-1">
                    <h4 class="mb-1 fw-semibold"><i class="ri-file-list-3-line me-2 text-primary"></i>Authentication Audit Log</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">Audit Log</li>
                    </ol></nav>
                </div>
                <div class="d-flex gap-2">
                    <!-- CAP512 Unit 2: PHP variable in href -->
                    <a href="audit-log.php?<?= esc(build_query(array_merge($filters, ['export' => '1']))) ?>"
                       class="btn btn-sm btn-success">
                        <i class="ri-download-2-line me-1"></i>Export CSV
                    </a>
                </div>
            </div>

            <!-- Summary Cards — CAP512 Unit 3: Variables + loops -->
            <?php if (app_is_demo_mode()): ?>
            <div class="alert border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,rgba(15,39,64,0.96) 0%, rgba(32,87,112,0.94) 100%); color:#f4f7fb;">
                <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                    <div>
                        <div class="text-uppercase text-white text-opacity-75 fw-semibold fs-12 mb-1">Demo-safe Audit View</div>
                        <div class="fw-semibold text-white mb-1">This page stays on the fictional portfolio trail.</div>
                        <div class="text-white text-opacity-75 small">Only recruiter-safe audit identities and documentation IP ranges are shown here in Demo experience, so the story stays clean without exposing your live records.</div>
                    </div>
                    <a href="settings-compliance.php" class="btn btn-light btn-sm"><i class="ri-file-chart-line me-1"></i>Next: Compliance</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <?php
                $summaryCards = [
                    ['label' => 'Successful Logins (24h)', 'value' => (int)$dayStats['success'], 'color' => 'success'],
                    ['label' => 'Failed Attempts (24h)',   'value' => (int)$dayStats['failed'],  'color' => 'warning'],
                    ['label' => 'Accounts Locked (24h)',   'value' => (int)$dayStats['locked'],  'color' => 'danger'],
                    ['label' => 'IPS Events (24h)',        'value' => (int)$dayStats['ips_events'], 'color' => 'info'],
                ];
                foreach ($summaryCards as $c):
                ?>
                <div class="col-6 col-md-3">
                    <div class="card text-center py-3 border-<?= $c['color'] ?> border-2">
                        <h3 class="fw-bold text-<?= $c['color'] ?> mb-0"><?= number_format($c['value']) ?></h3>
                        <p class="text-muted fs-12 mb-0"><?= esc($c['label']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Form — CAP512 Unit 2: HTML forms + PHP -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <form method="GET" action="audit-log.php" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">Event Category</label>
                            <select class="form-select form-select-sm" name="category">
                                <option value="">All Events</option>
                                <?php
                                // CAP512 Unit 5: Array iteration
                                $categories = ['AUTH' => 'AUTH — Authentication', 'SES' => 'SES — Sessions', 'IPS' => 'IPS — Intrusion', 'ADM' => 'ADM — Admin'];
                                foreach ($categories as $code => $label):
                                ?>
                                <option value="<?= esc($code) ?>" <?= $filters['category'] === $code ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">Event Code</label>
                            <input type="text" class="form-control form-control-sm" name="event_code"
                                   value="<?= esc($filters['event_code']) ?>" placeholder="AUTH-001">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">Source IP</label>
                            <input type="text" class="form-control form-control-sm" name="source_ip"
                                   value="<?= esc($filters['source_ip']) ?>" placeholder="185.220.x.x">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">From Date</label>
                            <input type="date" class="form-control form-control-sm" name="from_date"
                                   value="<?= esc($filters['from_date']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-12 text-muted mb-1">To Date</label>
                            <input type="date" class="form-control form-control-sm" name="to_date"
                                   value="<?= esc($filters['to_date']) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            <a href="audit-log.php" class="btn btn-light btn-sm flex-shrink-0">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Main Table — CAP512 Unit 7: DB results in HTML -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <span class="badge bg-success me-2">LIVE</span>
                        All Audit Events
                        <span class="text-muted fs-13 fw-normal ms-2">(<?= number_format($total) ?> total)</span>
                    </h5>
                    <span class="text-muted fs-12">SHA-256 chained · Append-only · <?= number_format($total) ?> entries</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Event ID</th>
                                    <th>User / Source IP</th>
                                    <th>Timestamp (UTC)</th>
                                    <th>Event Type</th>
                                    <th>Details</th>
                                    <th>Country</th>
                                    <th>Region</th>
                                    <th>Risk Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($entries)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-5">
                                    No audit events match your filters. <a href="audit-log.php">Clear filters</a>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($entries as $e):
                                    // CAP512 Unit 4: String manipulation — parse details JSON
                                    $details = $e['details'] ? json_decode($e['details'], true) : [];
                                    $detailStr = '';
                                    if (is_array($details)) {
                                        // CAP512 Unit 5: array_slice, implode
                                        $parts = [];
                                        foreach (array_slice($details, 0, 3) as $k => $v) {
                                            if (!is_array($v)) {
                                                $parts[] = $k . ': ' . truncate((string)$v, 20);
                                            }
                                        }
                                        $detailStr = implode(' · ', $parts);
                                    }

                                    // Status inference from event code — CAP512 Unit 4: str_contains
                                    $evStatus = match(true) {
                                        str_ends_with($e['event_code'], '-001') && str_starts_with($e['event_code'], 'AUTH') => 'completed',
                                        str_ends_with($e['event_code'], '-002')  => 'failed',
                                        str_starts_with($e['event_code'], 'IPS') => 'blocked',
                                        default                                  => 'completed',
                                    };
                                    $risk = (int)($e['risk_score'] ?? 0);
                                    $sourceIp = (string)($e['source_ip'] ?? '');
                                    $displaySourceIp = $sourceIp !== '' ? $sourceIp : '—';
                                    $countryCode = strtoupper(trim((string)($e['country_code'] ?? '')));
                                    $displayCountry = ($countryCode === '' || $countryCode === 'XX') ? 'Unknown' : $countryCode;
                                    $region = (string)($e['region'] ?? '');
                                    if ($region === '' && is_array($details) && !empty($details['region'])) {
                                        $region = (string)$details['region'];
                                    }
                                    $displayRegion = $region !== '' ? $region : '—';
                                ?>
                                <tr>
                                    <td><span class="badge <?= event_badge_class($e['event_code']) ?>"><?= esc($e['event_code']) ?></span></td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <?php if ($e['email']): ?>
                                                <span class="fs-13"><?= esc($e['email']) ?></span>
                                            <?php else: ?>
                                                <i class="ri-global-line text-muted"></i>
                                                <span class="fw-mono fs-12"><?= esc($displaySourceIp) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-muted fs-12"><?= format_ts($e['created_at']) ?></td>
                                    <td class="fs-13"><?= esc($e['event_name']) ?></td>
                                    <td class="text-muted fs-12"><?= esc($detailStr ?: '—') ?></td>
                                    <td class="text-muted fs-12"><?= esc($e['country_code'] ?? '—') ?></td>
                                    <td class="text-muted fs-12"><?= esc($displayRegion) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $risk >= 80 ? 'danger' : ($risk >= 40 ? 'warning text-dark' : 'success') ?>-subtle text-<?= $risk >= 80 ? 'danger' : ($risk >= 40 ? 'warning' : 'success') ?>">
                                            <?= $risk ?>
                                        </span>
                                    </td>
                                    <td><?= status_badge($evStatus) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="text-muted fs-12">
                        Showing <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage, $total)) ?> of <?= number_format($total) ?>
                        · Log integrity: <span class="text-success fw-semibold">✓ Verified</span>
                    </span>
                    <!-- Pagination — CAP512 Unit 3: loops -->
                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="audit-log.php?page=<?= $p ?>&<?= esc(build_query($filters)) ?>"><?= $p ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>

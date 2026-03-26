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
    <title>Incident Response | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-alarm-warning-line me-2 text-danger"></i>Incident Response</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item active">Incidents</li>
                    </ol></nav>
                </div>
                <a href="incidents-report.php" class="btn btn-sm btn-danger">
                    <i class="ri-add-line me-1"></i>File Incident Report
                </a>
            </div>

<?php
// CAP512 Unit 7: DB aggregation
$sevStats = $db->fetchAll(
    'SELECT severity, COUNT(*) as cnt FROM incidents
     WHERE status NOT IN ("resolved","closed") GROUP BY severity'
);
// CAP512 Unit 5: array_column
$sevMap = array_column($sevStats, 'cnt', 'severity');

$openInc     = get_incidents('open', 50);
$inProgInc   = get_incidents('in_progress', 20);
$resolvedInc = $db->fetchAll(
    'SELECT i.*, u1.email as reporter_email, u2.email as assignee_email
     FROM incidents i
     LEFT JOIN users u1 ON u1.id = i.reported_by
     LEFT JOIN users u2 ON u2.id = i.assigned_to
     WHERE i.status IN ("resolved","closed")
     ORDER BY i.resolved_at DESC LIMIT 20'
);

// CAP512 Unit 5: array_sum
$totalOpen = array_sum($sevMap);
?>

            <!-- SEV stat cards -->
            <div class="row g-3 mb-4">
                <?php
                // CAP512 Unit 5: array iteration
                $sevCards = [
                    ['sev' => 'sev1', 'label' => 'SEV-1 Critical', 'color' => 'danger',  'icon' => 'ri-error-warning-line',    'resp' => '1 hour'],
                    ['sev' => 'sev2', 'label' => 'SEV-2 High',     'color' => 'warning', 'icon' => 'ri-alarm-warning-line',    'resp' => '4 hours'],
                    ['sev' => 'sev3', 'label' => 'SEV-3 Medium',   'color' => 'info',    'icon' => 'ri-information-line',      'resp' => '24 hours'],
                    ['sev' => 'sev4', 'label' => 'SEV-4 Low',      'color' => 'success', 'icon' => 'ri-shield-check-line',     'resp' => '72 hours'],
                ];
                foreach ($sevCards as $c):
                $cnt = (int)($sevMap[$c['sev']] ?? 0);
                ?>
                <div class="col-6 col-md-3">
                    <div class="card border-<?= $c['color'] ?> border-2 border-bottom">
                        <div class="card-body py-3">
                            <div class="hstack gap-3">
                                <div class="avatar avatar-item text-<?= $c['color'] ?>"><i class="<?= $c['icon'] ?>"></i></div>
                                <div>
                                    <h3 class="fw-bold text-<?= $c['color'] ?> mb-0"><?= $cnt ?></h3>
                                    <p class="fs-12 text-muted mb-0"><?= esc($c['label']) ?></p>
                                    <p class="fs-11 text-muted mb-0">SLA: <?= $c['resp'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Open Incidents table -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Open Incidents (<?= $totalOpen ?>)</h5>
                    <span class="badge bg-danger">Requires Action</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Ref</th><th>Severity</th><th>Trigger</th><th>Detected</th><th>Status</th><th>Assignee</th><th>SLA</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($openInc)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No open incidents 🎉</td></tr>
                            <?php else: ?>
                            <?php foreach ($openInc as $inc):
                                // CAP512 Unit 4: String + time functions
                                $age = time() - strtotime($inc['detected_at']);
                                $ageStr = format_duration($age);
                                $slaMins = ['sev1'=>60,'sev2'=>240,'sev3'=>1440,'sev4'=>4320];
                                $slaMin  = $slaMins[$inc['severity']] ?? 1440;
                                $slaOk   = $age < $slaMin * 60;
                                ?>
                                <tr>
                                    <td class="fw-semibold fw-mono fs-12"><?= esc($inc['incident_ref']) ?></td>
                                    <td><?= severity_badge($inc['severity']) ?></td>
                                    <td class="fs-13"><?= esc(truncate($inc['trigger_summary'], 40)) ?></td>
                                    <td class="text-muted fs-12"><?= format_ts($inc['detected_at'], 'M d H:i') ?></td>
                                    <td><?= status_badge($inc['status']) ?></td>
                                    <td class="fs-12"><?= esc($inc['assignee_email'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge <?= $slaOk ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                            <?= $slaOk ? '✓ In SLA' : '⚠ Breach' ?> (<?= esc($ageStr) ?>)
                                        </span>
                                    </td>
                                    <td>
                                        <div class="hstack gap-1">
                                            <a href="incidents-report.php?ref=<?= esc($inc['incident_ref']) ?>" class="btn btn-light-primary icon-btn-sm" title="Edit"><i class="ri-edit-2-line"></i></a>
                                            <form method="POST" action="incidents-report.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <input type="hidden" name="ref" value="<?= esc($inc['incident_ref']) ?>">
                                                <button type="submit" class="btn btn-light-success icon-btn-sm" title="Resolve"><i class="ri-check-line"></i></button>
                                            </form>
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

            <!-- In Progress -->
            <?php if (!empty($inProgInc)): ?>
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">In Progress</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Ref</th><th>Severity</th><th>Trigger</th><th>Assignee</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($inProgInc as $inc): ?>
                            <tr>
                                <td class="fw-semibold fw-mono fs-12"><?= esc($inc['incident_ref']) ?></td>
                                <td><?= severity_badge($inc['severity']) ?></td>
                                <td class="fs-13"><?= esc(truncate($inc['trigger_summary'], 50)) ?></td>
                                <td class="fs-12"><?= esc($inc['assignee_email'] ?? '—') ?></td>
                                <td><?= status_badge($inc['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Resolved -->
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Recently Resolved</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Ref</th><th>Severity</th><th>Trigger</th><th>Resolved At</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if (empty($resolvedInc)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No resolved incidents yet.</td></tr>
                            <?php else: ?>
                            <?php foreach ($resolvedInc as $inc): ?>
                            <tr>
                                <td class="fw-semibold fw-mono fs-12"><?= esc($inc['incident_ref']) ?></td>
                                <td><?= severity_badge($inc['severity']) ?></td>
                                <td class="fs-13"><?= esc(truncate($inc['trigger_summary'], 50)) ?></td>
                                <td class="text-muted fs-12"><?= format_ts($inc['resolved_at'] ?? '', 'M d H:i') ?></td>
                                <td><?= status_badge($inc['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
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

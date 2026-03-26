<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — File Incident Report
 * CAP512: PHP forms, string handling, arrays, mysqli INSERT
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

$success = '';
$error   = '';

// Handle POST — CAP512 Unit 2: form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) die('CSRF fail');

    // Handle resolve action
    if ($_POST['action'] ?? '' === 'resolve') {
        $ref = $_POST['ref'] ?? '';
        if ($ref) {
            $db->execute("UPDATE incidents SET status='resolved', resolved_at=NOW() WHERE incident_ref=?", [$ref]);
        }
        header('Location: incidents-list.php');
        exit;
    }

    // File new incident — CAP512 Unit 4: String functions, Unit 7: INSERT
    $required = ['severity', 'trigger_summary', 'detected_at', 'description'];
    $missing  = array_filter($required, fn($f) => empty($_POST[$f]));

    if (!empty($missing)) {
        $error = 'Missing required fields: ' . implode(', ', $missing);
    } else {
        // Generate incident ref — CAP512 Unit 4: string + Unit 7: query
        $year = date('Y');
        $last = (int)$db->fetchScalar(
            "SELECT MAX(CAST(SUBSTRING_INDEX(incident_ref,'-',-1) AS UNSIGNED))
             FROM incidents WHERE incident_ref LIKE 'INC-{$year}-%'"
        );
        $ref  = sprintf('INC-%s-%03d', $year, $last + 1);

        $db->execute(
            'INSERT INTO incidents (incident_ref, severity, trigger_summary, affected_user_id,
             source_ip, detected_at, assigned_to, reported_by, description, actions_taken,
             personal_data_involved, gdpr_notification_required)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $ref,
                $_POST['severity'],
                trim($_POST['trigger_summary']),
                $_POST['affected_user_id'] ?? null,
                trim($_POST['source_ip'] ?? ''),
                date('Y-m-d H:i:s', strtotime($_POST['detected_at'])),
                $_POST['assigned_to'] ?? $user['sub'],
                $user['sub'] ?? '',
                trim($_POST['description']),
                trim($_POST['actions_taken'] ?? ''),
                (int)isset($_POST['personal_data']),
                (int)isset($_POST['gdpr_notify']),
            ]
        );
        $success = "Incident {$ref} filed successfully.";
    }
}

// Fetch users for assignee dropdown — CAP512 Unit 7 + Unit 5: arrays
$admins = $db->fetchAll(
    "SELECT id, display_name, email FROM users
     WHERE role IN ('admin','superadmin') AND status='active' AND deleted_at IS NULL
     ORDER BY display_name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>File Incident Report | Ownuh SAIPS</title>
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
                    <h4 class="mb-1 fw-semibold"><i class="ri-file-add-line me-2 text-danger"></i>File Incident Report</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="incidents-list.php">Incidents</a></li>
                        <li class="breadcrumb-item active">File Report</li>
                    </ol></nav>
                </div>
                <a href="incidents-list.php" class="btn btn-sm btn-light"><i class="ri-arrow-left-line me-1"></i>Back</a>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success d-flex gap-2 mb-4">
                <i class="ri-checkbox-circle-line flex-shrink-0"></i><span><?= esc($success) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex gap-2 mb-4">
                <i class="ri-error-warning-line flex-shrink-0"></i><span><?= esc($error) ?></span>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Incident Details</h5></div>
                        <div class="card-body">
                            <!-- CAP512 Unit 2: HTML form + PHP POST processing -->
                            <form method="POST" action="incidents-report.php">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Severity <span class="text-danger">*</span></label>
                                        <select class="form-select" name="severity" required>
                                            <option value="">Select severity…</option>
                                            <?php
                                            // CAP512 Unit 5: array iteration for select options
                                            $severities = [
                                                'sev1' => 'SEV-1 Critical — System compromise / data breach',
                                                'sev2' => 'SEV-2 High — Brute-force / account takeover',
                                                'sev3' => 'SEV-3 Medium — Suspicious activity / anomaly',
                                                'sev4' => 'SEV-4 Low — Policy violation / informational',
                                            ];
                                            foreach ($severities as $val => $label):
                                            ?>
                                            <option value="<?= $val ?>"><?= esc($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Detection Timestamp (UTC) <span class="text-danger">*</span></label>
                                        <!-- CAP512 Unit 4: date() string -->
                                        <input type="datetime-local" class="form-control" name="detected_at"
                                               value="<?= date('Y-m-d\TH:i') ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Trigger Summary <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="trigger_summary"
                                           placeholder="e.g. 47 failed logins from 185.220.101.47 in 5 minutes" required>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Affected User ID</label>
                                        <input type="text" class="form-control" name="affected_user_id"
                                               placeholder="user UUID (optional)">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Source IP</label>
                                        <input type="text" class="form-control" name="source_ip"
                                               placeholder="185.220.101.47">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Assigned To <span class="text-danger">*</span></label>
                                    <select class="form-select" name="assigned_to">
                                        <!-- CAP512 Unit 5: array_column + foreach to build select -->
                                        <?php foreach ($admins as $a): ?>
                                        <option value="<?= esc($a['id']) ?>"><?= esc($a['display_name']) ?> (<?= esc($a['email']) ?>)</option>
                                        <?php endforeach; ?>
                                        <?php if (empty($admins)): ?>
                                        <option value="">— No admins found —</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="description" rows="4"
                                              placeholder="Detailed description of the incident…" required></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Actions Taken</label>
                                    <textarea class="form-control" name="actions_taken" rows="3"
                                              placeholder="Steps already taken to investigate / contain…"></textarea>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="personal_data" id="pdCheck">
                                            <label class="form-check-label" for="pdCheck">
                                                <strong>Personal data involved</strong>
                                                <div class="text-muted fs-12">Names, emails, IP addresses, health data</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="gdpr_notify" id="gdprCheck">
                                            <label class="form-check-label" for="gdprCheck">
                                                <strong>GDPR Art. 33 notification required</strong>
                                                <div class="text-muted fs-12">72-hour supervisory authority deadline</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-danger px-4">
                                        <i class="ri-file-add-line me-2"></i>File Incident Report
                                    </button>
                                    <a href="incidents-list.php" class="btn btn-light">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar info -->
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="card-title mb-0">SLA Response Windows</h6></div>
                        <div class="card-body p-0">
                            <?php
                            // CAP512 Unit 5: Iterate array for SLA table
                            $slas = [
                                ['sev1', 'Critical', '1 hour',   'danger'],
                                ['sev2', 'High',     '4 hours',  'warning'],
                                ['sev3', 'Medium',   '24 hours', 'info'],
                                ['sev4', 'Low',      '72 hours', 'secondary'],
                            ];
                            foreach ($slas as [$code, $name, $window, $cls]):
                            ?>
                            <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?> border border-<?= $cls ?>"><?= $code ?> <?= $name ?></span>
                                <span class="fs-12 text-muted"><?= $window ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h6 class="card-title mb-0">Reporting Checklist</h6></div>
                        <div class="card-body">
                            <ul class="list-unstyled fs-13 mb-0">
                                <?php
                                // CAP512 Unit 5: array + foreach
                                $checks = [
                                    'Identify all affected systems and users',
                                    'Capture source IP and timestamps',
                                    'Preserve log evidence (audit_log IDs)',
                                    'Assess if personal data was exposed',
                                    'Check 72-hour GDPR notification requirement',
                                    'Assign to appropriate security personnel',
                                    'Document all containment actions',
                                ];
                                foreach ($checks as $i => $item):
                                ?>
                                <li class="mb-2 d-flex gap-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary flex-shrink-0"><?= $i+1 ?></span>
                                    <?= esc($item) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js" type="module"></script>
    <script>
    // Auto-check GDPR when personal data is ticked
    document.getElementById('pdCheck').addEventListener('change', function() {
        if (this.checked) document.getElementById('gdprCheck').checked = true;
    });
    </script>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/bootstrap.php';

use SAIPS\Services\AIService;
use SAIPS\Services\ExecutiveReportManager;

$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();
$reportManager = new ExecutiveReportManager($db);

$checks = get_compliance_checks();
$passed = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
$action = count(array_filter($checks, fn($c) => $c['status'] === 'action'));
$fail   = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
$rec    = count(array_filter($checks, fn($c) => $c['status'] === 'recommended'));
$score  = count($checks) > 0 ? (int)round(($passed / count($checks)) * 100) : 0;

$postureSnapshot = get_security_posture_snapshot();
$execReportResult = null;
$execReportWarning = null;
$execReportError = null;
$generatedReport = null;
$settingsMessage = null;
$reportSettings = $reportManager->getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $execReportError = 'Security validation failed. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'generate_exec_report') {
        $aiService = new AIService();
        $execReportResult = $aiService->generateExecutivePostureReport($postureSnapshot);
        $execReportWarning = $execReportResult['warning'] ?? null;
        $generatedReport = $execReportResult['report'] ?? null;
        if (is_array($generatedReport)) {
            $reportManager->saveGeneratedReport($generatedReport, $postureSnapshot, [
                'generated_by' => $user['id'] ?? null,
                'delivery_channel' => 'manual',
                'report_format' => 'onscreen',
                'provider' => $execReportResult['provider'] ?? 'report',
                'model' => $execReportResult['model'] ?? null,
            ]);
        }
    } elseif (($_POST['action'] ?? '') === 'save_exec_report_settings') {
        $reportManager->saveSettings([
            'email_enabled' => isset($_POST['email_enabled']),
            'cadence' => $_POST['cadence'] ?? 'weekly',
            'attach_format' => $_POST['attach_format'] ?? 'none',
        ], $user['id'] ?? null);
        $reportSettings = $reportManager->getSettings();
        $settingsMessage = 'Executive report delivery settings updated.';
    }
}

$reportHistory = $reportManager->getHistory(8);
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Compliance Checklist | Ownuh SAIPS'; $authLayout = false; include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>

    <main class="app-wrapper">
        <div class="app-container">
            <div class="hstack flex-wrap gap-3 mb-5">
                <div class="flex-grow-1">
                    <h4 class="mb-1 fw-semibold"><i class="ri-shield-check-line me-2 text-success"></i>Compliance Checklist</h4>
                    <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item">System Settings</li>
                        <li class="breadcrumb-item active">Compliance</li>
                    </ol></nav>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card text-center py-3 border-<?= $score >= 90 ? 'success' : 'warning' ?> border-2"><h2 class="fw-bold text-<?= $score >= 90 ? 'success' : 'warning' ?> mb-0"><?= $score ?>%</h2><p class="text-muted fs-12 mb-0">Compliance Score</p></div></div>
                <div class="col-md-3"><div class="card text-center py-3"><h3 class="fw-bold text-success mb-0"><?= $passed ?></h3><p class="text-muted fs-12 mb-0">Passed</p></div></div>
                <div class="col-md-3"><div class="card text-center py-3 <?= $action + $fail > 0 ? 'border-warning border-2' : '' ?>"><h3 class="fw-bold text-warning mb-0"><?= $action + $fail ?></h3><p class="text-muted fs-12 mb-0">Requires Action</p></div></div>
                <div class="col-md-3"><div class="card text-center py-3"><h3 class="fw-bold text-info mb-0"><?= $rec ?></h3><p class="text-muted fs-12 mb-0">Recommended</p></div></div>
            </div>

            <div class="card mb-4 border-primary border-opacity-25">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-1">AI Executive Report</h5>
                        <p class="text-muted fs-12 mb-0">Generate a leadership-ready summary of organisation posture from live SAIPS metrics, with deterministic fallback if external AI access is unavailable.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="POST" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                        <input type="hidden" name="action" value="generate_exec_report">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ri-ai-generate me-1"></i>Generate Executive Report
                        </button>
                    </form>
                        <a href="executive-report-export.php?format=html" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-file-code-line me-1"></i>Export HTML
                        </a>
                        <a href="executive-report-export.php?format=pdf" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-file-pdf-line me-1"></i>Export PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="rounded-3 bg-light p-3 h-100"><div class="fs-12 text-muted mb-1">Security Score</div><div class="fw-semibold fs-4"><?= esc((string)$postureSnapshot['security_score']) ?></div></div></div>
                        <div class="col-md-3"><div class="rounded-3 bg-light p-3 h-100"><div class="fs-12 text-muted mb-1">MFA Coverage</div><div class="fw-semibold fs-4"><?= esc((string)$postureSnapshot['users']['mfa_coverage']) ?>%</div></div></div>
                        <div class="col-md-3"><div class="rounded-3 bg-light p-3 h-100"><div class="fs-12 text-muted mb-1">Open Incidents</div><div class="fw-semibold fs-4"><?= esc((string)$postureSnapshot['incidents']['open_total']) ?></div></div></div>
                        <div class="col-md-3"><div class="rounded-3 bg-light p-3 h-100"><div class="fs-12 text-muted mb-1">High-Risk Events (24h)</div><div class="fw-semibold fs-4"><?= esc((string)$postureSnapshot['auth']['high_risk_events_24h']) ?></div></div></div>
                    </div>

                    <?php if ($execReportError): ?>
                        <div class="alert alert-danger mb-0"><?= esc($execReportError) ?></div>
                    <?php elseif ($generatedReport): ?>
                        <?php if ($execReportWarning): ?>
                            <div class="alert alert-warning"><?= esc($execReportWarning) ?></div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary">
                                <?= esc(ucfirst((string)($execReportResult['provider'] ?? 'report'))) ?>
                            </span>
                            <?php if (!empty($execReportResult['model'])): ?>
                                <span class="badge bg-light text-dark border"><?= esc((string)$execReportResult['model']) ?></span>
                            <?php endif; ?>
                            <span class="text-muted fs-12">Generated from live posture data at <?= esc(date('Y-m-d H:i')) ?></span>
                        </div>

                        <div class="rounded-3 border p-4">
                            <div class="mb-4">
                                <h4 class="fw-semibold mb-1"><?= esc((string)($generatedReport['report_title'] ?? 'Executive Security Posture Report')) ?></h4>
                                <div class="text-muted fs-12">Overall posture: <?= esc((string)($generatedReport['overall_posture'] ?? 'N/A')) ?></div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-semibold">Executive Summary</h6>
                                <p class="text-muted mb-0"><?= esc((string)($generatedReport['executive_summary'] ?? '')) ?></p>
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <h6 class="fw-semibold">Board Takeaways</h6>
                                    <ul class="mb-0">
                                        <?php foreach (($generatedReport['board_takeaways'] ?? []) as $item): ?>
                                            <li class="mb-2 text-muted"><?= esc((string)$item) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="col-lg-6">
                                    <h6 class="fw-semibold">Strengths</h6>
                                    <ul class="mb-0">
                                        <?php foreach (($generatedReport['strengths'] ?? []) as $item): ?>
                                            <li class="mb-2 text-muted"><?= esc((string)$item) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6 class="fw-semibold">Priority Risks</h6>
                                <div class="row g-3">
                                    <?php foreach (($generatedReport['priority_risks'] ?? []) as $risk): ?>
                                        <div class="col-md-6">
                                            <div class="border rounded-3 p-3 h-100">
                                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                    <div class="fw-semibold"><?= esc((string)($risk['title'] ?? 'Risk')) ?></div>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning"><?= esc((string)($risk['priority'] ?? 'medium')) ?></span>
                                                </div>
                                                <p class="text-muted fs-13 mb-2"><?= esc((string)($risk['impact'] ?? '')) ?></p>
                                                <div class="fs-13"><span class="fw-semibold">Recommendation:</span> <?= esc((string)($risk['recommendation'] ?? '')) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="row g-4 mt-1">
                                <div class="col-lg-5">
                                    <h6 class="fw-semibold">Key Metrics</h6>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (($generatedReport['key_metrics'] ?? []) as $metric): ?>
                                            <div class="list-group-item px-0 d-flex align-items-center justify-content-between">
                                                <span class="text-muted"><?= esc((string)($metric['label'] ?? 'Metric')) ?></span>
                                                <span class="fw-semibold"><?= esc((string)($metric['value'] ?? '')) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-lg-7">
                                    <h6 class="fw-semibold">Next 30 Days</h6>
                                    <ul class="mb-3">
                                        <?php foreach (($generatedReport['next_30_days'] ?? []) as $item): ?>
                                            <li class="mb-2 text-muted"><?= esc((string)$item) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <h6 class="fw-semibold">Compliance Outlook</h6>
                                    <p class="text-muted mb-0"><?= esc((string)($generatedReport['compliance_outlook'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-muted fs-13">
                            This report uses live compliance, incident, IPS, audit, and authentication posture to produce a board-style summary. If an OpenAI-compatible provider is not configured, unavailable, or out of quota, the app falls back to a deterministic local summary so the workflow still works.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Executive Report Delivery</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($settingsMessage): ?>
                                <div class="alert alert-success"><?= esc($settingsMessage) ?></div>
                            <?php endif; ?>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                <input type="hidden" name="action" value="save_exec_report_settings">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" <?= !empty($reportSettings['email_enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_enabled">Email scheduled executive reports to admins</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cadence</label>
                                    <select class="form-select" name="cadence">
                                        <option value="weekly" <?= ($reportSettings['cadence'] ?? 'weekly') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        <option value="monthly" <?= ($reportSettings['cadence'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Attachment Format</label>
                                    <select class="form-select" name="attach_format">
                                        <option value="none" <?= ($reportSettings['attach_format'] ?? 'none') === 'none' ? 'selected' : '' ?>>No attachment</option>
                                        <option value="html" <?= ($reportSettings['attach_format'] ?? '') === 'html' ? 'selected' : '' ?>>Attach HTML</option>
                                        <option value="pdf" <?= ($reportSettings['attach_format'] ?? '') === 'pdf' ? 'selected' : '' ?>>Attach PDF</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="fs-12 text-muted">
                                        The scheduler reads these settings when <code>backend/scripts/send-weekly-executive-report.php</code> runs.
                                        <?php if (!empty($reportSettings['last_sent_at'])): ?>
                                            Last scheduled send: <?= esc((string)$reportSettings['last_sent_at']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                        <i class="ri-save-line me-1"></i>Save Delivery Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Executive Report History</h5>
                            <span class="text-muted fs-12">Latest <?= count($reportHistory) ?> entries</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Generated</th>
                                            <th>Channel</th>
                                            <th>Format</th>
                                            <th>Provider</th>
                                            <th>By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if ($reportHistory === []): ?>
                                        <tr><td colspan="5" class="text-muted text-center py-4">No saved executive reports yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reportHistory as $entry): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold fs-13"><?= esc((string)($entry['report_title'] ?? 'Executive Security Posture Report')) ?></div>
                                                    <div class="text-muted fs-12"><?= esc((string)($entry['generated_at'] ?? '')) ?></div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)($entry['delivery_channel'] ?? 'manual')) ?></span></td>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)($entry['report_format'] ?? 'onscreen')) ?></span></td>
                                                <td>
                                                    <div class="fs-13"><?= esc((string)($entry['provider'] ?? 'report')) ?></div>
                                                    <?php if (!empty($entry['model'])): ?>
                                                        <div class="text-muted fs-12"><?= esc((string)$entry['model']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php $reportIdentity = app_demo_safe_identity((string)($entry['email'] ?? ''), (string)($entry['display_name'] ?? ''), 'admin'); ?>
                                                    <div class="fs-13"><?= esc($reportIdentity['display_name'] !== '' ? $reportIdentity['display_name'] : 'System') ?></div>
                                                    <?php if (!empty($entry['email_recipients'])): ?>
                                                        <div class="text-muted fs-12"><?= esc(app_demo_safe_text((string)$entry['email_recipients'])) ?></div>
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
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Compliance Controls (<?= count($checks) ?> checks · Live DB)</h5>
                    <span class="text-muted fs-12">Next review: <?= date('Y-m-d', strtotime('+90 days')) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>ID</th><th>Standard</th><th>Control</th><th>Detail</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($checks as $c):
                                $badgeMap = ['pass'=>'success','action'=>'warning','fail'=>'danger','recommended'=>'info'];
                                $iconMap  = ['pass'=>'ri-checkbox-circle-line','action'=>'ri-error-warning-line','fail'=>'ri-close-circle-line','recommended'=>'ri-information-line'];
                                $cls  = $badgeMap[$c['status']] ?? 'secondary';
                                $icon = $iconMap[$c['status']] ?? 'ri-question-line';
                                ?>
                                <tr>
                                    <td class="fw-mono fs-12 fw-semibold"><?= esc($c['id']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary fs-11"><?= esc($c['std']) ?></span></td>
                                    <td class="fs-13"><?= esc($c['control']) ?></td>
                                    <td class="text-muted fs-12"><?= esc($c['detail']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?> border border-<?= $cls ?>">
                                            <i class="<?= $icon ?> me-1"></i><?= esc(ucfirst($c['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer fs-12 text-muted">
                    Live compliance checks against the SAIPS database. Score updates automatically on each page load.
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/backend/partials/footer-scripts.php'; ?>
</body>
</html>

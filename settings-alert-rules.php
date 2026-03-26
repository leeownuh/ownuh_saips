<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Alert Rules Management (live from DB)
 * CAP512: PHP + MySQL, OOP, arrays, string functions, control flow
 */
require_once __DIR__ . '/backend/bootstrap.php';
$user = require_auth('admin');
$db   = Database::getInstance();
$csrf = csrf_token();

$success = '';
$error   = '';

// POST: add / toggle / delete alert rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name       = trim($_POST['rule_name']     ?? '');
        $eventType  = trim($_POST['event_type']    ?? '');
        $channel    = $_POST['channel']            ?? 'email';
        $threshold  = (int)($_POST['threshold']    ?? 1);
        $window     = (int)($_POST['window_min']   ?? 5);
        $dest       = trim($_POST['destination']   ?? '');

        if ($name && $eventType && $dest) {
            try {
                $db->execute(
                    'INSERT INTO alert_rules (id, rule_name, event_type, channel, threshold_count,
                     window_minutes, destination, created_by)
                     VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $eventType, $channel, $threshold, $window, $dest, $user['id']]
                );
                $success = "Alert rule '{$name}' created.";
            } catch (\Exception $e) {
                $error = 'Could not save rule: ' . $e->getMessage();
            }
        } else {
            $error = 'Rule name, event type, and destination are required.';
        }
    } elseif ($action === 'toggle') {
        $ruleId = $_POST['rule_id'] ?? '';
        if ($ruleId) {
            try {
                $db->execute('UPDATE alert_rules SET is_active = NOT is_active WHERE id = ?', [$ruleId]);
                $success = 'Alert rule toggled.';
            } catch (\Exception $e) {
                $error = 'Toggle failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $ruleId = $_POST['rule_id'] ?? '';
        if ($ruleId) {
            try {
                $db->execute('DELETE FROM alert_rules WHERE id = ?', [$ruleId]);
                $success = 'Alert rule deleted.';
            } catch (\Exception $e) {
                $error = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

// CAP512 Unit 7: load rules
$alertRules = [];
try {
    $alertRules = $db->fetchAll(
        'SELECT ar.id, ar.rule_name, ar.event_type, ar.channel, ar.threshold_count,
                ar.window_minutes, ar.destination, ar.is_active, ar.created_at,
                u.display_name as created_by_name
         FROM alert_rules ar
         LEFT JOIN users u ON u.id = ar.created_by
         ORDER BY ar.is_active DESC, ar.created_at DESC'
    );
} catch (\Exception $e) {
    // table may not exist yet
    $alertRules = [];
}

$activeCount = count(array_filter($alertRules, fn($r) => (int)$r['is_active'] === 1));

// Available event types (from audit log codes)
$eventTypes = [
    'AUTH-001' => 'Successful Login',
    'AUTH-002' => 'Failed Login',
    'AUTH-003' => 'Account Locked',
    'AUTH-004' => 'MFA Challenge',
    'AUTH-005' => 'MFA Failed',
    'IPS-001'  => 'IP Blocked',
    'IPS-002'  => 'Brute-Force Detected',
    'IPS-003'  => 'Geo Block Triggered',
    'USR-001'  => 'User Created',
    'USR-002'  => 'User Deleted',
    'USR-003'  => 'Role Changed',
    'SEC-001'  => 'Password Changed',
    'SEC-002'  => 'Suspicious Activity',
    'INC-001'  => 'Incident Created',
    'INC-002'  => 'Incident Escalated',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Alert Rules | Ownuh SAIPS</title>
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
                <h4 class="mb-1 fw-semibold"><i class="ri-notification-3-line me-2 text-warning"></i>Alert Rules</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                    <li class="breadcrumb-item active">Alert Rules</li>
                </ol></nav>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                <i class="ri-add-line me-1"></i>New Alert Rule
            </button>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i><?= esc($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="ri-error-warning-line me-2"></i><?= esc($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="fs-2 fw-bold text-primary"><?= count($alertRules) ?></div>
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
                    <div class="fs-2 fw-bold text-secondary"><?= count($alertRules) - $activeCount ?></div>
                    <div class="text-muted small">Disabled Rules</div>
                </div>
            </div>
        </div>

        <!-- Rules Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 py-3">
                <h5 class="mb-0 fw-semibold">Configured Alert Rules</h5>
                <p class="text-muted small mb-0">Rules trigger webhooks, emails, or Slack notifications when thresholds are met.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rule Name</th>
                                <th>Event Type</th>
                                <th>Threshold</th>
                                <th>Window</th>
                                <th>Channel</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($alertRules)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="ri-notification-off-line fs-3 d-block mb-2"></i>
                                    No alert rules configured. Create one to start receiving security notifications.
                                </td>
                            </tr>
                        <?php else: foreach ($alertRules as $rule): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($rule['rule_name']) ?></td>
                                <td>
                                    <code class="small"><?= esc($rule['event_type']) ?></code>
                                    <div class="text-muted" style="font-size:11px"><?= esc($eventTypes[$rule['event_type']] ?? '') ?></div>
                                </td>
                                <td><?= (int)$rule['threshold_count'] ?> events</td>
                                <td><?= (int)$rule['window_minutes'] ?> min</td>
                                <td>
                                    <?php
                                    $channelBadge = match($rule['channel']) {
                                        'email'   => ['bg-primary-subtle text-primary',   'ri-mail-line'],
                                        'slack'   => ['bg-success-subtle text-success',   'ri-slack-line'],
                                        'webhook' => ['bg-warning-subtle text-warning',   'ri-links-line'],
                                        'sms'     => ['bg-info-subtle text-info',         'ri-phone-line'],
                                        default   => ['bg-secondary-subtle text-secondary','ri-notification-line'],
                                    };
                                    ?>
                                    <span class="badge <?= $channelBadge[0] ?>">
                                        <i class="<?= $channelBadge[1] ?> me-1"></i><?= esc(ucfirst($rule['channel'])) ?>
                                    </span>
                                </td>
                                <td class="text-muted small text-truncate" style="max-width:160px" title="<?= esc($rule['destination']) ?>">
                                    <?= esc($rule['destination']) ?>
                                </td>
                                <td>
                                    <?php if ($rule['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="rule_id" value="<?= esc($rule['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Toggle">
                                            <i class="ri-toggle-line"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Delete this alert rule?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="rule_id" value="<?= esc($rule['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
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

<!-- Add Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-notification-3-line me-2"></i>New Alert Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Rule Name</label>
                            <input type="text" name="rule_name" class="form-control" placeholder="e.g. Brute-Force Alert" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Event Type</label>
                            <select name="event_type" class="form-select" required>
                                <option value="">Select event…</option>
                                <?php foreach ($eventTypes as $code => $label): ?>
                                <option value="<?= esc($code) ?>"><?= esc($code) ?> — <?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Threshold (events)</label>
                            <input type="number" name="threshold" class="form-control" min="1" value="5" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Window (minutes)</label>
                            <input type="number" name="window_min" class="form-control" min="1" value="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select" id="channelSelect">
                                <option value="email">Email</option>
                                <option value="slack">Slack</option>
                                <option value="webhook">Webhook</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" id="destLabel">Destination (email address)</label>
                            <input type="text" name="destination" class="form-control" id="destInput"
                                   placeholder="security@yourcompany.com" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Rule</button>
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
<script>
// Update destination placeholder based on channel
const channelSelect = document.getElementById('channelSelect');
const destLabel     = document.getElementById('destLabel');
const destInput     = document.getElementById('destInput');
const hints = {
    email:   ['Destination (email address)',        'security@yourcompany.com'],
    slack:   ['Destination (Slack webhook URL)',     'https://hooks.slack.com/services/...'],
    webhook: ['Destination (webhook URL)',           'https://your-siem.example.com/webhook'],
    sms:     ['Destination (E.164 phone number)',    '+61412345678'],
};
channelSelect.addEventListener('change', () => {
    const [label, placeholder] = hints[channelSelect.value] ?? hints.email;
    destLabel.textContent = label;
    destInput.placeholder = placeholder;
});
</script>
</body>
</html>

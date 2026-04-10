<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/bootstrap.php';

use SAIPS\Services\MLService;

function attribution_filters(array $source): array {
    $from = trim((string)($source['from_date'] ?? date('Y-m-d', strtotime('-120 days'))));
    $to = trim((string)($source['to_date'] ?? date('Y-m-d')));
    $limit = max(200, min(2000, (int)($source['limit'] ?? 1200)));
    $withLlm = ($source['with_llm'] ?? '') === '1';
    return ['from_date' => $from, 'to_date' => $to, 'limit' => $limit, 'with_llm' => $withLlm];
}

function attribution_case_by_id(array $cases, string $caseId): ?array {
    foreach ($cases as $case) {
        if (($case['case_id'] ?? '') === $caseId) {
            return $case;
        }
    }
    return null;
}

function attribution_safe_text(?string $value): string {
    return app_demo_safe_text((string)$value);
}

require_once __DIR__ . '/backend/attribution_visuals.php';

function attribution_present_value(mixed $value): string {
    if (is_array($value)) {
        $parts = array_map(
            static fn(mixed $item): string => is_scalar($item) || $item === null
                ? (string)$item
                : (json_encode($item, JSON_UNESCAPED_SLASHES) ?: '[complex]'),
            $value
        );
        return attribution_safe_text(implode(', ', array_filter($parts, static fn(string $item): bool => $item !== '')));
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if ($value === null || $value === '') {
        return '—';
    }

    return attribution_safe_text((string)$value);
}

function attribution_feedback_file(): string {
    return __DIR__ . '/backend/ml_service/reports/analyst_feedback.json';
}

function attribution_feedback_labels(): array {
    return ['true_positive', 'false_positive', 'needs_review'];
}

function attribution_load_feedback_map(): array {
    $path = attribution_feedback_file();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $cases = $decoded['cases'] ?? [];
    return is_array($cases) ? $cases : [];
}

function attribution_save_feedback_entry(string $caseId, string $label, string $note, string $analyst): bool {
    $label = strtolower(trim($label));
    if (!in_array($label, attribution_feedback_labels(), true)) {
        return false;
    }

    $path = attribution_feedback_file();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $payload = ['cases' => []];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
                if (!isset($payload['cases']) || !is_array($payload['cases'])) {
                    $payload['cases'] = [];
                }
            }
        }
    }

    $payload['cases'][$caseId] = [
        'label' => $label,
        'note' => trim($note),
        'analyst' => trim($analyst) !== '' ? trim($analyst) : 'analyst',
        'updated_at' => date('c'),
    ];
    $payload['updated_at'] = date('c');
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function attribution_feedback_badge_class(string $label): string {
    return match (strtolower($label)) {
        'true_positive' => 'success',
        'false_positive' => 'danger',
        default => 'warning',
    };
}

function attribution_feedback_label_text(string $label): string {
    return ucwords(str_replace('_', ' ', strtolower($label)));
}

function next_incident_ref(Database $db): string {
    $year = date('Y');
    $last = (int)$db->fetchScalar(
        "SELECT MAX(CAST(SUBSTRING_INDEX(incident_ref,'-',-1) AS UNSIGNED)) FROM incidents WHERE incident_ref LIKE ?",
        ["INC-{$year}-%"]
    );
    return sprintf('INC-%s-%03d', $year, $last + 1);
}

function create_attribution_incident(Database $db, array $case, string $operatorId): string {
    $incidentRef = next_incident_ref($db);
    $affectedUserId = (($case['entity_type'] ?? '') === 'user' && str_starts_with((string)($case['entity_id'] ?? ''), 'usr-'))
        ? (string)$case['entity_id']
        : null;
    $sourceIp = ($case['entity_type'] ?? '') === 'ip'
        ? (string)($case['entity_id'] ?? '')
        : (string)(($case['related_entities']['ips'][0] ?? '') ?: '');
    $description = trim(
        ($case['summary'] ?? 'Attribution-driven detection') . "\n\n" .
        ($case['explanation'] ?? '') . "\n\nRecommended actions:\n- " .
        implode("\n- ", $case['recommended_actions'] ?? [])
    );
    $actionsTaken = implode('; ', $case['recommended_actions'] ?? []);
    $relatedAuditEntries = json_encode(
        array_values(array_filter(array_map(static fn(array $event): int => (int)($event['id'] ?? 0), $case['supporting_events'] ?? []))),
        JSON_UNESCAPED_SLASHES
    );

    $db->execute(
        'INSERT INTO incidents (
            id, incident_ref, severity, status, trigger_summary, affected_user_id, source_ip,
            detected_at, acknowledged_at, resolved_at, assigned_to, reported_by,
            description, actions_taken, personal_data_involved, gdpr_notification_required,
            gdpr_notified_at, related_audit_entries, created_at, updated_at
        ) VALUES (
            UUID(), ?, ?, ?, ?, ?, ?, NOW(), NULL, NULL, ?, ?,
            ?, ?, ?, ?, NULL, ?, NOW(), NOW()
        )',
        [
            $incidentRef,
            $case['severity'] ?? 'sev3',
            'open',
            substr((string)($case['summary'] ?? 'ML attribution case'), 0, 255),
            $affectedUserId,
            $sourceIp !== '' ? $sourceIp : null,
            $operatorId,
            $operatorId,
            $description,
            $actionsTaken,
            0,
            0,
            $relatedAuditEntries ?: json_encode([], JSON_UNESCAPED_SLASHES),
        ]
    );

    return $incidentRef;
}

$user = require_auth('admin');
$db = Database::getInstance();
$csrf = csrf_token();
$filters = attribution_filters($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
$dateFrom = $filters['from_date'] !== '' ? $filters['from_date'] . ' 00:00:00' : null;
$dateTo = $filters['to_date'] !== '' ? $filters['to_date'] . ' 23:59:59' : null;
$analysis = (new MLService($db))->analyzeAttackAttribution($dateFrom, $dateTo, $filters['limit'], $filters['with_llm']);
$success = null;
$error = null;
$feedbackMap = attribution_load_feedback_map();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_attribution_incident') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please refresh and try again.';
    } elseif (($analysis['status'] ?? '') !== 'success') {
        $error = 'Attribution results were unavailable, so the incident could not be created.';
    } else {
        $case = attribution_case_by_id($analysis['cases'] ?? [], (string)($_POST['case_id'] ?? ''));
        if ($case === null) {
            $error = 'The selected attribution case could not be found.';
        } else {
            $success = 'Incident ' . create_attribution_incident($db, $case, (string)($user['sub'] ?? '')) . ' created from attribution case.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_case_feedback') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please refresh and try again.';
    } elseif (($analysis['status'] ?? '') !== 'success') {
        $error = 'Attribution results were unavailable, so feedback could not be saved.';
    } else {
        $caseId = trim((string)($_POST['case_id'] ?? ''));
        $label = trim((string)($_POST['feedback_label'] ?? 'needs_review'));
        $note = trim((string)($_POST['feedback_note'] ?? ''));
        $case = attribution_case_by_id($analysis['cases'] ?? [], $caseId);

        if ($case === null) {
            $error = 'The selected attribution case could not be found.';
        } elseif (!in_array(strtolower($label), attribution_feedback_labels(), true)) {
            $error = 'Feedback label is invalid.';
        } else {
            $analyst = (string)($user['email'] ?? ($user['sub'] ?? 'analyst'));
            if (attribution_save_feedback_entry($caseId, $label, $note, $analyst)) {
                $success = 'Feedback saved for case ' . $caseId . '.';
                $feedbackMap = attribution_load_feedback_map();
            } else {
                $error = 'Unable to save feedback at this time.';
            }
        }
    }
}

$cases = $analysis['cases'] ?? [];
$summary = $analysis['summary'] ?? [];
$llm = $analysis['llm'] ?? [];
$networkSnapshot = attribution_network_snapshot($cases);
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Attack Attribution | Ownuh SAIPS'; $authLayout = false; include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<style>
    .attribution-graph-svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .attribution-node-dot {
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 999px;
        display: inline-block;
    }

    .attribution-node-dot.user { background: #0d6efd; }
    .attribution-node-dot.ip { background: #dc3545; }
    .attribution-node-dot.device { background: #f59e0b; }
    .attribution-node-dot.incident { background: #198754; }
</style>
<main class="app-wrapper">
    <div class="app-container">
        <div class="hstack flex-wrap gap-3 mb-5">
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-semibold"><i class="ri-node-tree me-2 text-danger"></i>Attack Attribution</h4>
                <nav><ol class="breadcrumb breadcrumb-arrow mb-0"><li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li><li class="breadcrumb-item active">Attack Attribution</li></ol></nav>
            </div>
            <a href="incidents-list.php" class="btn btn-sm btn-light"><i class="ri-alarm-warning-line me-1"></i>Incident Queue</a>
        </div>

        <?php if (app_is_demo_mode()): ?>
        <div class="alert border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,rgba(15,39,64,0.96) 0%, rgba(21,94,99,0.94) 100%); color:#f4f7fb;">
            <div class="fw-semibold mb-1">Demo-safe attribution lane</div>
            <div class="small text-white text-opacity-75">The page fuses audit, IP, device, and incident context into case cards while keeping identities masked in Demo experience.</div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?><div class="alert alert-success mb-4"><?= esc($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger mb-4"><?= esc($error) ?></div><?php endif; ?>
        <?php if (($analysis['status'] ?? '') !== 'success'): ?>
            <div class="alert alert-warning mb-4"><?= esc((string)($analysis['message'] ?? 'Attribution analysis was unavailable.')) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" value="<?= esc($filters['from_date']) ?>"></div>
                    <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" value="<?= esc($filters['to_date']) ?>"></div>
                    <div class="col-md-2"><label class="form-label">Event Limit</label><input type="number" min="200" max="2000" step="100" class="form-control" name="limit" value="<?= esc((string)$filters['limit']) ?>"></div>
                    <div class="col-md-2"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="with_llm" name="with_llm" value="1" <?= $filters['with_llm'] ? 'checked' : '' ?>><label class="form-check-label" for="with_llm">LLM mode</label></div></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-danger w-100"><i class="ri-search-line me-1"></i>Refresh Cases</button></div>
                </form>
                <div class="text-muted fs-12 mt-3">Signal engines: anomaly <code><?= esc((string)($analysis['signals']['anomaly_engine'] ?? 'n/a')) ?></code>, attack <code><?= esc((string)($analysis['signals']['attack_engine'] ?? 'n/a')) ?></code>, entity <code><?= esc((string)($analysis['signals']['entity_engine'] ?? 'n/a')) ?></code>.</div>
                <div class="text-muted fs-12 mt-1">`LLM mode` is optional. The core attribution pipeline still works with deterministic local explanations when external provider access or quota is unavailable.</div>
                <?php if (($llm['requested'] ?? false) === true): ?>
                    <div class="mt-3 p-3 rounded-3 border bg-light-subtle">
                        <div class="fw-semibold mb-1">Attribution narrative mode</div>
                        <div class="small text-muted">
                            <?= ($llm['applied'] ?? false)
                                ? esc(sprintf(
                                    'Structured %s narratives were added to %d case(s)%s.',
                                    (string)($llm['provider'] ?? 'LLM'),
                                    (int)($llm['cases_enriched'] ?? 0),
                                    !empty($llm['model']) ? ' using ' . (string)$llm['model'] : ''
                                ))
                                : esc('Structured LLM narratives were requested, but the dashboard is currently showing deterministic local explanations only.') ?>
                        </div>
                        <?php if (!empty($llm['warning'])): ?><div class="small text-muted mt-2"><?= esc((string)$llm['warning']) ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $cards = [
                ['label' => 'Cases Surfaced', 'value' => (int)($summary['total_cases'] ?? 0), 'color' => 'danger'],
                ['label' => 'Top Case Risk', 'value' => (int)($summary['top_case_risk'] ?? 0), 'color' => 'warning'],
                ['label' => 'Linked Incidents', 'value' => (int)($summary['linked_incidents'] ?? 0), 'color' => 'info'],
                ['label' => 'Avg Case Risk', 'value' => (string)($summary['avg_case_risk'] ?? 0), 'color' => 'primary'],
            ];
            foreach ($cards as $card):
            ?>
            <div class="col-6 col-md-3"><div class="card text-center py-3 border-<?= $card['color'] ?> border-2"><h3 class="fw-bold text-<?= $card['color'] ?> mb-0"><?= esc((string)$card['value']) ?></h3><p class="text-muted fs-12 mb-0"><?= esc($card['label']) ?></p></div></div>
            <?php endforeach; ?>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <div class="card-header border-0 text-white" style="background:linear-gradient(135deg,#0f2740 0%,#155e63 100%);">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="text-uppercase text-white text-opacity-75 fw-semibold fs-12 mb-1">Relationship View</div>
                        <h5 class="card-title text-white mb-1">Graph-based linking across users, devices, IPs, and incidents</h5>
                        <p class="mb-0 text-white text-opacity-75 fs-12">Use this view to spot infrastructure reuse and connected investigation context before the pattern becomes obvious in the table.</p>
                    </div>
                    <span class="badge rounded-pill text-bg-light text-dark"><?= esc((string)($networkSnapshot['cases'] ?? 0)) ?> surfaced case(s)</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5">
                        <h6 class="fw-semibold mb-2">Investigation surface at a glance</h6>
                        <p class="text-muted mb-4">The attribution lane correlates identities, endpoints, addresses, and open incidents so analysts can move from a single alert to the connected story.</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-light text-dark border px-3 py-2"><?= esc((string)($networkSnapshot['users'] ?? 0)) ?> users</span>
                            <span class="badge bg-light text-dark border px-3 py-2"><?= esc((string)($networkSnapshot['ips'] ?? 0)) ?> IPs</span>
                            <span class="badge bg-light text-dark border px-3 py-2"><?= esc((string)($networkSnapshot['devices'] ?? 0)) ?> devices</span>
                            <span class="badge bg-light text-dark border px-3 py-2"><?= esc((string)($networkSnapshot['incidents'] ?? 0)) ?> incidents</span>
                        </div>
                        <div class="small text-muted">Top active signal: <span class="fw-semibold text-dark"><?= esc(str_replace('_', ' ', strtolower((string)($networkSnapshot['top_attack'] ?? 'ANOMALOUS_BEHAVIOR')))) ?></span> at risk <?= esc((string)($networkSnapshot['top_risk'] ?? 0)) ?>/100.</div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card card-hover overflow-hidden border-primary border-2 border-opacity-25">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <span class="text-uppercase text-muted fw-semibold fs-12">Current Investigation Surface</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary">Linked entities</span>
                                </div>
                                <?= attribution_render_summary_graph($networkSnapshot) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion" id="attributionCases">
            <?php if ($cases === []): ?>
                <div class="card"><div class="card-body text-muted">No attribution cases crossed the current risk threshold for this window.</div></div>
            <?php else: ?>
                <?php foreach ($cases as $index => $case): ?>
                    <?php
                    $entityLabel = attribution_case_entity_label($case);
                    $attackLabel = str_replace('_', ' ', (string)($case['attack_label'] ?? ''));
                    $collapseId = 'case-' . $index;
                    $linkCounts = attribution_case_link_counts($case);
                    $feedbackEntry = $feedbackMap[(string)($case['case_id'] ?? '')] ?? null;
                    $feedbackLabel = is_array($feedbackEntry)
                        ? strtolower((string)($feedbackEntry['label'] ?? 'needs_review'))
                        : 'needs_review';
                    $feedbackNote = is_array($feedbackEntry) ? (string)($feedbackEntry['note'] ?? '') : '';
                    $feedbackClass = attribution_feedback_badge_class($feedbackLabel);
                    $feedbackText = attribution_feedback_label_text($feedbackLabel);
                    ?>
                    <div class="accordion-item mb-3 border rounded-3 overflow-hidden">
                        <h2 class="accordion-header" id="heading-<?= esc((string)$index) ?>">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId) ?>">
                                <span class="me-3 fw-semibold"><?= esc($entityLabel) ?></span>
                                <span class="badge bg-light text-dark border me-2"><?= esc($attackLabel) ?></span>
                                <span class="badge bg-danger-subtle text-danger border border-danger me-2"><?= esc((string)($case['severity'] ?? 'sev3')) ?></span>
                                <span class="badge bg-<?= esc($feedbackClass) ?>-subtle text-<?= esc($feedbackClass) ?> border border-<?= esc($feedbackClass) ?> me-2"><?= esc($feedbackText) ?></span>
                                <span class="text-muted small">Risk <?= esc((string)($case['risk_score'] ?? 0)) ?></span>
                            </button>
                        </h2>
                        <div id="<?= esc($collapseId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#attributionCases">
                            <div class="accordion-body">
                                <div class="row g-4">
                                    <div class="col-lg-7">
                                        <h6 class="fw-semibold mb-2">Summary</h6>
                                        <p class="mb-2"><?= esc(attribution_safe_text((string)($case['summary'] ?? ''))) ?></p>
                                        <p class="text-muted mb-3"><?= esc(attribution_safe_text((string)($case['explanation'] ?? ''))) ?></p>
                                        <?php if (!empty($case['llm_summary']) || !empty($case['llm_explanation'])): ?>
                                            <div class="alert alert-light border shadow-sm mb-3">
                                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                                    <span class="fw-semibold">LLM Triage Narrative</span>
                                                    <span class="badge bg-dark-subtle text-dark border">
                                                        <?= esc(strtoupper((string)($case['llm_provider'] ?? ($llm['provider'] ?? 'LLM')))) ?>
                                                        <?= !empty($case['llm_model']) ? ' · ' . esc((string)$case['llm_model']) : '' ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($case['llm_summary'])): ?><p class="mb-2"><?= esc(attribution_safe_text((string)$case['llm_summary'])) ?></p><?php endif; ?>
                                                <?php if (!empty($case['llm_explanation'])): ?><p class="text-muted mb-2"><?= esc(attribution_safe_text((string)$case['llm_explanation'])) ?></p><?php endif; ?>
                                                <?php if (!empty($case['llm_confidence_statement'])): ?><p class="small text-muted mb-2"><?= esc(attribution_safe_text((string)$case['llm_confidence_statement'])) ?></p><?php endif; ?>
                                                <?php if (!empty($case['llm_triage_note'])): ?><p class="small mb-2"><strong>Triage note:</strong> <?= esc(attribution_safe_text((string)$case['llm_triage_note'])) ?></p><?php endif; ?>
                                                <?php if (!empty($case['llm_recommended_next_step'])): ?><div class="small"><strong>Next step:</strong> <?= esc(attribution_safe_text((string)$case['llm_recommended_next_step'])) ?></div><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <?php foreach (($case['recommended_actions'] ?? []) as $action): ?><span class="badge bg-light text-dark border"><?= esc((string)$action) ?></span><?php endforeach; ?>
                                        </div>
                                        <h6 class="fw-semibold mb-2">Supporting Events</h6>
                                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Code</th><th>IP</th><th>Risk</th><th>When</th></tr></thead><tbody>
                                            <?php foreach (($case['supporting_events'] ?? []) as $event): ?><tr><td><?= esc((string)($event['event_code'] ?? '')) ?></td><td><?= esc(app_demo_safe_ip((string)($event['source_ip'] ?? ''))) ?></td><td><?= esc((string)($event['risk_score'] ?? 0)) ?></td><td><?= esc(format_ts((string)($event['created_at'] ?? ''), 'M d H:i')) ?></td></tr><?php endforeach; ?>
                                        </tbody></table></div>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="card card-hover overflow-hidden border-0 shadow-sm mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                    <span class="text-uppercase text-muted fw-semibold fs-12">Entity Link Map</span>
                                                    <span class="badge bg-light text-dark border"><?= esc(strtoupper((string)($case['entity_type'] ?? 'case'))) ?> anchor</span>
                                                </div>
                                                <?= attribution_render_case_graph($case, $entityLabel) ?>
                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    <span class="badge bg-light text-dark border px-3 py-2"><span class="attribution-node-dot user me-1"></span><?= esc((string)$linkCounts['users']) ?> user<?= $linkCounts['users'] === 1 ? '' : 's' ?></span>
                                                    <span class="badge bg-light text-dark border px-3 py-2"><span class="attribution-node-dot ip me-1"></span><?= esc((string)$linkCounts['ips']) ?> IP<?= $linkCounts['ips'] === 1 ? '' : 's' ?></span>
                                                    <span class="badge bg-light text-dark border px-3 py-2"><span class="attribution-node-dot device me-1"></span><?= esc((string)$linkCounts['devices']) ?> device<?= $linkCounts['devices'] === 1 ? '' : 's' ?></span>
                                                    <span class="badge bg-light text-dark border px-3 py-2"><span class="attribution-node-dot incident me-1"></span><?= esc((string)$linkCounts['incidents']) ?> incident<?= $linkCounts['incidents'] === 1 ? '' : 's' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="fw-semibold mb-2">Evidence</h6>
                                        <div class="list-group list-group-flush mb-3">
                                            <?php foreach (($case['evidence'] ?? []) as $label => $value): ?><div class="list-group-item px-0 d-flex justify-content-between gap-3"><span class="text-muted"><?= esc(ucwords(str_replace('_', ' ', (string)$label))) ?></span><span class="fw-semibold text-end"><?= esc(attribution_present_value($value)) ?></span></div><?php endforeach; ?>
                                        </div>
                                        <div class="card border-0 bg-light-subtle mb-3">
                                            <div class="card-body">
                                                <h6 class="fw-semibold mb-2">Analyst Feedback</h6>
                                                <div class="small text-muted mb-3">Label this case so future evaluation can separate true incidents from false positives.</div>
                                                <form method="POST" class="row g-2">
                                                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                                    <input type="hidden" name="action" value="save_case_feedback">
                                                    <input type="hidden" name="case_id" value="<?= esc((string)($case['case_id'] ?? '')) ?>">
                                                    <input type="hidden" name="from_date" value="<?= esc($filters['from_date']) ?>">
                                                    <input type="hidden" name="to_date" value="<?= esc($filters['to_date']) ?>">
                                                    <input type="hidden" name="limit" value="<?= esc((string)$filters['limit']) ?>">
                                                    <input type="hidden" name="with_llm" value="<?= $filters['with_llm'] ? '1' : '0' ?>">
                                                    <div class="col-12">
                                                        <label class="form-label mb-1 small">Label</label>
                                                        <select class="form-select form-select-sm" name="feedback_label">
                                                            <?php foreach (attribution_feedback_labels() as $candidate): ?>
                                                                <option value="<?= esc($candidate) ?>" <?= $feedbackLabel === $candidate ? 'selected' : '' ?>><?= esc(attribution_feedback_label_text($candidate)) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label mb-1 small">Note</label>
                                                        <textarea class="form-control form-control-sm" name="feedback_note" rows="2" placeholder="Why this label?"><?= esc($feedbackNote) ?></textarea>
                                                    </div>
                                                    <div class="col-12 d-grid">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="ri-save-3-line me-1"></i>Save Feedback</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <?php if (!empty($case['related_incidents'])): ?>
                                            <h6 class="fw-semibold mb-2">Linked Incidents</h6>
                                            <ul class="list-unstyled mb-3"><?php foreach ($case['related_incidents'] as $incident): ?><li class="mb-2"><span class="fw-semibold"><?= esc((string)($incident['incident_ref'] ?? '')) ?></span> <span class="text-muted small"><?= esc((string)($incident['status'] ?? '')) ?></span></li><?php endforeach; ?></ul>
                                        <?php endif; ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                            <input type="hidden" name="action" value="create_attribution_incident">
                                            <input type="hidden" name="case_id" value="<?= esc((string)($case['case_id'] ?? '')) ?>">
                                            <input type="hidden" name="from_date" value="<?= esc($filters['from_date']) ?>">
                                            <input type="hidden" name="to_date" value="<?= esc($filters['to_date']) ?>">
                                            <input type="hidden" name="limit" value="<?= esc((string)$filters['limit']) ?>">
                                            <input type="hidden" name="with_llm" value="<?= $filters['with_llm'] ? '1' : '0' ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ri-alarm-warning-line me-1"></i>Create Incident From Case</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/js/app.js" type="module"></script>
</body>
</html>

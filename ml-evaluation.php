<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$user = require_auth('admin');

function ml_eval_read_report(string $filename): array
{
    $path = __DIR__ . '/backend/ml_service/reports/' . $filename;
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ml_eval_metric(mixed $value): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    return number_format((float)$value, 4);
}

function ml_eval_percent(mixed $value): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    return number_format((float)$value * 100, 1) . '%';
}

function ml_eval_badge_for_score(float $score): string
{
    if ($score >= 0.85) {
        return 'success';
    }
    if ($score >= 0.65) {
        return 'warning';
    }
    return 'danger';
}

$trainingReport = ml_eval_read_report('latest_training.json');
$evaluationReport = ml_eval_read_report('latest_evaluation.json');
$adversarialReport = ml_eval_read_report('latest_adversarial.json');

$dataset = $evaluationReport['dataset'] ?? [];
$modes = $evaluationReport['modes'] ?? [];
$anomalyModels = $evaluationReport['anomaly_models'] ?? [];
$attackClassifier = $evaluationReport['attack_classifier'] ?? [];
$caseStudies = $evaluationReport['case_studies'] ?? [];
$scenarios = $adversarialReport['scenarios'] ?? [];
$explanationQuality = $evaluationReport['explanation_quality'] ?? [];
$feedbackSummary = $evaluationReport['feedback'] ?? ($adversarialReport['feedback'] ?? []);
$feedbackRecent = $evaluationReport['feedback_recent'] ?? [];

$generatedSummary = [
    'training' => (string)($trainingReport['generated_at'] ?? '-'),
    'evaluation' => (string)($evaluationReport['generated_at'] ?? '-'),
    'adversarial' => (string)($adversarialReport['generated_at'] ?? '-'),
];
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'ML Evaluation | Ownuh SAIPS'; $authLayout = false; include __DIR__ . '/backend/partials/page-head.php'; ?>
<body>
<?php include __DIR__ . '/backend/partials/header.php'; ?>
<?php include __DIR__ . '/backend/partials/sidebar.php'; ?>
<?php include __DIR__ . '/backend/partials/mobile-sidebar.php'; ?>
<main class="app-wrapper">
    <div class="app-container">
        <div class="hstack flex-wrap gap-3 mb-5">
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-semibold"><i class="ri-bar-chart-box-line me-2 text-primary"></i>ML Evaluation</h4>
                <nav>
                    <ol class="breadcrumb breadcrumb-arrow mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Security Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="attack-attribution.php">Attack Attribution</a></li>
                        <li class="breadcrumb-item active">ML Evaluation</li>
                    </ol>
                </nav>
            </div>
            <a href="attack-attribution.php" class="btn btn-sm btn-light">
                <i class="ri-node-tree me-1"></i>Attribution Cases
            </a>
        </div>

        <div class="alert border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,rgba(15,39,64,0.96) 0%, rgba(21,94,99,0.94) 100%); color:#f4f7fb;">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase text-white text-opacity-75 fw-semibold fs-12 mb-1">Research Evidence Lane</div>
                    <h5 class="fw-semibold text-white mb-2">Pipeline metrics, ablation, and adversarial robustness in one place</h5>
                    <p class="mb-0 text-white text-opacity-75">This page reads reproducibility outputs from <code class="text-white">backend/ml_service/reports</code>. Run before demos: <code class="text-white">py -3.11 train_models.py</code>, <code class="text-white">py -3.11 evaluate_models.py</code>, <code class="text-white">py -3.11 run_adversarial_suite.py</code>, then label feedback with <code class="text-white">py -3.11 feedback_labels.py set CASE_ID true_positive</code>.</p>
                </div>
                <div class="d-flex flex-column gap-1 small text-white text-opacity-75">
                    <span>Training report: <?= esc($generatedSummary['training']) ?></span>
                    <span>Evaluation report: <?= esc($generatedSummary['evaluation']) ?></span>
                    <span>Adversarial report: <?= esc($generatedSummary['adversarial']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($evaluationReport === []): ?>
            <div class="alert alert-warning mb-4">
                Evaluation report not found. Run <code>py -3.11 evaluate_models.py</code> in <code>backend/ml_service</code> and refresh this page.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-primary border-2">
                    <h3 class="fw-bold text-primary mb-0"><?= esc((string)($dataset['test_cases'] ?? 0)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Test Cases</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-danger border-2">
                    <h3 class="fw-bold text-danger mb-0"><?= esc((string)($dataset['positive_cases'] ?? 0)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Positive Cases</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-success border-2">
                    <h3 class="fw-bold text-success mb-0"><?= esc((string)($dataset['negative_cases'] ?? 0)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Negative Cases</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-warning border-2">
                    <h3 class="fw-bold text-warning mb-0"><?= esc((string)($trainingReport['train_events'] ?? 0)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Training Events</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-info border-2">
                    <h3 class="fw-bold text-info mb-0"><?= esc((string)($feedbackSummary['labeled_cases'] ?? 0)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Labeled Cases</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-3 border-success border-2">
                    <h3 class="fw-bold text-success mb-0"><?= esc(ml_eval_metric($explanationQuality['overall'] ?? null)) ?></h3>
                    <p class="text-muted fs-12 mb-0">Explanation Quality</p>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Anomaly Model Comparison</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($anomalyModels === []): ?>
                            <p class="text-muted mb-0">No anomaly model metrics found yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Precision</th>
                                            <th>Recall</th>
                                            <th>F1</th>
                                            <th>ROC-AUC</th>
                                            <th>FP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($anomalyModels as $model => $metrics): ?>
                                            <?php $f1 = (float)($metrics['f1'] ?? 0.0); ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)$model) ?></span></td>
                                                <td><?= esc(ml_eval_metric($metrics['precision'] ?? null)) ?></td>
                                                <td><?= esc(ml_eval_metric($metrics['recall'] ?? null)) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= esc(ml_eval_badge_for_score($f1)) ?>-subtle text-<?= esc(ml_eval_badge_for_score($f1)) ?> border border-<?= esc(ml_eval_badge_for_score($f1)) ?>">
                                                        <?= esc(ml_eval_metric($metrics['f1'] ?? null)) ?>
                                                    </span>
                                                </td>
                                                <td><?= esc(ml_eval_metric($metrics['roc_auc'] ?? null)) ?></td>
                                                <td><?= esc((string)($metrics['false_positives'] ?? 0)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Pipeline Ablation</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($modes === []): ?>
                            <p class="text-muted mb-0">No ablation metrics found yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Mode</th>
                                            <th>Precision</th>
                                            <th>Recall</th>
                                            <th>F1</th>
                                            <th>ROC-AUC</th>
                                            <th>FP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modes as $mode => $metrics): ?>
                                            <?php $f1 = (float)($metrics['f1'] ?? 0.0); ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)$mode) ?></span></td>
                                                <td><?= esc(ml_eval_metric($metrics['precision'] ?? null)) ?></td>
                                                <td><?= esc(ml_eval_metric($metrics['recall'] ?? null)) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= esc(ml_eval_badge_for_score($f1)) ?>-subtle text-<?= esc(ml_eval_badge_for_score($f1)) ?> border border-<?= esc(ml_eval_badge_for_score($f1)) ?>">
                                                        <?= esc(ml_eval_metric($metrics['f1'] ?? null)) ?>
                                                    </span>
                                                </td>
                                                <td><?= esc(ml_eval_metric($metrics['roc_auc'] ?? null)) ?></td>
                                                <td><?= esc((string)($metrics['false_positives'] ?? 0)) ?></td>
                                            </tr>
                                            <?php if ($mode === 'graph_plus_anomaly_llm'): ?>
                                                <tr>
                                                    <td colspan="6" class="text-muted small">
                                                        Explanation coverage: <?= esc(ml_eval_percent($metrics['explanation_coverage'] ?? null)) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-5">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Attack Classifier</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($attackClassifier === []): ?>
                            <p class="text-muted mb-0">No attack-classifier metrics found yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-primary-subtle text-primary border border-primary">Precision <?= esc(ml_eval_metric($attackClassifier['precision'] ?? null)) ?></span>
                                <span class="badge bg-info-subtle text-info border border-info">Recall <?= esc(ml_eval_metric($attackClassifier['recall'] ?? null)) ?></span>
                                <span class="badge bg-success-subtle text-success border border-success">F1 <?= esc(ml_eval_metric($attackClassifier['f1'] ?? null)) ?></span>
                            </div>
                            <p class="text-muted mb-0 small">
                                Evaluated positive cases: <?= esc((string)($attackClassifier['evaluated_positive_cases'] ?? 0)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Adversarial Robustness</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($scenarios === []): ?>
                            <p class="text-muted mb-0">No adversarial-suite report found yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Scenario</th>
                                            <th>Recall</th>
                                            <th>F1</th>
                                            <th>ROC-AUC</th>
                                            <th>Recall Delta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scenarios as $name => $metrics): ?>
                                            <?php $delta = (float)($metrics['recall_delta_vs_baseline'] ?? 0.0); ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)$name) ?></span></td>
                                                <td><?= esc(ml_eval_metric($metrics['recall'] ?? null)) ?></td>
                                                <td><?= esc(ml_eval_metric($metrics['f1'] ?? null)) ?></td>
                                                <td><?= esc(ml_eval_metric($metrics['roc_auc'] ?? null)) ?></td>
                                                <td class="<?= $delta < 0 ? 'text-danger' : 'text-success' ?> fw-semibold">
                                                    <?= esc(number_format($delta, 4)) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Explanation Quality</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($explanationQuality === []): ?>
                            <p class="text-muted mb-0">No explanation quality metrics found yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-primary-subtle text-primary border border-primary">Overall <?= esc(ml_eval_metric($explanationQuality['overall'] ?? null)) ?></span>
                                <span class="badge bg-info-subtle text-info border border-info">Attack Alignment <?= esc(ml_eval_metric($explanationQuality['attack_alignment'] ?? null)) ?></span>
                                <span class="badge bg-success-subtle text-success border border-success">Entity Alignment <?= esc(ml_eval_metric($explanationQuality['entity_alignment'] ?? null)) ?></span>
                                <span class="badge bg-warning-subtle text-warning border border-warning">Focus Alignment <?= esc(ml_eval_metric($explanationQuality['focus_user_alignment'] ?? null)) ?></span>
                            </div>
                            <p class="small text-muted mb-0">These scores evaluate whether generated narratives reflect predicted attack type, linked entities, and behavioral focus context.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card card-hover h-100">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Analyst Feedback Loop</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-success-subtle text-success border border-success">True Positive <?= esc((string)($feedbackSummary['true_positive'] ?? 0)) ?></span>
                            <span class="badge bg-danger-subtle text-danger border border-danger">False Positive <?= esc((string)($feedbackSummary['false_positive'] ?? 0)) ?></span>
                            <span class="badge bg-warning-subtle text-warning border border-warning">Needs Review <?= esc((string)($feedbackSummary['needs_review'] ?? 0)) ?></span>
                        </div>
                        <?php if ($feedbackRecent === []): ?>
                            <p class="text-muted mb-0 small">No feedback entries yet. Use <code>python feedback_labels.py set CASE_ID true_positive</code> to start labeling.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Case</th>
                                            <th>Label</th>
                                            <th>Analyst</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feedbackRecent as $entry): ?>
                                            <tr>
                                                <td><?= esc((string)($entry['case_id'] ?? '')) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= esc((string)($entry['label'] ?? 'needs_review')) ?></span></td>
                                                <td><?= esc((string)($entry['analyst'] ?? 'analyst')) ?></td>
                                                <td><?= esc((string)($entry['updated_at'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-hover">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Case Studies (Top Risk)</h6>
            </div>
            <div class="card-body">
                <?php if ($caseStudies === []): ?>
                    <p class="text-muted mb-0">No case studies were generated yet.</p>
                <?php else: ?>
                    <div class="accordion" id="caseStudiesAccordion">
                        <?php foreach ($caseStudies as $idx => $case): ?>
                            <?php $itemId = 'case-study-' . $idx; ?>
                            <div class="accordion-item mb-3 border rounded-3 overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $idx === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($itemId) ?>">
                                        <span class="me-2 fw-semibold"><?= esc((string)($case['case_id'] ?? 'case')) ?></span>
                                        <span class="badge bg-light text-dark border me-2"><?= esc((string)($case['attack_type'] ?? 'UNKNOWN')) ?></span>
                                        <span class="text-muted small">score <?= esc(ml_eval_metric($case['graph_plus_anomaly_score'] ?? null)) ?></span>
                                    </button>
                                </h2>
                                <div id="<?= esc($itemId) ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#caseStudiesAccordion">
                                    <div class="accordion-body">
                                        <p class="mb-2"><?= esc((string)($case['description'] ?? '')) ?></p>
                                        <p class="text-muted mb-3"><?= esc((string)($case['llm_explanation'] ?? '')) ?></p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="fw-semibold mb-2">Predictions</div>
                                                <div class="small text-muted">True attack: <span class="text-dark fw-semibold"><?= esc((string)($case['attack_type'] ?? '')) ?></span></div>
                                                <div class="small text-muted">Predicted: <span class="text-dark fw-semibold"><?= esc((string)($case['predicted_attack_type'] ?? '')) ?></span></div>
                                                <div class="small text-muted">Behavioral focus user: <span class="text-dark fw-semibold"><?= esc((string)($case['behavioral_focus_user'] ?? '-')) ?></span></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fw-semibold mb-2">Top Behavioral Drivers</div>
                                                <?php $drivers = $case['behavioral_drivers'] ?? []; ?>
                                                <?php if ($drivers === []): ?>
                                                    <div class="small text-muted">No behavioral driver details available.</div>
                                                <?php else: ?>
                                                    <?php foreach ($drivers as $driver): ?>
                                                        <div class="small text-muted">
                                                            <?= esc((string)($driver['feature'] ?? 'feature')) ?>:
                                                            <span class="text-dark fw-semibold"><?= esc(ml_eval_metric($driver['value'] ?? null)) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/js/app.js" type="module"></script>
</body>
</html>

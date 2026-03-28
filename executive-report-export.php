<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/bootstrap.php';

use SAIPS\Services\AIService;
use SAIPS\Services\ExecutiveReportExportService;
use SAIPS\Services\ExecutiveReportManager;

$user = require_auth('admin');
$format = strtolower((string)($_GET['format'] ?? 'html'));

if (!in_array($format, ['html', 'pdf'], true)) {
    http_response_code(400);
    echo 'Unsupported export format.';
    exit;
}

$snapshot = get_security_posture_snapshot();
$aiResult = (new AIService())->generateExecutivePostureReport($snapshot);
$report = $aiResult['report'] ?? null;

if (!is_array($report)) {
    http_response_code(500);
    echo 'Unable to build executive report.';
    exit;
}

$renderer = new ExecutiveReportExportService();
$manager = new ExecutiveReportManager();
$dateStamp = date('Y-m-d');
$meta = [
    'generated_at' => date('c'),
    'provider' => $aiResult['provider'] ?? 'report',
    'model' => $aiResult['model'] ?? null,
    'requested_by' => $user['email'] ?? $user['display_name'] ?? 'admin',
];

$manager->saveGeneratedReport($report, $snapshot, [
    'generated_by' => $user['id'] ?? null,
    'delivery_channel' => 'manual',
    'report_format' => $format,
    'provider' => $aiResult['provider'] ?? 'report',
    'model' => $aiResult['model'] ?? null,
]);

if ($format === 'html') {
    $filename = 'executive-security-posture-' . $dateStamp . '.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $renderer->renderHtmlDocument($report, $snapshot, $meta);
    exit;
}

$filename = 'executive-security-posture-' . $dateStamp . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $renderer->renderPdf($report, $snapshot, $meta);

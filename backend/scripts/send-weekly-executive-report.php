<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use SAIPS\Services\AIService;
use SAIPS\Services\EmailService;
use SAIPS\Services\ExecutiveReportExportService;
use SAIPS\Services\ExecutiveReportManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = Database::getInstance();
$manager = new ExecutiveReportManager($db);
$settings = $manager->getSettings();

if (!$dryRun && !$manager->shouldSendScheduledReport()) {
    fwrite(STDOUT, "Scheduled executive report skipped for cadence policy.\n");
    exit(0);
}

$admins = $db->fetchAll(
    'SELECT email, display_name
     FROM users
     WHERE deleted_at IS NULL
       AND status = "active"
       AND role IN ("admin", "superadmin")
     ORDER BY role, display_name'
);

if (empty($admins)) {
    fwrite(STDOUT, "No active admin recipients found.\n");
    exit(0);
}

$snapshot = get_security_posture_snapshot();
$aiResult = (new AIService())->generateExecutivePostureReport($snapshot);
$report = $aiResult['report'] ?? null;

if (!is_array($report)) {
    fwrite(STDERR, "Failed to generate executive report.\n");
    exit(1);
}

$renderer = new ExecutiveReportExportService();
$htmlBody = $renderer->renderEmailHtml($report, $snapshot, [
    'generated_at' => date('c'),
    'provider' => $aiResult['provider'] ?? 'report',
    'model' => $aiResult['model'] ?? null,
]);

$subject = 'Weekly Executive Security Posture Report - ' . date('Y-m-d');
$attachmentFormat = (string)($settings['attach_format'] ?? 'none');

$emailService = new EmailService([
    'provider' => $_ENV['EMAIL_PROVIDER'] ?? 'smtp',
    'app_name' => $_ENV['APP_NAME'] ?? 'Ownuh SAIPS',
    'from_name' => $_ENV['EMAIL_FROM_NAME'] ?? 'Ownuh SAIPS',
    'from_email' => $_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com',
    'reply_to' => $_ENV['EMAIL_REPLY_TO'] ?? ($_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com'),
]);

$sent = 0;
$failed = 0;
$recipientList = [];

foreach ($admins as $admin) {
    $recipient = (string)($admin['email'] ?? '');
    if ($recipient === '') {
        continue;
    }
    $recipientList[] = $recipient;

    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] Would send weekly executive report to {$recipient}\n");
        $sent++;
        continue;
    }

    $options = [
        'queue' => false,
        'is_html' => true,
    ];
    if ($attachmentFormat === 'html') {
        $options['attachments'] = [[
            'filename' => 'executive-security-posture-' . date('Y-m-d') . '.html',
            'content_type' => 'text/html; charset=UTF-8',
            'content' => $renderer->renderHtmlDocument($report, $snapshot, [
                'generated_at' => date('c'),
                'provider' => $aiResult['provider'] ?? 'report',
                'model' => $aiResult['model'] ?? null,
            ]),
        ]];
    } elseif ($attachmentFormat === 'pdf') {
        $options['attachments'] = [[
            'filename' => 'executive-security-posture-' . date('Y-m-d') . '.pdf',
            'content_type' => 'application/pdf',
            'content' => $renderer->renderPdf($report, $snapshot, [
                'generated_at' => date('c'),
                'provider' => $aiResult['provider'] ?? 'report',
                'model' => $aiResult['model'] ?? null,
            ]),
        ]];
    }

    $result = $emailService->send($recipient, $subject, $htmlBody, $options);

    if (($result['success'] ?? false) === true) {
        fwrite(STDOUT, "Sent weekly executive report to {$recipient}\n");
        $sent++;
    } else {
        $failed++;
        $error = (string)($result['error'] ?? 'Unknown error');
        fwrite(STDERR, "Failed sending to {$recipient}: {$error}\n");
    }
}

if (!$dryRun && $sent > 0) {
    $manager->saveGeneratedReport($report, $snapshot, [
        'generated_by' => null,
        'delivery_channel' => 'email',
        'report_format' => $attachmentFormat === 'none' ? 'email' : $attachmentFormat,
        'provider' => $aiResult['provider'] ?? 'report',
        'model' => $aiResult['model'] ?? null,
        'cadence' => $settings['cadence'] ?? 'weekly',
        'email_recipients' => implode(', ', $recipientList),
    ]);
    $manager->markScheduledReportSent();
}

fwrite(STDOUT, "Weekly executive report complete. Sent: {$sent}; Failed: {$failed}\n");
exit($failed > 0 ? 1 : 0);

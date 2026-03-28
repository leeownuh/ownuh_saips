<?php
declare(strict_types=1);

namespace SAIPS\Services;

final class AlertDispatcherService
{
    private \Database $db;
    private EmailService $emailService;
    private WebhookService $webhookService;

    public function __construct(?\Database $db = null, ?EmailService $emailService = null, ?WebhookService $webhookService = null)
    {
        $this->db = $db ?? \Database::getInstance();
        $this->emailService = $emailService ?? new EmailService([
            'provider' => $_ENV['EMAIL_PROVIDER'] ?? 'smtp',
            'app_name' => $_ENV['APP_NAME'] ?? 'Ownuh SAIPS',
            'from_name' => $_ENV['EMAIL_FROM_NAME'] ?? 'Ownuh SAIPS',
            'from_email' => $_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com',
            'reply_to' => $_ENV['EMAIL_REPLY_TO'] ?? ($_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com'),
            'sendgrid_api_key' => $_ENV['SENDGRID_API_KEY'] ?? '',
            'aws_access_key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
            'aws_secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
        ]);
        $this->webhookService = $webhookService ?? new WebhookService([]);
    }

    public function dispatch(string $eventCode, array $payload = []): int
    {
        $rules = $this->db->fetchAll(
            'SELECT id, rule_name, event_type, channel, threshold_count, window_minutes, destination
             FROM alert_rules
             WHERE is_active = 1
               AND event_type = ?
             ORDER BY created_at DESC',
            [$eventCode]
        );

        $sent = 0;
        foreach ($rules as $rule) {
            if (!$this->meetsThreshold($rule, $payload)) {
                continue;
            }

            $result = match ((string)$rule['channel']) {
                'email' => $this->dispatchEmailRule($rule, $payload),
                'webhook', 'slack' => $this->dispatchWebhookRule($rule, $payload),
                default => ['success' => false],
            };

            if (($result['success'] ?? false) === true) {
                $sent++;
            }
        }

        return $sent;
    }

    private function meetsThreshold(array $rule, array $payload): bool
    {
        $threshold = (int)($rule['threshold_count'] ?? 1);
        $count = (int)($payload['match_count'] ?? 1);
        return $count >= max(1, $threshold);
    }

    private function dispatchEmailRule(array $rule, array $payload): array
    {
        $to = (string)($rule['destination'] ?? '');
        if ($to === '') {
            return ['success' => false, 'error' => 'No destination email configured.'];
        }

        $subject = '[' . ($payload['event_code'] ?? $rule['event_type'] ?? 'ALERT') . '] ' . ($rule['rule_name'] ?? 'Security Alert');
        $body = $this->buildEmailBody($rule, $payload);

        return $this->emailService->send($to, $subject, nl2br($body), [
            'queue' => false,
            'is_html' => true,
        ]);
    }

    private function dispatchWebhookRule(array $rule, array $payload): array
    {
        return $this->webhookService->sendAlert(
            strtolower((string)($payload['event_code'] ?? $rule['event_type'] ?? 'security_alert')),
            $payload,
            [
                'url' => (string)($rule['destination'] ?? ''),
                'format' => (string)$rule['channel'] === 'slack' ? 'slack' : 'generic',
                'queue' => false,
            ]
        );
    }

    private function buildEmailBody(array $rule, array $payload): string
    {
        $lines = [
            'Alert rule triggered: ' . (string)($rule['rule_name'] ?? 'Security Alert'),
            'Event code: ' . (string)($payload['event_code'] ?? $rule['event_type'] ?? ''),
            'Summary: ' . (string)($payload['summary'] ?? 'A configured security event threshold was met.'),
        ];

        if (!empty($payload['user_email'])) {
            $lines[] = 'User: ' . (string)$payload['user_email'];
        }
        if (!empty($payload['incident_ref'])) {
            $lines[] = 'Incident: ' . (string)$payload['incident_ref'];
        }
        if (!empty($payload['ip_address'])) {
            $lines[] = 'Source IP: ' . (string)$payload['ip_address'];
        }
        if (!empty($payload['match_count'])) {
            $lines[] = 'Matched events: ' . (string)$payload['match_count'];
        }
        $lines[] = 'Time: ' . date('Y-m-d H:i:s');

        return implode("\n\n", $lines);
    }
}

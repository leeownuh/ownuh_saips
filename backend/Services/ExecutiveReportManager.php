<?php
declare(strict_types=1);

namespace SAIPS\Services;

final class ExecutiveReportManager
{
    private \Database $db;
    private bool $storageReady = false;

    public function __construct(?\Database $db = null)
    {
        $this->db = $db ?? \Database::getInstance();
        $this->storageReady = $this->ensureStorage();
    }

    public function getSettings(): array
    {
        return [
            'email_enabled' => $this->toBool(\get_system_setting('executive_reports.email_enabled', '1')),
            'cadence' => $this->normalizeCadence((string)\get_system_setting('executive_reports.cadence', 'weekly')),
            'attach_format' => $this->normalizeAttachFormat((string)\get_system_setting('executive_reports.attach_format', 'none')),
            'last_sent_at' => (string)\get_system_setting('executive_reports.last_sent_at', ''),
        ];
    }

    public function saveSettings(array $input, ?string $updatedBy = null): void
    {
        \set_system_setting('executive_reports.email_enabled', !empty($input['email_enabled']) ? '1' : '0', $updatedBy);
        \set_system_setting('executive_reports.cadence', $this->normalizeCadence((string)($input['cadence'] ?? 'weekly')), $updatedBy);
        \set_system_setting('executive_reports.attach_format', $this->normalizeAttachFormat((string)($input['attach_format'] ?? 'none')), $updatedBy);
    }

    public function saveGeneratedReport(array $report, array $snapshot, array $meta = []): bool
    {
        if (!$this->storageReady) {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO executive_reports (
                    id, generated_by, delivery_channel, report_format, provider, model,
                    cadence, report_title, overall_posture, report_json, snapshot_json,
                    email_recipients, generated_at
                 )
                 VALUES (
                    UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )',
                [
                    $meta['generated_by'] ?? null,
                    $meta['delivery_channel'] ?? 'manual',
                    $meta['report_format'] ?? 'onscreen',
                    $meta['provider'] ?? 'fallback',
                    $meta['model'] ?? null,
                    $meta['cadence'] ?? null,
                    $report['report_title'] ?? 'Executive Security Posture Report',
                    $report['overall_posture'] ?? null,
                    json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $meta['email_recipients'] ?? null,
                    $meta['generated_at'] ?? date('Y-m-d H:i:s'),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getHistory(int $limit = 10): array
    {
        if (!$this->storageReady) {
            return [];
        }

        try {
            return $this->db->fetchAll(
                'SELECT er.id, er.delivery_channel, er.report_format, er.provider, er.model,
                        er.cadence, er.report_title, er.overall_posture, er.email_recipients,
                        er.generated_at, u.display_name, u.email
                 FROM executive_reports er
                 LEFT JOIN users u ON u.id = er.generated_by
                 ORDER BY er.generated_at DESC
                 LIMIT ?',
                [$limit],
                'i'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function shouldSendScheduledReport(?\DateTimeImmutable $now = null): bool
    {
        $settings = $this->getSettings();
        if ($settings['email_enabled'] !== true) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable('now');
        $lastSentRaw = trim((string)$settings['last_sent_at']);
        if ($lastSentRaw === '') {
            return true;
        }

        try {
            $lastSent = new \DateTimeImmutable($lastSentRaw);
        } catch (\Throwable $e) {
            return true;
        }

        $days = $settings['cadence'] === 'monthly' ? 28 : 7;
        return $lastSent->modify('+' . $days . ' days') <= $now;
    }

    public function markScheduledReportSent(?string $updatedBy = null, ?\DateTimeImmutable $when = null): void
    {
        $stamp = ($when ?? new \DateTimeImmutable('now'))->format('c');
        \set_system_setting('executive_reports.last_sent_at', $stamp, $updatedBy);
    }

    private function ensureStorage(): bool
    {
        try {
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS executive_reports (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    generated_by CHAR(36) NULL DEFAULT NULL,
                    delivery_channel VARCHAR(20) NOT NULL DEFAULT "manual",
                    report_format VARCHAR(20) NOT NULL DEFAULT "onscreen",
                    provider VARCHAR(40) NOT NULL DEFAULT "fallback",
                    model VARCHAR(100) NULL DEFAULT NULL,
                    cadence VARCHAR(20) NULL DEFAULT NULL,
                    report_title VARCHAR(255) NOT NULL,
                    overall_posture VARCHAR(50) NULL DEFAULT NULL,
                    report_json LONGTEXT NOT NULL,
                    snapshot_json LONGTEXT NOT NULL,
                    email_recipients TEXT NULL,
                    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_executive_reports_generated_at (generated_at),
                    INDEX idx_executive_reports_channel (delivery_channel),
                    CONSTRAINT fk_executive_reports_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizeCadence(string $cadence): string
    {
        $cadence = strtolower(trim($cadence));
        return in_array($cadence, ['weekly', 'monthly'], true) ? $cadence : 'weekly';
    }

    private function normalizeAttachFormat(string $format): string
    {
        $format = strtolower(trim($format));
        return in_array($format, ['none', 'html', 'pdf'], true) ? $format : 'none';
    }

    private function toBool(mixed $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

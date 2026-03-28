CREATE TABLE IF NOT EXISTS executive_reports (
    id CHAR(36) NOT NULL PRIMARY KEY,
    generated_by CHAR(36) NULL DEFAULT NULL,
    delivery_channel VARCHAR(20) NOT NULL DEFAULT 'manual',
    report_format VARCHAR(20) NOT NULL DEFAULT 'onscreen',
    provider VARCHAR(40) NOT NULL DEFAULT 'fallback',
    model VARCHAR(100) NULL DEFAULT NULL,
    cadence VARCHAR(20) NULL DEFAULT NULL,
    report_title VARCHAR(255) NOT NULL,
    overall_posture VARCHAR(50) NULL DEFAULT NULL,
    report_json LONGTEXT NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    email_recipients TEXT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exec_reports_generated_at (generated_at),
    INDEX idx_exec_reports_channel (delivery_channel),
    CONSTRAINT fk_exec_reports_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value)
VALUES
    ('executive_reports.email_enabled', '1'),
    ('executive_reports.cadence', 'weekly'),
    ('executive_reports.attach_format', 'none')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

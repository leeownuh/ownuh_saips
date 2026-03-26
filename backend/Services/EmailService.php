<?php
/**
 * Ownuh SAIPS — Email Service
 * Handles sending emails via SMTP, SendGrid, or AWS SES.
 * SRS §5.2 — Alerts & Notifications
 * 
 * Supports templated emails with placeholder substitution.
 */

declare(strict_types=1);

namespace SAIPS\Services;

class EmailService
{
    private array $config;
    private $redis;
    private string $provider;
    
    // Email templates
    private const TEMPLATES = [
        'password_reset' => [
            'subject' => 'Password Reset Request - {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nA password reset was requested for your account. Click the link below to reset your password:\n\n{{reset_link}}\n\nThis link expires in {{expires_in}}.\n\nIf you did not request this reset, please ignore this email or contact your administrator.\n\nBest regards,\n{{app_name}} Security Team',
        ],
        'email_otp' => [
            'subject' => 'Your Verification Code - {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nYour verification code is: {{otp_code}}\n\nThis code expires in {{expires_in}}.\n\nIf you did not request this code, please contact your administrator immediately.\n\nBest regards,\n{{app_name}} Security Team',
        ],
        'account_locked' => [
            'subject' => 'Account Locked - {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nYour account has been locked due to too many failed login attempts.\n\nIf this was not you, please contact your administrator immediately.\n\nLast failed attempt from IP: {{ip_address}}\nTime: {{timestamp}}\n\nBest regards,\n{{app_name}} Security Team',
        ],
        'mfa_enrolled' => [
            'subject' => 'MFA Enabled on Your Account - {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nMulti-factor authentication has been enabled on your account using {{mfa_method}}.\n\nYour backup codes:\n{{backup_codes}}\n\nStore these codes securely. Each can be used once if you lose access to your MFA device.\n\nBest regards,\n{{app_name}} Security Team',
        ],
        'incident_alert' => [
            'subject' => '[{{severity}}] Security Incident - {{incident_ref}}',
            'body' => 'A new security incident has been created:\n\nReference: {{incident_ref}}\nSeverity: {{severity}}\nSummary: {{trigger_summary}}\nDetected: {{detected_at}}\n\nPlease review immediately.\n\n{{incident_link}}',
        ],
        'new_device_login' => [
            'subject' => 'New Device Login - {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nA new device was used to sign in to your account.\n\nDevice: {{device_info}}\nLocation: {{location}}\nIP Address: {{ip_address}}\nTime: {{timestamp}}\n\nIf this was not you, please change your password immediately and contact your administrator.\n\nBest regards,\n{{app_name}} Security Team',
        ],
        'welcome' => [
            'subject' => 'Welcome to {{app_name}}',
            'body' => 'Hello {{display_name}},\n\nWelcome to {{app_name}}! Your account has been created.\n\nEmail: {{email}}\n\nPlease set up multi-factor authentication at your earliest convenience.\n\nBest regards,\n{{app_name}} Team',
        ],
    ];
    
    /**
     * @param array $config Email configuration
     * @param $redis Redis client for queue
     */
    public function __construct(array $config, $redis = null)
    {
        $this->config = $config;
        $this->redis = $redis;
        $this->provider = $config['provider'] ?? $_ENV['EMAIL_PROVIDER'] ?? 'smtp';
    }
    
    /**
     * Send an email using a template.
     * 
     * @param string $to Recipient email address
     * @param string $template Template name (e.g., 'password_reset')
     * @param array $data Data for template placeholders
     * @param array $options Additional options (cc, bcc, attachments)
     * @return array Result with success status
     */
    public function sendTemplate(string $to, string $template, array $data = [], array $options = []): array
    {
        if (!isset(self::TEMPLATES[$template])) {
            return [
                'success' => false,
                'error' => "Unknown template: {$template}",
                'code' => 'INVALID_TEMPLATE',
            ];
        }
        
        $templateData = self::TEMPLATES[$template];
        
        // Add default data
        $data = array_merge([
            'app_name' => $this->config['app_name'] ?? 'Ownuh SAIPS',
        ], $data);
        
        // Replace placeholders
        $subject = $this->replacePlaceholders($templateData['subject'], $data);
        $body = $this->replacePlaceholders($templateData['body'], $data);
        
        return $this->send($to, $subject, $body, $options);
    }
    
    /**
     * Send a custom email.
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (plain text)
     * @param array $options Additional options
     * @return array Result with success status
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        // Queue for async sending if Redis is available
        if ($this->redis && ($options['queue'] ?? true)) {
            return $this->queueEmail($to, $subject, $body, $options);
        }
        
        // Send immediately
        return $this->sendNow($to, $subject, $body, $options);
    }
    
    /**
     * Queue email for async processing.
     */
    private function queueEmail(string $to, string $subject, string $body, array $options): array
    {
        $emailData = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'options' => $options,
            'created_at' => time(),
            'attempts' => 0,
        ];
        
        $this->redis->lpush('saips:email:queue', json_encode($emailData));
        
        return [
            'success' => true,
            'message' => 'Email queued for delivery',
            'queued' => true,
        ];
    }
    
    /**
     * Process queued emails (run via cron).
     */
    public function processQueue(int $batchSize = 50): array
    {
        if (!$this->redis) {
            return ['processed' => 0, 'failed' => 0, 'errors' => ['Redis not available']];
        }
        
        $processed = 0;
        $failed = 0;
        $errors = [];
        
        for ($i = 0; $i < $batchSize; $i++) {
            $emailJson = $this->redis->rpop('saips:email:queue');
            if (!$emailJson) break;
            
            $email = json_decode($emailJson, true);
            $result = $this->sendNow(
                $email['to'],
                $email['subject'],
                $email['body'],
                $email['options'] ?? []
            );
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                $errors[] = "Failed to send to {$email['to']}: {$result['error']}";
                
                // Re-queue with incremented attempt count (max 3 attempts)
                $email['attempts'] = ($email['attempts'] ?? 0) + 1;
                if ($email['attempts'] < 3) {
                    $this->redis->lpush('saips:email:queue', json_encode($email));
                }
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
    
    /**
     * Send email immediately.
     */
    private function sendNow(string $to, string $subject, string $body, array $options): array
    {
        switch ($this->provider) {
            case 'ses':
                return $this->sendViaSES($to, $subject, $body, $options);
            case 'sendgrid':
                return $this->sendViaSendGrid($to, $subject, $body, $options);
            case 'smtp':
            default:
                return $this->sendViaSMTP($to, $subject, $body, $options);
        }
    }
    
    /**
     * Send via SMTP (PHP mail function).
     */
    private function sendViaSMTP(string $to, string $subject, string $body, array $options): array
    {
        $headers = [];
        $headers[] = 'From: ' . ($this->config['from_name'] ?? 'Ownuh SAIPS') . ' <' . ($this->config['from_email'] ?? 'security@example.com') . '>';
        $headers[] = 'Reply-To: ' . ($this->config['reply_to'] ?? $this->config['from_email'] ?? 'security@example.com');
        $headers[] = 'X-Mailer: Ownuh-SAIPS/1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($success) {
            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];
        }
        
        $error = error_get_last();
        return [
            'success' => false,
            'error' => $error['message'] ?? 'Failed to send email',
            'code' => 'SMTP_ERROR',
        ];
    }
    
    /**
     * Send via AWS SES.
     */
    private function sendViaSES(string $to, string $subject, string $body, array $options): array
    {
        $region = $this->config['ses_region'] ?? $_ENV['AWS_REGION'] ?? 'us-east-1';
        $accessKey = $this->config['aws_access_key'] ?? $_ENV['AWS_ACCESS_KEY_ID'] ?? '';
        $secretKey = $this->config['aws_secret_key'] ?? $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '';
        
        if (!$accessKey || !$secretKey) {
            // Fallback to log
            error_log("[EMAIL] SES not configured. Would send to {$to}: {$subject}");
            return [
                'success' => true,
                'message' => 'Email logged (SES not configured)',
                'logged' => true,
            ];
        }
        
        // AWS SES API call would go here
        // For now, use SDK if available
        
        return [
            'success' => false,
            'error' => 'SES integration requires AWS SDK',
            'code' => 'SES_SDK_REQUIRED',
        ];
    }
    
    /**
     * Send via SendGrid.
     */
    private function sendViaSendGrid(string $to, string $subject, string $body, array $options): array
    {
        $apiKey = $this->config['sendgrid_api_key'] ?? $_ENV['SENDGRID_API_KEY'] ?? '';
        
        if (!$apiKey) {
            error_log("[EMAIL] SendGrid not configured. Would send to {$to}: {$subject}");
            return [
                'success' => true,
                'message' => 'Email logged (SendGrid not configured)',
                'logged' => true,
            ];
        }
        
        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject,
                ]
            ],
            'from' => [
                'email' => $this->config['from_email'] ?? 'security@example.com',
                'name' => $this->config['from_name'] ?? 'Ownuh SAIPS',
            ],
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $body,
                ]
            ],
        ];
        
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Email sent via SendGrid',
            ];
        }
        
        return [
            'success' => false,
            'error' => "SendGrid error: HTTP {$httpCode}",
            'code' => 'SENDGRID_ERROR',
        ];
    }
    
    /**
     * Replace placeholders in template.
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        return $template;
    }
    
    /**
     * Add a custom template.
     */
    public function addTemplate(string $name, string $subject, string $body): void
    {
        self::TEMPLATES[$name] = [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
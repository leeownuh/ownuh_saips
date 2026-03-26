<?php
/**
 * Ownuh SAIPS — Webhook Alert Service
 * Dispatches alerts to external webhook endpoints (Slack, Teams, custom).
 * SRS §5.2 — Alerts & Notifications
 */

declare(strict_types=1);

namespace SAIPS\Services;

class WebhookService
{
    private array $config;
    private $redis;
    private int $timeout;
    private int $maxRetries;
    
    // Predefined webhook formats
    private const FORMATS = [
        'slack' => [
            'content_type' => 'application/json',
            'formatter' => 'formatSlack',
        ],
        'teams' => [
            'content_type' => 'application/json',
            'formatter' => 'formatTeams',
        ],
        'discord' => [
            'content_type' => 'application/json',
            'formatter' => 'formatDiscord',
        ],
        'generic' => [
            'content_type' => 'application/json',
            'formatter' => 'formatGeneric',
        ],
    ];
    
    /**
     * @param array $config Webhook configuration
     * @param $redis Redis client for queue
     */
    public function __construct(array $config, $redis = null)
    {
        $this->config = $config;
        $this->redis = $redis;
        $this->timeout = $config['timeout'] ?? 10;
        $this->maxRetries = $config['max_retries'] ?? 3;
    }
    
    /**
     * Send alert to webhook endpoint.
     * 
     * @param string $event Event type (e.g., 'incident_created', 'account_locked')
     * @param array $data Event data
     * @param array $options Webhook options (url, format, etc.)
     * @return array Result with success status
     */
    public function sendAlert(string $event, array $data, array $options = []): array
    {
        $webhookUrl = $options['url'] ?? $this->config['default_url'] ?? null;
        $format = $options['format'] ?? $this->config['default_format'] ?? 'generic';
        
        if (!$webhookUrl) {
            return [
                'success' => false,
                'error' => 'No webhook URL configured',
                'code' => 'NO_URL',
            ];
        }
        
        // Validate URL
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Invalid webhook URL',
                'code' => 'INVALID_URL',
            ];
        }
        
        // Prepare payload
        $payload = $this->formatPayload($event, $data, $format);
        $contentType = self::FORMATS[$format]['content_type'] ?? 'application/json';
        
        // Queue or send immediately
        if ($this->redis && ($options['queue'] ?? true)) {
            return $this->queueWebhook($webhookUrl, $payload, $contentType);
        }
        
        return $this->sendNow($webhookUrl, $payload, $contentType);
    }
    
    /**
     * Send incident alert.
     */
    public function sendIncidentAlert(array $incident, array $options = []): array
    {
        return $this->sendAlert('incident_created', [
            'incident_ref' => $incident['incident_ref'] ?? '',
            'severity' => $incident['severity'] ?? 'sev4',
            'status' => $incident['status'] ?? 'open',
            'trigger_summary' => $incident['trigger_summary'] ?? '',
            'detected_at' => $incident['detected_at'] ?? date('c'),
            'source_ip' => $incident['source_ip'] ?? null,
            'affected_user' => $incident['affected_user_id'] ?? null,
        ], $options);
    }
    
    /**
     * Send account locked alert.
     */
    public function sendAccountLockedAlert(array $user, string $reason, array $options = []): array
    {
        return $this->sendAlert('account_locked', [
            'user_id' => $user['id'] ?? '',
            'email' => $user['email'] ?? '',
            'reason' => $reason,
            'timestamp' => date('c'),
        ], $options);
    }
    
    /**
     * Send brute force detected alert.
     */
    public function sendBruteForceAlert(string $ip, int $attempts, array $options = []): array
    {
        return $this->sendAlert('brute_force_detected', [
            'ip_address' => $ip,
            'attempts' => $attempts,
            'timestamp' => date('c'),
            'recommended_action' => 'Block IP and investigate',
        ], $options);
    }
    
    /**
     * Send high-risk login alert.
     */
    public function sendHighRiskLoginAlert(array $user, int $riskScore, array $details, array $options = []): array
    {
        return $this->sendAlert('high_risk_login', [
            'user_id' => $user['id'] ?? '',
            'email' => $user['email'] ?? '',
            'risk_score' => $riskScore,
            'ip_address' => $details['ip'] ?? '',
            'country' => $details['country'] ?? '',
            'device' => $details['device'] ?? '',
            'timestamp' => date('c'),
        ], $options);
    }
    
    /**
     * Queue webhook for async processing.
     */
    private function queueWebhook(string $url, array $payload, string $contentType): array
    {
        $webhookData = [
            'url' => $url,
            'payload' => $payload,
            'content_type' => $contentType,
            'created_at' => time(),
            'attempts' => 0,
        ];
        
        $this->redis->lpush('saips:webhook:queue', json_encode($webhookData));
        
        return [
            'success' => true,
            'message' => 'Webhook queued for delivery',
            'queued' => true,
        ];
    }
    
    /**
     * Process queued webhooks (run via cron).
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
            $webhookJson = $this->redis->rpop('saips:webhook:queue');
            if (!$webhookJson) break;
            
            $webhook = json_decode($webhookJson, true);
            $result = $this->sendNow(
                $webhook['url'],
                $webhook['payload'],
                $webhook['content_type']
            );
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                $errors[] = "Failed to send to {$webhook['url']}: {$result['error']}";
                
                // Re-queue with incremented attempt count (max retries)
                $webhook['attempts'] = ($webhook['attempts'] ?? 0) + 1;
                if ($webhook['attempts'] < $this->maxRetries) {
                    $this->redis->lpush('saips:webhook:queue', json_encode($webhook));
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
     * Validate webhook URL to prevent SSRF attacks.
     * Only allows HTTPS URLs to public routable addresses.
     */
    private function validateWebhookUrl(string $url): bool
    {
        // Must be HTTPS
        if (!str_starts_with($url, 'https://')) {
            return false;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }

        $host = $parsed['host'];

        // Block localhost and loopback
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        // Resolve host and block RFC-1918 / link-local / metadata ranges
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false; // unresolvable
        }
        $privateRanges = [
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            '169.254.0.0/16', '127.0.0.0/8', '::1/128', 'fc00::/7',
            '100.64.0.0/10', // CGNAT
        ];
        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $ip      = ip2long($ip);
        $subnet  = ip2long($subnet);
        $mask    = $bits === '32' ? -1 : ~((1 << (32 - (int)$bits)) - 1);
        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Send webhook immediately.
     */
    private function sendNow(string $url, array $payload, string $contentType): array
    {
        // SECURITY FIX: validate URL to prevent SSRF
        if (!$this->validateWebhookUrl($url)) {
            return [
                'success' => false,
                'error'   => 'Webhook URL failed SSRF validation (must be public HTTPS)',
                'code'    => 'SSRF_BLOCKED',
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: ' . $contentType,
                'User-Agent: Ownuh-SAIPS-Webhook/1.0',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            // Prevent redirect-based SSRF bypass
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => "cURL error: {$error}",
                'code' => 'CURL_ERROR',
            ];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Webhook delivered successfully',
                'http_code' => $httpCode,
            ];
        }
        
        return [
            'success' => false,
            'error' => "HTTP error: {$httpCode}",
            'code' => 'HTTP_ERROR',
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }
    
    /**
     * Format payload based on webhook type.
     */
    private function formatPayload(string $event, array $data, string $format): array
    {
        $formatter = self::FORMATS[$format]['formatter'] ?? 'formatGeneric';
        
        return $this->$formatter($event, $data);
    }
    
    /**
     * Format for Slack incoming webhook.
     */
    private function formatSlack(string $event, array $data): array
    {
        $color = $this->getSeverityColor($data['severity'] ?? 'info');
        $title = $this->getEventTitle($event);
        
        $fields = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $key !== 'severity') {
                $fields[] = [
                    'title' => ucwords(str_replace('_', ' ', $key)),
                    'value' => (string)$value,
                    'short' => strlen((string)$value) < 25,
                ];
            }
        }
        
        return [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $title,
                    'fields' => $fields,
                    'footer' => 'Ownuh SAIPS',
                    'ts' => time(),
                ]
            ]
        ];
    }
    
    /**
     * Format for Microsoft Teams.
     */
    private function formatTeams(string $event, array $data): array
    {
        $color = $this->getSeverityColor($data['severity'] ?? 'info');
        $title = $this->getEventTitle($event);
        
        $sections = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $key !== 'severity') {
                $sections[] = [
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'value' => (string)$value,
                ];
            }
        }
        
        return [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => str_replace('#', '', $color),
            'summary' => $title,
            'sections' => [
                [
                    'activityTitle' => $title,
                    'facts' => $sections,
                    'markdown' => true,
                ]
            ]
        ];
    }
    
    /**
     * Format for Discord.
     */
    private function formatDiscord(string $event, array $data): array
    {
        $color = hexdec(str_replace('#', '', $this->getSeverityColor($data['severity'] ?? 'info')));
        $title = $this->getEventTitle($event);
        
        $fields = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $key !== 'severity') {
                $fields[] = [
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'value' => (string)$value,
                    'inline' => strlen((string)$value) < 25,
                ];
            }
        }
        
        return [
            'embeds' => [
                [
                    'title' => $title,
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => [
                        'text' => 'Ownuh SAIPS',
                    ],
                    'timestamp' => date('c'),
                ]
            ]
        ];
    }
    
    /**
     * Generic JSON format.
     */
    private function formatGeneric(string $event, array $data): array
    {
        return [
            'event' => $event,
            'data' => $data,
            'timestamp' => date('c'),
            'source' => 'ownuh-saips',
        ];
    }
    
    /**
     * Get color based on severity.
     */
    private function getSeverityColor(?string $severity): string
    {
        return match($severity) {
            'sev1', 'critical' => '#dc3545',
            'sev2', 'high' => '#fd7e14',
            'sev3', 'medium' => '#ffc107',
            'sev4', 'low' => '#17a2b8',
            default => '#6c757d',
        };
    }
    
    /**
     * Get human-readable event title.
     */
    private function getEventTitle(string $event): string
    {
        return match($event) {
            'incident_created' => '🚨 New Security Incident',
            'incident_updated' => '📝 Incident Updated',
            'account_locked' => '🔒 Account Locked',
            'brute_force_detected' => '⚠️ Brute Force Attack Detected',
            'high_risk_login' => '🔐 High Risk Login Attempt',
            'mfa_bypass_issued' => '🔑 MFA Bypass Token Issued',
            'user_deleted' => '👤 User Deleted',
            default => '📢 ' . ucwords(str_replace('_', ' ', $event)),
        };
    }
}
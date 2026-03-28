<?php
/**
 * Ownuh SAIPS - AI Service
 * Generates executive posture reports from live security metrics.
 */

declare(strict_types=1);

namespace SAIPS\Services;

final class AIService
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? ($_ENV['OPENAI_API_KEY'] ?? null);
        $this->baseUrl = rtrim($config['base_url'] ?? ($_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1'), '/');
        $this->model = $config['model'] ?? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini');
    }

    public function generateExecutivePostureReport(array $snapshot): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'provider' => 'fallback',
                'model' => null,
                'report' => $this->buildFallbackReport($snapshot),
                'warning' => 'OPENAI_API_KEY is not configured. Generated a deterministic local report instead.',
            ];
        }

        try {
            $response = $this->requestExecutiveReport($snapshot);
            $report = $this->extractStructuredReport($response);

            return [
                'success' => true,
                'provider' => 'openai',
                'model' => $this->model,
                'report' => $report,
                'warning' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'provider' => 'fallback',
                'model' => null,
                'report' => $this->buildFallbackReport($snapshot),
                'warning' => 'AI generation was unavailable, so a local summary was generated instead.',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    private function requestExecutiveReport(array $snapshot): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is not available.');
        }

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'developer',
                    'content' => implode("\n", [
                        'You are a security strategy analyst writing for executives and board stakeholders.',
                        'Use the provided posture snapshot only.',
                        'Be concise, plain-English, and decision-oriented.',
                        'Do not invent metrics or incidents.',
                        'Call out both strengths and risks.',
                        'Recommendations must be specific and feasible within 30 days.',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => 'Generate an executive report for this organisation posture snapshot: ' . json_encode($snapshot, JSON_UNESCAPED_SLASHES),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'executive_posture_report',
                    'strict' => true,
                    'schema' => $this->reportSchema(),
                ],
            ],
            'max_output_tokens' => 1400,
        ];

        $ch = curl_init($this->baseUrl . '/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            throw new \RuntimeException('OpenAI request failed: ' . $curlError);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI returned a non-JSON response.');
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            throw new \RuntimeException('OpenAI error: ' . $message);
        }

        return $decoded;
    }

    private function extractStructuredReport(array $response): array
    {
        $text = null;

        foreach (($response['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                    $text = $contentItem['text'];
                    break 2;
                }
            }
        }

        if ($text === null && isset($response['output_text']) && is_string($response['output_text'])) {
            $text = $response['output_text'];
        }

        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Structured report payload was empty.');
        }

        $report = json_decode($text, true);
        if (!is_array($report)) {
            throw new \RuntimeException('Structured report payload was not valid JSON.');
        }

        return $report;
    }

    private function buildFallbackReport(array $snapshot): array
    {
        $securityScore = (int)($snapshot['security_score'] ?? 0);
        $complianceScore = (int)($snapshot['compliance_score'] ?? 0);
        $mfaCoverage = (int)($snapshot['users']['mfa_coverage'] ?? 0);
        $openIncidents = (int)($snapshot['incidents']['open_total'] ?? 0);
        $sev1Open = (int)($snapshot['incidents']['open_by_severity']['sev1'] ?? 0);
        $blockedIps = (int)($snapshot['ips']['blocked_ips_active'] ?? 0);
        $highRiskEvents = (int)($snapshot['auth']['high_risk_events_24h'] ?? 0);
        $failedControls = $snapshot['compliance']['failing_controls'] ?? [];

        $overall = 'Moderate';
        if ($securityScore >= 85 && $complianceScore >= 85 && $sev1Open === 0) {
            $overall = 'Strong';
        } elseif ($securityScore < 70 || $complianceScore < 70 || $sev1Open > 0) {
            $overall = 'Needs Attention';
        }

        $strengths = [
            'Core security controls are measurable through live dashboard, audit, IPS, and incident data.',
            'The platform shows active account protection through MFA coverage, session controls, and rate-limiting visibility.',
        ];
        if ($blockedIps > 0) {
            $strengths[] = 'Intrusion prevention controls are actively blocking suspicious IP traffic.';
        }

        $priorityRisks = [];
        if ($sev1Open > 0) {
            $priorityRisks[] = [
                'title' => 'Open critical incident exposure',
                'priority' => 'high',
                'impact' => $sev1Open . ' SEV-1 incident(s) remain open and may require executive oversight.',
                'recommendation' => 'Drive daily review until critical incidents are contained and formally resolved.',
            ];
        }
        if ($mfaCoverage < 100) {
            $priorityRisks[] = [
                'title' => 'Incomplete MFA coverage',
                'priority' => $mfaCoverage >= 80 ? 'medium' : 'high',
                'impact' => 'MFA coverage is currently ' . $mfaCoverage . '%, leaving some accounts more exposed to credential-based attacks.',
                'recommendation' => 'Target unenrolled privileged and high-risk users first, then complete organisation-wide MFA rollout.',
            ];
        }
        if ($highRiskEvents > 0) {
            $priorityRisks[] = [
                'title' => 'Elevated risky authentication activity',
                'priority' => 'medium',
                'impact' => $highRiskEvents . ' high-risk authentication event(s) were recorded in the last 24 hours.',
                'recommendation' => 'Review the related audit trail, isolate recurring sources, and tighten monitoring thresholds where needed.',
            ];
        }
        if (empty($priorityRisks) && !empty($failedControls)) {
            $firstControl = $failedControls[0];
            $priorityRisks[] = [
                'title' => $firstControl['control'] ?? 'Compliance gap',
                'priority' => 'medium',
                'impact' => $firstControl['detail'] ?? 'A live control check requires attention.',
                'recommendation' => 'Assign ownership and remediation dates for the identified control gap.',
            ];
        }

        $next30Days = [
            'Close or downgrade open critical-risk items with an owner, due date, and weekly executive status update.',
            'Raise MFA coverage toward full adoption, starting with admin and privileged accounts.',
            'Review high-risk authentication and blocked-IP patterns to tune alerting and response playbooks.',
        ];

        return [
            'report_title' => 'Executive Security Posture Report',
            'overall_posture' => $overall,
            'executive_summary' => sprintf(
                'The organisation currently shows a %s security posture with a security score of %d and a compliance score of %d. The environment benefits from live monitoring across authentication, incidents, IPS, and audit logging, while the most important near-term focus areas are open high-severity issues, MFA completion, and follow-through on controls that still require action.',
                strtolower($overall),
                $securityScore,
                $complianceScore
            ),
            'board_takeaways' => [
                'Security monitoring is active and producing measurable operational data for leadership review.',
                $openIncidents > 0 ? $openIncidents . ' incident(s) remain open and should stay visible at the management level.' : 'There are no open incidents requiring immediate executive escalation.',
                'Near-term improvement should focus on risk reduction through control completion rather than broad platform change.',
            ],
            'strengths' => $strengths,
            'priority_risks' => $priorityRisks,
            'key_metrics' => [
                ['label' => 'Security Score', 'value' => (string)$securityScore],
                ['label' => 'Compliance Score', 'value' => (string)$complianceScore . '%'],
                ['label' => 'MFA Coverage', 'value' => (string)$mfaCoverage . '%'],
                ['label' => 'Open Incidents', 'value' => (string)$openIncidents],
                ['label' => 'Blocked IPs', 'value' => (string)$blockedIps],
            ],
            'next_30_days' => $next30Days,
            'compliance_outlook' => empty($failedControls)
                ? 'Current control coverage appears broadly healthy, with attention mainly on recommended hardening items.'
                : 'Compliance posture is workable but includes live controls that require action before leadership can present a stronger assurance position.',
        ];
    }

    private function reportSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'report_title' => ['type' => 'string'],
                'overall_posture' => ['type' => 'string'],
                'executive_summary' => ['type' => 'string'],
                'board_takeaways' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'strengths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'priority_risks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'priority' => ['type' => 'string'],
                            'impact' => ['type' => 'string'],
                            'recommendation' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'priority', 'impact', 'recommendation'],
                    ],
                ],
                'key_metrics' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                        ],
                        'required' => ['label', 'value'],
                    ],
                ],
                'next_30_days' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'compliance_outlook' => ['type' => 'string'],
            ],
            'required' => [
                'report_title',
                'overall_posture',
                'executive_summary',
                'board_takeaways',
                'strengths',
                'priority_risks',
                'key_metrics',
                'next_30_days',
                'compliance_outlook',
            ],
        ];
    }
}

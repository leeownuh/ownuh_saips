<?php
/**
 * Ownuh SAIPS - AI Service
 * Generates executive posture reports and optional analyst narratives.
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
                'warning' => 'No OpenAI-compatible provider is configured. Generated a deterministic local report instead.',
            ];
        }

        try {
            $response = $this->requestExecutiveReport($snapshot);
            $report = $this->isGroq()
                ? $this->extractChatReport($response)
                : $this->extractStructuredReport($response);
            $report = $this->normalizeExecutiveReport($report, $snapshot);

            return [
                'success' => true,
                'provider' => $this->isGroq() ? 'groq' : 'openai',
                'model' => $this->model,
                'report' => $report,
                'warning' => null,
            ];
        } catch (\Throwable $e) {
            $this->logAiFailure('executive_posture_report', $e);
            return [
                'success' => true,
                'provider' => 'fallback',
                'model' => null,
                'report' => $this->buildFallbackReport($snapshot),
                'warning' => $this->buildUnavailableWarning(
                    'External AI generation was unavailable or quota-limited, so a local summary was generated instead.',
                    $e
                ),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function generateAttributionExplanation(array $case, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'provider' => 'fallback',
                'model' => null,
                'analysis' => $this->buildFallbackAttributionExplanation($case, $context),
                'warning' => 'No OpenAI-compatible provider is configured. Generated a deterministic local analyst note instead.',
            ];
        }

        try {
            $response = $this->requestAttributionExplanation($case, $context);
            $analysis = $this->isGroq()
                ? $this->extractChatReport($response)
                : $this->extractStructuredReport($response);
            $analysis = $this->normalizeAttributionAnalysis($analysis, $case, $context);

            return [
                'success' => true,
                'provider' => $this->isGroq() ? 'groq' : 'openai',
                'model' => $this->model,
                'analysis' => $analysis,
                'warning' => null,
            ];
        } catch (\Throwable $e) {
            $this->logAiFailure('attribution_case_narrative', $e);
            return [
                'success' => true,
                'provider' => 'fallback',
                'model' => null,
                'analysis' => $this->buildFallbackAttributionExplanation($case, $context),
                'warning' => $this->buildUnavailableWarning(
                    'External AI generation was unavailable or quota-limited, so a local analyst note was generated instead.',
                    $e
                ),
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
        $promptSnapshot = $this->compactExecutiveSnapshot($snapshot);

        return $this->requestJsonSchema(
            'executive_posture_report',
            $this->reportSchema(),
            [
                'You are a security strategy analyst writing for executives and board stakeholders.',
                'Use the provided posture snapshot only.',
                'Be concise, plain-English, and decision-oriented.',
                'Do not invent metrics or incidents.',
                'Call out both strengths and risks.',
                'Recommendations must be specific and feasible within 30 days.',
                'Return a JSON object with exactly these top-level keys: report_title, overall_posture, executive_summary, board_takeaways, strengths, priority_risks, key_metrics, next_30_days, compliance_outlook.',
                'Each priority_risks item must contain title, priority, impact, recommendation.',
                'Each key_metrics item must contain label and value.',
            ],
            'Generate an executive report for this organisation posture snapshot: ' . json_encode($promptSnapshot, JSON_UNESCAPED_SLASHES),
            $this->executiveMaxTokens()
        );
    }

    private function requestAttributionExplanation(array $case, array $context): array
    {
        $payload = [
            'case' => [
                'case_id' => $case['case_id'] ?? null,
                'entity_type' => $case['entity_type'] ?? null,
                'entity_id' => $case['entity_id'] ?? null,
                'attack_label' => $case['attack_label'] ?? null,
                'severity' => $case['severity'] ?? null,
                'risk_score' => $case['risk_score'] ?? null,
                'summary' => $case['summary'] ?? null,
                'local_explanation' => $case['explanation'] ?? null,
                'source_signals' => $case['source_signals'] ?? [],
                'evidence' => $this->compactAttributionEvidence($case['evidence'] ?? []),
                'related_entities' => $this->compactRelatedEntities($case['related_entities'] ?? []),
                'recommended_actions' => array_slice($case['recommended_actions'] ?? [], 0, 2),
                'supporting_events' => array_slice($case['supporting_events'] ?? [], 0, 2),
                'related_incidents' => array_slice($case['related_incidents'] ?? [], 0, 2),
            ],
            'context' => [
                'window' => $context['window'] ?? [],
                'summary' => $this->compactAttributionSummary($context['summary'] ?? []),
                'signals' => $this->compactAttributionSignals($context['signals'] ?? []),
            ],
        ];

        return $this->requestJsonSchema(
            'attribution_case_narrative',
            $this->attributionSchema(),
            [
                'You are a cybersecurity triage analyst explaining an attack-attribution case to human defenders.',
                'Use only the supplied case details and pipeline signals.',
                'Do not invent evidence, entities, malware names, MITRE mappings, or incident history.',
                'Keep the writing concise, operational, and suitable for a SOC dashboard.',
                'If the evidence is weak or ambiguous, explicitly say so and set is_low_confidence to true.',
                'recommended_next_step must be a single concrete next analyst action.',
                'Return a JSON object with exactly these keys: analyst_summary, attribution_rationale, confidence_statement, triage_note, recommended_next_step, is_low_confidence.',
            ],
            'Explain this attack attribution case: ' . json_encode($payload, JSON_UNESCAPED_SLASHES),
            $this->attributionMaxTokens()
        );
    }

    private function requestJsonSchema(
        string $schemaName,
        array $schema,
        array $systemInstructions,
        string $userContent,
        int $maxTokens
    ): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is not available.');
        }

        $isGroq = $this->isGroq();
        if ($isGroq) {
            $payload = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            ...$systemInstructions,
                            'Return valid JSON only.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => $maxTokens,
            ];
            $endpoint = '/chat/completions';
        } else {
            $payload = [
                'model' => $this->model,
                'input' => [
                    [
                        'role' => 'developer',
                        'content' => implode("\n", $systemInstructions),
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $schemaName,
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
                'max_output_tokens' => $maxTokens,
            ];
            $endpoint = '/responses';
        }

        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 10,
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

    private function extractChatReport(array $response): array
    {
        $text = $this->extractChatContentText($response);
        if (!is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Chat completion payload was empty.');
        }

        return $this->decodeJsonText($text, 'Chat completion payload was not valid JSON.');
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

        return $this->decodeJsonText($text, 'Structured report payload was not valid JSON.');
    }

    private function extractChatContentText(array $response): ?string
    {
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                    continue;
                }
                if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                    $parts[] = $item['text'];
                }
            }

            $joined = trim(implode("\n", $parts));
            return $joined !== '' ? $joined : null;
        }

        return null;
    }

    private function decodeJsonText(string $text, string $failureMessage): array
    {
        $candidate = $this->extractJsonCandidate($text);
        $decoded = json_decode($candidate, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException($failureMessage);
        }

        return $decoded;
    }

    private function extractJsonCandidate(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\}|\[.*\])\s*```/is', $trimmed, $matches) === 1) {
            return trim((string)$matches[1]);
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            return substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $firstBracket = strpos($trimmed, '[');
        $lastBracket = strrpos($trimmed, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            return substr($trimmed, $firstBracket, $lastBracket - $firstBracket + 1);
        }

        return $trimmed;
    }

    private function isGroq(): bool
    {
        return stripos($this->baseUrl, 'groq.com') !== false;
    }

    private function executiveMaxTokens(): int
    {
        return $this->isGroq() ? 650 : 1400;
    }

    private function attributionMaxTokens(): int
    {
        return $this->isGroq() ? 320 : 900;
    }

    private function compactExecutiveSnapshot(array $snapshot): array
    {
        return [
            'security_score' => (int)($snapshot['security_score'] ?? 0),
            'compliance_score' => (int)($snapshot['compliance_score'] ?? 0),
            'users' => [
                'total' => (int)($snapshot['users']['total'] ?? 0),
                'mfa_coverage' => (int)($snapshot['users']['mfa_coverage'] ?? 0),
            ],
            'auth' => [
                'high_risk_events_24h' => (int)($snapshot['auth']['high_risk_events_24h'] ?? 0),
                'failed_logins_24h' => (int)($snapshot['auth']['failed_logins_24h'] ?? 0),
                'successful_logins_24h' => (int)($snapshot['auth']['successful_logins_24h'] ?? 0),
            ],
            'incidents' => [
                'open_total' => (int)($snapshot['incidents']['open_total'] ?? 0),
                'open_by_severity' => $snapshot['incidents']['open_by_severity'] ?? [],
            ],
            'ips' => [
                'blocked_ips_active' => (int)($snapshot['ips']['blocked_ips_active'] ?? 0),
            ],
            'compliance' => [
                'failing_controls' => array_slice($snapshot['compliance']['failing_controls'] ?? [], 0, 4),
            ],
        ];
    }

    private function compactAttributionEvidence(array $evidence): array
    {
        $keys = [
            'event_count',
            'failed_count',
            'success_count',
            'unique_ips',
            'unique_users',
            'unique_devices',
            'unique_countries',
            'avg_risk',
            'blocked',
            'last_seen',
        ];
        $compact = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $evidence)) {
                $compact[$key] = $evidence[$key];
            }
        }

        return $compact;
    }

    private function compactRelatedEntities(array $relatedEntities): array
    {
        $compact = [];
        foreach ($relatedEntities as $key => $values) {
            if (!is_array($values)) {
                continue;
            }
            $compact[$key] = array_slice(array_values($values), 0, 3);
        }

        return $compact;
    }

    private function compactAttributionSummary(array $summary): array
    {
        return [
            'total_cases' => (int)($summary['total_cases'] ?? 0),
            'linked_incidents' => (int)($summary['linked_incidents'] ?? 0),
            'top_case_risk' => (int)($summary['top_case_risk'] ?? 0),
            'avg_case_risk' => (float)($summary['avg_case_risk'] ?? 0.0),
        ];
    }

    private function compactAttributionSignals(array $signals): array
    {
        return [
            'anomaly_engine' => $signals['anomaly_engine'] ?? 'unknown',
            'attack_engine' => $signals['attack_engine'] ?? 'unknown',
            'entity_engine' => $signals['entity_engine'] ?? 'unknown',
            'anomalies_flagged' => (int)($signals['anomalies_flagged'] ?? 0),
            'attacks_flagged' => (int)($signals['attacks_flagged'] ?? 0),
            'entities_flagged' => (int)($signals['entities_flagged'] ?? 0),
        ];
    }

    private function normalizeExecutiveReport(array $report, array $snapshot): array
    {
        $fallback = $this->buildFallbackReport($snapshot);

        $priorityRisks = [];
        foreach (($report['priority_risks'] ?? []) as $risk) {
            if (!is_array($risk)) {
                continue;
            }
            $priorityRisks[] = [
                'title' => (string)($risk['title'] ?? 'Risk'),
                'priority' => (string)($risk['priority'] ?? 'medium'),
                'impact' => (string)($risk['impact'] ?? ''),
                'recommendation' => (string)($risk['recommendation'] ?? ''),
            ];
        }

        $keyMetrics = [];
        foreach (($report['key_metrics'] ?? []) as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $keyMetrics[] = [
                'label' => (string)($metric['label'] ?? 'Metric'),
                'value' => (string)($metric['value'] ?? ''),
            ];
        }

        return [
            'report_title' => $this->nonEmptyString($report['report_title'] ?? null, (string)$fallback['report_title']),
            'overall_posture' => $this->nonEmptyString($report['overall_posture'] ?? null, (string)$fallback['overall_posture']),
            'executive_summary' => $this->nonEmptyString($report['executive_summary'] ?? null, (string)$fallback['executive_summary']),
            'board_takeaways' => $this->normalizeStringList($report['board_takeaways'] ?? null, $fallback['board_takeaways']),
            'strengths' => $this->normalizeStringList($report['strengths'] ?? null, $fallback['strengths']),
            'priority_risks' => $priorityRisks !== [] ? $priorityRisks : $fallback['priority_risks'],
            'key_metrics' => $keyMetrics !== [] ? $keyMetrics : $fallback['key_metrics'],
            'next_30_days' => $this->normalizeStringList($report['next_30_days'] ?? null, $fallback['next_30_days']),
            'compliance_outlook' => $this->nonEmptyString($report['compliance_outlook'] ?? null, (string)$fallback['compliance_outlook']),
        ];
    }

    private function normalizeAttributionAnalysis(array $analysis, array $case, array $context): array
    {
        $fallback = $this->buildFallbackAttributionExplanation($case, $context);

        return [
            'analyst_summary' => $this->nonEmptyString($analysis['analyst_summary'] ?? null, (string)$fallback['analyst_summary']),
            'attribution_rationale' => $this->nonEmptyString($analysis['attribution_rationale'] ?? null, (string)$fallback['attribution_rationale']),
            'confidence_statement' => $this->nonEmptyString($analysis['confidence_statement'] ?? null, (string)$fallback['confidence_statement']),
            'triage_note' => $this->nonEmptyString($analysis['triage_note'] ?? null, (string)$fallback['triage_note']),
            'recommended_next_step' => $this->nonEmptyString($analysis['recommended_next_step'] ?? null, (string)$fallback['recommended_next_step']),
            'is_low_confidence' => (bool)($analysis['is_low_confidence'] ?? $fallback['is_low_confidence']),
        ];
    }

    private function normalizeStringList(mixed $value, array $fallback): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $items[] = trim($item);
        }

        return $items !== [] ? $items : $fallback;
    }

    private function nonEmptyString(mixed $value, string $fallback): string
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return trim($value);
    }

    private function buildUnavailableWarning(string $baseMessage, \Throwable $e): string
    {
        if ($this->canRevealAiError()) {
            return $baseMessage . ' Provider detail: ' . $e->getMessage();
        }

        return $baseMessage;
    }

    private function canRevealAiError(): bool
    {
        $isDevelopment = function_exists('app_is_development') && \app_is_development();
        $isDemo = function_exists('app_is_demo_mode') && \app_is_demo_mode();

        return $isDevelopment || $isDemo;
    }

    private function logAiFailure(string $operation, \Throwable $e): void
    {
        error_log(sprintf(
            '[SAIPS AI] %s failed via %s (%s): %s',
            $operation,
            $this->baseUrl,
            $this->model,
            $e->getMessage()
        ));
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

    private function buildFallbackAttributionExplanation(array $case, array $context): array
    {
        $riskScore = (int)($case['risk_score'] ?? 0);
        $attackLabel = strtolower(str_replace('_', ' ', (string)($case['attack_label'] ?? 'anomalous behavior')));
        $sourceSignals = $case['source_signals'] ?? [];
        $recommendedAction = (string)(($case['recommended_actions'][0] ?? 'Review the supporting events before escalating the case.'));
        $graphScore = (float)($sourceSignals['graph_score'] ?? 0.0);
        $anomalyScore = (float)($sourceSignals['anomaly_score'] ?? 0.0);
        $attackConfidence = (float)($sourceSignals['attack_confidence'] ?? 0.0);
        $linkedIncidents = (int)($context['summary']['linked_incidents'] ?? 0);
        $isLowConfidence = $riskScore < 65 || max($graphScore, $anomalyScore, $attackConfidence) < 0.6;

        $confidenceStatement = $isLowConfidence
            ? 'Confidence is moderate to low because the fused scores suggest drift or incomplete context rather than a fully corroborated attack chain.'
            : 'Confidence is stronger because graph, anomaly, and attack signals point in the same direction for this case.';

        $triageNote = sprintf(
            'Prioritise this %s case if the same entity reappears in the next analyst window or if related incidents are still open (%d linked incident references considered in the current summary).',
            $attackLabel,
            $linkedIncidents
        );

        return [
            'analyst_summary' => (string)($case['summary'] ?? 'Suspicious attribution case surfaced from fused security telemetry.'),
            'attribution_rationale' => (string)($case['explanation'] ?? 'The local attribution engine fused graph, anomaly, and attack signals to produce this case.'),
            'confidence_statement' => $confidenceStatement,
            'triage_note' => $triageNote,
            'recommended_next_step' => $recommendedAction,
            'is_low_confidence' => $isLowConfidence,
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

    private function attributionSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'analyst_summary' => ['type' => 'string'],
                'attribution_rationale' => ['type' => 'string'],
                'confidence_statement' => ['type' => 'string'],
                'triage_note' => ['type' => 'string'],
                'recommended_next_step' => ['type' => 'string'],
                'is_low_confidence' => ['type' => 'boolean'],
            ],
            'required' => [
                'analyst_summary',
                'attribution_rationale',
                'confidence_statement',
                'triage_note',
                'recommended_next_step',
                'is_low_confidence',
            ],
        ];
    }
}

<?php
declare(strict_types=1);

namespace SAIPS\Services;

final class MLService
{
    private string $mlServiceDir;
    private \Database $db;
    private array $pythonCandidates;
    private ?string $resolvedPythonCommand = null;

    public function __construct(?\Database $db = null, ?string $pythonPath = null)
    {
        $this->db = $db ?? \Database::getInstance();
        $this->mlServiceDir = realpath(__DIR__ . '/../ml_service') ?: (__DIR__ . '/../ml_service');

        $configuredPython = trim((string)($pythonPath ?? ($_ENV['ML_PYTHON_BIN'] ?? ($_ENV['PYTHON_BIN'] ?? ''))));
        $this->pythonCandidates = array_values(array_unique(array_filter([
            $configuredPython !== '' ? $configuredPython : null,
            'python3',
            'python',
            'py -3',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            'C:\\Program Files\\Python311\\python.exe',
            'C:\\Program Files\\Python310\\python.exe',
        ], static fn(mixed $value): bool => is_string($value) && trim($value) !== '')));
    }

    public function detectAnomalies(?string $dateFrom = null, ?string $dateTo = null, int $limit = 1000): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, $limit);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'No audit events to analyze.', 'anomalies' => []];
        }

        $result = $this->predictAnomalies($events);
        $result['events_analyzed'] = count($events);

        return $result;
    }

    public function trainAnomalyDetector(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, 5000);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'Insufficient data for training.'];
        }

        return $this->execPythonModule('anomaly_detector.py', 'train', ['events' => $events]);
    }

    public function detectAttacks(?string $dateFrom = null, ?string $dateTo = null, int $limit = 1000): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, $limit);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'No events to analyze.', 'attacks' => []];
        }

        $result = $this->predictAttacks($events);
        $result['events_analyzed'] = count($events);

        return $result;
    }

    public function trainAttackDetector(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, 5000);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'Insufficient data for training.'];
        }

        return $this->execPythonModule('attack_detector.py', 'train', ['events' => $events]);
    }

    public function detectCompromisedEntities(?string $dateFrom = null, ?string $dateTo = null, int $limit = 2000): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, $limit);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'No events to analyze.', 'compromised_entities' => []];
        }

        $result = $this->predictEntities($events);
        $result['events_analyzed'] = count($events);

        return $result;
    }

    public function analyzeEntityGraph(?string $dateFrom = null, ?string $dateTo = null, int $limit = 2000): array
    {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, $limit);
        if ($events === []) {
            return ['status' => 'error', 'message' => 'No events to analyze.'];
        }

        $result = $this->metricEntityGraph($events);
        $result['events_analyzed'] = count($events);

        return $result;
    }

    public function analyzeAttackAttribution(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 1200,
        bool $withLlm = false
    ): array {
        $events = $this->fetchAuditEvents($dateFrom, $dateTo, $limit);
        if ($events === []) {
            return [
                'status' => 'error',
                'message' => 'No audit events were available for attack attribution.',
                'cases' => [],
            ];
        }

        $blockedIps = $this->fetchBlockedIps($dateFrom, $dateTo, 250);
        $incidents = $this->fetchIncidents($dateFrom, $dateTo, 120);
        $anomalyResult = $this->predictAnomalies($events);
        $attackResult = $this->predictAttacks($events);
        $entityResult = $this->predictEntities($events);
        $graphMetrics = $this->metricEntityGraph($events);
        $behavior = $this->buildBehaviorSummaries($events, $blockedIps);

        $cases = $this->buildAttributionCases(
            $events,
            $behavior,
            $blockedIps,
            $incidents,
            $anomalyResult['anomalies'] ?? [],
            $attackResult['attacks'] ?? [],
            $entityResult['compromised_entities'] ?? []
        );

        $signals = [
            'anomaly_engine' => $anomalyResult['engine'] ?? 'unknown',
            'attack_engine' => $attackResult['engine'] ?? 'unknown',
            'entity_engine' => $entityResult['engine'] ?? 'unknown',
            'anomalies_flagged' => count($anomalyResult['anomalies'] ?? []),
            'attacks_flagged' => count($attackResult['attacks'] ?? []),
            'entities_flagged' => count($entityResult['compromised_entities'] ?? []),
            'graph_metrics' => $graphMetrics['graph_stats'] ?? [],
        ];
        $summary = $this->summarizeAttributionCases($cases, $events, $blockedIps, $incidents);
        $llm = [
            'requested' => $withLlm,
            'available' => false,
            'applied' => false,
            'mode' => 'local_explanation',
            'provider' => 'fallback',
            'model' => null,
            'cases_considered' => 0,
            'cases_enriched' => 0,
            'warning' => null,
        ];

        if ($withLlm && $cases !== []) {
            $enrichment = $this->enrichAttributionCasesWithLlm($cases, [
                'window' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => $limit],
                'summary' => $summary,
                'signals' => $signals,
            ]);
            $cases = $enrichment['cases'];
            $llm = array_merge($llm, $enrichment['meta']);
        }

        return [
            'status' => 'success',
            'generated_at' => date('c'),
            'window' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => $limit],
            'events_analyzed' => count($events),
            'cases' => $cases,
            'summary' => $summary,
            'signals' => $signals,
            'llm' => $llm,
        ];
    }

    private function fetchAuditEvents(?string $dateFrom = null, ?string $dateTo = null, int $limit = 1000): array
    {
        $query = 'SELECT
                    al.id, al.event_code, al.event_name, al.user_id, al.source_ip, al.user_agent,
                    al.country_code, al.region, al.device_fingerprint, al.mfa_method, al.risk_score,
                    al.details, al.admin_id, al.target_user_id, al.created_at,
                    u.display_name, u.email, u.role AS user_role
                FROM audit_log al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE 1=1';
        $params = [];
        $types = '';

        if ($dateFrom) {
            $query .= ' AND al.created_at >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo) {
            $query .= ' AND al.created_at <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $query .= ' ORDER BY al.created_at DESC LIMIT ?';
        $params[] = max(50, $limit);
        $types .= 'i';

        try {
            $rows = $this->db->fetchAll($query, $params, $types);
        } catch (\Throwable $e) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            $details = $row['details'] ?? [];
            if (is_string($details)) {
                $details = json_decode($details, true) ?? [];
            }
            if (!is_array($details)) {
                $details = [];
            }

            $derivedUserId = trim((string)($row['user_id'] ?? ''));
            if ($derivedUserId === '') {
                $derivedUserId = trim((string)($details['username'] ?? ($row['target_user_id'] ?? '')));
            }
            if ($derivedUserId === '') {
                $derivedUserId = 'unknown';
            }

            $events[] = [
                'id' => (int)($row['id'] ?? 0),
                'event_code' => (string)($row['event_code'] ?? ''),
                'event_name' => (string)($row['event_name'] ?? ''),
                'user_id' => $derivedUserId,
                'source_ip' => (string)($row['source_ip'] ?? ''),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'country_code' => (string)($row['country_code'] ?? 'XX'),
                'region' => (string)($row['region'] ?? ''),
                'device_fingerprint' => (string)($row['device_fingerprint'] ?? ''),
                'mfa_method' => (string)($row['mfa_method'] ?? 'none'),
                'risk_score' => (int)($row['risk_score'] ?? 0),
                'details' => $details,
                'created_at' => (string)($row['created_at'] ?? ''),
                'display_name' => (string)($row['display_name'] ?? ''),
                'email' => (string)($row['email'] ?? ($details['username'] ?? '')),
                'role' => (string)($row['user_role'] ?? ''),
                'admin_id' => (string)($row['admin_id'] ?? ''),
                'target_user_id' => (string)($row['target_user_id'] ?? ''),
            ];
        }

        return $events;
    }

    private function fetchBlockedIps(?string $dateFrom = null, ?string $dateTo = null, int $limit = 200): array
    {
        $query = 'SELECT ip_address, block_type, trigger_rule, country_code, threat_feed, blocked_at, expires_at
                  FROM blocked_ips
                  WHERE unblocked_at IS NULL';
        $params = [];
        $types = '';

        if ($dateFrom) {
            $query .= ' AND blocked_at >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo) {
            $query .= ' AND blocked_at <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $query .= ' ORDER BY blocked_at DESC LIMIT ?';
        $params[] = $limit;
        $types .= 'i';

        try {
            return $this->db->fetchAll($query, $params, $types);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchIncidents(?string $dateFrom = null, ?string $dateTo = null, int $limit = 100): array
    {
        $query = 'SELECT incident_ref, severity, status, trigger_summary, affected_user_id, source_ip, detected_at, related_audit_entries
                  FROM incidents
                  WHERE 1=1';
        $params = [];
        $types = '';

        if ($dateFrom) {
            $query .= ' AND detected_at >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo) {
            $query .= ' AND detected_at <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $query .= ' ORDER BY detected_at DESC LIMIT ?';
        $params[] = $limit;
        $types .= 'i';

        try {
            $rows = $this->db->fetchAll($query, $params, $types);
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as &$row) {
            if (is_string($row['related_audit_entries'] ?? null)) {
                $row['related_audit_entries'] = json_decode((string)$row['related_audit_entries'], true) ?? [];
            }
        }
        unset($row);

        return $rows;
    }

    private function predictAnomalies(array $events): array
    {
        $pythonResult = $this->execPythonModule('anomaly_detector.py', 'predict', ['events' => $events]);
        if ($this->shouldUsePythonResult($pythonResult, 'anomalies')) {
            $pythonResult['status'] = 'success';
            $pythonResult['engine'] = 'python_model';
            return $pythonResult;
        }

        $fallback = $this->buildHeuristicAnomalies($events);
        $fallback['engine'] = 'heuristic_fallback';
        if (($pythonResult['message'] ?? '') !== '') {
            $fallback['warning'] = $pythonResult['message'];
        }

        return $fallback;
    }

    private function predictAttacks(array $events): array
    {
        $pythonResult = $this->execPythonModule('attack_detector.py', 'predict', ['events' => $events]);
        if ($this->shouldUsePythonResult($pythonResult, 'attacks')) {
            $pythonResult['status'] = 'success';
            $pythonResult['engine'] = 'python_model';
            return $pythonResult;
        }

        $fallback = $this->buildHeuristicAttacks($events);
        $fallback['engine'] = 'heuristic_fallback';
        if (($pythonResult['message'] ?? '') !== '') {
            $fallback['warning'] = $pythonResult['message'];
        }

        return $fallback;
    }

    private function predictEntities(array $events): array
    {
        $pythonResult = $this->execPythonModule('entity_correlation.py', 'detect', ['events' => $events]);
        if ($this->shouldUsePythonResult($pythonResult, 'compromised_entities')) {
            $pythonResult['status'] = 'success';
            $pythonResult['engine'] = 'python_model';
            return $pythonResult;
        }

        $fallback = $this->buildHeuristicEntities($events);
        $fallback['engine'] = 'heuristic_fallback';
        if (($pythonResult['message'] ?? '') !== '') {
            $fallback['warning'] = $pythonResult['message'];
        }

        return $fallback;
    }

    private function metricEntityGraph(array $events): array
    {
        $pythonResult = $this->execPythonModule('entity_correlation.py', 'metrics', ['events' => $events]);
        if (($pythonResult['status'] ?? '') !== 'error' && isset($pythonResult['graph_stats'])) {
            $pythonResult['status'] = 'success';
            $pythonResult['engine'] = 'python_model';
            return $pythonResult;
        }

        $behavior = $this->buildBehaviorSummaries($events, []);
        return [
            'status' => 'success',
            'engine' => 'heuristic_fallback',
            'graph_stats' => [
                'nodes' => count($behavior['users']) + count($behavior['ips']) + count($behavior['devices']),
                'edges' => count($events) * 2,
                'node_types' => [
                    'user' => count($behavior['users']),
                    'ip' => count($behavior['ips']),
                    'device' => count($behavior['devices']),
                ],
            ],
            'summary' => [
                'num_components' => max(count($behavior['users']), count($behavior['ips']), 0),
                'largest_component_size' => max(count($behavior['users']), count($behavior['ips']), count($behavior['devices']), 0),
            ],
        ];
    }

    private function shouldUsePythonResult(array $result, string $collectionKey): bool
    {
        if (($result['status'] ?? '') === 'error') {
            return false;
        }
        $warning = strtolower((string)($result['summary']['warning'] ?? ''));
        if (str_contains($warning, 'not trained')) {
            return false;
        }

        return array_key_exists($collectionKey, $result);
    }

    private function buildHeuristicAnomalies(array $events): array
    {
        $behavior = $this->buildBehaviorSummaries($events, []);
        $anomalies = [];

        foreach ($behavior['users'] as $userId => $summary) {
            $score = min(1.0,
                ($summary['failed_count'] * 0.06) +
                ($summary['unique_ips_count'] * 0.09) +
                ($summary['unique_countries_count'] * 0.12) +
                ($summary['high_risk_events'] * 0.08) +
                (($summary['avg_risk'] ?? 0.0) / 100.0 * 0.35) +
                ($summary['mfa_events'] > 0 ? 0.05 : 0.0)
            );

            if ($score < 0.45) {
                continue;
            }

            $anomalies[] = [
                'user_id' => $userId,
                'anomaly_score' => round($score, 4),
                'risk_level' => $this->riskLevelFromNormalized($score),
                'isolation_forest_anomaly' => $score >= 0.55,
                'pca_anomaly' => $summary['unique_countries_count'] >= 2 || $summary['unique_devices_count'] >= 3,
            ];
        }

        usort($anomalies, static fn(array $left, array $right): int => $right['anomaly_score'] <=> $left['anomaly_score']);

        return [
            'status' => 'success',
            'anomalies' => $anomalies,
            'summary' => [
                'total_users' => count($behavior['users']),
                'anomalous_users' => count($anomalies),
                'anomaly_rate' => count($behavior['users']) > 0 ? count($anomalies) / count($behavior['users']) : 0.0,
                'models_used' => ['heuristic_risk_fusion'],
            ],
        ];
    }

    private function buildHeuristicAttacks(array $events): array
    {
        $behavior = $this->buildBehaviorSummaries($events, []);
        $attacks = [];

        foreach ($behavior['users'] as $userId => $summary) {
            $attackType = null;
            $confidence = 0.0;

            if ($this->containsAnyEventCode($summary, ['AUTH-017', 'AUTH-018'])) {
                $attackType = 'MFA_BYPASS';
                $confidence = 0.91;
            } elseif ($summary['failed_count'] >= 6 && $summary['unique_ips_count'] >= 3) {
                $attackType = 'DISTRIBUTED';
                $confidence = min(0.96, 0.55 + ($summary['failed_count'] * 0.03));
            } elseif ($summary['failed_count'] >= 8) {
                $attackType = 'BRUTE_FORCE';
                $confidence = min(0.93, 0.52 + ($summary['failed_count'] * 0.025));
            } elseif ($summary['unique_countries_count'] >= 2 && $summary['success_count'] >= 1 && $summary['avg_risk'] >= 40) {
                $attackType = 'ACCOUNT_TAKEOVER';
                $confidence = min(0.89, 0.5 + ($summary['avg_risk'] / 200));
            }

            if ($attackType === null) {
                continue;
            }

            $attacks[] = [
                'user_id' => $userId,
                'attack_type' => $attackType,
                'confidence' => round($confidence, 4),
                'probabilities' => [$attackType => round($confidence, 4), 'NORMAL' => round(max(0.0, 1.0 - $confidence), 4)],
            ];
        }

        foreach ($behavior['ips'] as $ip => $summary) {
            if ($summary['failed_count'] < 6 || $summary['unique_users_count'] < 3) {
                continue;
            }
            $confidence = min(0.97, 0.6 + ($summary['failed_count'] * 0.025));
            $attacks[] = [
                'user_id' => 'ip:' . $ip,
                'attack_type' => 'CREDENTIAL_STUFFING',
                'confidence' => round($confidence, 4),
                'probabilities' => ['CREDENTIAL_STUFFING' => round($confidence, 4), 'NORMAL' => round(max(0.0, 1.0 - $confidence), 4)],
            ];
        }

        usort($attacks, static fn(array $left, array $right): int => $right['confidence'] <=> $left['confidence']);
        $counts = [];
        foreach ($attacks as $attack) {
            $label = (string)($attack['attack_type'] ?? 'UNKNOWN');
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return ['status' => 'success', 'attacks' => $attacks, 'summary' => ['total_events' => count($events), 'attacks_detected' => count($attacks), 'attack_counts' => $counts]];
    }

    private function buildHeuristicEntities(array $events): array
    {
        $behavior = $this->buildBehaviorSummaries($events, []);
        $entities = [];

        foreach ($behavior['users'] as $userId => $summary) {
            $score = min(1.0,
                ($summary['unique_ips_count'] * 0.12) +
                ($summary['unique_countries_count'] * 0.18) +
                ($summary['failed_count'] * 0.05) +
                (($summary['avg_risk'] ?? 0.0) / 100.0 * 0.28)
            );
            if ($score >= 0.55) {
                $entities[] = [
                    'entity_id' => 'user:' . $userId,
                    'entity_type' => 'user',
                    'suspicion_score' => round($score, 4),
                    'reason' => sprintf('Observed %d IPs, %d countries, and %d failed events.', $summary['unique_ips_count'], $summary['unique_countries_count'], $summary['failed_count']),
                ];
            }
        }

        foreach ($behavior['ips'] as $ip => $summary) {
            $score = min(1.0,
                ($summary['failed_count'] * 0.08) +
                ($summary['unique_users_count'] * 0.15) +
                ($summary['blocked'] ? 0.25 : 0.0) +
                (($summary['avg_risk'] ?? 0.0) / 100.0 * 0.25)
            );
            if ($score >= 0.5) {
                $entities[] = [
                    'entity_id' => 'ip:' . $ip,
                    'entity_type' => 'ip',
                    'suspicion_score' => round($score, 4),
                    'reason' => sprintf('IP touched %d users and produced %d failed events.', $summary['unique_users_count'], $summary['failed_count']),
                ];
            }
        }

        foreach ($behavior['devices'] as $device => $summary) {
            $score = min(1.0,
                ($summary['unique_users_count'] * 0.2) +
                ($summary['unique_ips_count'] * 0.12) +
                ($summary['unique_countries_count'] * 0.14) +
                (($summary['avg_risk'] ?? 0.0) / 100.0 * 0.2)
            );
            if ($score >= 0.55) {
                $entities[] = [
                    'entity_id' => 'device:' . $device,
                    'entity_type' => 'device',
                    'suspicion_score' => round($score, 4),
                    'reason' => sprintf('Device linked to %d users and %d IPs.', $summary['unique_users_count'], $summary['unique_ips_count']),
                ];
            }
        }

        usort($entities, static fn(array $left, array $right): int => $right['suspicion_score'] <=> $left['suspicion_score']);

        return ['status' => 'success', 'compromised_entities' => $entities, 'summary' => ['total_entities' => count($behavior['users']) + count($behavior['ips']) + count($behavior['devices']), 'compromised_count' => count($entities)]];
    }

    private function buildBehaviorSummaries(array $events, array $blockedIps): array
    {
        $blockedIndex = [];
        foreach ($blockedIps as $blockedIp) {
            $ip = trim((string)($blockedIp['ip_address'] ?? ''));
            if ($ip !== '') {
                $blockedIndex[$ip] = $blockedIp;
            }
        }

        $users = [];
        $ips = [];
        $devices = [];

        foreach ($events as $event) {
            $userId = trim((string)($event['user_id'] ?? ''));
            $ip = trim((string)($event['source_ip'] ?? ''));
            $device = trim((string)($event['device_fingerprint'] ?? ''));
            $country = trim((string)($event['country_code'] ?? ''));
            $risk = (int)($event['risk_score'] ?? 0);
            $eventCode = strtoupper((string)($event['event_code'] ?? ''));
            $username = trim((string)($event['details']['username'] ?? ($event['email'] ?? '')));

            if ($userId !== '' && $userId !== 'unknown') {
                if (!isset($users[$userId])) {
                    $users[$userId] = $this->newUserSummary($event);
                }
                $users[$userId]['event_count']++;
                $users[$userId]['risk_total'] += $risk;
                $users[$userId]['risk_count']++;
                $users[$userId]['last_seen'] = $this->maxTimestamp($users[$userId]['last_seen'], (string)($event['created_at'] ?? ''));
                $users[$userId]['recent_event_codes'][$eventCode] = true;
                if ($ip !== '') {
                    $users[$userId]['ips'][$ip] = true;
                    if (isset($blockedIndex[$ip])) {
                        $users[$userId]['blocked_ips'][$ip] = true;
                    }
                }
                if ($device !== '') {
                    $users[$userId]['devices'][$device] = true;
                }
                if ($country !== '') {
                    $users[$userId]['countries'][$country] = true;
                }
                if ($username !== '') {
                    $users[$userId]['usernames'][$username] = true;
                }
                if ($this->isFailureEvent($eventCode)) {
                    $users[$userId]['failed_count']++;
                }
                if ($eventCode === 'AUTH-001') {
                    $users[$userId]['success_count']++;
                }
                if (str_contains($eventCode, 'MFA')) {
                    $users[$userId]['mfa_events']++;
                }
                if (str_starts_with($eventCode, 'IPS-')) {
                    $users[$userId]['ips_events']++;
                }
                if ($risk >= 70) {
                    $users[$userId]['high_risk_events']++;
                }
            }

            if ($ip !== '') {
                if (!isset($ips[$ip])) {
                    $ips[$ip] = $this->newIpSummary($event, $blockedIndex[$ip] ?? null);
                }
                $ips[$ip]['event_count']++;
                $ips[$ip]['risk_total'] += $risk;
                $ips[$ip]['risk_count']++;
                $ips[$ip]['last_seen'] = $this->maxTimestamp($ips[$ip]['last_seen'], (string)($event['created_at'] ?? ''));
                $ips[$ip]['recent_event_codes'][$eventCode] = true;
                if ($userId !== '' && $userId !== 'unknown') {
                    $ips[$ip]['users'][$userId] = true;
                }
                if ($username !== '') {
                    $ips[$ip]['usernames'][$username] = true;
                }
                if ($device !== '') {
                    $ips[$ip]['devices'][$device] = true;
                }
                if ($country !== '') {
                    $ips[$ip]['countries'][$country] = true;
                }
                if ($this->isFailureEvent($eventCode)) {
                    $ips[$ip]['failed_count']++;
                }
                if ($eventCode === 'AUTH-001') {
                    $ips[$ip]['success_count']++;
                }
            }

            if ($device !== '') {
                if (!isset($devices[$device])) {
                    $devices[$device] = $this->newDeviceSummary($event);
                }
                $devices[$device]['event_count']++;
                $devices[$device]['risk_total'] += $risk;
                $devices[$device]['risk_count']++;
                $devices[$device]['last_seen'] = $this->maxTimestamp($devices[$device]['last_seen'], (string)($event['created_at'] ?? ''));
                if ($userId !== '' && $userId !== 'unknown') {
                    $devices[$device]['users'][$userId] = true;
                }
                if ($ip !== '') {
                    $devices[$device]['ips'][$ip] = true;
                }
                if ($country !== '') {
                    $devices[$device]['countries'][$country] = true;
                }
                if ($risk >= 70) {
                    $devices[$device]['high_risk_events']++;
                }
            }
        }

        foreach ($users as &$summary) {
            $this->finalizeSummarySets($summary, ['ips', 'devices', 'countries', 'blocked_ips', 'usernames']);
        }
        unset($summary);
        foreach ($ips as &$summary) {
            $this->finalizeSummarySets($summary, ['users', 'devices', 'countries', 'usernames']);
            $summary['blocked'] = !empty($summary['block_type']);
        }
        unset($summary);
        foreach ($devices as &$summary) {
            $this->finalizeSummarySets($summary, ['users', 'ips', 'countries']);
        }
        unset($summary);

        return ['users' => $users, 'ips' => $ips, 'devices' => $devices];
    }

    private function newUserSummary(array $event): array
    {
        return [
            'display_name' => (string)($event['display_name'] ?? ''),
            'email' => (string)($event['email'] ?? ''),
            'role' => (string)($event['role'] ?? ''),
            'event_count' => 0,
            'failed_count' => 0,
            'success_count' => 0,
            'mfa_events' => 0,
            'ips_events' => 0,
            'high_risk_events' => 0,
            'risk_total' => 0.0,
            'risk_count' => 0,
            'avg_risk' => 0.0,
            'last_seen' => '',
            'ips' => [],
            'devices' => [],
            'countries' => [],
            'blocked_ips' => [],
            'usernames' => [],
            'recent_event_codes' => [],
        ];
    }

    private function newIpSummary(array $event, ?array $blockedRecord): array
    {
        return [
            'country_code' => (string)($event['country_code'] ?? ''),
            'event_count' => 0,
            'failed_count' => 0,
            'success_count' => 0,
            'risk_total' => 0.0,
            'risk_count' => 0,
            'avg_risk' => 0.0,
            'last_seen' => '',
            'users' => [],
            'devices' => [],
            'countries' => [],
            'usernames' => [],
            'recent_event_codes' => [],
            'blocked' => $blockedRecord !== null,
            'block_type' => (string)($blockedRecord['block_type'] ?? ''),
            'trigger_rule' => (string)($blockedRecord['trigger_rule'] ?? ''),
            'threat_feed' => (string)($blockedRecord['threat_feed'] ?? ''),
        ];
    }

    private function newDeviceSummary(array $event): array
    {
        return [
            'event_count' => 0,
            'high_risk_events' => 0,
            'risk_total' => 0.0,
            'risk_count' => 0,
            'avg_risk' => 0.0,
            'last_seen' => '',
            'users' => [],
            'ips' => [],
            'countries' => [],
        ];
    }

    private function finalizeSummarySets(array &$summary, array $keys): void
    {
        foreach ($keys as $key) {
            $values = array_keys($summary[$key] ?? []);
            sort($values);
            $summary[$key . '_values'] = $values;
            $summary['unique_' . $key . '_count'] = count($values);
        }
        $summary['avg_risk'] = ($summary['risk_count'] ?? 0) > 0
            ? round(((float)$summary['risk_total']) / max(1, (int)$summary['risk_count']), 2)
            : 0.0;
    }

    private function buildAttributionCases(array $events, array $behavior, array $blockedIps, array $incidents, array $anomalies, array $attacks, array $entities): array
    {
        $anomalyIndex = [];
        foreach ($anomalies as $anomaly) {
            $anomalyIndex[(string)($anomaly['user_id'] ?? '')] = $anomaly;
        }
        $attackIndex = [];
        foreach ($attacks as $attack) {
            $attackIndex[(string)($attack['user_id'] ?? '')] = $attack;
        }
        $entityIndex = [];
        foreach ($entities as $entity) {
            $entityIndex[(string)($entity['entity_id'] ?? '')] = $entity;
        }

        $cases = [];
        foreach ($behavior['users'] as $userId => $summary) {
            $anomaly = $anomalyIndex[$userId] ?? null;
            $attack = $attackIndex[$userId] ?? null;
            $entity = $entityIndex['user:' . $userId] ?? null;
            $riskScore = $this->scoreUserCase($summary, $anomaly, $attack, $entity);
            if ($riskScore < 40) {
                continue;
            }

            $attackLabel = $this->deriveUserAttackLabel($summary, $attack);
            $cases[] = [
                'case_id' => $this->buildCaseId('user', $userId),
                'entity_type' => 'user',
                'entity_id' => $userId,
                'display_name' => $summary['display_name'],
                'email' => $summary['email'],
                'role' => $summary['role'],
                'attack_label' => $attackLabel,
                'severity' => $this->severityFromRisk($riskScore),
                'risk_score' => $riskScore,
                'summary' => $this->buildUserSummary($summary, $attackLabel),
                'explanation' => $this->buildLocalExplanation($summary, $attackLabel, $riskScore, $anomaly, $attack, $entity),
                'recommended_actions' => $this->recommendedActions($attackLabel, 'user'),
                'source_signals' => [
                    'graph_score' => round((float)($entity['suspicion_score'] ?? 0), 4),
                    'anomaly_score' => round((float)($anomaly['anomaly_score'] ?? 0), 4),
                    'attack_confidence' => round((float)($attack['confidence'] ?? 0), 4),
                ],
                'evidence' => [
                    'event_count' => $summary['event_count'],
                    'failed_count' => $summary['failed_count'],
                    'success_count' => $summary['success_count'],
                    'unique_ips' => $summary['unique_ips_count'],
                    'unique_devices' => $summary['unique_devices_count'],
                    'unique_countries' => $summary['unique_countries_count'],
                    'avg_risk' => $summary['avg_risk'],
                    'blocked_ips' => $summary['blocked_ips_values'],
                    'last_seen' => $summary['last_seen'],
                ],
                'related_entities' => [
                    'ips' => $summary['ips_values'],
                    'devices' => $summary['devices_values'],
                    'countries' => $summary['countries_values'],
                ],
                'related_incidents' => $this->relatedIncidentsForCase($incidents, $userId, $summary['ips_values']),
                'supporting_events' => $this->supportingEventsForUser($events, $userId),
            ];
        }

        foreach ($behavior['ips'] as $ip => $summary) {
            $attack = $attackIndex['ip:' . $ip] ?? null;
            $entity = $entityIndex['ip:' . $ip] ?? null;
            $riskScore = $this->scoreIpCase($summary, $attack, $entity);
            if ($riskScore < 45) {
                continue;
            }

            $attackLabel = $this->deriveIpAttackLabel($summary, $attack);
            $cases[] = [
                'case_id' => $this->buildCaseId('ip', $ip),
                'entity_type' => 'ip',
                'entity_id' => $ip,
                'display_name' => null,
                'email' => null,
                'role' => null,
                'attack_label' => $attackLabel,
                'severity' => $this->severityFromRisk($riskScore),
                'risk_score' => $riskScore,
                'summary' => $this->buildIpSummary($ip, $summary, $attackLabel),
                'explanation' => $this->buildLocalExplanation($summary, $attackLabel, $riskScore, null, $attack, $entity),
                'recommended_actions' => $this->recommendedActions($attackLabel, 'ip'),
                'source_signals' => [
                    'graph_score' => round((float)($entity['suspicion_score'] ?? 0), 4),
                    'anomaly_score' => 0.0,
                    'attack_confidence' => round((float)($attack['confidence'] ?? 0), 4),
                ],
                'evidence' => [
                    'event_count' => $summary['event_count'],
                    'failed_count' => $summary['failed_count'],
                    'success_count' => $summary['success_count'],
                    'unique_users' => $summary['unique_users_count'],
                    'unique_devices' => $summary['unique_devices_count'],
                    'unique_countries' => $summary['unique_countries_count'],
                    'avg_risk' => $summary['avg_risk'],
                    'blocked' => $summary['blocked'],
                    'block_type' => $summary['block_type'],
                    'last_seen' => $summary['last_seen'],
                ],
                'related_entities' => [
                    'users' => $summary['users_values'],
                    'devices' => $summary['devices_values'],
                    'countries' => $summary['countries_values'],
                    'usernames' => $summary['usernames_values'],
                ],
                'related_incidents' => $this->relatedIncidentsForCase($incidents, null, [$ip]),
                'supporting_events' => $this->supportingEventsForIp($events, $ip),
            ];
        }

        usort($cases, static fn(array $left, array $right): int => $right['risk_score'] <=> $left['risk_score']);
        return array_slice($cases, 0, 12);
    }

    private function summarizeAttributionCases(array $cases, array $events, array $blockedIps, array $incidents): array
    {
        $severityCounts = [];
        $attackCounts = [];
        $linkedIncidents = 0;

        foreach ($cases as $case) {
            $severity = (string)($case['severity'] ?? 'unknown');
            $attackLabel = (string)($case['attack_label'] ?? 'unknown');
            $severityCounts[$severity] = ($severityCounts[$severity] ?? 0) + 1;
            $attackCounts[$attackLabel] = ($attackCounts[$attackLabel] ?? 0) + 1;
            $linkedIncidents += count($case['related_incidents'] ?? []);
        }

        return [
            'total_cases' => count($cases),
            'severity_counts' => $severityCounts,
            'attack_label_counts' => $attackCounts,
            'linked_incidents' => $linkedIncidents,
            'blocked_ip_matches' => count($blockedIps),
            'incident_backlog_considered' => count($incidents),
            'top_case_risk' => $cases[0]['risk_score'] ?? 0,
            'avg_case_risk' => count($cases) > 0 ? round(array_sum(array_map(static fn(array $case): int => (int)$case['risk_score'], $cases)) / count($cases), 1) : 0.0,
            'events_considered' => count($events),
        ];
    }

    private function enrichAttributionCasesWithLlm(array $cases, array $context): array
    {
        $maxCases = min(2, count($cases));
        $meta = [
            'available' => false,
            'applied' => false,
            'mode' => 'local_explanation',
            'provider' => 'fallback',
            'model' => null,
            'cases_considered' => $maxCases,
            'cases_enriched' => 0,
            'warning' => null,
        ];

        if ($maxCases === 0) {
            return ['cases' => $cases, 'meta' => $meta];
        }

        $aiService = new AIService();

        for ($index = 0; $index < $maxCases; $index++) {
            $result = $aiService->generateAttributionExplanation($cases[$index], $context);
            $provider = (string)($result['provider'] ?? 'fallback');
            $meta['provider'] = $provider;
            $meta['model'] = $result['model'] ?? $meta['model'];

            if (($result['warning'] ?? null) !== null && $meta['warning'] === null) {
                $meta['warning'] = (string)$result['warning'];
            }

            if ($provider === 'fallback') {
                $meta['mode'] = 'deterministic_fallback';
                break;
            }

            $analysis = $result['analysis'] ?? null;
            if (!is_array($analysis)) {
                continue;
            }

            $cases[$index]['llm_summary'] = (string)($analysis['analyst_summary'] ?? '');
            $cases[$index]['llm_explanation'] = (string)($analysis['attribution_rationale'] ?? '');
            $cases[$index]['llm_confidence_statement'] = (string)($analysis['confidence_statement'] ?? '');
            $cases[$index]['llm_triage_note'] = (string)($analysis['triage_note'] ?? '');
            $cases[$index]['llm_recommended_next_step'] = (string)($analysis['recommended_next_step'] ?? '');
            $cases[$index]['llm_low_confidence'] = (bool)($analysis['is_low_confidence'] ?? false);
            $cases[$index]['llm_provider'] = $provider;
            $cases[$index]['llm_model'] = (string)($result['model'] ?? '');

            $meta['available'] = true;
            $meta['applied'] = true;
            $meta['mode'] = 'structured_llm_narrative';
            $meta['cases_enriched']++;
        }

        if (!$meta['applied'] && $meta['warning'] === null) {
            $meta['warning'] = 'No structured LLM narratives were added. Local explanations remain available.';
        }

        return ['cases' => $cases, 'meta' => $meta];
    }

    private function scoreUserCase(array $summary, ?array $anomaly, ?array $attack, ?array $entity): int
    {
        $score = 0.0;
        $score += ((float)($entity['suspicion_score'] ?? 0.0)) * 35;
        $score += ((float)($anomaly['anomaly_score'] ?? 0.0)) * 30;
        $score += ((float)($attack['confidence'] ?? 0.0)) * 25;
        $score += min(10.0, ((float)($summary['avg_risk'] ?? 0.0)) / 10.0);
        $score += min(8.0, (float)$summary['failed_count']);
        $score += min(6.0, (float)$summary['unique_countries_count'] * 2.0);

        return (int)round(min(99.0, $score));
    }

    private function scoreIpCase(array $summary, ?array $attack, ?array $entity): int
    {
        $score = 0.0;
        $score += ((float)($entity['suspicion_score'] ?? 0.0)) * 40;
        $score += ((float)($attack['confidence'] ?? 0.0)) * 25;
        $score += min(15.0, (float)$summary['failed_count'] * 1.8);
        $score += min(10.0, (float)$summary['unique_users_count'] * 2.5);
        $score += !empty($summary['blocked']) ? 10.0 : 0.0;

        return (int)round(min(99.0, $score));
    }

    private function deriveUserAttackLabel(array $summary, ?array $attack): string
    {
        if (($attack['attack_type'] ?? null) !== null) {
            return (string)$attack['attack_type'];
        }
        if ($this->containsAnyEventCode($summary, ['AUTH-017', 'AUTH-018'])) {
            return 'MFA_BYPASS';
        }
        if ($summary['failed_count'] >= 6 && $summary['unique_ips_count'] >= 3) {
            return 'DISTRIBUTED';
        }
        if ($summary['failed_count'] >= 8) {
            return 'BRUTE_FORCE';
        }
        if ($summary['unique_countries_count'] >= 2 && $summary['success_count'] >= 1) {
            return 'ACCOUNT_TAKEOVER';
        }

        return 'ANOMALOUS_BEHAVIOR';
    }

    private function deriveIpAttackLabel(array $summary, ?array $attack): string
    {
        if (($attack['attack_type'] ?? null) !== null) {
            return (string)$attack['attack_type'];
        }
        if ($summary['failed_count'] >= 6 && $summary['unique_users_count'] >= 3) {
            return 'CREDENTIAL_STUFFING';
        }
        if (!empty($summary['blocked'])) {
            return strtoupper((string)($summary['block_type'] ?? 'IP_ABUSE'));
        }

        return 'IP_ABUSE';
    }

    private function recommendedActions(string $attackLabel, string $entityType): array
    {
        return match ($attackLabel) {
            'MFA_BYPASS' => [
                'Revoke the active session and re-enroll MFA on a trusted factor.',
                'Verify the approval trail for the bypass issuance and consumption path.',
                'Escalate to an incident if privileged access was granted after recovery.',
            ],
            'CREDENTIAL_STUFFING', 'BRUTE_FORCE', 'DISTRIBUTED' => [
                $entityType === 'ip'
                    ? 'Keep the source IP blocked and review whether rate-limit thresholds need tuning.'
                    : 'Force a password reset and verify recent session inventory for the targeted account.',
                'Review related usernames for spray patterns across the same infrastructure.',
                'Promote to an incident if the activity crossed analyst-defined thresholds.',
            ],
            'ACCOUNT_TAKEOVER' => [
                'Verify travel or business justification for the new geography and device pair.',
                'Review session continuity and recent privilege changes on the account.',
                'Require step-up authentication before allowing further privileged actions.',
            ],
            default => [
                'Review the supporting events and confirm whether the signal is benign drift or a true threat.',
                'Compare the entity against recent geo, device, and blocked-IP context.',
                'Open an incident if the pattern persists across the next analyst review window.',
            ],
        };
    }

    private function buildUserSummary(array $summary, string $attackLabel): string
    {
        return sprintf(
            '%s shows %s signals across %d IPs, %d devices, and %d countries.',
            $summary['display_name'] !== '' ? $summary['display_name'] : $summary['email'],
            strtolower(str_replace('_', ' ', $attackLabel)),
            $summary['unique_ips_count'],
            $summary['unique_devices_count'],
            $summary['unique_countries_count']
        );
    }

    private function buildIpSummary(string $ip, array $summary, string $attackLabel): string
    {
        return sprintf(
            '%s is linked to %d failed events, %d users, and a %s signal.',
            $ip,
            $summary['failed_count'],
            $summary['unique_users_count'],
            strtolower(str_replace('_', ' ', $attackLabel))
        );
    }

    private function buildLocalExplanation(array $summary, string $attackLabel, int $riskScore, ?array $anomaly, ?array $attack, ?array $entity): string
    {
        $parts = [sprintf('Risk score %d/100 driven by %s.', $riskScore, strtolower(str_replace('_', ' ', $attackLabel)))];
        if (($entity['suspicion_score'] ?? 0) > 0) {
            $parts[] = sprintf('Graph correlation added %.2f suspicion because %s', (float)$entity['suspicion_score'], strtolower((string)($entity['reason'] ?? 'of linked infrastructure.')));
        }
        if (($anomaly['anomaly_score'] ?? 0) > 0) {
            $parts[] = sprintf('The anomaly model scored this pattern at %.2f with %s risk.', (float)$anomaly['anomaly_score'], strtolower((string)($anomaly['risk_level'] ?? 'medium')));
        }
        if (($attack['confidence'] ?? 0) > 0) {
            $parts[] = sprintf('Attack classification confidence reached %.2f.', (float)$attack['confidence']);
        }
        if (isset($summary['failed_count'], $summary['avg_risk'])) {
            $parts[] = sprintf('Observed %d failed events with an average risk score of %.1f.', (int)$summary['failed_count'], (float)($summary['avg_risk'] ?? 0.0));
        }

        return implode(' ', $parts);
    }

    private function relatedIncidentsForCase(array $incidents, ?string $userId, array $ips): array
    {
        $related = [];
        foreach ($incidents as $incident) {
            $matchUser = $userId !== null && $userId !== '' && ($incident['affected_user_id'] ?? null) === $userId;
            $matchIp = in_array((string)($incident['source_ip'] ?? ''), $ips, true);
            if (!$matchUser && !$matchIp) {
                continue;
            }
            $related[] = [
                'incident_ref' => (string)($incident['incident_ref'] ?? ''),
                'severity' => (string)($incident['severity'] ?? ''),
                'status' => (string)($incident['status'] ?? ''),
                'trigger_summary' => (string)($incident['trigger_summary'] ?? ''),
                'detected_at' => (string)($incident['detected_at'] ?? ''),
            ];
        }

        return $related;
    }

    private function supportingEventsForUser(array $events, string $userId): array
    {
        $filtered = array_values(array_filter($events, static fn(array $event): bool => (string)($event['user_id'] ?? '') === $userId));
        usort($filtered, static fn(array $left, array $right): int => strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? '')));

        return array_slice(array_map(fn(array $event): array => $this->eventForCase($event), $filtered), 0, 6);
    }

    private function supportingEventsForIp(array $events, string $ip): array
    {
        $filtered = array_values(array_filter($events, static fn(array $event): bool => (string)($event['source_ip'] ?? '') === $ip));
        usort($filtered, static fn(array $left, array $right): int => strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? '')));

        return array_slice(array_map(fn(array $event): array => $this->eventForCase($event), $filtered), 0, 6);
    }

    private function eventForCase(array $event): array
    {
        return [
            'id' => (int)($event['id'] ?? 0),
            'event_code' => (string)($event['event_code'] ?? ''),
            'event_name' => (string)($event['event_name'] ?? ''),
            'user_id' => (string)($event['user_id'] ?? ''),
            'source_ip' => (string)($event['source_ip'] ?? ''),
            'country_code' => (string)($event['country_code'] ?? ''),
            'device_fingerprint' => (string)($event['device_fingerprint'] ?? ''),
            'risk_score' => (int)($event['risk_score'] ?? 0),
            'created_at' => (string)($event['created_at'] ?? ''),
            'details' => $event['details'] ?? [],
        ];
    }

    private function buildCaseId(string $entityType, string $entityId): string
    {
        return strtoupper($entityType) . '-' . strtoupper(substr(hash('sha256', $entityType . '|' . $entityId), 0, 10));
    }

    private function isFailureEvent(string $eventCode): bool
    {
        return str_contains($eventCode, 'FAILED') || $eventCode === 'AUTH-002';
    }

    private function containsAnyEventCode(array $summary, array $eventCodes): bool
    {
        foreach ($eventCodes as $eventCode) {
            if (!empty($summary['recent_event_codes'][$eventCode])) {
                return true;
            }
        }
        return false;
    }

    private function maxTimestamp(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }
        return strtotime($left) >= strtotime($right) ? $left : $right;
    }

    private function riskLevelFromNormalized(float $score): string
    {
        return $score >= 0.7 ? 'high' : ($score >= 0.55 ? 'medium' : 'low');
    }

    private function severityFromRisk(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 85 => 'sev1',
            $riskScore >= 65 => 'sev2',
            $riskScore >= 45 => 'sev3',
            default => 'sev4',
        };
    }

    private function execPythonModule(string $scriptName, string $mode, array $data): array
    {
        $scriptPath = $this->mlServiceDir . DIRECTORY_SEPARATOR . $scriptName;
        if (!file_exists($scriptPath)) {
            return ['status' => 'error', 'message' => 'ML script not found: ' . $scriptName];
        }

        $pythonCommand = $this->resolvePythonCommand();
        if ($pythonCommand === null) {
            return ['status' => 'error', 'message' => 'No usable Python interpreter was found for ML execution.'];
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['status' => 'error', 'message' => 'Failed to encode ML payload.'];
        }

        $payloadFile = tempnam($this->mlServiceDir, 'ml_payload_');
        if ($payloadFile === false) {
            return ['status' => 'error', 'message' => 'Failed to allocate a temporary ML payload file.'];
        }

        $rawOutput = '';
        try {
            if (file_put_contents($payloadFile, $payload, LOCK_EX) === false) {
                return ['status' => 'error', 'message' => 'Failed to write ML payload to a temporary file.'];
            }

            $command = $this->buildCommand($pythonCommand, [$scriptPath, $mode, '@' . $payloadFile]);
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            $rawOutput = trim(implode("\n", $output));
        } finally {
            if (is_file($payloadFile)) {
                @unlink($payloadFile);
            }
        }

        if ($returnCode !== 0) {
            return [
                'status' => 'error',
                'message' => 'Python execution failed.',
                'return_code' => $returnCode,
                'error' => $rawOutput,
            ];
        }

        $decoded = json_decode($rawOutput, true);
        if (!is_array($decoded)) {
            return ['status' => 'error', 'message' => 'ML script returned invalid JSON.', 'raw_output' => $rawOutput];
        }

        return $decoded;
    }

    private function resolvePythonCommand(): ?string
    {
        if ($this->resolvedPythonCommand !== null) {
            return $this->resolvedPythonCommand;
        }

        foreach ($this->pythonCandidates as $candidate) {
            $output = [];
            $returnCode = 0;
            exec($this->buildCommand($candidate, ['--version']) . ' 2>&1', $output, $returnCode);
            if ($returnCode === 0) {
                $this->resolvedPythonCommand = $candidate;
                return $candidate;
            }
        }

        return null;
    }

    private function buildCommand(string $baseCommand, array $arguments): string
    {
        $command = (!str_contains($baseCommand, ' ') || file_exists($baseCommand))
            ? escapeshellarg($baseCommand)
            : $baseCommand;

        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg((string)$argument);
        }

        return $command;
    }
}

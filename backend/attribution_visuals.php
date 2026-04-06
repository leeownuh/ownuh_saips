<?php
declare(strict_types=1);

function attribution_svg_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function attribution_graph_label(string $value, int $max = 18): string {
    $value = trim(preg_replace('/\s+/', ' ', attribution_safe_text($value)) ?? '');
    if ($value === '') {
        return 'Unknown';
    }

    return strlen($value) > $max
        ? substr($value, 0, max(1, $max - 3)) . '...'
        : $value;
}

function attribution_device_label(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return 'No device';
    }

    $value = str_replace('_', ' ', $value);
    $value = preg_replace('/\bdevice\b/i', 'dev', $value) ?? $value;

    return attribution_graph_label($value, 18);
}

function attribution_case_entity_label(array $case): string {
    if (($case['entity_type'] ?? '') === 'ip') {
        return app_demo_safe_ip((string)($case['entity_id'] ?? ''));
    }

    $label = trim(app_demo_safe_name(
        (string)($case['display_name'] ?? ''),
        (string)($case['email'] ?? ''),
        (string)($case['role'] ?? 'user')
    ));
    if ($label !== '') {
        return $label;
    }

    $email = trim(attribution_safe_text((string)($case['email'] ?? '')));
    if ($email !== '') {
        return $email;
    }

    return attribution_graph_label((string)($case['entity_id'] ?? 'Entity'), 20);
}

function attribution_ip_relation_labels(array $case): array {
    $labels = [];
    foreach (array_slice($case['related_entities']['ips'] ?? [], 0, 2) as $ip) {
        $ip = trim((string)$ip);
        if ($ip === '') {
            continue;
        }
        $labels[] = attribution_graph_label(app_demo_safe_ip($ip), 16);
    }

    return array_values(array_unique($labels));
}

function attribution_user_relation_labels(array $case): array {
    $source = $case['related_entities']['usernames'] ?? ($case['related_entities']['users'] ?? []);
    $labels = [];
    foreach (array_slice($source, 0, 2) as $value) {
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }
        $labels[] = attribution_graph_label($value, 16);
    }

    return array_values(array_unique($labels));
}

function attribution_device_relation_labels(array $case): array {
    $labels = [];
    foreach (array_slice($case['related_entities']['devices'] ?? [], 0, 2) as $device) {
        $deviceLabel = attribution_device_label((string)$device);
        if ($deviceLabel !== 'No device') {
            $labels[] = $deviceLabel;
        }
    }

    return array_values(array_unique($labels));
}

function attribution_incident_relation_labels(array $case): array {
    $labels = [];
    foreach (array_slice($case['related_incidents'] ?? [], 0, 2) as $incident) {
        $ref = trim((string)($incident['incident_ref'] ?? ''));
        if ($ref === '') {
            continue;
        }
        $labels[] = attribution_graph_label($ref, 16);
    }

    return array_values(array_unique($labels));
}

function attribution_case_link_counts(array $case): array {
    return [
        'users' => ($case['entity_type'] ?? '') === 'ip'
            ? count(array_filter($case['related_entities']['usernames'] ?? ($case['related_entities']['users'] ?? []), static fn(mixed $value): bool => trim((string)$value) !== ''))
            : 1,
        'ips' => ($case['entity_type'] ?? '') === 'ip'
            ? 1
            : count(array_filter($case['related_entities']['ips'] ?? [], static fn(mixed $value): bool => trim((string)$value) !== '')),
        'devices' => count(array_filter($case['related_entities']['devices'] ?? [], static fn(mixed $value): bool => trim((string)$value) !== '')),
        'incidents' => count($case['related_incidents'] ?? []),
    ];
}

function attribution_network_snapshot(array $cases): array {
    $users = [];
    $ips = [];
    $devices = [];
    $incidents = [];

    foreach (array_slice($cases, 0, 6) as $case) {
        if (($case['entity_type'] ?? '') === 'user') {
            $users[(string)($case['entity_id'] ?? '')] = true;
            foreach (($case['related_entities']['ips'] ?? []) as $ip) {
                $ip = trim((string)$ip);
                if ($ip !== '') {
                    $ips[$ip] = true;
                }
            }
        } elseif (($case['entity_type'] ?? '') === 'ip') {
            $ip = trim((string)($case['entity_id'] ?? ''));
            if ($ip !== '') {
                $ips[$ip] = true;
            }
            foreach (($case['related_entities']['usernames'] ?? ($case['related_entities']['users'] ?? [])) as $user) {
                $user = trim((string)$user);
                if ($user !== '') {
                    $users[$user] = true;
                }
            }
        }

        foreach (($case['related_entities']['devices'] ?? []) as $device) {
            $device = trim((string)$device);
            if ($device !== '') {
                $devices[$device] = true;
            }
        }

        foreach (($case['related_incidents'] ?? []) as $incident) {
            $ref = trim((string)($incident['incident_ref'] ?? ''));
            if ($ref !== '') {
                $incidents[$ref] = true;
            }
        }
    }

    return [
        'cases' => count($cases),
        'users' => count($users),
        'ips' => count($ips),
        'devices' => count($devices),
        'incidents' => count($incidents),
        'top_attack' => (string)($cases[0]['attack_label'] ?? 'ANOMALOUS_BEHAVIOR'),
        'top_risk' => (int)($cases[0]['risk_score'] ?? 0),
    ];
}

function attribution_graph_theme(string $type): array {
    return match ($type) {
        'core' => ['fill' => '#0f2740', 'stroke' => '#155e63', 'text' => '#f4f7fb'],
        'user' => ['fill' => '#e8f1fb', 'stroke' => '#0d6efd', 'text' => '#0f2740'],
        'ip' => ['fill' => '#fff0f1', 'stroke' => '#dc3545', 'text' => '#7f1d1d'],
        'device' => ['fill' => '#fff7e6', 'stroke' => '#f59e0b', 'text' => '#7c2d12'],
        'incident' => ['fill' => '#ecfdf3', 'stroke' => '#198754', 'text' => '#065f46'],
        default => ['fill' => '#f8fafc', 'stroke' => '#94a3b8', 'text' => '#334155'],
    };
}

function attribution_render_graph_svg(array $nodes, array $edges, int $width = 420, int $height = 224): string {
    $lookup = [];
    foreach ($nodes as $node) {
        $lookup[(string)$node['id']] = $node;
    }

    $svg = [];
    $svg[] = '<svg class="attribution-graph-svg" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">';
    $svg[] = '<rect x="10" y="10" width="' . ($width - 20) . '" height="' . ($height - 20) . '" rx="18" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1.5"/>';

    foreach ($edges as $edge) {
        $from = $lookup[(string)($edge['from'] ?? '')] ?? null;
        $to = $lookup[(string)($edge['to'] ?? '')] ?? null;
        if ($from === null || $to === null) {
            continue;
        }

        $theme = attribution_graph_theme((string)($to['type'] ?? 'default'));
        $svg[] = '<line x1="' . (int)$from['x'] . '" y1="' . (int)$from['y'] . '" x2="' . (int)$to['x'] . '" y2="' . (int)$to['y'] . '" stroke="' . $theme['stroke'] . '" stroke-opacity="0.45" stroke-width="2"/>';
    }

    foreach ($nodes as $node) {
        $theme = attribution_graph_theme((string)($node['type'] ?? 'default'));
        $x = (int)$node['x'];
        $y = (int)$node['y'];
        $badge = attribution_svg_escape((string)($node['badge'] ?? ''));
        $label = attribution_svg_escape((string)($node['label'] ?? ''));

        $svg[] = '<circle cx="' . $x . '" cy="' . $y . '" r="26" fill="' . $theme['fill'] . '" stroke="' . $theme['stroke'] . '" stroke-width="2.2"/>';
        $svg[] = '<text x="' . $x . '" y="' . ($y + 4) . '" text-anchor="middle" font-size="11" font-weight="700" fill="' . $theme['text'] . '" font-family="Arial, sans-serif">' . $badge . '</text>';
        $svg[] = '<text x="' . $x . '" y="' . ($y + 43) . '" text-anchor="middle" font-size="11" fill="#64748b" font-family="Arial, sans-serif">' . $label . '</text>';
    }

    $svg[] = '</svg>';
    return implode('', $svg);
}

function attribution_render_summary_graph(array $snapshot): string {
    $nodes = [
        ['id' => 'core', 'x' => 210, 'y' => 112, 'type' => 'core', 'badge' => 'AI', 'label' => 'Attribution'],
        ['id' => 'users', 'x' => 84, 'y' => 84, 'type' => 'user', 'badge' => (string)($snapshot['users'] ?? 0), 'label' => 'Users'],
        ['id' => 'ips', 'x' => 84, 'y' => 154, 'type' => 'ip', 'badge' => (string)($snapshot['ips'] ?? 0), 'label' => 'IPs'],
        ['id' => 'devices', 'x' => 336, 'y' => 84, 'type' => 'device', 'badge' => (string)($snapshot['devices'] ?? 0), 'label' => 'Devices'],
        ['id' => 'incidents', 'x' => 336, 'y' => 154, 'type' => 'incident', 'badge' => (string)($snapshot['incidents'] ?? 0), 'label' => 'Incidents'],
    ];

    $edges = [
        ['from' => 'core', 'to' => 'users'],
        ['from' => 'core', 'to' => 'ips'],
        ['from' => 'core', 'to' => 'devices'],
        ['from' => 'core', 'to' => 'incidents'],
    ];

    return attribution_render_graph_svg($nodes, $edges, 420, 224);
}

function attribution_render_case_graph(array $case, string $entityLabel): string {
    $centerType = ($case['entity_type'] ?? '') === 'ip' ? 'ip' : 'user';
    $sideType = $centerType === 'ip' ? 'user' : 'ip';
    $sideBadge = $centerType === 'ip' ? 'USR' : 'IP';
    $sideLabels = $centerType === 'ip'
        ? attribution_user_relation_labels($case)
        : attribution_ip_relation_labels($case);
    $deviceLabels = attribution_device_relation_labels($case);
    $incidentLabels = attribution_incident_relation_labels($case);

    $nodes = [[
        'id' => 'center',
        'x' => 210,
        'y' => 112,
        'type' => $centerType,
        'badge' => $centerType === 'ip' ? 'IP' : 'USR',
        'label' => attribution_graph_label($entityLabel, 18),
    ]];
    $edges = [];

    $sidePositions = count($sideLabels) <= 1 ? [[84, 112]] : [[84, 84], [84, 154]];
    foreach ($sideLabels as $index => $label) {
        $nodes[] = [
            'id' => 'side-' . $index,
            'x' => $sidePositions[$index][0],
            'y' => $sidePositions[$index][1],
            'type' => $sideType,
            'badge' => $sideBadge,
            'label' => $label,
        ];
        $edges[] = ['from' => 'center', 'to' => 'side-' . $index];
    }

    $devicePositions = count($deviceLabels) <= 1 ? [[210, 42]] : [[154, 48], [266, 48]];
    foreach ($deviceLabels as $index => $label) {
        $nodes[] = [
            'id' => 'device-' . $index,
            'x' => $devicePositions[$index][0],
            'y' => $devicePositions[$index][1],
            'type' => 'device',
            'badge' => 'DEV',
            'label' => $label,
        ];
        $edges[] = ['from' => 'center', 'to' => 'device-' . $index];
    }

    $incidentPositions = count($incidentLabels) <= 1 ? [[336, 112]] : [[336, 84], [336, 154]];
    foreach ($incidentLabels as $index => $label) {
        $nodes[] = [
            'id' => 'incident-' . $index,
            'x' => $incidentPositions[$index][0],
            'y' => $incidentPositions[$index][1],
            'type' => 'incident',
            'badge' => 'INC',
            'label' => $label,
        ];
        $edges[] = ['from' => 'center', 'to' => 'incident-' . $index];
    }

    return attribution_render_graph_svg($nodes, $edges, 420, 224);
}

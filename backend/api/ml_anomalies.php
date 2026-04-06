<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap.php';

use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Services\MLService;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Use POST for ML anomaly detection.']);
    exit;
}

$secConfig = require __DIR__ . '/../config/security.php';
$auth = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$auth->requireRole($payload, 'admin');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$result = (new MLService())->detectAnomalies(
    $input['date_from'] ?? null,
    $input['date_to'] ?? null,
    (int)($input['limit'] ?? 1000)
);

http_response_code(($result['status'] ?? '') === 'error' ? 500 : 200);
echo json_encode($result, JSON_UNESCAPED_SLASHES);

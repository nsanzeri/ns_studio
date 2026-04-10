<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_usage.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$featureKey = trim((string)($payload['feature_key'] ?? ''));
if ($featureKey === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'feature_key_required']);
    exit;
}

$ok = log_tool_usage($pdo, [
    'feature_key'    => $featureKey,
    'action_key'     => $payload['action_key'] ?? 'run',
    'calendar_count' => $payload['calendar_count'] ?? null,
    'input'          => $payload['input'] ?? null,
    'input_json'     => $payload['input_json'] ?? null,
    'result_count'   => $payload['result_count'] ?? null,
    'status'         => $payload['status'] ?? 'success',
    'note'           => $payload['note'] ?? null,
]);

echo json_encode(['ok' => $ok]);

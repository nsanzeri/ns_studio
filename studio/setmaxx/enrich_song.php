<?php
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');

if (!csrf_verify($_GET['_csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired.']);
    exit;
}

if (!$isProUser) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Upgrade required.']);
    exit;
}

$title = trim((string)($_GET['title'] ?? ''));
$artist = trim((string)($_GET['artist'] ?? ''));
if ($title === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Song title is required.']);
    exit;
}

$term = trim($title . ' ' . $artist);
$url = 'https://itunes.apple.com/search?entity=song&limit=1&term=' . rawurlencode($term);
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'header' => "User-Agent: SetMaxx/1.0\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
    ],
]);

$body = @file_get_contents($url, false, $context);
if ($body === false || $body === '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Metadata lookup failed.']);
    exit;
}

$data = json_decode($body, true);
$result = $data['results'][0] ?? null;
if (!is_array($result)) {
    echo json_encode(['ok' => true, 'result' => null]);
    exit;
}

echo json_encode([
    'ok' => true,
    'result' => [
        'artistName' => (string)($result['artistName'] ?? ''),
        'releaseDate' => (string)($result['releaseDate'] ?? ''),
        'primaryGenreName' => (string)($result['primaryGenreName'] ?? ''),
        'trackTimeMillis' => (int)($result['trackTimeMillis'] ?? 0),
    ],
]);

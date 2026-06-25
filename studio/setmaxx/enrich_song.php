<?php
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');

if (!csrf_verify($_GET['_csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired.']);
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
$url = 'https://itunes.apple.com/search?media=music&entity=song&country=US&limit=1&term=' . rawurlencode($term);
$body = '';
$statusCode = 0;
$lookupError = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 SetMaxx/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $body = (string)curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $lookupError = (string)curl_error($ch);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "User-Agent: Mozilla/5.0 SetMaxx/1.0\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $body = (string)@file_get_contents($url, false, $context);
    $statusCode = $body !== '' ? 200 : 0;
}

if ($body === '' || ($statusCode >= 400)) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Metadata lookup failed.',
        'status' => $statusCode,
        'detail' => $lookupError,
    ]);
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

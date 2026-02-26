<?php
// download.php
require_once __DIR__ . '/../_core/db_connect.php';
require_once __DIR__ . '/../config/stripe.php';

$token = $_GET['t'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
	http_response_code(400);
	echo "Invalid download link.";
	exit;
}

// Fetch token record
$stmt = $pdo->prepare("SELECT * FROM download_tokens WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
	http_response_code(404);
	echo "Download link not found.";
	exit;
}

// Expiry
$now = new DateTimeImmutable('now');
$expiresAt = new DateTimeImmutable($row['expires_at']);

if ($now > $expiresAt) {
	http_response_code(410);
	echo "This download link has expired.";
	exit;
}

// Uses
if ((int)$row['uses_remaining'] <= 0) {
	http_response_code(410);
	echo "This download link has already been used.";
	exit;
}

// Validate product key -> ensure file path matches allowed map
$products = product_file_map();
$product_key = $row['product_key'];

if (!isset($products[$product_key])) {
	http_response_code(400);
	echo "Invalid product.";
	exit;
}

$expectedPath = $products[$product_key]['file_path'];
$downloadName = $products[$product_key]['download_name'];

// Prevent path tampering
if ($row['file_path'] !== $expectedPath) {
	http_response_code(400);
	echo "Invalid download mapping.";
	exit;
}

$filePath = $expectedPath;
if (!is_file($filePath)) {
	http_response_code(404);
	echo "File not available.";
	exit;
}

// Decrement uses BEFORE streaming (avoids abuse on interrupted downloads; tweak if desired)
$upd = $pdo->prepare("UPDATE download_tokens SET uses_remaining = uses_remaining - 1 WHERE id = ? AND uses_remaining > 0");
$upd->execute([$row['id']]);

// Stream
$size = filesize($filePath);
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
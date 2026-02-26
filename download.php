<?php
// download.php?t=... (token gated file download)

require_once __DIR__ . '/../_core/db_connect.php';
require_once __DIR__ . '/../config/stripe.php';

$token = $_GET['t'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(400);
  echo 'Invalid download link.';
  exit;
}

$stmt = $pdo->prepare('SELECT * FROM download_tokens WHERE token = ? LIMIT 1');
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  echo 'Download link not found.';
  exit;
}

$now = new DateTimeImmutable('now');
$expiresAt = new DateTimeImmutable($row['expires_at']);

if ($now > $expiresAt) {
  http_response_code(410);
  echo 'This download link has expired.';
  exit;
}

if ((int)$row['uses_remaining'] <= 0) {
  http_response_code(410);
  echo 'This download link has already been used.';
  exit;
}

$products = product_file_map();
$product_key = $row['product_key'];

if (!isset($products[$product_key])) {
  http_response_code(400);
  echo 'Invalid product.';
  exit;
}

$expectedPath = $products[$product_key]['file_path'];
$downloadName = $products[$product_key]['download_name'];

if ($row['file_path'] !== $expectedPath) {
  http_response_code(400);
  echo 'Invalid download mapping.';
  exit;
}

if (!is_file($expectedPath)) {
  http_response_code(404);
  echo 'File not available.';
  exit;
}

$upd = $pdo->prepare('UPDATE download_tokens SET uses_remaining = uses_remaining - 1 WHERE id = ? AND uses_remaining > 0');
$upd->execute([$row['id']]);

$size = filesize($expectedPath);
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($expectedPath);
exit;

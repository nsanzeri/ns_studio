<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

Auth::requireLogin(base_url('member/download.php'));

$userId = Auth::userId();
$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0) {
	http_response_code(400);
	echo 'Missing product.';
	exit;
}

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.slug,
        p.file_path
    FROM entitlements e
    JOIN products p ON p.id = e.product_id
    WHERE e.user_id = ?
      AND e.product_id = ?
      AND e.status = 'active'
      AND (e.expires_at IS NULL OR e.expires_at > NOW())
    LIMIT 1
");
$stmt->execute([$userId, $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
	http_response_code(403);
	echo 'You do not have access to that product.';
	exit;
}

$filePath = trim((string)($product['file_path'] ?? ''));
$productKey = (string)$product['slug'];

if ($filePath === '') {
	http_response_code(500);
	echo 'Product download configuration is missing.';
	exit;
}

$user = Auth::currentUser($pdo);

$token = bin2hex(random_bytes(32));
$expiresAt = (new DateTimeImmutable('now'))
->add(new DateInterval('P3D'))
->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    INSERT INTO download_tokens (
        token,
        checkout_session_id,
        purchaser_email,
        product_key,
        file_path,
        expires_at,
        uses_remaining,
        product_id
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
		$token,
		'member-' . $userId . '-' . $productId . '-' . time(),
		$user['email'] ?? null,
		$productKey,
		$filePath,
		$expiresAt,
		3,
		$productId,
]);

$studioRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
	$url = $studioRoot . '/download.php?t=' . urlencode($token) . '&download=1';

$downloadSecret = env('DOWNLOAD_SECRET');
if ($downloadSecret) {
	$url .= '&s=' . urlencode(hash_hmac('sha256', $token, $downloadSecret));
}

redirect($url);

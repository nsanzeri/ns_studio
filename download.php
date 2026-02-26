<?php
// download.php?t=...  (token-gated file download)

require_once __DIR__ . '/_core/bootstrap.php';
require_once __DIR__ . '/config/stripe.php'; // provides product_file_map()

// ---------- helpers ----------
function ip_to_bin(?string $ip): ?string {
	if (!$ip) return null;
	$bin = @inet_pton($ip);
	return $bin === false ? null : $bin;
}

function log_download(PDO $pdo, array $data): void {
	// Expected columns (from the table we created):
	// token_id, purchase_id, checkout_session_id, purchaser_email, product_key, file_path, ip, user_agent, result, note
	$sql = "INSERT INTO download_log
    (token_id, purchase_id, checkout_session_id, purchaser_email, product_key, file_path, ip, user_agent, result, note)
    VALUES
    (:token_id, :purchase_id, :checkout_session_id, :purchaser_email, :product_key, :file_path, :ip, :user_agent, :result, :note)";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
			':token_id' => $data['token_id'] ?? null,
			':purchase_id' => $data['purchase_id'] ?? null,
			':checkout_session_id' => $data['checkout_session_id'] ?? null,
			':purchaser_email' => $data['purchaser_email'] ?? null,
			':product_key' => $data['product_key'] ?? '',
			':file_path' => $data['file_path'] ?? '',
			':ip' => $data['ip'] ?? null,
			':user_agent' => $data['user_agent'] ?? null,
			':result' => $data['result'] ?? 'error',
			':note' => $data['note'] ?? null,
	]);
}

// ---------- input ----------
$token = $_GET['t'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
	http_response_code(400);
	echo 'Invalid download link.';
	exit;
}

$ipBin = ip_to_bin($_SERVER['REMOTE_ADDR'] ?? null);
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

$products = product_file_map();

// ---------- transactional validation + decrement ----------
try {
	$pdo->beginTransaction();
	
	// Lock the token row so concurrent requests can't both decrement
	$stmt = $pdo->prepare("SELECT * FROM download_tokens WHERE token = ? LIMIT 1 FOR UPDATE");
	$stmt->execute([$token]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$row) {
		// log + commit (no row lock to hold anyway, but keep symmetry)
		log_download($pdo, [
				'token_id' => null,
				'purchase_id' => null,
				'checkout_session_id' => null,
				'purchaser_email' => null,
				'product_key' => '',
				'file_path' => '',
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'not_found',
				'note' => 'Token not found',
		]);
		$pdo->commit();
		http_response_code(404);
		echo 'Download link not found.';
		exit;
	}
	
	$product_key = (string)$row['product_key'];
	
	if (!isset($products[$product_key])) {
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => (string)($row['file_path'] ?? ''),
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'invalid',
				'note' => 'Unknown product_key',
		]);
		$pdo->commit();
		http_response_code(400);
		echo 'Invalid product.';
		exit;
	}
	
	// Optional: enforce revoked_at if you added it
	if (!empty($row['revoked_at'])) {
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => (string)($row['file_path'] ?? ''),
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'revoked',
				'note' => 'Token revoked',
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link is no longer valid.';
		exit;
	}
	
	// Expiration enforcement
	$now = new DateTimeImmutable('now');
	$expiresAt = new DateTimeImmutable((string)$row['expires_at']);
	if ($now >= $expiresAt) {
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => (string)($row['file_path'] ?? ''),
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'expired',
				'note' => 'Token expired',
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has expired.';
		exit;
	}
	
	if ((int)$row['uses_remaining'] <= 0) {
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => (string)($row['file_path'] ?? ''),
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'exhausted',
				'note' => 'No uses remaining',
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has already been used.';
		exit;
	}
	
	// Resolve file mapping ONLY from server-side product map
	$expectedPath = $products[$product_key]['file_path'];
	$downloadName = $products[$product_key]['download_name'];
	
	if (!is_file($expectedPath)) {
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => $expectedPath,
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'error',
				'note' => 'File missing on disk',
		]);
		$pdo->commit();
		http_response_code(404);
		echo 'File not available.';
		exit;
	}
	
	// Decrement uses + update last_used_at atomically while holding the row lock
	$upd = $pdo->prepare("
    UPDATE download_tokens
    SET uses_remaining = uses_remaining - 1,
        last_used_at = NOW()
    WHERE id = ? AND uses_remaining > 0
  ");
	$upd->execute([(int)$row['id']]);
	
	if ($upd->rowCount() !== 1) {
		// Another request raced and spent the last use
		log_download($pdo, [
				'token_id' => (int)$row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
				'purchaser_email' => $row['purchaser_email'] ?? null,
				'product_key' => $product_key,
				'file_path' => $expectedPath,
				'ip' => $ipBin,
				'user_agent' => $ua,
				'result' => 'exhausted',
				'note' => 'Race: no uses remaining at update time',
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has already been used.';
		exit;
	}
	
	// Success log (before streaming)
	log_download($pdo, [
			'token_id' => (int)$row['id'],
			'purchase_id' => $row['purchase_id'] ?? null,
			'checkout_session_id' => $row['stripe_checkout_session_id'] ?? null,
			'purchaser_email' => $row['purchaser_email'] ?? null,
			'product_key' => $product_key,
			'file_path' => $expectedPath,
			'ip' => $ipBin,
			'user_agent' => $ua,
			'result' => 'success',
			'note' => null,
	]);
	
	$pdo->commit();
	
	// ---------- stream file ----------
	$size = filesize($expectedPath);
	
	// Use a safer content-type fallback
	$mime = 'application/octet-stream';
	if (function_exists('mime_content_type')) {
		$detected = @mime_content_type($expectedPath);
		if ($detected) $mime = $detected;
	}
	
	header('Content-Description: File Transfer');
	header('Content-Type: ' . $mime);
	header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
	header('Content-Length: ' . $size);
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
	
	// Clean output buffers to avoid corruption
	while (ob_get_level()) { ob_end_clean(); }
	
	readfile($expectedPath);
	exit;
	
} catch (Throwable $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	
	// Best-effort log (may fail if DB is down)
	try {
		log_download($pdo, [
				'token_id' => null,
				'purchase_id' => null,
				'checkout_session_id' => null,
				'purchaser_email' => null,
				'product_key' => '',
				'file_path' => '',
				'ip' => $ipBin ?? null,
				'user_agent' => $ua ?? null,
				'result' => 'error',
				'note' => 'Exception: ' . substr($e->getMessage(), 0, 240),
		]);
	} catch (Throwable $ignored) {}
	
	http_response_code(500);
	echo 'An error occurred. Please contact support.';
	exit;
}	
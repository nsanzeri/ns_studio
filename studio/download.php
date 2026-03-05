<?php
require_once __DIR__ . '/_core/bootstrap.php';
require_once __DIR__ . '/config/stripe.php';

// -----------------------------
// Helpers
// -----------------------------
function ip_to_bin(?string $ip): ?string {
	if (!$ip) return null;
	$bin = @inet_pton($ip);
	return $bin === false ? null : $bin;
}

function log_download(PDO $pdo, array $data): void {
	$stmt = $pdo->prepare("
        INSERT INTO download_log
        (token_id, purchase_id, checkout_session_id, purchaser_email,
         product_key, file_path, ip, user_agent, result, note)
        VALUES
        (:token_id, :purchase_id, :checkout_session_id, :purchaser_email,
         :product_key, :file_path, :ip, :user_agent, :result, :note)
    ");
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

// -----------------------------
// Validate token format
// -----------------------------
$token = $_GET['t'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
	http_response_code(400);
	echo 'Invalid download link.';
	exit;
}

$ipBin = ip_to_bin($_SERVER['REMOTE_ADDR'] ?? null);
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

$products = product_file_map();

try {
	$pdo->beginTransaction();
	
	// LOCK the row (prevents race conditions)
	$stmt = $pdo->prepare("
        SELECT *
        FROM download_tokens
        WHERE token = ?
        LIMIT 1
        FOR UPDATE
    ");
	$stmt->execute([$token]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$row) {
		log_download($pdo, [
				'result' => 'not_found',
				'ip' => $ipBin,
				'user_agent' => $ua,
				'note' => 'Token not found'
		]);
		$pdo->commit();
		http_response_code(404);
		echo 'Download link not found.';
		exit;
	}
	
	$product_key = $row['product_key'];
	
	if (!isset($products[$product_key])) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'result' => 'invalid',
				'ip' => $ipBin,
				'user_agent' => $ua,
				'note' => 'Unknown product_key'
		]);
		$pdo->commit();
		http_response_code(400);
		echo 'Invalid product.';
		exit;
	}
	
	// Expiration enforcement
	if (new DateTimeImmutable() >= new DateTimeImmutable($row['expires_at'])) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['checkout_session_id'],
				'purchaser_email' => $row['purchaser_email'],
				'product_key' => $product_key,
				'result' => 'expired',
				'ip' => $ipBin,
				'user_agent' => $ua
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has expired.';
		exit;
	}
	
	if ((int)$row['uses_remaining'] <= 0) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['checkout_session_id'],
				'purchaser_email' => $row['purchaser_email'],
				'product_key' => $product_key,
				'result' => 'exhausted',
				'ip' => $ipBin,
				'user_agent' => $ua
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has already been used.';
		exit;
	}
	
	$filePath = $products[$product_key]['file_path'];
	$downloadName = $products[$product_key]['download_name'];
	
	if (!is_file($filePath)) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'result' => 'error',
				'note' => 'File missing',
				'ip' => $ipBin,
				'user_agent' => $ua
		]);
		$pdo->commit();
		http_response_code(404);
		echo 'File not available.';
		exit;
	}
	
	// Atomic decrement
	$update = $pdo->prepare("
        UPDATE download_tokens
        SET uses_remaining = uses_remaining - 1,
            last_used_at = NOW()
        WHERE id = ? AND uses_remaining > 0
    ");
	$update->execute([$row['id']]);
	
	if ($update->rowCount() !== 1) {
		$pdo->commit();
		http_response_code(410);
		echo 'Download link already used.';
		exit;
	}
	
	log_download($pdo, [
			'token_id' => $row['id'],
			'purchase_id' => $row['purchase_id'] ?? null,
			'checkout_session_id' => $row['checkout_session_id'],
			'purchaser_email' => $row['purchaser_email'],
			'product_key' => $product_key,
			'file_path' => $filePath,
			'result' => 'success',
			'ip' => $ipBin,
			'user_agent' => $ua
	]);
	
	$pdo->commit();
	
	// Stream file
	while (ob_get_level()) ob_end_clean();
	
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
	header('Content-Length: ' . filesize($filePath));
	header('Cache-Control: no-store');
	
	readfile($filePath);
	exit;
	
} catch (Throwable $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	http_response_code(500);
	echo 'An error occurred. Please contact support.';
	exit;
}
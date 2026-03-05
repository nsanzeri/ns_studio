<?php
require_once __DIR__ . '/_private/_core/bootstrap.php';
require_once __DIR__ . '/_private/config/stripe.php';

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

// -----------------------------
// Basic bot detection (conservative)
// -----------------------------
if (!$ua || preg_match('/bot|crawl|spider|wget|curl|python|scrapy|httpclient|postman|insomnia/i', $ua)) {
	// Best-effort log (bootstrap normally provides $pdo)
	if (isset($pdo) && $pdo instanceof PDO) {
		try {
			log_download($pdo, [
					'result' => 'blocked',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'Bot detected'
			]);
		} catch (Throwable $e) { /* ignore */ }
	}
	http_response_code(403);
	echo 'Automated downloads are not allowed.';
	exit;
}

// -----------------------------
// Optional signed-link verification (set DOWNLOAD_SECRET in .env to enable)
// Link format: /download.php?t=<token>&s=<hmac>
// Where: hmac = hash_hmac('sha256', token, DOWNLOAD_SECRET)
// -----------------------------
$downloadSecret = $_ENV['DOWNLOAD_SECRET'] ?? getenv('DOWNLOAD_SECRET') ?: null;
if ($downloadSecret) {
	$sig = $_GET['s'] ?? '';
	$expected = hash_hmac('sha256', $token, $downloadSecret);
	if (!$sig || !hash_equals($expected, $sig)) {
		http_response_code(403);
		echo 'Invalid download signature.';
		exit;
	}
}


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
	
	// -----------------------------
	// Anti-link-sharing: bind token to first IP that uses it
	// Requires:
	//   ALTER TABLE download_tokens ADD COLUMN first_ip VARBINARY(16) NULL;
	// -----------------------------
	if (!empty($row['first_ip'])) {
		if ($ipBin === null || $row['first_ip'] !== $ipBin) {
			log_download($pdo, [
					'token_id' => $row['id'],
					'purchase_id' => $row['purchase_id'] ?? null,
					'checkout_session_id' => $row['checkout_session_id'],
					'purchaser_email' => $row['purchaser_email'],
					'product_key' => $product_key,
					'result' => 'blocked',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'IP mismatch'
			]);
			$pdo->commit();
			http_response_code(403);
			echo 'Download link cannot be used from this location.';
			exit;
		}
	} else {
		// First use binds the token to this IP (best-effort; row is already locked).
		$bind = $pdo->prepare("UPDATE download_tokens SET first_ip = ? WHERE id = ? AND first_ip IS NULL");
		$bind->execute([$ipBin, $row['id']]);
		$row['first_ip'] = $ipBin; // keep local copy consistent for logging/debugging
	}
	
	
	
	// -----------------------------
	// Rate limiting (per token, DB-backed; default: 5 hits / 60 seconds)
	// Requires:
	//   CREATE TABLE download_rate_limit (
	//     token_id INT PRIMARY KEY,
	//     window_start DATETIME NOT NULL,
	//     hits INT NOT NULL
	//   );
	// Optional .env:
	//   DOWNLOAD_RATE_LIMIT=5
	//   DOWNLOAD_RATE_WINDOW=60
	// -----------------------------
	$rateLimit = (int)($_ENV['DOWNLOAD_RATE_LIMIT'] ?? getenv('DOWNLOAD_RATE_LIMIT') ?: 5);
	$rateWindow = (int)($_ENV['DOWNLOAD_RATE_WINDOW'] ?? getenv('DOWNLOAD_RATE_WINDOW') ?: 60);
	if ($rateLimit > 0 && $rateWindow > 0) {
		$rlSel = $pdo->prepare("SELECT token_id, window_start, hits FROM download_rate_limit WHERE token_id = ? LIMIT 1 FOR UPDATE");
		$rlSel->execute([$row['id']]);
		$rl = $rlSel->fetch(PDO::FETCH_ASSOC);
		
		if (!$rl) {
			$pdo->prepare("INSERT INTO download_rate_limit (token_id, window_start, hits) VALUES (?, NOW(), 1)")
			->execute([$row['id']]);
		} else {
			$start = new DateTimeImmutable($rl['window_start']);
			$now = new DateTimeImmutable();
			$age = $now->getTimestamp() - $start->getTimestamp();
			
			if ($age > $rateWindow) {
				$pdo->prepare("UPDATE download_rate_limit SET window_start = NOW(), hits = 1 WHERE token_id = ?")
				->execute([$row['id']]);
			} elseif ((int)$rl['hits'] >= $rateLimit) {
				log_download($pdo, [
						'token_id' => $row['id'],
						'purchase_id' => $row['purchase_id'] ?? null,
						'checkout_session_id' => $row['checkout_session_id'],
						'purchaser_email' => $row['purchaser_email'],
						'product_key' => $product_key,
						'result' => 'blocked',
						'ip' => $ipBin,
						'user_agent' => $ua,
						'note' => 'Rate limit exceeded'
				]);
				$pdo->commit();
				http_response_code(429);
				header('Retry-After: ' . (string)max(1, $rateWindow - $age));
				echo 'Too many download attempts. Please wait and try again.';
				exit;
			} else {
				$pdo->prepare("UPDATE download_rate_limit SET hits = hits + 1 WHERE token_id = ?")
				->execute([$row['id']]);
			}
		}
	}
	
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
	
	// -----------------------------
	// "Signed streaming" / direct-access prevention:
	// Enforce that the served file lives under a private storage root.
	// Recommended: keep files OUTSIDE the public web root.
	// Optional .env:
	//   DOWNLOAD_STORAGE_ROOT=/absolute/path/to/private/downloads
	// Default: <this_dir>/_private/downloads
	// -----------------------------
	$storageRoot = $_ENV['DOWNLOAD_STORAGE_ROOT'] ?? getenv('DOWNLOAD_STORAGE_ROOT') ?: (__DIR__ . '/_private/downloads');
	$rootReal = realpath($storageRoot);
	$fileReal = realpath($filePath);
	
	if ($rootReal && $fileReal) {
		$rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		if (strpos($fileReal, $rootPrefix) !== 0) {
			log_download($pdo, [
					'token_id' => $row['id'],
					'purchase_id' => $row['purchase_id'] ?? null,
					'checkout_session_id' => $row['checkout_session_id'],
					'purchaser_email' => $row['purchaser_email'],
					'product_key' => $product_key,
					'result' => 'error',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'File path outside storage root'
			]);
			$pdo->commit();
			http_response_code(403);
			echo 'File not available.';
			exit;
		}
	} // If rootReal is missing, we do not block (keeps backward compatibility).
	
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
	
	header('Content-Type: ' . (function_exists('mime_content_type') ? (mime_content_type($filePath) ?: 'application/octet-stream') : 'application/octet-stream'));
	header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
	header('Content-Length: ' . filesize($filePath));
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	
	readfile($filePath);
	exit;
	
} catch (Throwable $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	http_response_code(500);
	echo 'An error occurred. Please contact support.';
	exit;
}
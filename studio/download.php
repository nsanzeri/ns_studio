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


function download_page_h(string $value): string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function render_download_landing(array $row, string $downloadName, string $token, ?string $sig): void {
	$productTitle = trim((string)($row['product_key'] ?? '')) === 'btb' ? 'Backing Track Blueprint' : 'your product';
	$email = trim((string)($row['purchaser_email'] ?? ''));
	$query = ['t' => $token, 'download' => '1'];
	if ($sig) { $query['s'] = $sig; }
	$downloadUrl = base_url('download.php') . '?' . http_build_query($query);
	$registerUrl = base_url('member/register.php') . ($email !== '' ? '?email=' . urlencode($email) : '');
	$loginUrl = base_url('member/login.php');
	$productsUrl = base_url('member/library.php');
	?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Download <?= download_page_h($productTitle) ?></title>
	<style>
		:root{--bg:#020611;--panel:#fffdf8;--text:#171717;--muted:#5e6472;--gold1:#e8c65c;--gold2:#c99b2d;--goldText:#1c1505;}
		*{box-sizing:border-box} body{margin:0;min-height:100vh;font-family:Arial,Helvetica,sans-serif;background:radial-gradient(circle at top,#061327 0%,var(--bg) 55%);color:var(--text);} .wrap{max-width:860px;margin:0 auto;padding:56px 20px}.card{background:var(--panel);border:1px solid rgba(184,141,43,.28);border-radius:24px;padding:42px 30px;box-shadow:0 18px 50px rgba(0,0,0,.28)} h1{margin:0 0 14px;font-size:clamp(32px,5vw,48px);line-height:1.08}.lead{font-size:20px;line-height:1.55;color:#242424;margin:0 0 22px}.callout{background:#fff2c8;border:1px solid rgba(184,141,43,.35);border-radius:18px;padding:18px;margin:22px 0;color:#332508}.muted{color:var(--muted);line-height:1.65}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.btn{display:inline-block;padding:14px 22px;border-radius:999px;text-decoration:none;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.btn-primary{background:linear-gradient(180deg,var(--gold1),var(--gold2));color:var(--goldText)}.btn-outline{border:1px solid rgba(0,0,0,.22);color:#1c2230;background:#fff}.small{font-size:14px;margin-top:20px}@media(max-width:640px){.card{padding:30px 20px}.btn{width:100%;text-align:center}}
	</style>
</head>
<body>
	<div class="wrap"><div class="card">
		<h1>Your download is ready.</h1>
		<p class="lead">This is a temporary download link for <strong><?= download_page_h($productTitle) ?></strong>. Click below to download the PDF, then save it to your device.</p>
		<div class="callout"><strong>Want permanent access?</strong><br>Create a free login using the same email you used at checkout. Your purchase will appear in <strong>My Products</strong>, where you can access it again anytime.</div>
		<div class="actions">
			<a class="btn btn-primary" href="<?= download_page_h($downloadUrl) ?>">Download PDF</a>
			<a class="btn btn-outline" href="<?= download_page_h($registerUrl) ?>">Create Free Login</a>
			<a class="btn btn-outline" href="<?= download_page_h($loginUrl) ?>">Log In</a>
		</div>
		<p class="muted small">Already logged in? Go to <a href="<?= download_page_h($productsUrl) ?>">My Products</a> to access your purchases and tools.</p>
	</div></div>
</body>
</html>
	<?php
}

function logged_in_user_has_download_access(PDO $pdo, array $row): bool {
	if (!class_exists('Auth') || !Auth::isLoggedIn()) {
		return false;
	}

	$userId = (int)(Auth::userId() ?? 0);
	if ($userId <= 0) {
		return false;
	}

	$productId = (int)($row['product_id'] ?? 0);
	$productKey = trim((string)($row['product_key'] ?? ''));

	if ($productId > 0) {
		$stmt = $pdo->prepare("\n\t\t\tSELECT 1\n\t\t\tFROM entitlements e\n\t\t\tWHERE e.user_id = ?\n\t\t\t  AND e.product_id = ?\n\t\t\t  AND e.status = 'active'\n\t\t\t  AND (e.expires_at IS NULL OR e.expires_at > NOW())\n\t\t\tLIMIT 1\n\t\t");
		$stmt->execute([$userId, $productId]);
		if ($stmt->fetchColumn()) {
			return true;
		}
	}

	if ($productKey !== '') {
		$stmt = $pdo->prepare("\n\t\t\tSELECT 1\n\t\t\tFROM entitlements e\n\t\t\tJOIN products p ON p.id = e.product_id\n\t\t\tWHERE e.user_id = ?\n\t\t\t  AND p.slug = ?\n\t\t\t  AND e.status = 'active'\n\t\t\t  AND (e.expires_at IS NULL OR e.expires_at > NOW())\n\t\t\tLIMIT 1\n\t\t");
		$stmt->execute([$userId, $productKey]);
		return (bool)$stmt->fetchColumn();
	}

	return false;
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
	if (isset($pdo) && $pdo instanceof PDO) {
		try {
			log_download($pdo, [
					'result' => 'blocked',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'Bot detected',
			]);
		} catch (Throwable $e) {
			// ignore
		}
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
				'note' => 'Token not found',
		]);
		$pdo->commit();
		http_response_code(404);
		echo 'Download link not found.';
		exit;
	}
	
	$productKey = (string)($row['product_key'] ?? '');
	
	// -----------------------------
	// Anti-link-sharing: bind token to first IP that uses it
	// -----------------------------
	if (!empty($row['first_ip'])) {
		if ($ipBin === null || $row['first_ip'] !== $ipBin) {
			log_download($pdo, [
					'token_id' => $row['id'],
					'purchase_id' => $row['purchase_id'] ?? null,
					'checkout_session_id' => $row['checkout_session_id'],
					'purchaser_email' => $row['purchaser_email'],
					'product_key' => $productKey,
					'result' => 'blocked',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'IP mismatch',
			]);
			$pdo->commit();
			http_response_code(403);
			echo 'Download link cannot be used from this location.';
			exit;
		}
	} else {
		$bind = $pdo->prepare("UPDATE download_tokens SET first_ip = ? WHERE id = ? AND first_ip IS NULL");
		$bind->execute([$ipBin, $row['id']]);
		$row['first_ip'] = $ipBin;
	}
	
	// -----------------------------
	// Rate limiting (per token, DB-backed; default: 5 hits / 60 seconds)
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
						'product_key' => $productKey,
						'result' => 'blocked',
						'ip' => $ipBin,
						'user_agent' => $ua,
						'note' => 'Rate limit exceeded',
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
	
	// Expiration enforcement
	if (new DateTimeImmutable() >= new DateTimeImmutable($row['expires_at'])) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['checkout_session_id'],
				'purchaser_email' => $row['purchaser_email'],
				'product_key' => $productKey,
				'result' => 'expired',
				'ip' => $ipBin,
				'user_agent' => $ua,
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
				'product_key' => $productKey,
				'result' => 'exhausted',
				'ip' => $ipBin,
				'user_agent' => $ua,
		]);
		$pdo->commit();
		http_response_code(410);
		echo 'This download link has already been used.';
		exit;
	}
	
	// Use the token row as the source of truth.
	$filePath = trim((string)($row['file_path'] ?? ''));
	if ($filePath === '') {
		log_download($pdo, [
				'token_id' => $row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['checkout_session_id'],
				'purchaser_email' => $row['purchaser_email'],
				'product_key' => $productKey,
				'result' => 'invalid',
				'ip' => $ipBin,
				'user_agent' => $ua,
				'note' => 'Missing file_path on token',
		]);
		$pdo->commit();
		http_response_code(400);
		echo 'Invalid product.';
		exit;
	}
	
	$downloadName = basename($filePath);

	// Show the human-friendly download page only for initial purchase/email links.
	// Logged-in members who already own the product should get the file immediately.
	$isExplicitDownload = (($_GET['download'] ?? '') === '1');
	$isMemberGeneratedToken = str_starts_with((string)($row['checkout_session_id'] ?? ''), 'member-');
	$isLoggedInOwner = logged_in_user_has_download_access($pdo, $row);

	if (!$isExplicitDownload && !$isMemberGeneratedToken && !$isLoggedInOwner) {
		$pdo->commit();
		render_download_landing($row, $downloadName, $token, $_GET['s'] ?? null);
		exit;
	}
	
	// -----------------------------
	// "Signed streaming" / direct-access prevention:
	// Enforce that the served file lives under a private storage root.
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
					'product_key' => $productKey,
					'result' => 'error',
					'ip' => $ipBin,
					'user_agent' => $ua,
					'note' => 'File path outside storage root',
			]);
			$pdo->commit();
			http_response_code(403);
			echo 'File not available.';
			exit;
		}
	}
	
	if (!is_file($filePath)) {
		log_download($pdo, [
				'token_id' => $row['id'],
				'purchase_id' => $row['purchase_id'] ?? null,
				'checkout_session_id' => $row['checkout_session_id'],
				'purchaser_email' => $row['purchaser_email'],
				'product_key' => $productKey,
				'file_path' => $filePath,
				'result' => 'error',
				'note' => 'File missing',
				'ip' => $ipBin,
				'user_agent' => $ua,
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
			'product_key' => $productKey,
			'file_path' => $filePath,
			'result' => 'success',
			'ip' => $ipBin,
			'user_agent' => $ua,
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
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	http_response_code(500);
	echo 'An error occurred. Please contact support.';
	exit;
}

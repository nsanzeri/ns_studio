<?php
require_once dirname(__DIR__) . '/_private/_core/bootstrap.php';
require_once dirname(__DIR__) . '/_private/config/stripe.php';

$session_id = $_GET['sid'] ?? '';
if (!$session_id) {
	http_response_code(400);
	echo "Missing session id.";
	exit;
}

$products = product_file_map();

/**
 * Pick Stripe API key based on session id prefix.
 * This prevents "mode=live" env settings from breaking cs_test_ sessions.
 */
function stripe_key_for_session(string $sessionId): ?string {
	if (str_starts_with($sessionId, 'cs_test_')) {
		return env('STRIPE_SECRET_KEY_TEST') ?: (env('STRIPE_SECRET_KEY') ?: null);
	}
	return env('STRIPE_SECRET_KEY_LIVE') ?: (env('STRIPE_SECRET_KEY') ?: null);
}

/**
 * Build an absolute URL in the SAME web directory as this script.
 * This avoids APP_URL / SITE_URL path duplication problems.
 */
function current_script_dir_url(string $filename): string {
	$isHttps = (
			(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
			(($_SERVER['SERVER_PORT'] ?? '') == '443')
			);
	
	$scheme = $isHttps ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	
	$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
	$dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
	
	if ($dir === '/' || $dir === '.') {
		$dir = '';
	}
	
	return $scheme . '://' . $host . $dir . '/' . ltrim($filename, '/');
}

try {
	$key = stripe_key_for_session($session_id);
	if ($key) {
		\Stripe\Stripe::setApiKey($key);
	}
	
	// 1) Verify payment with Stripe
	$session = \Stripe\Checkout\Session::retrieve($session_id);
	if (($session->payment_status ?? '') !== 'paid') {
		http_response_code(200);
		echo "Payment not confirmed yet. Refresh in a moment.";
		exit;
	}
	
	// 2) Determine product
	$product_key = $session->metadata->product_key ?? '';
	if (!$product_key) {
		$product_key = $_GET['p'] ?? '';
	}
	if (!$product_key || !isset($products[$product_key])) {
		http_response_code(400);
		echo "Missing or invalid product.";
		exit;
	}
	
	$email = $session->customer_details->email ?? null;
	$meta = $products[$product_key];
	
	$expires_at = (new DateTimeImmutable('now'))
	->add(new DateInterval('PT' . max(1, (int)$meta['expires_minutes']) . 'M'))
	->format('Y-m-d H:i:s');
	
	// 3) Mint token immediately (idempotent)
	$pdo->beginTransaction();
	
	$token = bin2hex(random_bytes(32));
	try {
		$pdo->prepare("
			INSERT INTO download_tokens
				(token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining)
			VALUES
				(?, ?, ?, ?, ?, ?, ?)
		")->execute([
				$token,
				$session_id,
				$email,
				$product_key,
				$meta['file_path'],
				$expires_at,
				(int)$meta['uses'],
		]);
	} catch (PDOException $e) {
		$sqlState = $e->getCode();
		$driverCode = $e->errorInfo[1] ?? null;
		
		if ($sqlState === '23000' || $driverCode === 1062) {
			$stmt = $pdo->prepare("
				SELECT token
				FROM download_tokens
				WHERE checkout_session_id = ? AND product_key = ?
				LIMIT 1
			");
			$stmt->execute([$session_id, $product_key]);
			$existing = $stmt->fetch(PDO::FETCH_ASSOC);
			$token = $existing['token'] ?? $token;
		} else {
			throw $e;
		}
	}
	
	$pdo->commit();
	
	$downloadUrl = base_url('download.php') . '?t=' . urlencode($token);
	
	// Optional signed link (enabled when DOWNLOAD_SECRET exists)
	$downloadSecret = $_ENV['DOWNLOAD_SECRET'] ?? getenv('DOWNLOAD_SECRET') ?: null;
	if ($downloadSecret) {
		$sig = hash_hmac('sha256', $token, $downloadSecret);
		$downloadUrl .= '&s=' . urlencode($sig);
	}
	
	$isTest = str_starts_with($session_id, 'cs_test_');
	$productTitle = $meta['title'] ?? 'your download';
	
} catch (Throwable $e) {
	if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
		$pdo->rollBack();
	}
	
	if (function_exists('is_local') && is_local()) {
		http_response_code(500);
		echo "Could not finalize download: " . htmlspecialchars($e->getMessage());
		exit;
	}
	
	http_response_code(500);
	echo "Could not finalize download. Please contact support.";
	exit;
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Success | <?= htmlspecialchars($productTitle) ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		:root{
			--bg:#020611;
			--panel:#fffdf8;
			--panel-border:rgba(184,141,43,.28);
			--text:#171717;
			--muted:#5e6472;
			--gold1:#e8c65c;
			--gold2:#c99b2d;
			--goldText:#1c1505;
		}
		*{box-sizing:border-box}
		html,body{margin:0;padding:0}
		body{
			font-family:Arial, Helvetica, sans-serif;
			background:radial-gradient(circle at top, #061327 0%, var(--bg) 55%);
			color:var(--text);
			min-height:100vh;
		}
		.wrap{
			max-width:860px;
			margin:0 auto;
			padding:56px 20px;
		}
		.card{
			background:var(--panel);
			border:1px solid var(--panel-border);
			border-radius:24px;
			padding:42px 30px;
			box-shadow:0 18px 50px rgba(0,0,0,.28);
			text-align:center;
		}
		.badge{
			display:inline-block;
			margin-bottom:14px;
			padding:8px 14px;
			border-radius:999px;
			background:#fff2c8;
			color:#916400;
			font-size:12px;
			font-weight:700;
			letter-spacing:.08em;
		}
		h1{
			margin:0 0 14px;
			font-size:clamp(34px, 5vw, 52px);
			line-height:1.06;
			color:#101010;
		}
		.lead{
			font-size:21px;
			line-height:1.5;
			max-width:680px;
			margin:0 auto 16px;
			color:#242424;
		}
		.receipt{
			margin:0 auto 28px;
			max-width:680px;
			font-size:16px;
			line-height:1.6;
			color:#5b6373;
		}
		.receipt strong{
			color:#2a3244;
		}
		.copy{
			max-width:680px;
			margin:0 auto 28px;
			text-align:left;
		}
		.copy p{
			margin:0 0 16px;
			font-size:18px;
			line-height:1.75;
			color:#2b2b2b;
		}
		.copy p:last-child{
			margin-bottom:0;
		}
		.btn{
			display:inline-block;
			padding:16px 28px;
			border-radius:999px;
			background:linear-gradient(180deg, var(--gold1), var(--gold2));
			color:var(--goldText);
			text-decoration:none;
			font-weight:800;
			font-size:18px;
			letter-spacing:.12em;
			text-transform:uppercase;
			box-shadow:0 18px 28px rgba(0,0,0,.30);
		}
		.note{
			margin-top:20px;
			font-size:15px;
			line-height:1.7;
			color:#666d7c;
		}
		@media (max-width: 640px){
			.card{padding:30px 20px}
			.copy p{font-size:17px}
			.lead{font-size:19px}
			.btn{width:100%; max-width:360px}
		}
	</style>
</head>
<body>
	<div class="wrap">
		<div class="card">

			<?php if ($isTest): ?>
				<div class="badge">TEST MODE</div>
			<?php endif; ?>

			<h1>You’re in.</h1>

			<p class="lead">
				Your purchase is confirmed, and your <strong><?= htmlspecialchars($productTitle) ?></strong> is ready.
			</p>

			<?php if (!empty($email)): ?>
				<p class="receipt">
					A receipt and backup access link were sent to <strong><?= htmlspecialchars($email) ?></strong>.
				</p>
			<?php endif; ?>

			<div class="copy">
				<p>
					Inside this guide, you’ll get the exact concepts, shortcuts, and practical moves behind a bigger, tighter, more professional backing-track setup.
				</p>

				<p>
					This is the real-world approach behind making tracks support the show instead of fighting it.
				</p>

				<p>
					Best move: download it now, read through it once, and pull out one or two ideas you can use immediately.
				</p>
			</div>

			<p style="margin:30px 0 0;">
				<a class="btn" href="<?= htmlspecialchars($downloadUrl) ?>" download>
					Download Your Guide
				</a>
			</p>

			<p class="note">
				This link expires and has limited uses to protect the product.
				If anything goes sideways, reply to your receipt email.
			</p>
		</div>
	</div>
</body>
</html>
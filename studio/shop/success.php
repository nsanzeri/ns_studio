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
			$stmt = $pdo->prepare("SELECT token FROM download_tokens WHERE checkout_session_id=? AND product_key=? LIMIT 1");
			$stmt->execute([$session_id, $product_key]);
			$existing = $stmt->fetch(PDO::FETCH_ASSOC);
			$token = $existing['token'] ?? $token;
		} else {
			throw $e;
		}
	}
	
	$pdo->commit();
	
	$downloadUrl = rtrim(SITE_URL, '/') . base_url('download.php') . '?t=' . urlencode($token);
	
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
	<link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
</head>
<body>
	<div class="container" style="padding:70px 20px; max-width:820px; margin:0 auto;">
		<div style="background:#fff; border:1px solid rgba(184,141,43,.25); border-radius:18px; padding:38px 28px; box-shadow:0 10px 30px rgba(0,0,0,.08); text-align:center;">
			
			<?php if ($isTest): ?>
				<div style="display:inline-block; margin-bottom:14px; padding:6px 12px; border-radius:999px; background:#fff7df; color:#8a6400; font-size:12px; font-weight:700; letter-spacing:.04em;">
					TEST MODE
				</div>
			<?php endif; ?>

			<h1 style="margin:0 0 14px; font-size:clamp(32px, 4vw, 46px); line-height:1.1;">
				You're in.
			</h1>

			<p style="font-size:20px; line-height:1.5; max-width:640px; margin:0 auto 16px;">
				Your purchase is confirmed, and your <strong><?= htmlspecialchars($productTitle) ?></strong> is ready.
			</p>

			<?php if (!empty($email)): ?>
				<p class="muted" style="margin:0 auto 24px; max-width:640px;">
					A receipt and backup access link were sent to <strong><?= htmlspecialchars($email) ?></strong>.
				</p>
			<?php endif; ?>

			<div style="max-width:660px; margin:0 auto 26px; text-align:left;">
				<p style="margin:0 0 14px; font-size:17px; line-height:1.7;">
					Inside this guide, you’ll get the exact concepts, shortcuts, and practical moves behind a bigger, tighter, more professional backing-track setup.
				</p>

				<p style="margin:0 0 14px; font-size:17px; line-height:1.7;">
					This is not fluff. It’s the real-world approach behind making tracks support the show instead of fighting it.
				</p>

				<p style="margin:0; font-size:17px; line-height:1.7;">
					Best move: download it now, read through it once, and steal one or two ideas you can apply immediately.
				</p>
			</div>

			<p style="margin:28px 0 0;">
				<a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>" style="display:inline-block; padding:15px 26px; font-size:18px;" download>
					Download Your Guide
				</a>
			</p>

			<p class="muted small" style="margin-top:18px; line-height:1.6;">
				This link expires and has limited uses to protect the product.
				If anything goes sideways, reply to your receipt email.
			</p>
		</div>
	</div>
</body>
</html>
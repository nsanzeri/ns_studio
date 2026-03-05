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

	// 1) Verify payment with Stripe (authoritative)
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

	// Token mint (idempotent via uniq_session_product on download_tokens)
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
		// Only treat duplicate-key errors as "already minted"
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

	$downloadUrl = base_url('download.php') . '?t=' . urlencode($token);

} catch (Throwable $e) {
	if ($pdo instanceof PDO && $pdo->inTransaction()) {
		$pdo->rollBack();
	}
	// Developer debugging: set APP_ENV=local to show error details.
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
  <title>Success | Download</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
</head>
<body>
  <div class="container" style="padding:80px 20px; text-align:center; max-width:780px; margin:0 auto;">
    <h1>You're in 🎉</h1>

    <?php if (!empty($email)): ?>
      <p class="muted" style="margin-top:10px;">
        Confirmation sent to <strong><?= htmlspecialchars($email) ?></strong>
      </p>
    <?php endif; ?>

    <p style="margin-top:22px;">Your payment is confirmed.</p>

    <a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>" style="margin-top:18px; display:inline-block;">
      Download Now
    </a>

    <p class="muted small" style="margin-top:18px;">
      This link expires and has limited uses (to protect the product).
      If anything goes sideways, reply to your receipt email.
    </p>
  </div>
</body>
</html>

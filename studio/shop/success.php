<?php
require_once dirname(__DIR__) . '/_core/bootstrap.php';
require_once dirname(__DIR__) . '/config/stripe.php';

$session_id = $_GET['sid'] ?? '';
if (!$session_id) {
	http_response_code(400);
	echo "Missing session id.";
	exit;
}

$products = product_file_map();

try {
	// Verify payment with Stripe (authoritative)
	$session = \Stripe\Checkout\Session::retrieve($session_id);
	
	if (($session->payment_status ?? '') !== 'paid') {
		http_response_code(200);
		echo "Payment not confirmed yet. If you were charged, refresh in a moment.";
		exit;
	}
	
	// Prefer product_key from Stripe metadata
	$product_key = $session->metadata->product_key ?? '';
	
	// Allow fallback from URL if metadata is missing (but metadata is the right way)
	if (!$product_key) {
		$product_key = $_GET['p'] ?? '';
	}
	
	if (!$product_key || !isset($products[$product_key])) {
		http_response_code(400);
		echo "Missing or invalid product.";
		exit;
	}
	
	// Look up token minted by webhook
	$stmt = $pdo->prepare("
    SELECT token
    FROM download_tokens
    WHERE checkout_session_id = ? AND product_key = ?
    LIMIT 1
  ");
	$stmt->execute([$session_id, $product_key]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$token = $row['token'] ?? null;
	$downloadUrl = $token ? base_url('download.php') . '?t=' . urlencode($token) : null;
	
	// Optional: show customer email if available
	$email = $session->customer_details->email ?? '';
	
} catch (Throwable $e) {
	http_response_code(500);
	echo "Could not verify your purchase. Please contact support.";
	exit;
}

// Auto-refresh while waiting for webhook (usually seconds)
$shouldRefresh = !$downloadUrl;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Success | Download</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
  <?php if ($shouldRefresh): ?>
    <meta http-equiv="refresh" content="3">
  <?php endif; ?>
</head>
<body>
  <div class="container" style="padding:80px 20px; text-align:center; max-width:780px; margin:0 auto;">
    <h1>You're in 🎉</h1>

    <?php if ($email): ?>
      <p class="muted" style="margin-top:10px;">
        Confirmation sent to <strong><?= htmlspecialchars($email) ?></strong>
      </p>
    <?php endif; ?>

    <?php if ($downloadUrl): ?>
      <p style="margin-top:22px;">Your payment is confirmed. Download your file below.</p>

      <a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>" style="margin-top:18px; display:inline-block;">
        Download Now
      </a>

      <p class="muted small" style="margin-top:18px;">
        This link expires and has limited uses (to protect the product).
        If anything goes sideways, reply to your receipt email.
      </p>

    <?php else: ?>
      <p style="margin-top:22px;">Your payment is confirmed.</p>
      <p><strong>Preparing your download link…</strong> (this usually takes a few seconds)</p>
      <p class="muted small">This page will refresh automatically.</p>

      <div style="margin-top:18px;">
        <a class="btn btn-outline" href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">Refresh now</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
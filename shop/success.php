<?php
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../config/stripe.php';

$session_id  = $_GET['sid'] ?? '';
$product_key = $_GET['p'] ?? '';

if (!$session_id) {
	http_response_code(400);
	echo "Missing session id.";
	exit;
}

try {
	$session = \Stripe\Checkout\Session::retrieve($session_id);
	
	if (($session->payment_status ?? '') !== 'paid') {
		echo "Payment not confirmed yet. If you were charged, refresh in a moment.";
		exit;
	}
	
	// Trust Stripe metadata for product_key
	$metaProduct = $session->metadata->product_key ?? '';
	if (!$metaProduct) {
		http_response_code(400);
		echo "Missing product metadata.";
		exit;
	}
	
	// If URL includes p, enforce it matches
	if ($product_key && $product_key !== $metaProduct) {
		http_response_code(400);
		echo "Product mismatch.";
		exit;
	}
	$product_key = $metaProduct;
	
	$products = product_file_map();
	if (!isset($products[$product_key])) {
		http_response_code(400);
		echo "Invalid product.";
		exit;
	}
	
	// LOOKUP ONLY — webhook must have minted token
	$stmt = $pdo->prepare("SELECT token FROM download_tokens WHERE checkout_session_id = ? AND product_key = ? LIMIT 1");
	$stmt->execute([$session_id, $product_key]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$token = $row['token'] ?? null;
	$downloadUrl = $token ? (base_url('download.php') . '?t=' . urlencode($token)) : null;
	
} catch (Exception $e) {
	http_response_code(500);
	echo "Could not verify your purchase. Please contact support.";
	exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Success | Download</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/style.css')) ?>">
  <?php if (!$downloadUrl): ?>
    <meta http-equiv="refresh" content="3">
  <?php endif; ?>
</head>
<body>
  <div class="container" style="padding:80px 20px; text-align:center;">
    <h1>You're in 🎉</h1>

    <?php if ($downloadUrl): ?>
      <p>Your purchase is confirmed. Download your Backing Track Blueprint below.</p>
      <a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>" style="margin-top:20px;">
        Download Now
      </a>
    <?php else: ?>
      <p>Your payment is confirmed.</p>
      <p><strong>Preparing your download link…</strong> (this usually takes a few seconds)</p>
      <p class="muted small">This page will refresh automatically.</p>
    <?php endif; ?>

    <div style="margin-top:28px; max-width:640px; margin-left:auto; margin-right:auto;">
      <p class="muted small" style="margin-bottom:10px;">
        Want this saved in <strong>My Library</strong>? Create your Studio login (15 sec).
      </p>
      <a class="btn btn-outline" href="<?= htmlspecialchars(base_url('studio/signup.php')) ?>?save=1&sid=<?= urlencode($session_id) ?>&p=<?= urlencode($product_key) ?>">
        Create Login &amp; Save to Library
      </a>
    </div>

    <p class="muted small" style="margin-top:22px;">
      Download links expire and have limited uses for protection. If you need help, reply to your receipt email.
    </p>
  </div>
</body>
</html>
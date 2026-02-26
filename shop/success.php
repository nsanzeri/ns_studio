<?php
// shop/success.php
require_once __DIR__ . '/../_core/bootstrap.php';

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/stripe.php';

$session_id  = $_GET['sid'] ?? '';
$product_key = $_GET['p'] ?? '';

$products = product_file_map();
if (!$session_id || !isset($products[$product_key])) {
  http_response_code(400);
  echo "Missing or invalid session/product.";
  exit;
}

try {
  $session = \Stripe\Checkout\Session::retrieve($session_id);

  // Confirm paid
  $paid = ($session->payment_status === 'paid');
  if (!$paid) {
    echo "Payment not confirmed yet. If you were charged, refresh in a moment.";
    exit;
  }

  // Create token only once (safe on refresh)
  $token = null;
  

  $stmt = $pdo->prepare("SELECT token FROM download_tokens WHERE checkout_session_id = ? AND product_key = ? LIMIT 1");
  $stmt->execute([$session_id, $product_key]);
  $existing = $stmt->fetch();

  if ($existing) {
    $token = $existing['token'];
  } else {
    $token = bin2hex(random_bytes(32)); // 64 chars

    $meta = $products[$product_key];
    $expires_at = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . (int)$meta['expires_minutes'] . 'M'));

    $insert = $pdo->prepare("
      INSERT INTO download_tokens
      (token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $insert->execute([
      $token,
      $session_id,
      $session->customer_details->email ?? null,
      $product_key,
      $meta['file_path'],
      $expires_at->format('Y-m-d H:i:s'),
      (int)$meta['uses']
    ]);
  }

  $downloadUrl = base_url('download.php') . '?t=' . urlencode($token);
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
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="container" style="padding:80px 20px; text-align:center;">
    <h1>You're in 🎉</h1>
    <p>Your purchase is confirmed. Download your Backing Track Blueprint below.</p>

    <a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>" style="margin-top:20px;">
      Download Now
    </a>

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
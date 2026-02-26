<?php
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../config/stripe.php';

$secret = env('STRIPE_WEBHOOK_SECRET_TEST');   // for test endpoint use STRIPE_WEBHOOK_SECRET_TEST
if (!$secret) {
	http_response_code(500);
	echo "Missing webhook secret";
	exit;
}

$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
	$event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
} catch (\Throwable $e) {
	http_response_code(400);
	echo "Invalid signature";
	exit;
}

$livemode = !empty($event->livemode) ? 1 : 0;

// Hard stop if wrong endpoint is receiving the wrong mode (safety net)
if ($livemode !== 1) { // this is LIVE endpoint
	http_response_code(400);
	echo "Wrong mode for live endpoint";
	exit;
}

// Idempotency: record the event first (unique on stripe_event_id + livemode)
try {
	$stmt = $pdo->prepare("
    INSERT INTO stripe_webhook_events
      (stripe_event_id, livemode, event_type, api_version, payload_json, signature_header, process_status)
    VALUES
      (?, ?, ?, ?, ?, ?, 'received')
  ");
	$stmt->execute([
			$event->id,
			$livemode,
			$event->type,
			$event->api_version ?? null,
			$payload,
			$sig
	]);
} catch (\PDOException $e) {
	// Duplicate event -> already handled (or being handled)
	// Return 200 so Stripe stops retrying
	http_response_code(200);
	echo "Duplicate";
	exit;
}

function mark_event(PDO $pdo, string $eventId, int $livemode, string $status, ?string $err = null): void {
	$pdo->prepare("
    UPDATE stripe_webhook_events
    SET process_status = ?, processed_at = NOW(), error_message = ?
    WHERE stripe_event_id = ? AND livemode = ?
  ")->execute([$status, $err, $eventId, $livemode]);
}

try {
	// Only act on the key event you care about
	if ($event->type !== 'checkout.session.completed') {
		mark_event($pdo, $event->id, $livemode, 'ignored', 'Unhandled type');
		http_response_code(200);
		echo "Ignored";
		exit;
	}
	
	/** @var \Stripe\Checkout\Session $session */
	$session = $event->data->object;
	
	// Make sure money actually moved
	if (($session->payment_status ?? '') !== 'paid') {
		mark_event($pdo, $event->id, $livemode, 'ignored', 'Not paid');
		http_response_code(200);
		echo "Not paid";
		exit;
	}
	
	$sessionId = (string)$session->id;
	$email = $session->customer_details->email ?? null;
	$productKey = $session->metadata->product_key ?? '';
	
	$products = product_file_map();
	if (!$productKey || !isset($products[$productKey])) {
		mark_event($pdo, $event->id, $livemode, 'failed', 'Missing/invalid product_key');
		http_response_code(200);
		echo "Invalid product";
		exit;
	}
	
	$meta = $products[$productKey];
	$expires_at = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . (int)$meta['expires_minutes'] . 'M'));
	
	$pdo->beginTransaction();
	
	// Record purchase (idempotent by unique session id + livemode if you created that constraint)
	$pdo->prepare("
    INSERT INTO purchases
      (stripe_checkout_session_id, purchaser_email, amount_total, currency, livemode, status, paid_at)
    VALUES
      (?, ?, ?, ?, ?, 'paid', NOW())
    ON DUPLICATE KEY UPDATE
      status='paid', paid_at=COALESCE(paid_at, NOW())
  ")->execute([
  		$sessionId,
  		$email,
  		$session->amount_total ?? null,
  		$session->currency ?? null,
  		$livemode
  ]);
	
	// Mint token (idempotent thanks to uniq_session_product on download_tokens)
	$token = bin2hex(random_bytes(32));
	$ins = $pdo->prepare("
    INSERT INTO download_tokens
      (token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining)
    VALUES
      (?, ?, ?, ?, ?, ?, ?)
  ");
	try {
		$ins->execute([
				$token,
				$sessionId,
				$email,
				$productKey,
				$meta['file_path'],
				$expires_at->format('Y-m-d H:i:s'),
				(int)$meta['uses'],
		]);
	} catch (\PDOException $e) {
		// likely duplicate (refresh/replay) — do nothing
	}
	
	$pdo->commit();
	
	mark_event($pdo, $event->id, $livemode, 'processed', null);
	http_response_code(200);
	echo "OK";
} catch (\Throwable $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	mark_event($pdo, $event->id, $livemode, 'failed', substr($e->getMessage(), 0, 240));
	http_response_code(200); // still 200 to stop retries while you debug using logs
	echo "Failed";
}
<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';
require_once __DIR__ . '/../_private/_core/email.php';

$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

/**
 * Choose webhook secret based on the configured app mode.
 * This lets one endpoint serve test or live safely.
 */
$mode = env('STRIPE_MODE', 'test');
$secret = ($mode === 'live')
? env('STRIPE_WEBHOOK_SECRET_LIVE')
: env('STRIPE_WEBHOOK_SECRET_TEST');

if (!$secret) {
	http_response_code(500);
	echo "Missing webhook secret";
	exit;
}

try {
	$event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
} catch (\Throwable $e) {
	http_response_code(400);
	echo "Invalid signature";
	exit;
}

$livemode = !empty($event->livemode) ? 1 : 0;

/**
 * Safety check:
 * - if app is in test mode, reject live events
 * - if app is in live mode, reject test events
 */
if ($mode === 'live' && $livemode !== 1) {
	http_response_code(400);
	echo "Wrong mode for live endpoint";
	exit;
}

if ($mode !== 'live' && $livemode !== 0) {
	http_response_code(400);
	echo "Wrong mode for test endpoint";
	exit;
}

if (!function_exists('mark_webhook_event')) {
	function mark_webhook_event(PDO $pdo, string $eventId, int $livemode, string $status, ?string $err = null): void {
		$pdo->prepare("
			UPDATE stripe_webhook_events
			SET process_status = ?, processed_at = NOW(), error_message = ?
			WHERE stripe_event_id = ? AND livemode = ?
		")->execute([$status, $err, $eventId, $livemode]);
	}
}

/**
 * Idempotency: record event first.
 * Assumes unique key on (stripe_event_id, livemode)
 */
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
	http_response_code(200);
	echo "Duplicate";
	exit;
}

try {
	if ($event->type !== 'checkout.session.completed') {
		mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Unhandled type');
		http_response_code(200);
		echo "Ignored";
		exit;
	}
	
	/** @var \Stripe\Checkout\Session $session */
	$session = $event->data->object;
	
	if (($session->payment_status ?? '') !== 'paid') {
		mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Not paid');
		http_response_code(200);
		echo "Not paid";
		exit;
	}
	
	$sessionId  = (string) $session->id;
	$email      = $session->customer_details->email ?? null;
	$productKey = $session->metadata->product_key ?? '';
	
	$products = product_file_map();
	if (!$productKey || !isset($products[$productKey])) {
		mark_webhook_event($pdo, $event->id, $livemode, 'failed', 'Missing/invalid product_key');
		http_response_code(200);
		echo "Invalid product";
		exit;
	}
	
	$meta = $products[$productKey];
	$expires_at = (new DateTimeImmutable('now'))->add(
			new DateInterval('PT' . (int) $meta['expires_minutes'] . 'M')
			);
	
	$pdo->beginTransaction();
	
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
	
	$token = bin2hex(random_bytes(32));
	
	try {
		$pdo->prepare("
			INSERT INTO download_tokens
				(token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining)
			VALUES
				(?, ?, ?, ?, ?, ?, ?)
		")->execute([
				$token,
				$sessionId,
				$email,
				$productKey,
				$meta['file_path'],
				$expires_at->format('Y-m-d H:i:s'),
				(int) $meta['uses']
		]);
	} catch (\PDOException $e) {
		$sqlState   = $e->getCode();
		$driverCode = $e->errorInfo[1] ?? null;
		
		if ($sqlState === '23000' || $driverCode === 1062) {
			$stmt = $pdo->prepare("
				SELECT token
				FROM download_tokens
				WHERE checkout_session_id = ? AND product_key = ?
				LIMIT 1
			");
			$stmt->execute([$sessionId, $productKey]);
			$existing = $stmt->fetch(PDO::FETCH_ASSOC);
			$token = $existing['token'] ?? $token;
		} else {
			throw $e;
		}
	}
	
	$pdo->commit();
	mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
	
	if ($email) {
		$downloadUrl = base_url('download.php') . '?t=' . urlencode($token);
		$successUrl  = base_url('success.php') . '?sid=' . urlencode($sessionId);
		
		$isTest = ($livemode === 0);
		
		$subject = $isTest
		? "Your Backing Track Blueprint download link (TEST)"
				: "Your Backing Track Blueprint download link";
				
				$body =
				"Hey there,\n\n" .
				($isTest ? "Your TEST purchase is confirmed. Here are your links:\n\n" : "Your purchase is confirmed. Here are your links:\n\n") .
				"Download link:\n" . $downloadUrl . "\n\n" .
				"Success page (backup):\n" . $successUrl . "\n\n" .
				($isTest ? "" :
						"Notes:\n" .
						"- This link expires and has limited uses (to protect the product).\n" .
						"- If you have any trouble, reply to this email.\n\n"
						) .
						"Thanks!\n" .
						"Nick Sanzeri\n";
						
						$idemKey = "dl_link:" . ($livemode ? "live" : "test") . ":" . $sessionId . ":" . $productKey;
						
						try {
							send_and_log_email($pdo, [
									'message_type'    => 'download_link',
									'recipient'       => $email,
									'subject'         => $subject,
									'body'            => $body,
									'from_email'      => 'no-reply@nicksanzeri.com',
									'from_name'       => 'Nick Sanzeri',
									'reply_to'        => 'nsanzeri@gmail.com',
									'idempotency_key' => $idemKey,
									'related_table'   => 'purchases',
									'related_id'      => null,
							]);
						} catch (Throwable $e) {
							error_log("Webhook email send failed: " . $e->getMessage());
						}
	}
	
	http_response_code(200);
	echo "OK";
	
} catch (\Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	
	mark_webhook_event($pdo, $event->id, $livemode, 'failed', substr($e->getMessage(), 0, 240));
	error_log("Stripe webhook processing failed: " . $e->getMessage());
	
	http_response_code(200);
	echo "Failed";
}
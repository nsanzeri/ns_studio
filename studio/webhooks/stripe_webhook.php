<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';
require_once __DIR__ . '/../_private/_core/email.php';

$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$mode = env('STRIPE_MODE', 'test');
$secret = ($mode === 'live')
? env('STRIPE_WEBHOOK_SECRET_LIVE')
: env('STRIPE_WEBHOOK_SECRET_TEST');

if (!$secret) {
	http_response_code(500);
	echo 'Missing webhook secret';
	exit;
}

try {
	$event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
} catch (\Throwable $e) {
	http_response_code(400);
	echo 'Invalid signature';
	exit;
}

$livemode = !empty($event->livemode) ? 1 : 0;

if ($mode === 'live' && $livemode !== 1) {
	http_response_code(400);
	echo 'Wrong mode for live endpoint';
	exit;
}

if ($mode !== 'live' && $livemode !== 0) {
	http_response_code(400);
	echo 'Wrong mode for test endpoint';
	exit;
}

if (!function_exists('mark_webhook_event')) {
	function mark_webhook_event(PDO $pdo, string $eventId, int $livemode, string $status, ?string $err = null): void
	{
		$pdo->prepare("
            UPDATE stripe_webhook_events
            SET process_status = ?, processed_at = NOW(), error_message = ?
            WHERE stripe_event_id = ? AND livemode = ?
        ")->execute([$status, $err, $eventId, $livemode]);
	}
}

// Record event first for idempotency
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
	echo 'Duplicate';
	exit;
}

try {
	if ($event->type !== 'checkout.session.completed') {
		mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Unhandled type');
		http_response_code(200);
		echo 'Ignored';
		exit;
	}
	
	/** @var \Stripe\Checkout\Session $session */
	$session = $event->data->object;
	
	if (($session->payment_status ?? '') !== 'paid') {
		mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Not paid');
		http_response_code(200);
		echo 'Not paid';
		exit;
	}
	
	$sessionId        = (string)($session->id ?? '');
	$paymentIntentId  = (string)($session->payment_intent ?? '');
	$customerId       = (string)($session->customer ?? '');
	$email            = trim((string)($session->customer_details->email ?? $session->customer_email ?? ''));
	$productKey       = trim((string)($session->metadata->product_key ?? ''));
	
	$products = product_file_map();
	
	if ($productKey === '' || !isset($products[$productKey])) {
		mark_webhook_event($pdo, $event->id, $livemode, 'failed', 'Missing/invalid product_key');
		http_response_code(200);
		echo 'Invalid product';
		exit;
	}
	
	if ($email === '') {
		mark_webhook_event($pdo, $event->id, $livemode, 'failed', 'Missing customer email');
		http_response_code(200);
		echo 'Missing email';
		exit;
	}
	
	$stmt = $pdo->prepare("SELECT id, slug, name, file_path FROM products WHERE slug = ? LIMIT 1");
	$stmt->execute([$productKey]);
	$product = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$product) {
		mark_webhook_event($pdo, $event->id, $livemode, 'failed', 'No product row found for slug: ' . $productKey);
		http_response_code(200);
		echo 'Missing product row';
		exit;
	}
	
	$productId = (int)$product['id'];
	$meta = $products[$productKey];
	$expiresAt = (new DateTimeImmutable('now'))->add(
			new DateInterval('PT' . (int)$meta['expires_minutes'] . 'M')
			);
	
	$pdo->beginTransaction();
	
	$pdo->prepare("
        INSERT INTO purchases
            (stripe_checkout_session_id, stripe_payment_intent_id, stripe_customer_id, purchaser_email, product_id, amount_total, currency, livemode, status, paid_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())
        ON DUPLICATE KEY UPDATE
            stripe_payment_intent_id = VALUES(stripe_payment_intent_id),
            stripe_customer_id = VALUES(stripe_customer_id),
            purchaser_email = VALUES(purchaser_email),
            product_id = VALUES(product_id),
            amount_total = VALUES(amount_total),
            currency = VALUES(currency),
            status = 'paid',
            paid_at = COALESCE(paid_at, VALUES(paid_at))
    ")->execute([
    		$sessionId,
    		$paymentIntentId !== '' ? $paymentIntentId : null,
    		$customerId !== '' ? $customerId : null,
    		$email,
    		$productId,
    		$session->amount_total ?? null,
    		$session->currency ?? null,
    		$livemode
    ]);
	
	$stmt = $pdo->prepare("
        SELECT id
        FROM purchases
        WHERE stripe_checkout_session_id = ? AND livemode = ?
        LIMIT 1
    ");
	$stmt->execute([$sessionId, $livemode]);
	$purchaseId = (int)$stmt->fetchColumn();
	
	if ($purchaseId <= 0) {
		throw new RuntimeException('Unable to resolve purchase id after upsert.');
	}
	
	$pdo->prepare("
        INSERT IGNORE INTO purchase_items
            (purchase_id, product_id, quantity, unit_amount, line_amount_total, metadata_json)
        VALUES
            (?, ?, 1, ?, ?, ?)
    ")->execute([
    		$purchaseId,
    		$productId,
    		$session->amount_total ?? null,
    		$session->amount_total ?? null,
    		json_encode([
    				'source' => 'stripe_webhook',
    				'checkout_session_id' => $sessionId,
    				'product_key' => $productKey,
    		], JSON_UNESCAPED_SLASHES)
    ]);
	
	$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
	$stmt->execute([$email]);
	$userId = $stmt->fetchColumn();
	
	if ($userId) {
		$pdo->prepare("
            INSERT IGNORE INTO entitlements
                (user_id, product_id, source, status, expires_at)
            VALUES
                (?, ?, 'purchase', 'active', NULL)
        ")->execute([(int)$userId, $productId]);
	}
	
	$token = bin2hex(random_bytes(32));
	
	try {
		$pdo->prepare("
            INSERT INTO download_tokens
                (token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining, purchase_id, product_id)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
        		$token,
        		$sessionId,
        		$email,
        		$productKey,
        		$meta['file_path'],
        		$expiresAt->format('Y-m-d H:i:s'),
        		(int)$meta['uses'],
        		$purchaseId,
        		$productId
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
			
			$pdo->prepare("
                UPDATE download_tokens
                SET purchaser_email = ?,
                    file_path = ?,
                    expires_at = ?,
                    uses_remaining = ?,
                    purchase_id = COALESCE(purchase_id, ?),
                    product_id = COALESCE(product_id, ?)
                WHERE checkout_session_id = ? AND product_key = ?
            ")->execute([
            		$email,
            		$meta['file_path'],
            		$expiresAt->format('Y-m-d H:i:s'),
            		(int)$meta['uses'],
            		$purchaseId,
            		$productId,
            		$sessionId,
            		$productKey
            ]);
		} else {
			throw $e;
		}
	}
	
	$pdo->commit();
	
	// Send email after DB commit
	$downloadUrl = rtrim(SITE_URL, '/') . '/download.php?t=' . urlencode($token);
	$successUrl  = rtrim(SITE_URL, '/') . '/shop/success.php?sid=' . urlencode($sessionId);
	$isTest      = ($livemode === 0);
	
	$subject = $isTest
	? 'Your Backing Track Blueprint is here (TEST)'
			: 'Your Backing Track Blueprint is here';
			
			$body =
			"Hey there,\n\n" .
			($isTest ? "[TEST MODE]\n\n" : "") .
			"Thanks for grabbing Backing Track Blueprint.\n\n" .
			"Inside, you'll get the exact ideas, shortcuts, and practical moves I use to make my tracks hit harder, feel bigger, and support a stronger live show.\n\n" .
			"Use what fits, skip what doesn't, and start tightening up your sound right away.\n\n" .
			"Download your guide: {$downloadUrl}\n" .
			"View your success page: {$successUrl}\n\n" .
			"A couple notes:\n" .
			"- Your download link expires and has limited uses.\n" .
			"- If anything gives you trouble, just reply to this email.\n\n" .
			"Thanks again,\n" .
			"Nick Sanzeri\n";
			
			$htmlBody = '
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Backing Track Blueprint is here</title>
</head>
<body style="margin:0; padding:0; background:#f6f3ea; font-family:Arial, Helvetica, sans-serif; color:#1f1a12;">
  <div style="max-width:640px; margin:0 auto; padding:32px 20px;">
    <div style="background:#ffffff; border:1px solid #e7dcc5; border-radius:14px; padding:32px;">
      <h1 style="margin:0 0 18px; font-size:28px; line-height:1.2;">Your Backing Track Blueprint is here</h1>
      ' . ($isTest ? '<p style="margin:0 0 18px; color:#9a6a00;"><strong>TEST MODE</strong></p>' : '') . '
      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">Hey there,</p>
      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">Thanks for grabbing <strong>Backing Track Blueprint</strong>.</p>
      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">Inside, you\'ll get the exact ideas, shortcuts, and practical moves I use to make my tracks hit harder, feel bigger, and support a stronger live show.</p>
      <p style="margin:0 0 24px; font-size:16px; line-height:1.6;">Use what fits, skip what doesn\'t, and start tightening up your sound right away.</p>
      		
      <p style="margin:0 0 14px;">
        <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:#b68a2f; color:#ffffff; text-decoration:none; padding:14px 22px; border-radius:8px; font-weight:bold;">
          Download your guide
        </a>
      </p>
        		
      <p style="margin:0 0 24px;">
        <a href="' . htmlspecialchars($successUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#7a5a16; text-decoration:underline;">
          View your success page
        </a>
      </p>
        		
      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;"><strong>A couple notes:</strong></p>
      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;">
        • Your download link expires and has limited uses.<br>
        • If anything gives you trouble, just reply to this email.
      </p>
        		
      <p style="margin:24px 0 0; font-size:16px; line-height:1.6;">
        Thanks again,<br>
        Nick Sanzeri
      </p>
    </div>
  </div>
</body>
</html>';
			
			$idemKey = 'dl_link:' . ($livemode ? 'live' : 'test') . ':' . $sessionId . ':' . $productKey;
			error_log('About to call send_and_log_email for ' . $email . ' subject=' . $subject);
			try {
				$sent = send_and_log_email($pdo, [
						'message_type'    => 'download_link',
						'recipient'       => $email,
						'subject'         => $subject,
						'body'            => $body,
						'html_body'       => $htmlBody,
						'from_email'      => env('SMTP_FROM', 'nick@nicksanzeri.com'),
						'from_name'       => env('SMTP_FROM_NAME', 'Nick Sanzeri Music'),
						'reply_to'        => env('SMTP_REPLY_TO', 'nick@nicksanzeri.com'),
						'idempotency_key' => $idemKey,
						'related_table'   => 'purchases',
						'related_id'      => $purchaseId ?: null,
				]);
				
				if (!$sent) {
					mark_webhook_event($pdo, $event->id, $livemode, 'processed_with_email_failure', 'Email send failed');
				} else {
					mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
				}
			} catch (\Throwable $e) {
				error_log('Webhook email send failed: ' . $e->getMessage());
				mark_webhook_event($pdo, $event->id, $livemode, 'processed_with_email_failure', substr($e->getMessage(), 0, 240));
			}
			
			http_response_code(200);
			echo 'OK';
			exit;
			
} catch (\Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	
	mark_webhook_event($pdo, $event->id, $livemode, 'failed', substr($e->getMessage(), 0, 240));
	
	error_log('Stripe webhook failure: ' . $e->getMessage());
	
	http_response_code(200);
	echo 'Handled';
	exit;
}
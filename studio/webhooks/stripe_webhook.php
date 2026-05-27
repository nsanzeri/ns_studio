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
	echo 'Invalid signature | mode=' . $mode . ' | secret_prefix=' . substr((string)$secret, 0, 8);
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
        $allowed = ['received', 'processed', 'ignored', 'failed'];
        if (!in_array($status, $allowed, true)) {
            $status = 'processed';
        }

        $pdo->prepare(
            "UPDATE stripe_webhook_events
             SET process_status = ?, processed_at = NOW(), error_message = ?
             WHERE stripe_event_id = ? AND livemode = ?"
        )->execute([$status, $err, $eventId, $livemode]);
    }
}

if (!function_exists('safe_event_error')) {
    function safe_event_error(Throwable $e): string
    {
        return mb_substr($e->getMessage(), 0, 240);
    }
}

if (!function_exists('stripe_status_to_local_status')) {
    function stripe_status_to_local_status(string $status): string
    {
        return match ($status) {
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => 'past_due',
            'canceled' => 'canceled',
            default => 'expired',
        };
    }
}

if (!function_exists('subscription_is_active_like')) {
    function subscription_is_active_like(string $status): bool
    {
        return in_array($status, ['trialing', 'active'], true);
    }
}

if (!function_exists('stripe_customer_email')) {
    function stripe_customer_email(?string $customerId): ?string
    {
        $customerId = trim((string)$customerId);
        if ($customerId === '') {
            return null;
        }

        try {
            $customer = \Stripe\Customer::retrieve($customerId);
            $email = strtolower(trim((string)($customer->email ?? '')));
            return $email !== '' ? $email : null;
        } catch (Throwable $e) {
            error_log('Unable to retrieve Stripe customer email: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('find_user_id_for_subscription')) {
    function find_user_id_for_subscription(PDO $pdo, array $context): ?int
    {
        $candidates = [];

        $metadataUserId = (int)($context['metadata_user_id'] ?? 0);
        if ($metadataUserId > 0) {
            $candidates[] = ['sql' => 'SELECT id FROM users WHERE id = ? LIMIT 1', 'value' => $metadataUserId];
        }

        $clientReferenceId = (int)($context['client_reference_id'] ?? 0);
        if ($clientReferenceId > 0) {
            $candidates[] = ['sql' => 'SELECT id FROM users WHERE id = ? LIMIT 1', 'value' => $clientReferenceId];
        }

        $email = strtolower(trim((string)($context['email'] ?? '')));
        if ($email !== '') {
            $candidates[] = ['sql' => 'SELECT id FROM users WHERE email = ? LIMIT 1', 'value' => $email];
        }

        $customerId = trim((string)($context['customer_id'] ?? ''));
        if ($customerId !== '') {
            $candidates[] = [
                'sql' => 'SELECT user_id AS id FROM user_subscriptions WHERE stripe_customer_id = ? ORDER BY id DESC LIMIT 1',
                'value' => $customerId,
            ];
        }

        $subscriptionId = trim((string)($context['subscription_id'] ?? ''));
        if ($subscriptionId !== '') {
            $candidates[] = [
                'sql' => 'SELECT user_id AS id FROM user_subscriptions WHERE stripe_subscription_id = ? ORDER BY id DESC LIMIT 1',
                'value' => $subscriptionId,
            ];
        }

        foreach ($candidates as $candidate) {
            $stmt = $pdo->prepare($candidate['sql']);
            $stmt->execute([$candidate['value']]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }
}

if (!function_exists('resolve_subscription_plan_row')) {
    function resolve_subscription_plan_row(PDO $pdo, ?string $requestedSlug): ?array
    {
        $requestedSlug = trim((string)$requestedSlug);
        $planMeta = $requestedSlug !== '' ? find_subscription_plan_meta($requestedSlug) : null;
        $slugCandidates = $planMeta['subscription_plan_slugs'] ?? ($requestedSlug !== '' ? [$requestedSlug] : []);

        if (!$slugCandidates) {
            $slugCandidates = ['rss-pro', 'ready-set-shows-pro', 'calendar-tools-pro'];
        }

        $placeholders = implode(',', array_fill(0, count($slugCandidates), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, slug, name, price_monthly_cents, shop_discount_percent, is_active
             FROM subscription_plans
             WHERE slug IN ($placeholders)
             ORDER BY FIELD(slug, $placeholders)
             LIMIT 1"
        );
        $stmt->execute(array_merge($slugCandidates, $slugCandidates));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row && !$planMeta) {
            $planMeta = find_subscription_plan_meta((string)$row['slug']);
        }

        if ($row && $planMeta) {
            $row['_meta'] = $planMeta;
        }

        return $row;
    }
}

if (!function_exists('subscription_entitlement_product_ids')) {
    function subscription_entitlement_product_ids(PDO $pdo, array $planMeta): array
    {
        $slugs = $planMeta['entitlement_product_slugs'] ?? [];
        $ids = [];

        if ($slugs) {
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = $pdo->prepare("SELECT id FROM products WHERE slug IN ($placeholders)");
            $stmt->execute($slugs);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $bundleSourceSlugs = $planMeta['entitlement_product_slugs'] ?? [];
        if ($bundleSourceSlugs) {
            $placeholders = implode(',', array_fill(0, count($bundleSourceSlugs), '?'));
            $sql = "SELECT DISTINCT pbi.child_product_id
                    FROM product_bundle_items pbi
                    JOIN products parent ON parent.id = pbi.bundle_product_id
                    WHERE parent.slug IN ($placeholders)";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bundleSourceSlugs);
                $bundleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                $ids = array_merge($ids, $bundleIds);
            } catch (Throwable $e) {
                // bundle table may not be populated yet; keep going
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn($id) => (int)$id > 0)));
        sort($ids);
        return $ids;
    }
}

if (!function_exists('upsert_subscription_record')) {
    function upsert_subscription_record(PDO $pdo, array $data): void
    {
        $pdo->prepare(
            "INSERT INTO user_subscriptions
                (user_id, subscription_plan_id, stripe_subscription_id, stripe_customer_id, status, current_period_start, current_period_end, canceled_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                stripe_customer_id = VALUES(stripe_customer_id),
                status = VALUES(status),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end),
                canceled_at = VALUES(canceled_at)"
        )->execute([
            $data['user_id'],
            $data['subscription_plan_id'],
            $data['stripe_subscription_id'],
            $data['stripe_customer_id'],
            $data['status'],
            $data['current_period_start'],
            $data['current_period_end'],
            $data['canceled_at'],
        ]);
    }
}

if (!function_exists('sync_subscription_entitlements')) {
    function sync_subscription_entitlements(PDO $pdo, int $userId, array $planMeta, string $localStatus, ?DateTimeImmutable $periodEnd): void
    {
        $productIds = subscription_entitlement_product_ids($pdo, $planMeta);
        if (!$productIds) {
            return;
        }

        $expiresAt = $periodEnd?->format('Y-m-d H:i:s');
        $active = subscription_is_active_like($localStatus);

        foreach ($productIds as $productId) {
            if ($active) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM entitlements
                     WHERE user_id = ? AND product_id = ? AND source = 'subscription'
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$userId, $productId]);
                $existingId = (int)($stmt->fetchColumn() ?: 0);

                if ($existingId > 0) {
                    $pdo->prepare(
                        "UPDATE entitlements
                         SET status = 'active', expires_at = ?
                         WHERE id = ?"
                    )->execute([$expiresAt, $existingId]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
                         VALUES (?, ?, 'subscription', 'active', ?)"
                    )->execute([$userId, $productId, $expiresAt]);
                }
            } else {
                $pdo->prepare(
                    "UPDATE entitlements
                     SET status = 'expired', expires_at = COALESCE(?, NOW())
                     WHERE user_id = ? AND product_id = ? AND source = 'subscription' AND status <> 'expired'"
                )->execute([$expiresAt, $userId, $productId]);
            }
        }
    }
}

if (!function_exists('handle_paid_product_checkout')) {
    function handle_paid_product_checkout(PDO $pdo, \Stripe\Checkout\Session $session, int $livemode): void
    {
        $sessionId       = (string)($session->id ?? '');
        $paymentIntentId = (string)($session->payment_intent ?? '');
        $customerId      = (string)($session->customer ?? '');
        $email           = trim((string)($session->customer_details->email ?? $session->customer_email ?? ''));
        $productKey      = trim((string)($session->metadata->product_key ?? ''));

        $products = product_file_map();

        if ($productKey === '' || !isset($products[$productKey])) {
            throw new RuntimeException('Missing/invalid product_key');
        }

        if ($email === '') {
            throw new RuntimeException('Missing customer email');
        }

        $stmt = $pdo->prepare('SELECT id, slug, name, file_path FROM products WHERE slug = ? LIMIT 1');
        $stmt->execute([$productKey]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('No product row found for slug: ' . $productKey);
        }

        $productId = (int)$product['id'];
        $meta = $products[$productKey];
        $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . (int)$meta['expires_minutes'] . 'M'));

        $pdo->beginTransaction();

        $pdo->prepare(
            "INSERT INTO purchases
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
                paid_at = COALESCE(paid_at, VALUES(paid_at))"
        )->execute([
            $sessionId,
            $paymentIntentId !== '' ? $paymentIntentId : null,
            $customerId !== '' ? $customerId : null,
            $email,
            $productId,
            $session->amount_total ?? null,
            $session->currency ?? null,
            $livemode,
        ]);

        $stmt = $pdo->prepare('SELECT id FROM purchases WHERE stripe_checkout_session_id = ? AND livemode = ? LIMIT 1');
        $stmt->execute([$sessionId, $livemode]);
        $purchaseId = (int)$stmt->fetchColumn();
        if ($purchaseId <= 0) {
            throw new RuntimeException('Unable to resolve purchase id after upsert.');
        }

        $pdo->prepare(
            "INSERT IGNORE INTO purchase_items
                (purchase_id, product_id, quantity, unit_amount, line_amount_total, metadata_json)
             VALUES
                (?, ?, 1, ?, ?, ?)"
        )->execute([
            $purchaseId,
            $productId,
            $session->amount_total ?? null,
            $session->amount_total ?? null,
            json_encode([
                'source' => 'stripe_webhook',
                'checkout_session_id' => $sessionId,
                'product_key' => $productKey,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userId = (int)($stmt->fetchColumn() ?: 0);
        if ($userId > 0) {
            $pdo->prepare(
                "INSERT IGNORE INTO entitlements
                    (user_id, product_id, source, status, expires_at)
                 VALUES
                    (?, ?, 'purchase', 'active', NULL)"
            )->execute([$userId, $productId]);
        }

        $token = bin2hex(random_bytes(32));

        try {
            $pdo->prepare(
                "INSERT INTO download_tokens
                    (token, checkout_session_id, purchaser_email, product_key, file_path, expires_at, uses_remaining, purchase_id, product_id)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $token,
                $sessionId,
                $email,
                $productKey,
                $meta['file_path'],
                $expiresAt->format('Y-m-d H:i:s'),
                (int)$meta['uses'],
                $purchaseId,
                $productId,
            ]);
        } catch (PDOException $e) {
            $sqlState   = $e->getCode();
            $driverCode = $e->errorInfo[1] ?? null;

            if ($sqlState === '23000' || $driverCode === 1062) {
                $stmt = $pdo->prepare('SELECT token FROM download_tokens WHERE checkout_session_id = ? AND product_key = ? LIMIT 1');
                $stmt->execute([$sessionId, $productKey]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                $token = $existing['token'] ?? $token;

                $pdo->prepare(
                    "UPDATE download_tokens
                     SET purchaser_email = ?,
                         file_path = ?,
                         expires_at = ?,
                         uses_remaining = ?,
                         purchase_id = COALESCE(purchase_id, ?),
                         product_id = COALESCE(product_id, ?)
                     WHERE checkout_session_id = ? AND product_key = ?"
                )->execute([
                    $email,
                    $meta['file_path'],
                    $expiresAt->format('Y-m-d H:i:s'),
                    (int)$meta['uses'],
                    $purchaseId,
                    $productId,
                    $sessionId,
                    $productKey,
                ]);
            } else {
                throw $e;
            }
        }

        $pdo->commit();

        $downloadUrl = rtrim(SITE_URL, '/') . '/download.php?t=' . urlencode($token);
        $successUrl  = rtrim(SITE_URL, '/') . '/shop/success.php?sid=' . urlencode($sessionId);
        $isTest      = ($livemode === 0);

        $subject = $isTest ? 'Your Backing Track Blueprint is here (TEST)' : 'Your Backing Track Blueprint is here';
        $body =
            "Hey there,\n\n" .
            ($isTest ? "[TEST MODE]\n\n" : '') .
            "Thanks for grabbing Backing Track Blueprint.\n\n" .
            "Inside, you'll get the exact ideas, shortcuts, and practical moves I use to make my tracks hit harder, feel bigger, and support a stronger live show.\n\n" .
            "Use what fits, skip what doesn't, and start tightening up your sound right away.\n\n" .
            "Download your guide: {$downloadUrl}\n" .
            "View your success page: {$successUrl}\n\n" .
            "A couple notes:\n" .
            "- Your download link is temporary and has limited uses.\n" .
            "- For permanent access, create a free login with this same email and open My Products.\n" .
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
        <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:#b68a2f; color:#ffffff; text-decoration:none; padding:14px 22px; border-radius:8px; font-weight:bold;">Download your guide</a>
      </p>
      <p style="margin:0 0 24px;">
        <a href="' . htmlspecialchars($successUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#7a5a16; text-decoration:underline;">View your success page</a>
      </p>
      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;"><strong>A couple notes:</strong></p>
      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;">• Your download link is temporary and has limited uses.<br>• For permanent access, create a free login with this same email and open My Products.<br>• If anything gives you trouble, just reply to this email.</p>
      <p style="margin:24px 0 0; font-size:16px; line-height:1.6;">Thanks again,<br>Nick Sanzeri</p>
    </div>
  </div>
</body>
</html>';

        $idemKey = 'dl_link:' . ($livemode ? 'live' : 'test') . ':' . $sessionId . ':' . $productKey;
        send_and_log_email($pdo, [
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
    }
}

if (!function_exists('handle_setmaxx_tip_checkout')) {
    function handle_setmaxx_tip_checkout(PDO $pdo, \Stripe\Checkout\Session $session): void
    {
        $sessionId = (string)($session->id ?? '');
        $paymentIntentId = (string)($session->payment_intent ?? '');
        $gigSessionId = (int)($session->metadata->gig_session_id ?? 0);
        $songId = (int)($session->metadata->song_id ?? 0);
        $performerUserId = (int)($session->metadata->performer_user_id ?? 0);
        $requesterName = trim((string)($session->metadata->requester_name ?? ''));
        $requestNote = trim((string)($session->metadata->request_note ?? ''));
        $amountCents = (int)($session->amount_total ?? 0);

        if ($sessionId === '' || $paymentIntentId === '' || $gigSessionId <= 0 || $songId <= 0 || $performerUserId <= 0 || $amountCents <= 0) {
            throw new RuntimeException('Missing Set Maxx tip checkout metadata.');
        }

        $stmt = $pdo->prepare(
            "SELECT s.id
             FROM setmaxx_songs s
             JOIN setmaxx_gig_sessions gs ON gs.user_id = s.user_id
             WHERE gs.id = ?
               AND gs.user_id = ?
               AND s.id = ?
               AND s.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$gigSessionId, $performerUserId, $songId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Set Maxx paid request song/session mismatch.');
        }

        $pdo->prepare(
            "INSERT INTO setmaxx_requests
                (gig_session_id, song_id, requester_name, request_note, amount_cents, status, active_lock, stripe_payment_intent_id)
             VALUES
                (?, ?, ?, ?, ?, 'pending', 1, ?)
             ON DUPLICATE KEY UPDATE
                requester_name = COALESCE(requester_name, VALUES(requester_name)),
                request_note = COALESCE(request_note, VALUES(request_note)),
                amount_cents = GREATEST(amount_cents, VALUES(amount_cents)),
                stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, VALUES(stripe_payment_intent_id)),
                status = IF(status = 'canceled', 'pending', status),
                active_lock = 1"
        )->execute([
            $gigSessionId,
            $songId,
            $requesterName !== '' ? $requesterName : null,
            $requestNote !== '' ? $requestNote : null,
            $amountCents,
            $paymentIntentId,
        ]);
    }
}

if (!function_exists('ensure_setmaxx_general_tips_table')) {
    function ensure_setmaxx_general_tips_table(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `setmaxx_general_tips` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `gig_session_id` bigint(20) unsigned DEFAULT NULL,
              `user_id` int(10) unsigned NOT NULL,
              `tipper_name` varchar(190) DEFAULT NULL,
              `tip_note` varchar(255) DEFAULT NULL,
              `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
              `status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
              `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_setmaxx_general_tips_pi` (`stripe_payment_intent_id`),
              KEY `idx_setmaxx_general_tips_user` (`user_id`,`created_at`),
              KEY `idx_setmaxx_general_tips_session` (`gig_session_id`,`created_at`),
              CONSTRAINT `fk_setmaxx_general_tips_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_setmaxx_general_tips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('handle_setmaxx_general_tip_checkout')) {
    function handle_setmaxx_general_tip_checkout(PDO $pdo, \Stripe\Checkout\Session $session): void
    {
        ensure_setmaxx_general_tips_table($pdo);
        $paymentIntentId = (string)($session->payment_intent ?? '');
        $gigSessionId = (int)($session->metadata->gig_session_id ?? 0);
        $performerUserId = (int)($session->metadata->performer_user_id ?? 0);
        $tipperName = trim((string)($session->metadata->tipper_name ?? ''));
        $tipNote = trim((string)($session->metadata->tip_note ?? ''));
        $amountCents = (int)($session->amount_total ?? 0);

        if ($paymentIntentId === '' || $performerUserId <= 0 || $amountCents <= 0) {
            throw new RuntimeException('Missing Set Maxx general tip metadata.');
        }

        if ($gigSessionId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM setmaxx_gig_sessions WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$gigSessionId, $performerUserId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Set Maxx general tip session mismatch.');
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$performerUserId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Set Maxx general tip user mismatch.');
            }
        }

        $pdo->prepare(
            "INSERT INTO setmaxx_general_tips
                (gig_session_id, user_id, tipper_name, tip_note, amount_cents, status, stripe_payment_intent_id)
             VALUES
                (?, ?, ?, ?, ?, 'paid', ?)
             ON DUPLICATE KEY UPDATE
                tipper_name = COALESCE(tipper_name, VALUES(tipper_name)),
                tip_note = COALESCE(tip_note, VALUES(tip_note)),
                amount_cents = VALUES(amount_cents),
                status = 'paid'"
        )->execute([
            $gigSessionId > 0 ? $gigSessionId : null,
            $performerUserId,
            $tipperName !== '' ? $tipperName : null,
            $tipNote !== '' ? $tipNote : null,
            $amountCents,
            $paymentIntentId,
        ]);
    }
}

if (!function_exists('handle_subscription_checkout_completed')) {
    function handle_subscription_checkout_completed(PDO $pdo, \Stripe\Checkout\Session $session): void
    {
        $planKey = trim((string)($session->metadata->plan_key ?? 'rss-pro'));
        $subscriptionId = trim((string)($session->subscription ?? ''));
        $customerId = trim((string)($session->customer ?? ''));
        $email = strtolower(trim((string)($session->customer_details->email ?? $session->customer_email ?? $session->metadata->app_email ?? '')));
        $metadataUserId = (int)($session->metadata->app_user_id ?? 0);
        $clientReferenceId = (int)($session->client_reference_id ?? 0);

        $planRow = resolve_subscription_plan_row($pdo, $planKey);
        if (!$planRow) {
            throw new RuntimeException('No subscription_plans row found for slug: ' . $planKey);
        }

        $userId = find_user_id_for_subscription($pdo, [
            'metadata_user_id'   => $metadataUserId,
            'client_reference_id'=> $clientReferenceId,
            'email'              => $email,
            'customer_id'        => $customerId,
            'subscription_id'    => $subscriptionId,
        ]);

        if (!$userId) {
            throw new RuntimeException('Unable to resolve user for subscription checkout.');
        }

        if ($subscriptionId === '') {
            return;
        }

        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        handle_subscription_object($pdo, $subscription, $planKey, $userId, $email);
    }
}

if (!function_exists('handle_subscription_object')) {
    function handle_subscription_object(PDO $pdo, \Stripe\Subscription $subscription, ?string $fallbackPlanKey = null, ?int $fallbackUserId = null, ?string $fallbackEmail = null): void
    {
        $planKey = trim((string)($subscription->metadata->plan_key ?? $fallbackPlanKey ?? 'rss-pro'));
        $customerId = trim((string)($subscription->customer ?? ''));
        $subscriptionId = trim((string)($subscription->id ?? ''));
        $email = strtolower(trim((string)($subscription->metadata->app_email ?? $fallbackEmail ?? '')));
        if ($email === '') {
            $email = (string)(stripe_customer_email($customerId) ?? '');
        }

        $planRow = resolve_subscription_plan_row($pdo, $planKey);
        if (!$planRow) {
            throw new RuntimeException('No subscription_plans row found for slug: ' . $planKey);
        }

        $userId = $fallbackUserId ?: find_user_id_for_subscription($pdo, [
            'metadata_user_id'   => (int)($subscription->metadata->app_user_id ?? 0),
            'email'              => $email,
            'customer_id'        => $customerId,
            'subscription_id'    => $subscriptionId,
        ]);

        if (!$userId) {
            throw new RuntimeException('Unable to resolve user for subscription event ' . $subscriptionId);
        }

        $localStatus = stripe_status_to_local_status((string)($subscription->status ?? ''));
        $periodStart = !empty($subscription->current_period_start)
            ? (new DateTimeImmutable('@' . (int)$subscription->current_period_start))->setTimezone(new DateTimeZone(date_default_timezone_get()))
            : null;
        $periodEnd = !empty($subscription->current_period_end)
            ? (new DateTimeImmutable('@' . (int)$subscription->current_period_end))->setTimezone(new DateTimeZone(date_default_timezone_get()))
            : null;
        $canceledAt = !empty($subscription->canceled_at)
            ? (new DateTimeImmutable('@' . (int)$subscription->canceled_at))->setTimezone(new DateTimeZone(date_default_timezone_get()))
            : null;

        $pdo->beginTransaction();

        upsert_subscription_record($pdo, [
            'user_id'               => $userId,
            'subscription_plan_id'  => (int)$planRow['id'],
            'stripe_subscription_id'=> $subscriptionId !== '' ? $subscriptionId : null,
            'stripe_customer_id'    => $customerId !== '' ? $customerId : null,
            'status'                => $localStatus,
            'current_period_start'  => $periodStart?->format('Y-m-d H:i:s'),
            'current_period_end'    => $periodEnd?->format('Y-m-d H:i:s'),
            'canceled_at'           => $canceledAt?->format('Y-m-d H:i:s'),
        ]);

        sync_subscription_entitlements($pdo, $userId, $planRow['_meta'] ?? find_subscription_plan_meta((string)$planRow['slug']) ?? [], $localStatus, $periodEnd);

        $pdo->commit();
    }
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO stripe_webhook_events
            (stripe_event_id, livemode, event_type, api_version, payload_json, signature_header, process_status)
         VALUES
            (?, ?, ?, ?, ?, ?, 'received')"
    );
    $stmt->execute([
        $event->id,
        $livemode,
        $event->type,
        $event->api_version ?? null,
        $payload,
        $sig,
    ]);
} catch (PDOException $e) {
    http_response_code(200);
    echo 'Duplicate';
    exit;
}

try {
    switch ($event->type) {
        case 'checkout.session.completed':
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;
            $checkoutMode = (string)($session->mode ?? 'payment');

            if ($checkoutMode === 'subscription') {
                handle_subscription_checkout_completed($pdo, $session);
                mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
                http_response_code(200);
                echo 'OK';
                exit;
            }

            if (($session->payment_status ?? '') !== 'paid') {
                mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Not paid');
                http_response_code(200);
                echo 'Not paid';
                exit;
            }

            if (trim((string)($session->metadata->kind ?? '')) === 'setmaxx_tip') {
                handle_setmaxx_tip_checkout($pdo, $session);
                mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
                http_response_code(200);
                echo 'OK';
                exit;
            }

            if (trim((string)($session->metadata->kind ?? '')) === 'setmaxx_general_tip') {
                handle_setmaxx_general_tip_checkout($pdo, $session);
                mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
                http_response_code(200);
                echo 'OK';
                exit;
            }

            handle_paid_product_checkout($pdo, $session, $livemode);
            mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
            http_response_code(200);
            echo 'OK';
            exit;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            /** @var \Stripe\Subscription $subscription */
            $subscription = $event->data->object;
            handle_subscription_object($pdo, $subscription);
            mark_webhook_event($pdo, $event->id, $livemode, 'processed', null);
            http_response_code(200);
            echo 'OK';
            exit;

        default:
            mark_webhook_event($pdo, $event->id, $livemode, 'ignored', 'Unhandled type');
            http_response_code(200);
            echo 'Ignored';
            exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    mark_webhook_event($pdo, $event->id, $livemode, 'failed', safe_event_error($e));
    error_log('Stripe webhook failure: ' . $e->getMessage());

    http_response_code(200);
    echo 'Handled';
    exit;
}

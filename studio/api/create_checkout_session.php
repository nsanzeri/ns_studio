<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('ns_find_existing_tool_subscription')) {
	/**
	 * Finds an existing Stripe-backed tool subscription for this app user.
	 * Manual trial entitlements are intentionally NOT checked here, so a free-trial
	 * user can still upgrade into paid checkout.
	 */
	function ns_find_existing_tool_subscription(PDO $pdo, int $userId): ?array
	{
		if ($userId <= 0) {
			return null;
		}
		
		if (!rss_table_exists($pdo, 'user_subscriptions') || !rss_table_exists($pdo, 'subscription_plans')) {
			return null;
		}
		
		$toolSlugs = rss_tools_product_slugs();
		if (!$toolSlugs) {
			return null;
		}
		
		$placeholders = implode(',', array_fill(0, count($toolSlugs), '?'));
		
		$sql = "
            SELECT
                us.stripe_customer_id,
                us.stripe_subscription_id,
                us.status,
                us.current_period_end
            FROM user_subscriptions us
            JOIN subscription_plans sp ON sp.id = us.subscription_plan_id
            WHERE us.user_id = ?
              AND us.stripe_customer_id IS NOT NULL
              AND us.stripe_customer_id <> ''
              AND us.stripe_subscription_id IS NOT NULL
              AND us.stripe_subscription_id <> ''
              AND us.status IN ('trialing', 'active', 'past_due', 'unpaid')
              AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
              AND sp.slug IN ($placeholders)
            ORDER BY
              CASE us.status
                WHEN 'active' THEN 1
                WHEN 'trialing' THEN 2
                WHEN 'past_due' THEN 3
                WHEN 'unpaid' THEN 4
                ELSE 5
              END,
              us.id DESC
            LIMIT 1
        ";
		
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array_merge([$userId], $toolSlugs));
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
		
		return $row ?: null;
	}
}

if (!function_exists('ns_customer_has_live_tool_subscription')) {
	/**
	 * Stripe-side safety net. This catches duplicate subscriptions even if the
	 * local webhook/DB is behind during testing or after an interrupted request.
	 */
	function ns_customer_has_live_tool_subscription(string $stripeCustomerId): bool
	{
		$stripeCustomerId = trim($stripeCustomerId);
		if ($stripeCustomerId === '') {
			return false;
		}
		
		$subs = \Stripe\Subscription::all([
				'customer' => $stripeCustomerId,
				'status' => 'all',
				'limit' => 20,
		]);
		
		foreach ($subs->data as $sub) {
			$status = (string)($sub->status ?? '');
			if (in_array($status, ['trialing', 'active', 'past_due', 'unpaid'], true)) {
				return true;
			}
		}
		
		return false;
	}
}

if (!function_exists('ns_create_billing_portal_url')) {
	function ns_create_billing_portal_url(string $stripeCustomerId): string
	{
		$session = \Stripe\BillingPortal\Session::create([
				'customer' => $stripeCustomerId,
				'return_url' => rtrim(SITE_URL, '/') . '/member/settings.php',
		]);
		
		return (string)$session->url;
	}
}

try {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		http_response_code(405);
		echo json_encode(['error' => 'Method not allowed']);
		exit;
	}
	
	$productKey = trim((string)($_POST['product_key'] ?? ''));
	$planKey    = trim((string)($_POST['plan_key'] ?? ''));
	
	if ($productKey !== '' && $planKey !== '') {
		http_response_code(400);
		echo json_encode(['error' => 'Use either product_key or plan_key, not both']);
		exit;
	}
	
	if ($productKey !== '') {
		$products = product_file_map();
		if (!isset($products[$productKey])) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid product_key']);
			exit;
		}
		
		$item = $products[$productKey];
		if (empty($item['price_id'])) {
			throw new RuntimeException('Missing Stripe price_id for product_key=' . $productKey);
		}
		
		$successUrl = rtrim(SITE_URL, '/') . '/shop/success.php?sid={CHECKOUT_SESSION_ID}&p=' . urlencode($productKey);
		$cancelUrl  = rtrim(SITE_URL, '/') . '/shop/blueprint.php?canceled=1';
		
		$session = \Stripe\Checkout\Session::create([
				'mode' => 'payment',
				'line_items' => [[
						'price' => $item['price_id'],
						'quantity' => 1,
				]],
				'success_url' => $successUrl,
				'cancel_url'  => $cancelUrl,
				'metadata' => [
						'product_key' => $productKey,
						'kind'        => 'product',
				],
		]);
		
		echo json_encode(['url' => $session->url]);
		exit;
	}
	
	if ($planKey !== '') {
		if (!Auth::isLoggedIn()) {
			http_response_code(401);
			echo json_encode([
					'error' => 'Login required',
					'login_url' => base_url('/member/login.php'),
			]);
			exit;
		}
		
		$planMeta = find_subscription_plan_meta($planKey);
		if (!$planMeta) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid plan_key']);
			exit;
		}
		
		if (empty($planMeta['price_id'])) {
			throw new RuntimeException('Missing Stripe price_id for plan_key=' . $planKey);
		}
		
		$user = Auth::currentUser($pdo);
		$userId = (int)($user['id'] ?? 0);
		$email = strtolower(trim((string)($user['email'] ?? '')));
		$displayName = trim((string)($user['display_name'] ?? ''));
		
		if ($userId <= 0 || $email === '') {
			throw new RuntimeException('Logged-in user is missing required account data.');
		}
		
		$successUrl = rtrim(SITE_URL, '/') . '/tools/index.php?upgraded=1&session_id={CHECKOUT_SESSION_ID}';
		$cancelUrl  = rtrim(SITE_URL, '/') . '/member/pricing.php?canceled=1';
		
		$stripeCustomerId = null;
		
		$existingSubscription = ns_find_existing_tool_subscription($pdo, $userId);
		if ($existingSubscription) {
			$existingCustomerId = trim((string)($existingSubscription['stripe_customer_id'] ?? ''));
			if ($existingCustomerId !== '') {
				echo json_encode([
						'url' => ns_create_billing_portal_url($existingCustomerId),
						'already_subscribed' => true,
						'message' => 'You already have an active subscription. Opening billing management instead.',
				]);
				exit;
			}
		}
		
		$existingCustomers = \Stripe\Customer::all([
				'email' => $email,
				'limit' => 1,
		]);
		
		if (!empty($existingCustomers->data)) {
			$stripeCustomerId = (string)$existingCustomers->data[0]->id;
		} else {
			$customerPayload = [
					'email' => $email,
					'metadata' => [
							'app_user_id' => (string)$userId,
							'app_email'   => $email,
					],
			];
			
			if ($displayName !== '') {
				$customerPayload['name'] = $displayName;
			}
			
			$customer = \Stripe\Customer::create($customerPayload);
			$stripeCustomerId = (string)$customer->id;
		}
		
		if ($stripeCustomerId === '') {
			throw new RuntimeException('Unable to resolve Stripe customer for subscription checkout.');
		}
		
		if (ns_customer_has_live_tool_subscription($stripeCustomerId)) {
			echo json_encode([
					'url' => ns_create_billing_portal_url($stripeCustomerId),
					'already_subscribed' => true,
					'message' => 'You already have an active subscription. Opening billing management instead.',
			]);
			exit;
		}
		
		$session = \Stripe\Checkout\Session::create([
				'mode' => 'subscription',
				'line_items' => [[
						'price' => $planMeta['price_id'],
						'quantity' => 1,
				]],
				'success_url' => $successUrl,
				'cancel_url'  => $cancelUrl,
				'client_reference_id' => (string)$userId,
				'customer' => $stripeCustomerId,
				'allow_promotion_codes' => true,
				'metadata' => [
						'kind'        => 'subscription',
						'plan_key'    => $planKey,
						'app_user_id' => (string)$userId,
						'app_email'   => $email,
				],
				'subscription_data' => [
						'metadata' => [
								'kind'        => 'subscription',
								'plan_key'    => $planKey,
								'app_user_id' => (string)$userId,
								'app_email'   => $email,
						],
				],
		]);
		
		echo json_encode(['url' => $session->url]);
		exit;
	}
	
	http_response_code(400);
	echo json_encode(['error' => 'Missing product_key or plan_key']);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
			'error' => 'Checkout session failed',
			'detail' => is_local() ? $e->getMessage() : 'Please try again.',
	]);
}

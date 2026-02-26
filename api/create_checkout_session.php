<?php
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../config/stripe.php';

header('Content-Type: application/json; charset=utf-8');

try {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['error' => 'Method not allowed']);
		exit;
	}
	
	$productKey = $_POST['product_key'] ?? '';
	$products = product_file_map();
	
	if (!$productKey || !isset($products[$productKey])) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid product_key']);
		exit;
	}
	
	$p = $products[$productKey];
	
	if (empty($p['price_id'])) {
		throw new RuntimeException('Missing Stripe price_id for product.');
	}
	
	$successUrl = SITE_URL . "/shop/success.php?sid={CHECKOUT_SESSION_ID}&p=" . urlencode($productKey);
	$cancelUrl  = SITE_URL . "/shop/blueprint.php?canceled=1";
	
	$session = \Stripe\Checkout\Session::create([
			'mode' => 'payment',
			'line_items' => [[
					'price' => $p['price_id'],
					'quantity' => 1,
			]],
			'success_url' => $successUrl,
			'cancel_url'  => $cancelUrl,
			// Optional, but helpful for tracking:
			'metadata' => [
					'product_key' => $productKey,
			],
	]);
	
	echo json_encode(['url' => $session->url]);
} catch (Throwable $e) {
	http_response_code(500);
	// Don’t leak full details in production
	echo json_encode(['error' => 'Checkout session failed', 'detail' => $e->getMessage()]);
}
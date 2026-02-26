<?php
// api/create_checkout_session.php
require_once __DIR__ . '/../stripe_config.php';

header('Content-Type: application/json');

$product_key = $_POST['product_key'] ?? '';
$products = product_file_map();
$SITE_URL = site_url();

if (!isset($products[$product_key])) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid product']);
	exit;
}

$priceId = $products[$product_key]['price_id'] ?? '';
if (!$priceId || str_contains($priceId, 'REPLACE_ME')) {
	http_response_code(500);
	echo json_encode(['error' => 'Stripe price_id not configured']);
	exit;
}

try {
	$session = \Stripe\Checkout\Session::create([
			'mode' => 'payment',
			'line_items' => [[
					'price' => $priceId,
					'quantity' => 1,
			]],
			// ✅ Patched params: sid + p (avoid mod_security rules on "session_id")
			'success_url' => $SITE_URL . '/shop/success.php?sid={CHECKOUT_SESSION_ID}&p=' . urlencode($product_key),
			'cancel_url'  => $SITE_URL . '/shop/cancel.php',
			'allow_promotion_codes' => true,
			'billing_address_collection' => 'auto',
	]);
	
	echo json_encode(['url' => $session->url]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Checkout session failed']);
}
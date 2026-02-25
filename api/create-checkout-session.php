<?php
// api/create_checkout_session.php
require_once __DIR__ . '/../stripe_config.php';

header('Content-Type: application/json');

$product_key = $_POST['product_key'] ?? '';
$products = product_file_map();

if (!isset($products[$product_key])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid product']);
  exit;
}

// You can price this via Stripe Price IDs (recommended)
$priceId = 'price_XXXX'; // Price for Backing Track Blueprint

try {
  $session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'line_items' => [[
      'price' => $priceId,
      'quantity' => 1,
    ]],
    'success_url' => SITE_URL . '/shop/success.php?session_id={CHECKOUT_SESSION_ID}&product=' . urlencode($product_key),
    'cancel_url'  => SITE_URL . '/shop/cancel.html',
    'allow_promotion_codes' => true,
    'billing_address_collection' => 'auto',
  ]);

  echo json_encode(['url' => $session->url]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Checkout session failed']);
}
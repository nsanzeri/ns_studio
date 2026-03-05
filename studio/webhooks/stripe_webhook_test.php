<?php
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../config/stripe.php';

$secret = env('STRIPE_WEBHOOK_SECRET_TEST');
if (!$secret) { http_response_code(500); echo "Missing webhook secret"; exit; }

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

// TEST endpoint must only accept test events
if ($livemode !== 0) {
	http_response_code(400);
	echo "Wrong mode for test endpoint";
	exit;
}

// Everything else identical to live (you can literally paste the same body)
// Just keep $livemode as 0 and the correct secret.
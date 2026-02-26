<?php

$stripeKey = env('STRIPE_SECRET_KEY');

if (!$stripeKey) {
	throw new RuntimeException('Missing STRIPE_SECRET_KEY');
}

\Stripe\Stripe::setApiKey($stripeKey);

if (!defined('SITE_URL')) {
	define('SITE_URL', rtrim(env('APP_URL'), '/'));
}

function product_file_map(): array {
	$ROOT = dirname(__DIR__);
	
	return [
			'btb' => [
					'file_path' => $ROOT . '/private_downloads/Backing-Track-Blueprint.pdf',
					'download_name' => 'Backing-Track-Blueprint.pdf',
					'expires_minutes' => 60,
					'uses' => 3,
					'price_id' => env('STRIPE_PRICE_BTB'),
					'title' => 'Backing Track Blueprint',
			],
	];
}
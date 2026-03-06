<?php

$mode = env('STRIPE_MODE') ?: 'test'; // 'test' or 'live'

if ($mode === 'test') {
	$stripeKey = env('STRIPE_SECRET_KEY_TEST') ?: env('STRIPE_SECRET_KEY');
} else {
	$stripeKey = env('STRIPE_SECRET_KEY_LIVE') ?: env('STRIPE_SECRET_KEY');
}

if (!$stripeKey) {
	throw new RuntimeException('Missing Stripe secret key for mode=' . $mode);
}

\Stripe\Stripe::setApiKey($stripeKey);

if (!defined('SITE_URL')) {
	define('SITE_URL', rtrim(env('APP_URL'), '/'));
}

function product_file_map(): array {
	$ROOT = dirname(__DIR__);
	
	// Price ID can also be mode-specific if you want it
	$priceBtb = env('STRIPE_PRICE_BTB');
	if ((env('STRIPE_MODE') ?: 'test') === 'test') {
		$priceBtb = env('STRIPE_PRICE_BTB_TEST') ?: $priceBtb;
	} else {
		$priceBtb = env('STRIPE_PRICE_BTB_LIVE') ?: $priceBtb;
	}
	
	return [
			'btb' => [
					'file_path' => $ROOT . '/private_downloads/Backing-Track-Blueprint.pdf',
					'download_name' => 'Backing-Track-Blueprint.pdf',
					'expires_minutes' => 60,
					'uses' => 3,
					'price_id' => $priceBtb,
					'title' => 'Backing Track Blueprint',
			],
	];
}
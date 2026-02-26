<?php
// stripe.php
// Requires Composer dependency: stripe/stripe-php

require __DIR__ . '/bootstrap.php';

$stripeKey = env('STRIPE_SECRET_KEY');
if (!$stripeKey) {
  throw new RuntimeException('Missing STRIPE_SECRET_KEY. Set it in .env or server environment variables.');
}

\Stripe\Stripe::setApiKey($stripeKey);

$config = @include __DIR__ . '/_core/config.php';
function site_url(): string {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	
	// If you're running in a subfolder locally (like /ns_studio), keep that base path.
	// If you're running at the domain root, this becomes ''.
	$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'); // e.g. /ns_studio/api or /api
	$basePath  = preg_replace('#/(api|shop)$#', '', $scriptDir);      // e.g. /ns_studio or ''
	if ($basePath === '/' || $basePath === '\\') $basePath = '';
	
	return $scheme . '://' . $host . $basePath;
}

$base = '';
if (is_array($config) && isset($config['app']['base_url'])) {
  $base = trim((string)$config['app']['base_url']);
}



define('SITE_URL', $base !== '' ? rtrim($base, '/') : 'https://nicksanzeri.com');

// Map product_key -> file metadata
// Put files in /private_downloads (ideally outside public web root on production)
function product_file_map(): array {
  return [
    'btb' => [
      'file_path' => __DIR__ . '/private_downloads/Backing-Track-Blueprint.pdf',
      'download_name' => 'Backing-Track-Blueprint.pdf',
      'expires_minutes' => 60,
      'uses' => 3,
      // TODO: set your Stripe Price ID
      'price_id' => 'price_1T4XT6I8bUVPaxreeFjmZz89',
      'title' => 'Backing Track Blueprint',
      'description' => 'Instant download (PDF)'
    ],
  ];
}

<?php
// config/stripe.php
// Requires Composer dependency: stripe/stripe-php
// Assumes /_core/bootstrap.php has already been loaded (so env() exists and Composer autoload is in place).

$ROOT = dirname(__DIR__); // project root

$stripeKey = env('STRIPE_SECRET_KEY');
if (!$stripeKey) {
  throw new RuntimeException('Missing STRIPE_SECRET_KEY. Set it in .env or server environment variables.');
}

\Stripe\Stripe::setApiKey($stripeKey);

// Optional: read base_url from your existing core config (if present)
$config = @include $ROOT . '/_core/config.php';

// Fallback SITE_URL (used for absolute links in emails, etc.)
$base = '';
if (is_array($config) && isset($config['app']['base_url'])) {
  $base = trim((string)$config['app']['base_url']);
}

define('SITE_URL', $base !== '' ? rtrim($base, '/') : 'https://nicksanzeri.com');

// Map product_key -> file metadata
// Put files in /private_downloads (ideally outside public web root on production)
function product_file_map(): array {
  $ROOT = dirname(__DIR__);
  return [
    'btb' => [
      'file_path' => $ROOT . '/private_downloads/Backing-Track-Blueprint.pdf',
      'download_name' => 'Backing-Track-Blueprint.pdf',
      'expires_minutes' => 60,
      'uses' => 3,
      'price_id' => 'price_1T4XT6I8bUVPaxreeFjmZz89',
      'title' => 'Backing Track Blueprint',
      'description' => 'Instant download (PDF)',
    ],
  ];
}

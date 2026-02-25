<?php
// stripe_config.php
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey('sk_live_XXXX'); // or sk_test_XXXX

// Useful constants
define('SITE_URL', 'https://nicksanzeri.com');

// Map product_key -> actual file path on server
// Put the PDF OUTSIDE public web root if you can.
function product_file_map(): array {
  return [
    'btb' => [
      'file_path' => __DIR__ . '/../private_downloads/Backing-Track-Blueprint.pdf',
      'download_name' => 'Backing-Track-Blueprint.pdf',
      'expires_minutes' => 60, // token lifetime
      'uses' => 3
    ],
  ];
}
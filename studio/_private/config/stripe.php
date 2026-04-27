<?php

declare(strict_types=1);

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
    define('SITE_URL', rtrim((string) env('APP_URL'), '/'));
}

if (!function_exists('stripe_price_for_mode')) {
    function stripe_price_for_mode(string $baseEnvKey): ?string
    {
        $mode = env('STRIPE_MODE') ?: 'test';

        if ($mode === 'test') {
            return env($baseEnvKey . '_TEST') ?: env($baseEnvKey) ?: null;
        }

        return env($baseEnvKey . '_LIVE') ?: env($baseEnvKey) ?: null;
    }
}

if (!function_exists('product_file_map')) {
    function product_file_map(): array
    {
        $root = dirname(__DIR__);

        return [
            'btb' => [
                'file_path'        => $root . '/private_downloads/backing-track-blueprint.pdf',
                'download_name'    => 'backing-track-blueprint.pdf',
                'expires_minutes'  => 4320,
                'uses'             => 3,
                'price_id'         => stripe_price_for_mode('STRIPE_PRICE_BTB'),
                'title'            => 'Backing Track Blueprint',
                'checkout_mode'    => 'payment',
                'success_path'     => '/shop/success.php',
                'cancel_path'      => '/shop/blueprint.php?canceled=1',
            ],
        ];
    }
}

if (!function_exists('subscription_plan_map')) {
    function subscription_plan_map(): array
    {
        return [
            'rss-pro' => [
                'title'                     => 'Ready Set Shows Pro',
                'price_id'                  => stripe_price_for_mode('STRIPE_PRICE_RSS_PRO_MONTHLY'),
                'checkout_mode'             => 'subscription',
                'success_path'              => '/member/pricing.php?upgraded=1',
                'cancel_path'               => '/member/pricing.php?canceled=1',
                'subscription_plan_slugs'   => ['rss-pro', 'ready-set-shows-pro', 'calendar-tools-pro'],
           		'entitlement_product_slugs' => ['rss-pro'],
            ],
        ];
    }
}

if (!function_exists('find_subscription_plan_meta')) {
    function find_subscription_plan_meta(string $slug): ?array
    {
        foreach (subscription_plan_map() as $planSlug => $meta) {
            $aliases = $meta['subscription_plan_slugs'] ?? [$planSlug];
            if ($slug === $planSlug || in_array($slug, $aliases, true)) {
                $meta['canonical_slug'] = $planSlug;
                return $meta;
            }
        }

        return null;
    }
}

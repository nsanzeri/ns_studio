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

if (!function_exists('ensure_product_row_for_key')) {
    function ensure_product_row_for_key(PDO $pdo, string $productKey, array $meta): array
    {
        $productKey = trim($productKey);
        if ($productKey === '') {
            throw new RuntimeException('Missing product key.');
        }

        $title = trim((string)($meta['title'] ?? $productKey));
        $filePath = trim((string)($meta['file_path'] ?? ''));

        $pdo->prepare(
            "INSERT INTO products (slug, name, kind, file_path)
             VALUES (?, ?, 'digital', ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                kind = VALUES(kind),
                file_path = VALUES(file_path)"
        )->execute([$productKey, $title !== '' ? $title : $productKey, $filePath !== '' ? $filePath : null]);

        $stmt = $pdo->prepare('SELECT id, slug, name, kind, file_path FROM products WHERE slug = ? LIMIT 1');
        $stmt->execute([$productKey]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('No product row found for slug: ' . $productKey);
        }

        return $product;
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

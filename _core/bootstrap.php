<?php
/**
 * bootstrap.php
 *
 * Minimal bootstrap for the NickSanzeri.com PHP endpoints.
 * - Loads Composer autoload
 * - Loads environment variables from .env (via vlucas/phpdotenv)
 *
 * Usage:
 *   require __DIR__ . '/bootstrap.php';
 *   $smtpHost = $_ENV['SMTP_HOST'];
 */

// Composer autoload (required for Dotenv, PHPMailer, Stripe SDK, etc.)
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException("Missing vendor/autoload.php. Run 'composer install'.");
}
require $autoload;

// Load .env if present
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    // If .env doesn't exist (e.g. production env vars), don't error.
    $dotenv->safeLoad();

    // Optional: enforce required vars in production.
    if (($_ENV['APP_ENV'] ?? '') === 'production') {
        $dotenv->required([
            'SMTP_HOST',
            'SMTP_USERNAME',
            'SMTP_PASSWORD',
            'STRIPE_SECRET_KEY',
        ]);
    }
}

// Tiny helper for defaults
function env(string $key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

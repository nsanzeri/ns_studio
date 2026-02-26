<?php
/**
 * _core/bootstrap.php
 *
 * Loads:
 * - Composer autoload (vendor/autoload.php)
 * - .env variables (vlucas/phpdotenv) into $_ENV
 * - Existing app bootstrap (sessions, helpers, db, auth)
 */

$ROOT = dirname(__DIR__); // project root (e.g. C:\xampp\htdocs\ns_studio)

// Composer autoload
$autoload = $ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // If you haven't installed deps yet, you'll see this immediately.
    // Run: composer install
    // (We don't hard-fail here because some pages might not need Composer.)
}

// Load environment variables from project root
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable($ROOT);
    $dotenv->safeLoad();
}

// env() helper
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

// ---- Existing app bootstrap ----

$config = require __DIR__ . '/config.php';

session_name($config['app']['session_name']);
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/Auth.php';

$pdo = db($config);

// Detect project base path (works in /ns_studio and in domain root)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');  // e.g. /ns_studio/shop
$basePath  = preg_replace('#/shop$#', '', $scriptDir);            // e.g. /ns_studio

// If it becomes just "/" treat as empty
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}

define('BASE_PATH', $basePath);

// Helper: base_url('assets/css/style.css') => /ns_studio/assets/css/style.css
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $path = ltrim($path, '/');
        return rtrim(BASE_PATH, '/') . ($path ? '/' . $path : '');
    }
}

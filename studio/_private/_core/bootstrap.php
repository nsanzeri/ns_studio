<?php
/**
 * _core/bootstrap.php
 */

// Studio app root (…/studio)
$APP_ROOT     = dirname(__DIR__, 2);   // studio
$PRIVATE_ROOT = dirname(__DIR__);      // studio/_private

// Composer autoload (lives in studio/vendor)
$autoload = $APP_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
} else {
	throw new RuntimeException("Missing Composer autoload at: $autoload (run composer install in /studio)");
}

// Load environment variables
// If you want .env inside _private, keep this:
if (class_exists(\Dotenv\Dotenv::class)) {
	$dotenv = \Dotenv\Dotenv::createImmutable($PRIVATE_ROOT);
	$dotenv->safeLoad();
}

// env() helper
if (!function_exists('env')) {
	function env(string $key, $default = null) {
		return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
	}
}

if (!function_exists('app_env')) {
	function app_env(): string {
		return env('APP_ENV', 'production');
	}
}

if (!function_exists('is_local')) {
	function is_local(): bool {
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
		return app_env() === 'local';
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

<?php
/**
 * _core/bootstrap.php (hardened)
 */

declare(strict_types=1);

// Studio app root (…/studio)
$APP_ROOT     = dirname(__DIR__, 2);   // studio
$PRIVATE_ROOT = dirname(__DIR__);      // studio/_private

// -------------------------
// Composer autoload
// -------------------------
$autoload = $APP_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
} else {
	throw new RuntimeException("Missing Composer autoload at: $autoload (run composer install in /studio)");
}

// -------------------------
// Load environment variables (.env in _private)
// -------------------------
if (class_exists(\Dotenv\Dotenv::class)) {
	$dotenv = \Dotenv\Dotenv::createImmutable($PRIVATE_ROOT);
	$dotenv->safeLoad();
}

// -------------------------
// Helpers: env / environment
// -------------------------
if (!function_exists('env')) {
	function env(string $key, $default = null) {
		return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
	}
}

if (!function_exists('app_env')) {
	function app_env(): string {
		return (string) env('APP_ENV', 'production');
	}
}

if (!function_exists('is_local')) {
	function is_local(): bool {
		return app_env() === 'local';
	}
}

// -------------------------
// Timezone (consistent logs + date handling)
// -------------------------
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Chicago'));

// -------------------------
// Error display/logging settings
// -------------------------
ini_set('display_errors', is_local() ? '1' : '0');
ini_set('display_startup_errors', is_local() ? '1' : '0');
ini_set('log_errors', '1');
if ($logFile = env('PHP_ERROR_LOG', '')) {
	ini_set('error_log', (string) $logFile);
}
error_reporting(E_ALL);

// Correlation id for debugging (shows up in logs)
if (!defined('REQUEST_ID')) {
	define('REQUEST_ID', bin2hex(random_bytes(8)));
}

// -------------------------
// Global error/exception handling
// -------------------------
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
	// Convert PHP notices/warnings into exceptions so we can catch/log consistently
	throw new ErrorException($message, 0, $severity, $file, $line);
});
	
	set_exception_handler(function (Throwable $e): void {
		$payload = sprintf(
				"[REQUEST_ID=%s] %s: %s in %s:%d\nStack:\n%s\n",
				REQUEST_ID,
				get_class($e),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
				);
		
		error_log($payload);
		
		// Avoid leaking details in production
		http_response_code(500);
		
		if (is_local()) {
			header('Content-Type: text/plain; charset=utf-8');
			echo $payload;
		} else {
			// Keep it simple; you can swap to a nice HTML error page later
			header('Content-Type: text/plain; charset=utf-8');
			echo "Something went wrong. Please try again.\n";
			echo "Reference: " . REQUEST_ID;
		}
		exit;
	});
		
		// Shutdown handler: catch fatal errors (parse errors, out of memory, etc.)
		register_shutdown_function(function (): void {
			$err = error_get_last();
			if (!$err) return;
			
			$isFatal = in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
			if (!$isFatal) return;
			
			$payload = sprintf(
					"[REQUEST_ID=%s] FATAL %s in %s:%d\n",
					REQUEST_ID,
					$err['message'] ?? 'Unknown fatal error',
					$err['file'] ?? 'unknown',
					$err['line'] ?? 0
					);
			
			error_log($payload);
			
			http_response_code(500);
			header('Content-Type: text/plain; charset=utf-8');
			if (is_local()) {
				echo $payload;
			} else {
				echo "Something went wrong. Please try again.\n";
				echo "Reference: " . REQUEST_ID;
			}
		});
			
			// -------------------------
			// Basic security headers (safe defaults)
			// -------------------------
			if (!headers_sent()) {
				header('X-Content-Type-Options: nosniff');
				header('X-Frame-Options: SAMEORIGIN');
				header('Referrer-Policy: strict-origin-when-cross-origin');
				
				// Only set HSTS if you're on HTTPS (don’t brick local dev)
				$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| (($_SERVER['SERVER_PORT'] ?? '') == '443');
				
				if ($isHttps) {
					// Enable when you're confident everything is HTTPS-only
					header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
				}
			}
			
			// -------------------------
			// Load app config
			// -------------------------
			$config = require __DIR__ . '/config.php';
			
			// -------------------------
			// Session hardening
			// -------------------------
			$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (($_SERVER['SERVER_PORT'] ?? '') == '443');
			
			session_name($config['app']['session_name']);
			$sessionLifetime = max(3600, (int)($config['app']['session_lifetime_seconds'] ?? 43200));
			ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
			
			// Must be set before session_start()
			session_set_cookie_params([
					'lifetime' => $sessionLifetime,
					'path'     => '/',
					'domain'   => '',
					'secure'   => $cookieSecure,
					'httponly' => true,
					'samesite' => 'Lax', // Lax is right for checkout redirects; Strict can break some flows
			]);
			
			if (session_status() !== PHP_SESSION_ACTIVE) {
				session_start();
			}
			
			// -------------------------
			// Existing app bootstrap
			// -------------------------
			require __DIR__ . '/helpers.php';
			require __DIR__ . '/database.php';
			require __DIR__ . '/Auth.php';
			
			$pdo = db($config);
			Auth::attemptRememberLogin($pdo, $config);
			
			// Detect studio app base path (works for /studio locally or in production)
			$scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g. /ns_studio/studio/tools/index.php
			
			if (preg_match('#^(.*?/studio)(?:/.*)?$#', $scriptName, $m)) {
				$basePath = $m[1]; // /ns_studio/studio or /studio
			} else {
				// Fallback if /studio is not present for some reason
				$basePath = rtrim(dirname($scriptName), '/');
			}
			
			// If it becomes just "/" treat as empty
			if ($basePath === '/' || $basePath === '\\') {
				$basePath = '';
			}
			
			define('BASE_PATH', $basePath);
			
			// Helper: base_url('assets/css/style.css') => /ns_studio/studio/assets/css/style.css
			if (!function_exists('base_url')) {
				function base_url(string $path = ''): string {
					$path = ltrim($path, '/');
					return rtrim(BASE_PATH, '/') . ($path ? '/' . $path : '');
				}
			}

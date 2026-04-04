<?php
// /_core/config.php
// Reads configuration from environment variables (.env)

return [
		
		'app' => [
				'base_url'     => env('BASE_URL', ''),
				'session_name' => env('SESSION_NAME', 'ns_studio'),
				'env'          => env('APP_ENV', 'production'),
		],
		
		'db' => [
				'host'    => env('DB_HOST', 'localhost'),
				'port'    => env('DB_PORT', 3306),
				'name'    => env('DB_NAME', 'ns_studio'),
				'user'    => env('DB_USER', 'root'),
				'pass'    => env('DB_PASS', ''),
				'charset' => env('DB_CHARSET', 'utf8mb4'),
		],
		
		'auth' => [
				'session_key'            => env('AUTH_SESSION_KEY', 'studio_user_id'),
				'remember_cookie_name'   => env('AUTH_REMEMBER_COOKIE', 'ns_studio_remember'),
				'remember_cookie_days'   => (int) env('AUTH_REMEMBER_COOKIE_DAYS', 30),
				'password_min_length'    => (int) env('AUTH_PASSWORD_MIN_LENGTH', 8),
				'allow_registration'     => (bool) env('AUTH_ALLOW_REGISTRATION', true),
				'allow_google_login'     => (bool) env('AUTH_ALLOW_GOOGLE_LOGIN', true),
		],
		
		'google' => [
				'enabled'       => (bool) env('GOOGLE_OAUTH_ENABLED', false),
				'client_id'     => env('GOOGLE_CLIENT_ID', ''),
				'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
				'redirect_uri'  => env('GOOGLE_REDIRECT_URI', ''),
		],
];
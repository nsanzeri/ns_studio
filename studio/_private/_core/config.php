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
		
];
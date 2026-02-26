<?php
// db_connect.php (shared PDO connection)

$config = require __DIR__ . '/_core/config.php';

require_once __DIR__ . '/_core/database.php';

function db(): PDO {
	static $pdo = null;
	if ($pdo) return $pdo;
	
	$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', env('DB_HOST'), env('DB_NAME'));
	$pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	return $pdo;
}

<?php
// db_connect.php (shared PDO connection)

$config = require __DIR__ . '/_core/config.php';

require_once __DIR__ . '/_core/database.php';

$pdo = db($config);

<?php
// db_connect.php
$dsn = "mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4";
$user = "YOUR_USER";
$pass = "YOUR_PASS";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);
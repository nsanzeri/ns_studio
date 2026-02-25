<?php
// /_core/bootstrap.php

$config = require __DIR__ . '/config.php';

session_name($config['app']['session_name']);
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/Auth.php';

$pdo = db($config);
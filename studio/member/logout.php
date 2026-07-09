<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
$brand = (($_GET['brand'] ?? '') === 'rss' || ($_SESSION['auth_brand'] ?? '') === 'rss') ? 'rss' : '';
Auth::logout();
flash_set('success', 'You have been logged out.');
redirect(base_url('member/login.php') . ($brand === 'rss' ? '?brand=rss' : ''));

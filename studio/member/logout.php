<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
$referrer = str_replace('\\', '/', (string)($_SERVER['HTTP_REFERER'] ?? ''));
$rssReferrerPages = ['/directory.php', '/booking-request.php', '/my-bookings.php', '/artist-bookings.php', '/artist-bid.php', '/studio/'];
$fromRssReferrer = false;
foreach ($rssReferrerPages as $page) {
	if (str_contains($referrer, $page)) {
		$fromRssReferrer = true;
		break;
	}
}
$brand = (($_GET['brand'] ?? '') === 'rss' || ($_SESSION['auth_brand'] ?? '') === 'rss' || $fromRssReferrer) ? 'rss' : '';
Auth::logout();
flash_set('success', 'You have been logged out.');
redirect(base_url('member/login.php') . ($brand === 'rss' ? '?brand=rss' : ''));

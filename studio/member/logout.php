<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
$referrer = str_replace('\\', '/', (string)($_SERVER['HTTP_REFERER'] ?? ''));
$rssReferrerPages = ['/directory.php', '/booking-request.php', '/my-bookings.php', '/artist-bookings.php', '/artist-bid.php'];
$fromRssReferrer = false;
foreach ($rssReferrerPages as $page) {
	if (str_contains($referrer, $page)) {
		$fromRssReferrer = true;
		break;
	}
}
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$referrerHost = strtolower((string)(parse_url($referrer, PHP_URL_HOST) ?: ''));
$referrerHost = preg_replace('/:\d+$/', '', $referrerHost);
$referrerPath = str_replace('\\', '/', (string)(parse_url($referrer, PHP_URL_PATH) ?: ''));
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$sameHostReferrer = $referrer !== '' && ($referrerHost === '' || $referrerHost === $requestHost);
$brand = (($_GET['brand'] ?? '') === 'rss' || $isReadySetShowsHost || $fromRssReferrer) ? 'rss' : '';
$returnUrl = '';

if ($sameHostReferrer) {
	$protectedAfterLogout = [
		'/member/login.php',
		'/member/logout.php',
		'/member/library.php',
		'/member/settings.php',
		'/member/pricing.php',
		'/tools/',
		'/setmaxx/',
		'/finance/',
		'/publishing/',
	];
	$isProtectedReferrer = false;
	foreach ($protectedAfterLogout as $path) {
		if (str_contains($referrerPath, $path)) {
			$isProtectedReferrer = true;
			break;
		}
	}

	if (!$isProtectedReferrer) {
		$query = (string)(parse_url($referrer, PHP_URL_QUERY) ?: '');
		$returnUrl = $referrerPath . ($query !== '' ? '?' . $query : '');
	}
}

if ($returnUrl === '') {
	$returnUrl = $brand === 'rss'
		? Auth::siteBasePath() . '/index.php'
		: base_url('shop/');
}

Auth::logout();
flash_set('success', 'You have been logged out.');
redirect($returnUrl);

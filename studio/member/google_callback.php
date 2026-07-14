<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

function rss_google_redirect_uri(): string
{
	$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
	$host = preg_replace('/:\d+$/', '', $host);

	if (in_array($host, ['readysetshows.com', 'www.readysetshows.com'], true)) {
		return 'https://readysetshows.com/studio/member/google_callback.php';
	}

	return (string)env('GOOGLE_REDIRECT_URI');
}

function google_login_safe_next_path(string $next): string
{
	$next = trim($next);
	if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
		return '';
	}
	if (preg_match('/[\x00-\x1F\x7F]/', $next)) {
		return '';
	}

	$parts = parse_url($next);
	if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
		return '';
	}

	return $next;
}

function google_login_default_post_login_url(PDO $pdo, int $userId, bool $isReadySetShowsContext): string
{
	return $isReadySetShowsContext
		? Auth::defaultPostLoginUrl($pdo, $userId)
		: base_url('member/library.php');
}

if (!isset($_GET['state']) || !hash_equals((string)($_SESSION['google_oauth_state'] ?? ''), (string)$_GET['state'])) {
	http_response_code(400);
	echo 'Invalid Google login state.';
	exit;
}
unset($_SESSION['google_oauth_state']);

$client = new Google_Client();
$client->setClientId(env('GOOGLE_CLIENT_ID'));
$client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(rss_google_redirect_uri());

$token = $client->fetchAccessTokenWithAuthCode((string)($_GET['code'] ?? ''));
if (!empty($token['error'])) {
	http_response_code(400);
	echo 'Google login failed.';
	exit;
}

$client->setAccessToken($token);
$oauth2 = new Google_Service_Oauth2($client);
$googleUser = $oauth2->userinfo->get();

$email = strtolower(trim((string)$googleUser->email));
$sub   = (string)$googleUser->id;
$name  = trim((string)$googleUser->name);

if (!$email || !$sub) {
	http_response_code(400);
	echo 'Google did not return the required profile information.';
	exit;
}

$userId = Auth::upsertGoogleUser($pdo, $sub, $email, $name ?: null);
$requestedAccountType = isset($_SESSION['registration_account_type']) ? Auth::normalizeAccountType((string)$_SESSION['registration_account_type']) : '';
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$nextPath = google_login_safe_next_path((string)($_SESSION['login_next'] ?? ''));
$isReadySetShowsContext = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true)
	|| (($_SESSION['auth_brand'] ?? '') === 'rss' && $nextPath === '')
	|| $requestedAccountType !== ''
	|| str_contains($nextPath, '/booking-request.php');
if ($requestedAccountType === 'artist' && Auth::accountTypeColumnExists($pdo) && Auth::accountTypeForUser($pdo, $userId) === 'customer') {
	Auth::updateAccountType($pdo, $userId, 'artist');
}
sync_user_entitlements($pdo, $userId);
Auth::login($userId);

$next = $nextPath ?: google_login_default_post_login_url($pdo, $userId, $isReadySetShowsContext);
$accountType = Auth::accountTypeForUser($pdo, $userId);
if ($accountType === 'artist' && str_contains((string)$next, '/booking-request.php')) {
	$next = google_login_default_post_login_url($pdo, $userId, $isReadySetShowsContext);
}
if (empty($_SESSION['login_next']) && $requestedAccountType === 'artist') {
	$next = base_url('member/artist_onboarding.php');
}
unset($_SESSION['login_next']);
unset($_SESSION['registration_account_type']);
unset($_SESSION['auth_brand']);
redirect($next);

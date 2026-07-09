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
if ($requestedAccountType === 'artist' && Auth::accountTypeColumnExists($pdo) && Auth::accountTypeForUser($pdo, $userId) === 'customer') {
	Auth::updateAccountType($pdo, $userId, 'artist');
}
sync_user_entitlements($pdo, $userId);
Auth::login($userId);

$next = $_SESSION['login_next'] ?? Auth::defaultPostLoginUrl($pdo, $userId);
$accountType = Auth::accountTypeForUser($pdo, $userId);
if ($accountType === 'artist' && str_contains((string)$next, '/booking-request.php')) {
	$next = Auth::defaultPostLoginUrl($pdo, $userId);
}
if (empty($_SESSION['login_next']) && $requestedAccountType === 'artist') {
	$next = base_url('member/artist_onboarding.php');
}
unset($_SESSION['login_next']);
unset($_SESSION['registration_account_type']);
unset($_SESSION['auth_brand']);
redirect($next);

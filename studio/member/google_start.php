<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
//echo "NEW VERSION"; exit;
$clientId = env('GOOGLE_CLIENT_ID');
$clientSecret = env('GOOGLE_CLIENT_SECRET');

function rss_google_redirect_uri(): string
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);

    if (in_array($host, ['readysetshows.com', 'www.readysetshows.com'], true)) {
        return 'https://readysetshows.com/studio/member/google_callback.php';
    }

    return (string)env('GOOGLE_REDIRECT_URI');
}

$redirectUri = rss_google_redirect_uri();

if (!$clientId || !$clientSecret || !$redirectUri) {
    http_response_code(500);
    echo 'Google login is not configured yet.';
    exit;
}

$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

if (($_GET['calendar'] ?? '') === '1') {
    require_once __DIR__ . '/../_private/_core/google_calendar.php';
    $intent = $_SESSION['google_calendar_connect_intent'] ?? [];
    unset($_SESSION['google_calendar_connect_intent']);
    $user = Auth::isLoggedIn() ? Auth::currentUser($pdo) : null;
    if (!$user || (int)($intent['user_id'] ?? 0) !== (int)$user['id'] || (int)($intent['expires'] ?? 0) < time()) {
        http_response_code(400);
        exit('Please start the Google Calendar connection from Manage Calendars.');
    }
    $client->setScopes(array_merge(['openid', 'email'], RSS_GOOGLE_CALENDAR_SCOPES));
    $client->setAccessType('offline');
    $client->setPrompt('consent select_account');
    $client->setLoginHint((string)$user['email']);
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_calendar_oauth'] = ['state' => $state, 'user_id' => (int)$user['id'], 'expires' => time() + 600];
    $client->setState($state);
    redirect($client->createAuthUrl());
}

$client->setScopes([
		'openid',
		'email',
		'profile',
]);

$client->setAccessType('online');
$client->setPrompt('select_account');

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

redirect($client->createAuthUrl());

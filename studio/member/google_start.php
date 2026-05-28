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

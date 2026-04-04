<?php
require __DIR__ . '/../_private/_core/bootstrap.php';

if (!isset($_GET['state']) || !hash_equals((string)($_SESSION['google_oauth_state'] ?? ''), (string)$_GET['state'])) {
    http_response_code(400);
    echo 'Invalid Google login state.';
    exit;
}
unset($_SESSION['google_oauth_state']);

$client = new Google_Client();
$client->setClientId(env('GOOGLE_CLIENT_ID'));
$client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

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
$sub = (string)$googleUser->id;
$name = trim((string)$googleUser->name);

if (!$email || !$sub) {
    http_response_code(400);
    echo 'Google did not return the required profile information.';
    exit;
}

$userId = Auth::upsertGoogleUser($pdo, $sub, $email, $name ?: null);
Auth::syncEntitlementsByEmail($pdo, $userId);
Auth::login($userId);

$next = $_SESSION['login_next'] ?? base_url('library.php');
unset($_SESSION['login_next']);
redirect($next);

<?php
require_once __DIR__ . '/_common.php';

if (!$isProUser) {
    header('Location: ' . $upgradeUrl);
    exit;
}

if (setmaxx_user_uses_direct_platform_tips($userId)) {
    header('Location: ' . base_url('/setmaxx/payments.php'));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isRefresh = $method === 'GET' && isset($_GET['refresh']);

if ($method !== 'POST' && !$isRefresh) {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if ($method === 'POST' && !csrf_verify($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo 'Invalid request token.';
    exit;
}

try {
    require_once __DIR__ . '/../_private/config/stripe.php';

    $connectAccount = setmaxx_connect_account_row($pdo, $userId);
    $accountId = trim((string)($connectAccount['stripe_account_id'] ?? ''));

    if ($accountId === '') {
        if ($isRefresh) {
            header('Location: ' . base_url('/setmaxx/payments.php'));
            exit;
        }

        $email = strtolower(trim((string)($user['email'] ?? '')));
        $displayName = trim((string)($user['display_name'] ?? ($user['name'] ?? '')));
        $accountPayload = [
            'type' => 'express',
            'country' => (string)env('SETMAXX_CONNECT_COUNTRY', 'US'),
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'app_user_id' => (string)$userId,
                'product' => 'setmaxx',
            ],
        ];

        if ($email !== '') {
            $accountPayload['email'] = $email;
        }

        if ($displayName !== '') {
            $accountPayload['business_profile'] = ['name' => $displayName];
        }

        $stripeAccount = \Stripe\Account::create($accountPayload);
        $accountId = (string)$stripeAccount->id;
        setmaxx_upsert_connect_account($pdo, $userId, $accountId, $stripeAccount);
    }

    $link = \Stripe\AccountLink::create([
        'account' => $accountId,
        'refresh_url' => setmaxx_absolute_url(base_url('/setmaxx/connect_start.php?refresh=1')),
        'return_url' => setmaxx_absolute_url(base_url('/setmaxx/payments.php?stripe_return=1')),
        'type' => 'account_onboarding',
    ]);

    header('Location: ' . (string)$link->url);
    exit;
} catch (Throwable $e) {
    error_log('SetMaxx Stripe onboarding failed: ' . $e->getMessage());
    $_SESSION['setmaxx_stripe_error'] = mb_substr($e->getMessage(), 0, 240);
    header('Location: ' . base_url('/setmaxx/payments.php?stripe_error=1'));
    exit;
}

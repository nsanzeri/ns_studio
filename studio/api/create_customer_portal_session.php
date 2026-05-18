<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Login required',
            'login_url' => base_url('member/login.php'),
        ]);
        exit;
    }

    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        echo json_encode(['error' => 'Security check failed. Please refresh the page and try again.']);
        exit;
    }

    if (!rss_table_exists($pdo, 'user_subscriptions') || !rss_table_exists($pdo, 'subscription_plans')) {
        http_response_code(404);
        echo json_encode(['error' => 'No subscription records were found for this account.']);
        exit;
    }

    $userId = Auth::userId();
    $toolSlugs = rss_tools_product_slugs();
    $placeholders = implode(',', array_fill(0, count($toolSlugs), '?'));

    $sql = "
        SELECT
            us.stripe_customer_id,
            us.stripe_subscription_id,
            us.status,
            us.current_period_end
        FROM user_subscriptions us
        JOIN subscription_plans sp ON sp.id = us.subscription_plan_id
        WHERE us.user_id = ?
          AND us.stripe_customer_id IS NOT NULL
          AND us.stripe_customer_id <> ''
          AND us.status IN ('trialing', 'active', 'past_due', 'unpaid')
          AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
          AND sp.slug IN ($placeholders)
        ORDER BY
          CASE us.status
            WHEN 'active' THEN 1
            WHEN 'trialing' THEN 2
            WHEN 'past_due' THEN 3
            WHEN 'unpaid' THEN 4
            ELSE 5
          END,
          us.id DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $toolSlugs));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'No active Stripe subscription was found for this account.']);
        exit;
    }

    $customerId = trim((string)($row['stripe_customer_id'] ?? ''));
    if ($customerId === '') {
        http_response_code(404);
        echo json_encode(['error' => 'This subscription is missing a Stripe customer ID.']);
        exit;
    }

    $session = \Stripe\BillingPortal\Session::create([
        'customer' => $customerId,
        'return_url' => rtrim(SITE_URL, '/') . '/member/settings.php',
    ]);

    echo json_encode(['url' => $session->url]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to open billing portal',
        'detail' => is_local() ? $e->getMessage() : 'Please try again.',
    ]);
}

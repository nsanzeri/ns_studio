<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!class_exists('Auth') || !Auth::isLoggedIn()) {
    header('Location: ' . base_url('/member/login.php?next=' . urlencode(base_url('/member/trial.php'))));
    exit;
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(403);
    echo 'Unable to resolve your account.';
    exit;
}

if (rss_current_user_is_pro($pdo)) {
    header('Location: ' . base_url('/tools/index.php?already_pro=1'));
    exit;
}

/**
 * Trial rules
 * - one manual trial ever per user
 * - 30 day expiration
 * - canonical product slug = rss-pro
 */

function rss_find_product_id_by_slug(PDO $pdo, string $slug): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function rss_user_has_ever_used_manual_trial(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM entitlements
        WHERE user_id = ?
          AND source = 'manual_grant'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

$productId = rss_find_product_id_by_slug($pdo, 'rss-pro');

if (!$productId) {
    http_response_code(500);
    echo 'Trial product is not configured.';
    exit;
}

if (rss_user_has_ever_used_manual_trial($pdo, $userId)) {
    header('Location: ' . base_url('/member/pricing.php?trial=used'));
    exit;
}

$expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
        VALUES (?, ?, 'manual_grant', 'active', ?)
    ");
    $stmt->execute([$userId, $productId, $expiresAt]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo 'Unable to activate trial.';
    exit;
}

header('Location: ' . base_url('/tools/index.php?trial_started=1'));
exit;
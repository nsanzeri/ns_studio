<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

Auth::requireLogin(rss_studio_root_url() . '/member/trial.php');

$user = Auth::currentUser($pdo);
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    redirect(rss_studio_root_url() . '/member/login.php');
}

$state = rss_current_tools_access_state($pdo);
if ($state === 'paid') {
    flash_set('pricing_notice', 'Pro is already active on your account.');
    redirect(rss_tool_upgrade_url());
}
if ($state === 'trial') {
    flash_set('pricing_notice', 'Your free trial is already active.');
    redirect(rss_tool_upgrade_url());
}

if (!rss_table_exists($pdo, 'entitlements') || !rss_table_exists($pdo, 'products')) {
    flash_set('pricing_notice', 'The trial could not be started because the tools product has not been set up yet.');
    redirect(rss_tool_upgrade_url());
}

$productStmt = $pdo->prepare(
    'SELECT id, slug, name FROM products WHERE slug IN (?, ?, ?, ?, ?) ORDER BY FIELD(slug, ?, ?, ?, ?, ?) LIMIT 1'
);
$slugs = rss_tools_product_slugs();
$productStmt->execute(array_merge($slugs, $slugs));
$product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$product) {
    flash_set('pricing_notice', 'The trial could not be started because no Calendar Tools product was found in products.');
    redirect(rss_tool_upgrade_url());
}

$expiresAt = (new DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("
        INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
        VALUES (?, ?, 'manual_grant', 'active', ?)
    ");
    $insert->execute([$userId, (int) $product['id'], $expiresAt]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

flash_set('pricing_notice', 'Your 30-day Calendar Tools trial is active now.');
redirect(rss_tool_library_url());

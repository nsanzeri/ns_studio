<?php

declare(strict_types=1);

if (!function_exists('rss_table_exists')) {
	function rss_table_exists(PDO $pdo, string $tableName): bool
	{
		static $cache = [];
		
		$tableName = strtolower(trim($tableName));
		if ($tableName === '') {
			return false;
		}
		
		if (array_key_exists($tableName, $cache)) {
			return $cache[$tableName];
		}
		
		try {
			$sql = "
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                LIMIT 1
            ";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$tableName]);
			$cache[$tableName] = (bool)$stmt->fetchColumn();
		} catch (Throwable $e) {
			$cache[$tableName] = false;
		}
		
		return $cache[$tableName];
	}
}

if (!function_exists('rss_current_user_is_pro')) {
	function rss_current_user_is_pro(PDO $pdo): bool
	{
		if (!class_exists('Auth') || !Auth::isLoggedIn()) {
			return false;
		}
		
		$user = Auth::currentUser($pdo);
		$userId = (int)($user['id'] ?? 0);
		if ($userId <= 0) {
			return false;
		}
		
		$productSlugs = [
				'calendar-tools-pro',
				'ready-set-shows-pro',
				'rss-pro',
		];
		
		if (rss_table_exists($pdo, 'user_subscriptions') && rss_table_exists($pdo, 'subscription_plans')) {
			$placeholders = implode(',', array_fill(0, count($productSlugs), '?'));
			$sql = "
                SELECT 1
                FROM user_subscriptions us
                JOIN subscription_plans sp
                  ON sp.id = us.subscription_plan_id
                WHERE us.user_id = ?
                  AND us.status IN ('trialing', 'active')
                  AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
                  AND sp.slug IN ($placeholders)
                  AND sp.is_active = 1
                LIMIT 1
            ";
			$params = array_merge([$userId], $productSlugs);
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			if ($stmt->fetchColumn()) {
				return true;
			}
		}
		
		if (rss_table_exists($pdo, 'entitlements') && rss_table_exists($pdo, 'products')) {
			$placeholders = implode(',', array_fill(0, count($productSlugs), '?'));
			$sql = "
                SELECT 1
                FROM entitlements e
                JOIN products p ON p.id = e.product_id
                WHERE e.user_id = ?
                  AND e.status = 'active'
                  AND (e.expires_at IS NULL OR e.expires_at > NOW())
                  AND p.slug IN ($placeholders)
                LIMIT 1
            ";
			$params = array_merge([$userId], $productSlugs);
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			if ($stmt->fetchColumn()) {
				return true;
			}
		}
		
		return false;
	}
}

if (!function_exists('rss_tool_upgrade_url')) {
	function rss_tool_upgrade_url(): string
	{
		return base_url('/member/pricing.php');
	}
}
<?php
// /_core/helpers.php

declare(strict_types=1);

function e(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
  header("Location: {$path}");
  exit;
}

function flash_set(string $key, string $msg): void {
  $_SESSION['_flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
  if (!isset($_SESSION['_flash'][$key])) return null;
  $m = $_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return $m;
}

function csrf_token(): string {
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool {
  return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function is_post(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

if (!function_exists('sync_user_entitlements')) {
	function sync_user_entitlements(PDO $pdo, int $userId): void
	{
		$stmt = $pdo->prepare("
            INSERT INTO entitlements (
                user_id,
                product_id,
                source,
                status,
                expires_at
            )
            SELECT DISTINCT
                u.id,
                pi.product_id,
                'purchase',
                'active',
                NULL
            FROM users u
            JOIN purchases pu
                ON pu.purchaser_email = u.email
            JOIN purchase_items pi
                ON pi.purchase_id = pu.id
            LEFT JOIN entitlements e
                ON e.user_id = u.id
               AND e.product_id = pi.product_id
               AND e.source = 'purchase'
            WHERE u.id = ?
              AND pu.status = 'paid'
              AND e.id IS NULL
        ");
		
		$stmt->execute([$userId]);
	}
}


if (!function_exists('rss_get_current_user_trial_status')) {
	function rss_get_current_user_trial_status(PDO $pdo): ?array
	{
		if (!class_exists('Auth') || !Auth::isLoggedIn()) {
			return null;
		}

		$user = Auth::currentUser($pdo);
		$userId = (int)($user['id'] ?? 0);
		if ($userId <= 0) {
			return null;
		}

		$stmt = $pdo->prepare("
            SELECT e.expires_at, p.name AS product_name
            FROM entitlements e
            JOIN products p ON p.id = e.product_id
            WHERE e.user_id = ?
              AND e.source = 'manual_grant'
              AND e.status = 'active'
              AND e.expires_at IS NOT NULL
              AND e.expires_at > NOW()
            ORDER BY e.expires_at ASC
            LIMIT 1
        ");
		$stmt->execute([$userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row || empty($row['expires_at'])) {
			return null;
		}

		$expiresTs = strtotime((string)$row['expires_at']);
		if (!$expiresTs) {
			return null;
		}

		$secondsRemaining = $expiresTs - time();
		if ($secondsRemaining <= 0) {
			return null;
		}

		$daysRemaining = (int) ceil($secondsRemaining / 86400);

		return [
			'expires_at' => (string)$row['expires_at'],
			'expires_on' => date('M j, Y', $expiresTs),
			'days_remaining' => max(1, $daysRemaining),
			'product_name' => (string)($row['product_name'] ?? ''),
		];
	}
}

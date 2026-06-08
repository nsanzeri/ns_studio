<?php

declare(strict_types=1);


if (!function_exists('rss_studio_root_url')) {
    function rss_studio_root_url(): string
    {
        $base = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
        $base = preg_replace('#/(member|shop|tools|admin|api|webhooks)$#', '', $base) ?: $base;
        return $base;
    }
}

if (!function_exists('rss_public_root_url')) {
    function rss_public_root_url(): string
    {
        $studioRoot = rss_studio_root_url();
        return preg_replace('#/studio$#', '', $studioRoot) ?: $studioRoot;
    }
}

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
            $cache[$tableName] = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }
}

if (!function_exists('rss_tools_product_slugs')) {
    function rss_tools_product_slugs(): array
    {
        return [
            'calendar-tools-pro',
            'ready-set-shows-pro',
            'rss-pro',
            'calendar-tools',
            'ready-set-shows',
        ];
    }
}

if (!function_exists('rss_tool_upgrade_url')) {
    function rss_tool_upgrade_url(): string
    {
        return rss_studio_root_url() . '/member/pricing.php';
    }
}

if (!function_exists('rss_tool_trial_url')) {
    function rss_tool_trial_url(): string
    {
        return rss_studio_root_url() . '/member/trial.php';
    }
}

if (!function_exists('rss_tool_library_url')) {
    function rss_tool_library_url(): string
    {
        return rss_studio_root_url() . '/member/library.php';
    }
}

if (!function_exists('rss_tool_launch_url')) {
    function rss_tool_launch_url(): string
    {
        return rss_studio_root_url() . '/setmaxx/index.php';
    }
}

if (!function_exists('rss_current_tools_access_state')) {
    function rss_current_tools_access_state(PDO $pdo): string
    {
        if (!class_exists('Auth') || !Auth::isLoggedIn()) {
            return 'free';
        }

        $user = Auth::currentUser($pdo);
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return 'free';
        }

        if (rss_table_exists($pdo, 'user_subscriptions') && rss_table_exists($pdo, 'subscription_plans')) {
            $slugs = rss_tools_product_slugs();
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $sql = "
                SELECT us.status
                FROM user_subscriptions us
                JOIN subscription_plans sp
                  ON sp.id = us.subscription_plan_id
                WHERE us.user_id = ?
                  AND us.status IN ('trialing', 'active')
                  AND (us.current_period_end IS NULL OR us.current_period_end > NOW())
                  AND sp.slug IN ($placeholders)
                  AND sp.is_active = 1
                ORDER BY FIELD(us.status, 'active', 'trialing')
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$userId], $slugs));
            $subStatus = (string) ($stmt->fetchColumn() ?: '');
            if ($subStatus === 'active') {
                return 'paid';
            }
            if ($subStatus === 'trialing') {
                return 'trial';
            }
        }

        if (rss_table_exists($pdo, 'entitlements') && rss_table_exists($pdo, 'products')) {
            $slugs = rss_tools_product_slugs();
            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $sql = "
                SELECT e.source, e.expires_at
                FROM entitlements e
                JOIN products p ON p.id = e.product_id
                WHERE e.user_id = ?
                  AND e.status = 'active'
                  AND (e.expires_at IS NULL OR e.expires_at > NOW())
                  AND p.slug IN ($placeholders)
                ORDER BY
                  CASE
                    WHEN e.source = 'subscription' THEN 1
                    WHEN e.source = 'purchase' THEN 2
                    WHEN e.source = 'manual_grant' AND e.expires_at IS NULL THEN 3
                    WHEN e.source = 'manual_grant' THEN 4
                    ELSE 5
                  END,
                  e.created_at DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$userId], $slugs));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $source = (string) ($row['source'] ?? '');
                $expiresAt = $row['expires_at'] ?? null;
                if ($source === 'manual_grant' && !empty($expiresAt)) {
                    return 'trial';
                }
                return 'paid';
            }
        }

        return 'free';
    }
}

if (!function_exists('rss_current_user_is_pro')) {
    function rss_current_user_is_pro(PDO $pdo): bool
    {
        return in_array(rss_current_tools_access_state($pdo), ['trial', 'paid'], true);
    }
}

if (!function_exists('rss_tools_access_badge')) {
    function rss_tools_access_badge(PDO $pdo): array
    {
        $state = rss_current_tools_access_state($pdo);
        switch ($state) {
            case 'paid':
                return [
                    'state' => 'paid',
                    'label' => 'Pro active',
                    'description' => 'Full access is unlocked on your account.',
                ];
            case 'trial':
                return [
                    'state' => 'trial',
                    'label' => 'Trial active',
                    'description' => 'You have temporary Pro access right now.',
                ];
            default:
                return [
                    'state' => 'free',
                    'label' => 'Free plan',
                    'description' => 'Use the free version now or start a 30-day trial.',
                ];
        }
    }
}

<?php
declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

function rss_push_table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function rss_push_ensure_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `app_secrets` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `namespace` varchar(64) NOT NULL,
          `secret_key` varchar(128) NOT NULL,
          `secret_value` text NOT NULL,
          `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_app_secrets` (`namespace`,`secret_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `setmaxx_push_subscriptions` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `endpoint_hash` char(64) NOT NULL,
          `endpoint` text NOT NULL,
          `public_key` varchar(255) NOT NULL,
          `auth_token` varchar(255) NOT NULL,
          `content_encoding` varchar(32) NOT NULL DEFAULT 'aes128gcm',
          `user_agent` varchar(255) DEFAULT NULL,
          `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
          `last_used_at` datetime DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_setmaxx_push_endpoint` (`endpoint_hash`),
          KEY `idx_setmaxx_push_user_enabled` (`user_id`,`is_enabled`),
          CONSTRAINT `fk_setmaxx_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function rss_push_secret(PDO $pdo, string $key): ?string {
    if (!rss_push_table_exists($pdo, 'app_secrets')) return null;
    $stmt = $pdo->prepare("SELECT secret_value FROM app_secrets WHERE namespace = 'web_push' AND secret_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) && $value !== '' ? $value : null;
}

function rss_push_set_secret(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO app_secrets (namespace, secret_key, secret_value, is_encrypted)
        VALUES ('web_push', ?, ?, 0)
        ON DUPLICATE KEY UPDATE secret_value = VALUES(secret_value), is_encrypted = 0
    ");
    $stmt->execute([$key, $value]);
}

function rss_push_prepare_openssl_config(): void {
    if ((string)getenv('OPENSSL_CONF') !== '') return;

    $candidates = [
        'C:\\xampp\\apache\\conf\\openssl.cnf',
        'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
        'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            putenv('OPENSSL_CONF=' . $path);
            $_ENV['OPENSSL_CONF'] = $path;
            $_SERVER['OPENSSL_CONF'] = $path;
            return;
        }
    }
}

function rss_push_vapid_keys(PDO $pdo): array {
    rss_push_ensure_tables($pdo);

    $publicKey = trim((string)(env('WEB_PUSH_PUBLIC_KEY', '') ?: rss_push_secret($pdo, 'public_key')));
    $privateKey = trim((string)(env('WEB_PUSH_PRIVATE_KEY', '') ?: rss_push_secret($pdo, 'private_key')));

    if ($publicKey === '' || $privateKey === '') {
        rss_push_prepare_openssl_config();
        $keys = VAPID::createVapidKeys();
        $publicKey = (string)$keys['publicKey'];
        $privateKey = (string)$keys['privateKey'];
        rss_push_set_secret($pdo, 'public_key', $publicKey);
        rss_push_set_secret($pdo, 'private_key', $privateKey);
    }

    return ['publicKey' => $publicKey, 'privateKey' => $privateKey];
}

function rss_push_subject(): string {
    $subject = trim((string)env('WEB_PUSH_SUBJECT', ''));
    if ($subject !== '') return $subject;

    $fromEmail = trim((string)env('SMTP_FROM', ''));
    if ($fromEmail !== '') return 'mailto:' . $fromEmail;

    $appUrl = trim((string)env('APP_URL', ''));
    return $appUrl !== '' ? $appUrl : 'mailto:notifications@readysetshows.com';
}

function rss_push_public_key(PDO $pdo): string {
    return (string)rss_push_vapid_keys($pdo)['publicKey'];
}

function rss_push_save_subscription(PDO $pdo, int $userId, array $subscription, string $userAgent = ''): void {
    rss_push_ensure_tables($pdo);

    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $publicKey = trim((string)($subscription['keys']['p256dh'] ?? ''));
    $authToken = trim((string)($subscription['keys']['auth'] ?? ''));
    $contentEncoding = trim((string)($subscription['contentEncoding'] ?? 'aes128gcm'));

    if ($endpoint === '' || $publicKey === '' || $authToken === '') {
        throw new RuntimeException('Missing push subscription details.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO setmaxx_push_subscriptions
            (user_id, endpoint_hash, endpoint, public_key, auth_token, content_encoding, user_agent, is_enabled)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            endpoint = VALUES(endpoint),
            public_key = VALUES(public_key),
            auth_token = VALUES(auth_token),
            content_encoding = VALUES(content_encoding),
            user_agent = VALUES(user_agent),
            is_enabled = 1,
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        hash('sha256', $endpoint),
        $endpoint,
        $publicKey,
        $authToken,
        in_array($contentEncoding, ['aesgcm', 'aes128gcm'], true) ? $contentEncoding : 'aes128gcm',
        mb_substr($userAgent, 0, 255),
    ]);
}

function rss_push_disable_subscription(PDO $pdo, int $userId, string $endpoint): void {
    if (!rss_push_table_exists($pdo, 'setmaxx_push_subscriptions')) return;
    $stmt = $pdo->prepare("UPDATE setmaxx_push_subscriptions SET is_enabled = 0, updated_at = NOW() WHERE user_id = ? AND endpoint_hash = ?");
    $stmt->execute([$userId, hash('sha256', $endpoint)]);
}

function rss_push_send_to_user(PDO $pdo, int $userId, array $payload): void {
    if ($userId <= 0 || !rss_push_table_exists($pdo, 'setmaxx_push_subscriptions')) return;

    try {
        $keys = rss_push_vapid_keys($pdo);
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => rss_push_subject(),
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ],
        ], ['TTL' => 3600, 'urgency' => 'high']);

        $stmt = $pdo->prepare("SELECT * FROM setmaxx_push_subscriptions WHERE user_id = ? AND is_enabled = 1");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return;

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!$json) return;

        foreach ($rows as $row) {
            $subscription = Subscription::create([
                'endpoint' => (string)$row['endpoint'],
                'publicKey' => (string)$row['public_key'],
                'authToken' => (string)$row['auth_token'],
                'contentEncoding' => (string)($row['content_encoding'] ?: 'aes128gcm'),
            ]);
            $report = $webPush->sendOneNotification($subscription, $json);
            if ($report->isSuccess()) {
                $pdo->prepare("UPDATE setmaxx_push_subscriptions SET last_used_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
            } elseif ($report->isSubscriptionExpired()) {
                $pdo->prepare("UPDATE setmaxx_push_subscriptions SET is_enabled = 0, updated_at = NOW() WHERE id = ?")->execute([(int)$row['id']]);
            }
        }
    } catch (Throwable $e) {
        error_log('Web push send failed: ' . $e->getMessage());
    }
}

function rss_push_notify_setmaxx_request(PDO $pdo, int $requestId): void {
    if ($requestId <= 0) return;

    $stmt = $pdo->prepare("
        SELECT r.id, r.requester_name, r.amount_cents, r.payment_method, s.title, s.artist, gs.title AS session_title, gs.user_id
        FROM setmaxx_requests r
        JOIN setmaxx_songs s ON s.id = r.song_id
        JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $amountCents = (int)($row['amount_cents'] ?? 0);
    $requester = trim((string)($row['requester_name'] ?? ''));
    $title = trim((string)($row['title'] ?? 'Song request'));
    $artist = trim((string)($row['artist'] ?? ''));
    $amount = $amountCents > 0 ? '$' . number_format($amountCents / 100, 0) : 'No tip';

    rss_push_send_to_user($pdo, (int)$row['user_id'], [
        'title' => 'New SetMaxx request',
        'body' => trim($title . ($artist !== '' ? ' - ' . $artist : '') . ' · ' . $amount . ($requester !== '' ? ' from ' . $requester : '')),
        'body' => trim($title . ($artist !== '' ? ' - ' . $artist : '') . ' - ' . $amount . ($requester !== '' ? ' from ' . $requester : '')),
        'url' => base_url('/setmaxx/requests.php'),
        'tag' => 'setmaxx-request-' . (int)$row['id'],
        'badge' => base_url('/icons/rss-badge.png'),
    ]);
}

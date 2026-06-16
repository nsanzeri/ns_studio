<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../_private/_core/push_notifications.php';

header('Content-Type: application/json; charset=utf-8');

function setmaxx_push_json(array $payload): void {
    echo json_encode($payload);
    exit;
}

try {
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'status');

    if ($action === 'key' || $action === 'status') {
        setmaxx_push_json([
            'success' => true,
            'publicKey' => rss_push_public_key($pdo),
            'csrf' => csrf_token(),
        ]);
    }

    if (!is_post() || !csrf_verify($_POST['_csrf'] ?? null)) {
        setmaxx_push_json(['success' => false, 'error' => 'Security check failed. Refresh and try again.']);
    }

    if ($action === 'subscribe') {
        $raw = (string)($_POST['subscription'] ?? '');
        $subscription = json_decode($raw, true);
        if (!is_array($subscription)) {
            setmaxx_push_json(['success' => false, 'error' => 'Notification setup failed.']);
        }
        rss_push_save_subscription($pdo, $userId, $subscription, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        setmaxx_push_json(['success' => true, 'message' => 'Request notifications are enabled on this device.']);
    }

    if ($action === 'unsubscribe') {
        $endpoint = trim((string)($_POST['endpoint'] ?? ''));
        if ($endpoint !== '') {
            rss_push_disable_subscription($pdo, $userId, $endpoint);
        }
        setmaxx_push_json(['success' => true, 'message' => 'Request notifications are disabled on this device.']);
    }

    setmaxx_push_json(['success' => false, 'error' => 'Unknown notification action.']);
} catch (Throwable $e) {
    error_log('SetMaxx push endpoint failed: ' . $e->getMessage());
    setmaxx_push_json(['success' => false, 'error' => 'Notification setup is not available right now.']);
}

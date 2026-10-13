<?php
declare(strict_types=1);

require_once __DIR__ . '/studio/_private/_core/bootstrap.php';
require_once __DIR__ . '/studio/_private/_core/email.php';

function rss_feedback_redirect(string $url, string $status): void
{
    $parts = parse_url($url);
    $path = (string)($parts['path'] ?? '/');
    $query = (string)($parts['query'] ?? '');

    if ($path === '' || !str_starts_with($path, '/')) {
        $path = '/';
    }

    parse_str($query, $params);
    unset($params['feedback'], $params['feedback_error']);
    $params[$status === 'sent' ? 'feedback' : 'feedback_error'] = $status === 'sent' ? 'sent' : '1';

    $location = $path . '?' . http_build_query($params);
    header('Location: ' . $location);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo 'Please submit feedback from the site.';
    exit;
}

$returnUrl = trim((string)($_POST['return_url'] ?? '/'));

if (!csrf_verify($_POST['_csrf'] ?? null)) {
    rss_feedback_redirect($returnUrl, 'error');
}

if (trim((string)($_POST['website'] ?? '')) !== '') {
    rss_feedback_redirect($returnUrl, 'sent');
}

$allowedTypes = [
    'bug' => 'Bug report',
    'idea' => 'Improvement idea',
    'confusing' => 'Confusing experience',
];

$type = (string)($_POST['feedback_type'] ?? '');
$typeLabel = $allowedTypes[$type] ?? '';
$message = trim((string)($_POST['message'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pageUrl = trim((string)($_POST['page_url'] ?? ''));

$message = preg_replace('/\s+/', ' ', $message) ?? '';
$message = mb_substr($message, 0, 1800);
$pageUrl = mb_substr($pageUrl, 0, 500);

if ($typeLabel === '' || $message === '') {
    rss_feedback_redirect($returnUrl, 'error');
}

if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
    rss_feedback_redirect($returnUrl, 'error');
}

$recipient = trim((string)env('READYSETSHOWS_FEEDBACK_EMAIL', 'nsanzeri@gmail.com'));
$subject = 'Ready Set Shows feedback: ' . $typeLabel;
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$body = "Type: {$typeLabel}\n"
    . "Page: {$pageUrl}\n"
    . "Reply-to: " . ($email !== '' ? $email : 'Not provided') . "\n"
    . "IP: {$ip}\n"
    . "Browser: {$userAgent}\n\n"
    . "Message:\n{$message}\n";

$sent = send_and_log_email($pdo, [
    'message_type' => 'readysetshows_feedback',
    'recipient' => $recipient,
    'subject' => $subject,
    'body' => $body,
    'reply_to' => $email !== '' ? $email : null,
    'idempotency_key' => hash('sha256', $type . '|' . $message . '|' . $pageUrl . '|' . $ip . '|' . date('Y-m-d-H')),
]);

rss_feedback_redirect($returnUrl, $sent ? 'sent' : 'error');

<?php
declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/email.php';
require_once __DIR__ . '/../_private/_core/brevo.php';

header('Content-Type: application/json; charset=utf-8');

function sample_json(int $status, array $payload): void
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	sample_json(405, ['ok' => false, 'error' => 'Please submit the form from the blueprint page.']);
}

$token = (string)($_POST['token'] ?? '');
$sessionToken = (string)($_SESSION['blueprint_lead_token'] ?? '');
if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
	sample_json(400, ['ok' => false, 'error' => 'Please refresh the page and try again.']);
}

if (trim((string)($_POST['website'] ?? '')) !== '') {
	sample_json(200, ['ok' => true, 'message' => 'Check your inbox. The sample is on its way.']);
}

$firstName = trim((string)($_POST['first_name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));

$firstName = preg_replace('/\s+/', ' ', $firstName) ?? '';
$firstName = substr($firstName, 0, 120);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
	sample_json(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}

$source = 'blueprint_sample';
$stmt = $pdo->prepare('SELECT id, first_name FROM leads WHERE email = ? AND source = ? ORDER BY id ASC LIMIT 1');
$stmt->execute([$email, $source]);
$lead = $stmt->fetch();

if ($lead) {
	$leadId = (int)$lead['id'];
	if ($firstName !== '' && trim((string)($lead['first_name'] ?? '')) === '') {
		$pdo->prepare('UPDATE leads SET first_name = ? WHERE id = ?')->execute([$firstName, $leadId]);
	}
} else {
	$insert = $pdo->prepare('INSERT INTO leads (first_name, email, source, status, created_at) VALUES (?, ?, ?, ?, NOW())');
	$insert->execute([$firstName !== '' ? $firstName : null, $email, $source, 'new']);
	$leadId = (int)$pdo->lastInsertId();
}

$brevoListId = (int)env('BREVO_BLUEPRINT_SAMPLE_LIST_ID', 0);
$brevoResult = brevo_sync_contact_to_list($email, $firstName !== '' ? $firstName : null, $brevoListId);
if (!$brevoResult['ok']) {
	error_log(sprintf(
		'Brevo blueprint sample sync failed: email=%s status=%d error=%s body=%s',
		$email,
		(int)($brevoResult['status'] ?? 0),
		substr((string)($brevoResult['error'] ?? ''), 0, 500),
		substr((string)($brevoResult['body'] ?? ''), 0, 500)
	));
}

$downloadUrl = rtrim((string)env('APP_URL'), '/') . '/shop/blueprint-sample-download.php';
$buyUrl = rtrim((string)env('APP_URL'), '/') . '/shop/blueprint.php';
$nameLine = $firstName !== '' ? "Hey {$firstName},\n\n" : "Hey,\n\n";

$plainBody =
	$nameLine .
	"Here is the free Backing Track Blueprint sample:\n" .
	$downloadUrl . "\n\n" .
	"It includes the intro, my story, and the first two chapters so you can get a real feel for the system before buying the full guide.\n\n" .
	"When you are ready for the full 66-page blueprint, you can grab it here:\n" .
	$buyUrl . "\n\n" .
	"- Nick\n";

$htmlBody = '<!doctype html><html><body style="margin:0;background:#f6f2e8;font-family:Arial,Helvetica,sans-serif;color:#171717;">'
	. '<div style="max-width:640px;margin:0 auto;padding:32px 20px;">'
	. '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.15;">Your Backing Track Blueprint sample</h1>'
	. '<p style="font-size:16px;line-height:1.6;margin:0 0 16px;">' . htmlspecialchars($nameLine === "Hey,\n\n" ? 'Hey,' : 'Hey ' . $firstName . ',', ENT_QUOTES, 'UTF-8') . '</p>'
	. '<p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Here is the free sample. It includes the intro, my story, and the first two chapters so you can get a real feel for the system before buying the full guide.</p>'
	. '<p style="margin:0 0 22px;"><a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#171717;color:#f6f2e8;text-decoration:none;font-weight:700;padding:14px 20px;border-radius:999px;">Download the sample PDF</a></p>'
	. '<p style="font-size:16px;line-height:1.6;margin:0 0 14px;">When you are ready for the full 66-page blueprint, you can grab it here:</p>'
	. '<p style="margin:0 0 22px;"><a href="' . htmlspecialchars($buyUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#8a5a00;font-weight:700;">Get the full Backing Track Blueprint</a></p>'
	. '<p style="font-size:16px;line-height:1.6;margin:0;">- Nick</p>'
	. '</div></body></html>';

$sent = send_and_log_email($pdo, [
	'message_type' => 'blueprint_sample',
	'recipient' => $email,
	'subject' => 'Your free Backing Track Blueprint sample',
	'body' => $plainBody,
	'html_body' => $htmlBody,
	'related_table' => 'leads',
	'related_id' => $leadId,
]);

if (!$sent) {
	sample_json(500, ['ok' => false, 'error' => 'I saved your email, but the sample email did not send. Please try again in a minute.']);
}

sample_json(200, ['ok' => true, 'message' => 'Check your inbox. The sample is on its way.']);

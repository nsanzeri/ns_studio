<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_and_log_email(PDO $pdo, array $args): bool
{
	$messageType = trim((string)($args['message_type'] ?? 'general'));
	$recipient   = trim((string)($args['recipient'] ?? ''));
	$subject     = trim((string)($args['subject'] ?? ''));
	$body        = (string)($args['body'] ?? '');
	$htmlBody    = (string)($args['html_body'] ?? '');
	
	$fromEmail = trim((string)($args['from_email'] ?? env('SMTP_FROM', 'nick@nicksanzeri.com')));
	$fromName  = trim((string)($args['from_name'] ?? env('SMTP_FROM_NAME', 'Nick Sanzeri Music')));
	$replyTo   = trim((string)($args['reply_to'] ?? env('SMTP_REPLY_TO', $fromEmail)));
	
	$idemKey    = $args['idempotency_key'] ?? null;
	$relatedTbl = $args['related_table'] ?? null;
	$relatedId  = $args['related_id'] ?? null;
	
	if ($recipient === '' || $subject === '' || ($body === '' && $htmlBody === '')) {
		throw new InvalidArgumentException('Missing recipient, subject, or message body.');
	}
	
	$emailLogId = null;
	
	try {
		$stmt = $pdo->prepare("
            INSERT INTO email_log
                (message_type, recipient, related_table, related_id, subject, status, idempotency_key, created_at)
            VALUES
                (?, ?, ?, ?, ?, 'queued', ?, NOW())
        ");
		$stmt->execute([
				$messageType,
				$recipient,
				$relatedTbl,
				$relatedId,
				$subject,
				$idemKey,
		]);
		
		$emailLogId = (int)$pdo->lastInsertId();
	} catch (PDOException $e) {
		// duplicate idempotency key => already processed
		if (($e->getCode() ?? '') === '23000') {
			error_log('send_and_log_email skipped duplicate idempotency_key: ' . (string)$idemKey);
			return true;
		}
		throw $e;
	}
	
	try {
		$smtpHost = trim((string)env('SMTP_HOST', ''));
		$smtpUser = trim((string)env('SMTP_USERNAME', ''));
		$smtpPass = trim((string)env('SMTP_PASSWORD', ''));
		$smtpPort = (int)env('SMTP_PORT', 587);
		
		if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
			throw new RuntimeException('SMTP configuration is incomplete.');
		}
		
		$mail = new PHPMailer(true);
		$mail->isSMTP();
		$mail->Host       = $smtpHost;
		$mail->SMTPAuth   = true;
		$mail->Username   = $smtpUser;
		$mail->Password   = $smtpPass;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Port       = $smtpPort;
		$mail->CharSet    = 'UTF-8';
		
		$mail->setFrom($fromEmail, $fromName);
		$mail->addAddress($recipient);
		
		if ($replyTo !== '') {
			$mail->addReplyTo($replyTo);
		}
		
		$mail->Subject = $subject;
		
		if ($htmlBody !== '') {
			$mail->isHTML(true);
			$mail->Body    = $htmlBody;
			$mail->AltBody = $body !== '' ? $body : trim(strip_tags($htmlBody));
		} else {
			$mail->isHTML(false);
			$mail->Body = $body;
		}
		
		$mail->send();
		
		$pdo->prepare("
            UPDATE email_log
            SET status = 'sent',
                sent_at = NOW(),
                error_message = NULL
            WHERE id = ?
        ")->execute([$emailLogId]);
		
		return true;
		
	} catch (Throwable $e) {
		if ($emailLogId !== null) {
			$pdo->prepare("
                UPDATE email_log
                SET status = 'failed',
                    error_message = ?
                WHERE id = ?
            ")->execute([
            		substr($e->getMessage(), 0, 65535),
            		$emailLogId
            ]);
		}
		
		error_log('Email send failed: ' . $e->getMessage());
		return false;
	}
}
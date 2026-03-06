<?php
// _core/email.php

function send_and_log_email(PDO $pdo, array $args): bool {
    // args: message_type, recipient, subject, body, from_email, from_name, reply_to, idempotency_key, related_table, related_id
    $messageType = $args['message_type'];
    $recipient   = $args['recipient'];
    $subject     = $args['subject'];
    $body        = $args['body'];

    $fromEmail   = $args['from_email'] ?? 'no-reply@nicksanzeri.com';
    $fromName    = $args['from_name'] ?? 'Nick Sanzeri';
    $replyTo     = $args['reply_to'] ?? 'nsanzeri@gmail.com';

    $idemKey     = $args['idempotency_key'] ?? null;
    $relatedTbl  = $args['related_table'] ?? null;
    $relatedId   = $args['related_id'] ?? null;

    // 1) Insert email_log row first (idempotent)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_log
              (message_type, recipient, related_table, related_id, status, idempotency_key, created_at)
            VALUES
              (?, ?, ?, ?, 'queued', ?, NOW())
        ");
        $stmt->execute([$messageType, $recipient, $relatedTbl, $relatedId, $idemKey]);
        $emailLogId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
    	// Only treat duplicate-key as idempotent success
    	// SQLSTATE 23000 is integrity constraint violation (duplicate key)
    	if (($e->getCode() ?? '') === '23000') {
    		return true;
    	}
    	throw $e;
    }
    
    // 2) send
    $safeFromName  = trim(str_replace(["\r","\n"], '', $fromName));
    $safeFromEmail = trim(str_replace(["\r","\n"], '', $fromEmail));
    $safeReplyTo   = trim(str_replace(["\r","\n"], '', $replyTo));

    $headers  = "From: {$safeFromName} <{$safeFromEmail}>\r\n";
    $headers .= "Reply-To: {$safeReplyTo}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $ok = @mail($recipient, $subject, $body, $headers);

    // 3) update log row
    if ($ok) {
        $pdo->prepare("UPDATE email_log SET status='sent', sent_at=NOW() WHERE id=?")->execute([$emailLogId]);
        return true;
    } else {
        $pdo->prepare("UPDATE email_log SET status='failed', error_message=? WHERE id=?")
            ->execute(['mail() returned false', $emailLogId]);
        return false;
    }
}
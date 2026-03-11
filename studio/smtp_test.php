<?php

require __DIR__ . '/vendor/autoload.php';   // PHPMailer via Composer
require __DIR__ . '/_private/_core/bootstrap.php';   // loads your .env variables
// var_dump($_ENV['SMTP_HOST']);
// var_dump($_ENV['SMTP_PORT']);
// var_dump($_ENV['SMTP_USERNAME']);
// var_dump(strlen($_ENV['SMTP_PASSWORD']));
// exit;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
	
	$mail->isSMTP();
	$mail->Host       = $_ENV['SMTP_HOST'];
	$mail->SMTPAuth   = true;
	$mail->Username   = $_ENV['SMTP_USERNAME'];
	$mail->Password   = $_ENV['SMTP_PASSWORD'];
	$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	$mail->Port       = $_ENV['SMTP_PORT'];
	
	$mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
	$mail->addAddress('nsanzeri@gmail.com');
	
	$mail->Subject = 'Brevo SMTP Test - 5';
	$mail->Body    = 'If you received this email, Brevo SMTP is working.';
	
	$mail->send();
	
	echo "SUCCESS: Email sent.";
	
} catch (Exception $e) {
	
	echo "Mailer Error: " . $mail->ErrorInfo;
	
}
<?php

require __DIR__ . '/bootstrap.php';

// --------------------------------------------
// CONFIGURATION
// --------------------------------------------
$recipient = env('BOOKING_RECIPIENT', 'nsanzeri@gmail.com');
$subject   = "New Booking Inquiry from nicksanzeri.com";

// --------------------------------------------
// Basic spam trap (honeypot)
// --------------------------------------------
if (!empty($_POST['website'])) {
    // If this hidden field is filled, it's a bot
    header('Location: thank_you.html');
    exit;
}

// --------------------------------------------
// Helper to safely fetch fields
// --------------------------------------------
function field($key) {
    return isset($_POST[$key]) ? htmlspecialchars(trim((string)$_POST[$key])) : "";
}

// --------------------------------------------
// Collect fields
// --------------------------------------------
$name          = field("name");
$email         = field("email");
$phone         = field("phone");
$event_type    = field("event_type");
$event_date    = field("event_date");
$event_time    = field("event_time");
$venue_name    = field("venue_name");
$venue_loc     = field("venue_location");
$guest_count   = field("guest_count");
$budget_range  = field("budget_range");
$vibe          = field("vibe");
$heard_about   = field("heard_about");
$other_details = field("other_details");

// Multiple checkbox options
$needs = isset($_POST["needs"]) ? $_POST["needs"] : [];
$needs_list = implode(", ", array_map("htmlspecialchars", $needs));

// --------------------------------------------
// Basic required validation
// --------------------------------------------
if (!$name || !$email || !$phone) {
    http_response_code(400);
    die('Missing required fields. Please go back and complete all required fields.');
}

// --------------------------------------------
// Build the email body
// --------------------------------------------
$body = "
A new booking inquiry has been submitted:

Name: $name
Email: $email
Phone: $phone

Event Type: $event_type
Event Date: $event_date
Event Time: $event_time

Venue Name: $venue_name
Venue Location: $venue_loc
Guest Count: $guest_count
Budget Range: $budget_range

Needs: $needs_list

Vibe / Vision:
$vibe

How they heard about Nick:
$heard_about

Other Details:
$other_details

-- End of message --
";

// --------------------------------------------
// Prepare headers
// --------------------------------------------
// --------------------------------------------
// Send the email (SMTP via PHPMailer)
// --------------------------------------------

// Optional env vars with sensible defaults
$smtpHost = env('SMTP_HOST');
$smtpUser = env('SMTP_USERNAME');
$smtpPass = env('SMTP_PASSWORD');
$smtpPort = (int) env('SMTP_PORT', 587);

$fromEmail = env('SMTP_FROM', 'no-reply@nicksanzeri.com');
$fromName  = env('SMTP_FROM_NAME', 'Booking Form');

if (!$smtpHost || !$smtpUser || !$smtpPass) {
    error_log('booking_submit missing SMTP env vars.');
    header('Location: thank_you.html');
    exit;
}

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($recipient);
    $mail->addReplyTo($email, $name);

    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->send();
} catch (Throwable $e) {
    // Don't leak details to the user.
    error_log('booking_submit mail error: ' . $e->getMessage());
}

// --------------------------------------------
// Redirect to Thank You page
// --------------------------------------------
header('Location: thank_you.html');
exit;

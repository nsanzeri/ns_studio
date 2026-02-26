<?php
// booking_submit.php
// - Honeypot
// - Validation + length caps
// - Basic file-based rate limiting
// - Basic logging
// - Sends admin email + confirmation email to requester

// -----------------------------
// CONFIG
// -----------------------------
$adminRecipient = "nsanzeri@gmail.com";
$adminSubject   = "New Booking Inquiry from nicksanzeri.com";

// Where to write logs (make sure this folder is writable)
$logDir  = __DIR__ . '/_logs';
$logFile = $logDir . '/booking.log';

// Rate limit: max submissions per IP in window
$rateMax   = 5;
$rateMins  = 30;
$rateStore = $logDir . '/booking_rate.json';

// -----------------------------
// Helpers
// -----------------------------
function ensure_dir($dir) {
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

function log_line($file, $msg) {
	$ts = date('Y-m-d H:i:s');
	@file_put_contents($file, "[$ts] $msg\n", FILE_APPEND);
}

function field($key, $maxLen = 5000) {
	$val = isset($_POST[$key]) ? trim((string)$_POST[$key]) : "";
	// Collapse weird whitespace
	$val = preg_replace('/\s+/', ' ', $val);
	if (mb_strlen($val) > $maxLen) $val = mb_substr($val, 0, $maxLen);
	// For display inside email body (not HTML output), we still strip control chars
	$val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
	return $val;
}

function valid_email($email) {
	if (!$email) return false;
	if (strlen($email) > 190) return false;
	return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function safe_header_value($s) {
	// prevent header injection
	return trim(str_replace(["\r", "\n"], '', (string)$s));
}

function get_ip() {
	// If you're behind Cloudflare/etc you can enhance this later.
	return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limit_ok($storeFile, $ip, $max, $windowSeconds) {
	$now = time();
	$data = [];
	if (is_file($storeFile)) {
		$json = @file_get_contents($storeFile);
		$data = json_decode($json, true);
		if (!is_array($data)) $data = [];
	}
	if (!isset($data[$ip]) || !is_array($data[$ip])) $data[$ip] = [];
	
	// prune old
	$data[$ip] = array_values(array_filter($data[$ip], function($t) use ($now, $windowSeconds) {
		return is_int($t) && ($now - $t) <= $windowSeconds;
	}));
		
		if (count($data[$ip]) >= $max) {
			// save pruned
			@file_put_contents($storeFile, json_encode($data));
			return false;
		}
		
		// record new hit
		$data[$ip][] = $now;
		@file_put_contents($storeFile, json_encode($data));
		return true;
}

// -----------------------------
// Init
// -----------------------------
ensure_dir($logDir);

// Honeypot
if (!empty($_POST["website"])) {
	log_line($logFile, "SPAM honeypot triggered ip=" . get_ip());
	header("Location: thank_you.html");
	exit;
}

// Rate limit
$ip = get_ip();
if (!rate_limit_ok($rateStore, $ip, $rateMax, $rateMins * 60)) {
	log_line($logFile, "RATE_LIMIT ip=$ip");
	// Still redirect so bots don't learn anything
	header("Location: thank_you.html");
	exit;
}

// Collect fields
$name          = field("name", 120);
$email         = field("email", 190);
$phone         = field("phone", 64);
$event_type    = field("event_type", 120);
$event_date    = field("event_date", 64);
$event_time    = field("event_time", 64);
$venue_name    = field("venue_name", 190);
$venue_loc     = field("venue_location", 255);
$guest_count   = field("guest_count", 64);
$budget_range  = field("budget_range", 64);
$vibe          = field("vibe", 4000);
$heard_about   = field("heard_about", 190);
$other_details = field("other_details", 4000);

// Needs (checkbox)
$needs = isset($_POST["needs"]) && is_array($_POST["needs"]) ? $_POST["needs"] : [];
$needs = array_map(fn($x) => preg_replace('/[\r\n]/', '', trim((string)$x)), $needs);
$needs_list = implode(", ", array_slice($needs, 0, 20));

// Validation
if (!$name || !$email || !$phone) {
	log_line($logFile, "INVALID missing_required ip=$ip name=" . ($name ?: '-') . " email=" . ($email ?: '-'));
	http_response_code(400);
	echo "Missing required fields. Please go back and complete all required fields.";
	exit;
}
if (!valid_email($email)) {
	log_line($logFile, "INVALID bad_email ip=$ip email=$email");
	http_response_code(400);
	echo "Please enter a valid email address.";
	exit;
}

// Build admin email
$body = "A new booking inquiry has been submitted:\n\n"
		. "Name: $name\n"
		. "Email: $email\n"
		. "Phone: $phone\n\n"
		. "Event Type: $event_type\n"
		. "Event Date: $event_date\n"
		. "Event Time: $event_time\n\n"
		. "Venue Name: $venue_name\n"
		. "Venue Location: $venue_loc\n"
		. "Guest Count: $guest_count\n"
		. "Budget Range: $budget_range\n\n"
		. "Needs: $needs_list\n\n"
		. "Vibe / Vision:\n$vibe\n\n"
		. "How they heard about Nick:\n$heard_about\n\n"
		. "Other Details:\n$other_details\n\n"
		. "--\n"
				. "Meta:\n"
						. "IP: $ip\n"
						. "UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n";
						
						$fromName  = "NickSanzeri.com Booking";
						$fromEmail = "no-reply@nicksanzeri.com";
						
						// Headers (safe)
						$headers  = "From: " . safe_header_value($fromName) . " <" . safe_header_value($fromEmail) . ">\r\n";
						$headers .= "Reply-To: " . safe_header_value($email) . "\r\n";
						$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
						
						// Send admin email
						$adminOk = @mail($adminRecipient, $adminSubject, $body, $headers);
						
						// Send confirmation email (simple)
						$confirmSubject = "Got it — your booking inquiry was received";
						$confirmBody = "Hey $name,\n\n"
						. "Thanks for reaching out — I got your booking inquiry.\n"
								. "I’ll reply as soon as I can (usually within 24 hours).\n\n"
										. "Quick summary of what you sent:\n"
												. "Event Type: $event_type\n"
												. "Event Date: $event_date\n"
												. "Event Time: $event_time\n"
												. "Location: $venue_loc\n\n"
												. "Talk soon,\n"
														. "Nick Sanzeri\n"
																. "NickSanzeri.com\n";
																
																$confirmHeaders  = "From: " . safe_header_value("Nick Sanzeri") . " <" . safe_header_value($fromEmail) . ">\r\n";
																$confirmHeaders .= "Reply-To: " . safe_header_value($adminRecipient) . "\r\n";
																$confirmHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
																
																$confirmOk = @mail($email, $confirmSubject, $confirmBody, $confirmHeaders);
																
																// Log outcome
																log_line(
																		$logFile,
																		"SUBMIT ip=$ip email=$email adminOk=" . ($adminOk ? "1" : "0") . " confirmOk=" . ($confirmOk ? "1" : "0")
																		);
																
																// Always redirect (don’t leak info to bots)
																header("Location: thank_you.html");
																exit;
<?php
// --------------------------------------------
// CONFIGURATION
// --------------------------------------------
$recipient = "nsanzeri@gmail.com";   // <-- replace with your real email
$subject   = "New Booking Inquiry from nicksanzeri.com";

// --------------------------------------------
// Basic spam trap (honeypot)
// --------------------------------------------
if (!empty($_POST["website"])) {
    // If this hidden field is filled, it's a bot
    header("Location: thank_you.html");
    exit;
}

// --------------------------------------------
// Helper to safely fetch fields
// --------------------------------------------
function field($key) {
    return isset($_POST[$key]) ? htmlspecialchars(trim($_POST[$key])) : "";
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
    die("Missing required fields. Please go back and complete all required fields.");
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
$headers = "From: Booking Form <no-reply@nicksanzeri.com>\r\n";
$headers .= "Reply-To: $email\r\n";

// --------------------------------------------
// Send the email
// --------------------------------------------
mail($recipient, $subject, $body, $headers);

// --------------------------------------------
// Redirect to Thank You page
// --------------------------------------------
header("Location: thank_you.html");
exit;
?>

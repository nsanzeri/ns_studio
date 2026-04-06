<?php
// wedding_outline_submit.php
// Patterned after booking_submit.php
// - Honeypot
// - Validation + length caps
// - Basic file-based rate limiting
// - Basic logging
// - Sends admin email + confirmation email to requester

// -----------------------------
// CONFIG
// -----------------------------
$adminRecipient = "nsanzeri@gmail.com";
$adminSubject   = "New Wedding Outline from nicksanzeri.com";

$logDir  = __DIR__ . '/_logs';
$logFile = $logDir . '/wedding_outline.log';

// Rate limit: max submissions per IP in window
$rateMax   = 5;
$rateMins  = 30;
$rateStore = $logDir . '/wedding_outline_rate.json';

// Redirect target
$thankYouUrl = "wedding_thank_you.html";

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
    $val = preg_replace('/\s+/', ' ', $val);
    if (mb_strlen($val) > $maxLen) $val = mb_substr($val, 0, $maxLen);
    $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
    return $val;
}

function valid_email($email) {
    if (!$email) return false;
    if (strlen($email) > 190) return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function safe_header_value($s) {
    return trim(str_replace(["\r", "\n"], '', (string)$s));
}

function get_ip() {
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

    if (!isset($data[$ip]) || !is_array($data[$ip])) {
        $data[$ip] = [];
    }

    $data[$ip] = array_values(array_filter($data[$ip], function($t) use ($now, $windowSeconds) {
        return is_int($t) && ($now - $t) <= $windowSeconds;
    }));

    if (count($data[$ip]) >= $max) {
        @file_put_contents($storeFile, json_encode($data));
        return false;
    }

    $data[$ip][] = $now;
    @file_put_contents($storeFile, json_encode($data));
    return true;
}

function yn($key) {
    $v = field($key, 10);
    return in_array($v, ['Yes', 'No'], true) ? $v : '';
}

function line_if_has_value($label, $value) {
    return $value !== '' ? $label . ': ' . $value . "\n" : '';
}

function section_title($title) {
    return "\n==================================================\n"
         . $title . "\n"
         . "==================================================\n";
}

// -----------------------------
// Init
// -----------------------------
ensure_dir($logDir);

// Honeypot
if (!empty($_POST["website"])) {
    log_line($logFile, "SPAM honeypot triggered ip=" . get_ip());
    header("Location: " . $thankYouUrl);
    exit;
}

// Rate limit
$ip = get_ip();
if (!rate_limit_ok($rateStore, $ip, $rateMax, $rateMins * 60)) {
    log_line($logFile, "RATE_LIMIT ip=$ip");
    header("Location: " . $thankYouUrl);
    exit;
}

// -----------------------------
// Collect top-level fields
// -----------------------------
$reception_date          = field('reception_date', 64);
$start_time              = field('start_time', 64);
$end_time                = field('end_time', 64);
$reception_location_name = field('reception_location_name', 190);
$phone_number            = field('phone_number', 64);
$address_city_state      = field('address_city_state', 255);
$specific_area           = field('specific_area', 255);
$coordinator_name        = field('coordinator_name', 190);

$bride_name              = field('bride_name', 120);
$bride_pronounced        = field('bride_pronounced', 120);
$groom_name              = field('groom_name', 120);
$groom_pronounced        = field('groom_pronounced', 120);

$bride_father            = field('bride_father', 120);
$bride_father_pronounced = field('bride_father_pronounced', 120);
$bride_mother            = field('bride_mother', 120);
$bride_mother_pronounced = field('bride_mother_pronounced', 120);

$groom_father            = field('groom_father', 120);
$groom_father_pronounced = field('groom_father_pronounced', 120);
$groom_mother            = field('groom_mother', 120);
$groom_mother_pronounced = field('groom_mother_pronounced', 120);

$guests_arrival_time     = field('guests_arrival_time', 64);
$cocktail_start_time     = field('cocktail_start_time', 64);
$dinner_start_time       = field('dinner_start_time', 64);
$dinner_live_set         = yn('dinner_live_set');

$introducing_bridal_party = yn('introducing_bridal_party');
$same_song_entire_party   = yn('same_song_entire_party');
$entire_party_song        = field('entire_party_song', 190);
$entire_party_artist      = field('entire_party_artist', 190);

$fog_name                = field('fog_name', 120);
$fog_pronounced          = field('fog_pronounced', 120);
$mog_name                = field('mog_name', 120);
$mog_pronounced          = field('mog_pronounced', 120);
$groom_parents_song      = field('groom_parents_song', 190);
$groom_parents_artist    = field('groom_parents_artist', 190);

$fob_name                = field('fob_name', 120);
$fob_pronounced          = field('fob_pronounced', 120);
$mob_name                = field('mob_name', 120);
$mob_pronounced          = field('mob_pronounced', 120);
$bride_parents_song      = field('bride_parents_song', 190);
$bride_parents_artist    = field('bride_parents_artist', 190);

$announcement_wording    = field('announcement_wording', 2000);
$couple_intro_song       = field('couple_intro_song', 190);
$couple_intro_artist     = field('couple_intro_artist', 190);

// $speeches_notes          = field('speeches_notes', 3000);

$contact_name            = field('contact_name', 120);
$contact_email           = field('contact_email', 190);

// -----------------------------
// Validation
// -----------------------------
if (!$contact_name || !$contact_email) {
    log_line($logFile, "INVALID missing_required ip=$ip contact_name=" . ($contact_name ?: '-') . " email=" . ($contact_email ?: '-'));
    http_response_code(400);
    echo "Missing required fields. Please go back and complete your name and email.";
    exit;
}

if (!valid_email($contact_email)) {
    log_line($logFile, "INVALID bad_email ip=$ip email=$contact_email");
    http_response_code(400);
    echo "Please enter a valid email address.";
    exit;
}

// -----------------------------
// Build email body
// -----------------------------
$body  = "A new wedding outline has been submitted.\n";

$body .= section_title("CONTACT");
$body .= line_if_has_value("Name", $contact_name);
$body .= line_if_has_value("Email", $contact_email);

$body .= section_title("GENERAL INFO");
$body .= line_if_has_value("Reception Date", $reception_date);
$body .= line_if_has_value("Start Time", $start_time);
$body .= line_if_has_value("End Time", $end_time);
$body .= line_if_has_value("Reception Location Name", $reception_location_name);
$body .= line_if_has_value("Address (City State)", $address_city_state);
$body .= line_if_has_value("Specific room / hall / pavilion area", $specific_area);
$body .= line_if_has_value("Phone Number", $phone_number);
$body .= line_if_has_value("Banquet Manager / Coordinator / Contact", $coordinator_name);

$body .= section_title("KEY PEOPLE - NAMES");
$body .= line_if_has_value("Bride's Name", $bride_name);
$body .= line_if_has_value("Bride's Name Pronounced", $bride_pronounced);
$body .= line_if_has_value("Groom's Name", $groom_name);
$body .= line_if_has_value("Groom's Name Pronounced", $groom_pronounced);

$body .= line_if_has_value("Bride's Father", $bride_father);
$body .= line_if_has_value("Bride's Father Pronounced", $bride_father_pronounced);
$body .= line_if_has_value("Bride's Mother", $bride_mother);
$body .= line_if_has_value("Bride's Mother Pronounced", $bride_mother_pronounced);

$body .= line_if_has_value("Groom's Father", $groom_father);
$body .= line_if_has_value("Groom's Father Pronounced", $groom_father_pronounced);
$body .= line_if_has_value("Groom's Mother", $groom_mother);
$body .= line_if_has_value("Groom's Mother Pronounced", $groom_mother_pronounced);

$body .= section_title("RECEPTION");
$body .= line_if_has_value("Guests Arrival Time", $guests_arrival_time);
$body .= line_if_has_value("Cocktail Start Time", $cocktail_start_time);
$body .= line_if_has_value("Dinner Start Time", $dinner_start_time);
$body .= line_if_has_value("40 minute live set during dinner", $dinner_live_set);

$body .= section_title("BRIDAL PARTY INTRODUCTION");
$body .= line_if_has_value("Is Nick introducing the Bridal Party?", $introducing_bridal_party);
$body .= line_if_has_value("Same song for entire Bridal Party?", $same_song_entire_party);
$body .= line_if_has_value("Entire Bridal Party Song", $entire_party_song);
$body .= line_if_has_value("Entire Bridal Party Artist", $entire_party_artist);

// Bridal party pairs
for ($i = 1; $i <= 8; $i++) {
    $bridesmaid   = field("bridesmaid_$i", 120);
    $bridesmaid_p = field("bridesmaid_{$i}_pronounced", 120);
    $groomsman    = field("groomsman_$i", 120);
    $groomsman_p  = field("groomsman_{$i}_pronounced", 120);
    $pair_song    = field("pair_{$i}_song", 190);
    $pair_artist  = field("pair_{$i}_artist", 190);

    if ($bridesmaid || $bridesmaid_p || $groomsman || $groomsman_p || $pair_song || $pair_artist) {
        $body .= "\n-- Bridal Party Pair $i --\n";
        $body .= line_if_has_value("Bridesmaid", $bridesmaid);
        $body .= line_if_has_value("Bridesmaid Pronounced", $bridesmaid_p);
        $body .= line_if_has_value("Groomsman", $groomsman);
        $body .= line_if_has_value("Groomsman Pronounced", $groomsman_p);
        $body .= line_if_has_value("Introduced to Song", $pair_song);
        $body .= line_if_has_value("Artist", $pair_artist);
    }
}

$body .= "\n-- Groom's Parents Introduction --\n";
$body .= line_if_has_value("Father of the Groom", $fog_name);
$body .= line_if_has_value("Father of the Groom Pronounced", $fog_pronounced);
$body .= line_if_has_value("Mother of the Groom", $mog_name);
$body .= line_if_has_value("Mother of the Groom Pronounced", $mog_pronounced);
$body .= line_if_has_value("Introduced to Song", $groom_parents_song);
$body .= line_if_has_value("Artist", $groom_parents_artist);

$body .= "\n-- Bride's Parents Introduction --\n";
$body .= line_if_has_value("Father of the Bride", $fob_name);
$body .= line_if_has_value("Father of the Bride Pronounced", $fob_pronounced);
$body .= line_if_has_value("Mother of the Bride", $mob_name);
$body .= line_if_has_value("Mother of the Bride Pronounced", $mob_pronounced);
$body .= line_if_has_value("Introduced to Song", $bride_parents_song);
$body .= line_if_has_value("Artist", $bride_parents_artist);

$body .= "\n-- Bride and Groom --\n";
$body .= line_if_has_value("How would they like to be announced?", $announcement_wording);
$body .= line_if_has_value("Introduced to Song", $couple_intro_song);
$body .= line_if_has_value("Artist", $couple_intro_artist);

// Dances
$body .= section_title("FIRST DANCE / OTHER TRADITIONAL DANCES");
$danceRows = [
    'bride_groom'    => 'Bride and Groom',
    'father_daughter'=> 'Father / Daughter',
    'mother_son'     => 'Mother / Son',
    'bridal_party'   => 'Bridal Party',
    'other_special'  => 'Other Special Dance',
];

foreach ($danceRows as $key => $label) {
    $yesno  = yn("{$key}_yesno");
    $song   = field("{$key}_song", 190);
    $artist = field("{$key}_artist", 190);

    if ($yesno || $song || $artist) {
        $body .= "\n-- $label --\n";
        $body .= line_if_has_value("Include this?", $yesno);
        $body .= line_if_has_value("Song", $song);
        $body .= line_if_has_value("Artist", $artist);
    }
}

// Special music
$body .= section_title("OTHER SPECIAL MUSIC");
$specialRows = [
    'cake_cutting'   => 'Cake Cutting',
    'bouquet_toss'   => 'Bouquet Toss',
    'garter_removal' => 'Garter Removal',
];

foreach ($specialRows as $key => $label) {
    $yesno      = yn("{$key}_yesno");
    $song       = field("{$key}_song", 190);
    $artist     = field("{$key}_artist", 190);
    $letNick    = yn("{$key}_let_nick_decide");

    if ($yesno || $song || $artist || $letNick) {
        $body .= "\n-- $label --\n";
        $body .= line_if_has_value("Include this?", $yesno);
        $body .= line_if_has_value("Song", $song);
        $body .= line_if_has_value("Artist", $artist);
        $body .= line_if_has_value("Let Nick decide?", $letNick);
    }
}

// Special requests
$body .= section_title("SPECIAL REQUESTS");
for ($i = 1; $i <= 5; $i++) {
    $song   = field("request_song_$i", 190);
    $artist = field("request_artist_$i", 190);

    if ($song || $artist) {
        $body .= "Request $i:\n";
        $body .= line_if_has_value("Song", $song);
        $body .= line_if_has_value("Artist", $artist);
        $body .= "\n";
    }
}

//$body .= section_title("SPEECHES");
//$body .= line_if_has_value("Notes", $speeches_notes);

$body .= section_title("META");
$body .= "IP: $ip\n";
$body .= "UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n";

// -----------------------------
// Send emails
// -----------------------------
$fromName  = "NickSanzeri.com Wedding";
$fromEmail = "no-reply@nicksanzeri.com";

$headers  = "From: " . safe_header_value($fromName) . " <" . safe_header_value($fromEmail) . ">\r\n";
$headers .= "Reply-To: " . safe_header_value($contact_email) . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$adminOk = @mail($adminRecipient, $adminSubject, $body, $headers);

// confirmation email
$confirmSubject = "Got it — your wedding outline was received";
$confirmBody  = "Hi $contact_name,\n\n";
$confirmBody .= "Thanks for sending over your wedding outline.\n";
$confirmBody .= "I received it and will use it to help make the night smooth, fun, and easy.\n\n";
$confirmBody .= "Quick summary:\n";
$confirmBody .= line_if_has_value("Reception Date", $reception_date);
$confirmBody .= line_if_has_value("Venue", $reception_location_name);
$confirmBody .= line_if_has_value("Bride", $bride_name);
$confirmBody .= line_if_has_value("Groom", $groom_name);
$confirmBody .= "\n";
$confirmBody .= "If anything changes, just reply to this email.\n\n";
$confirmBody .= "Thank you again — it is truly an honor.\n\n";
$confirmBody .= "Nick Sanzeri\n";
$confirmBody .= "NickSanzeri.com\n";
$confirmBody .= "224-535-0104\n";

$confirmHeaders  = "From: " . safe_header_value("Nick Sanzeri") . " <" . safe_header_value($fromEmail) . ">\r\n";
$confirmHeaders .= "Reply-To: " . safe_header_value($adminRecipient) . "\r\n";
$confirmHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";

$confirmOk = @mail($contact_email, $confirmSubject, $confirmBody, $confirmHeaders);

// Log outcome
log_line(
    $logFile,
    "SUBMIT ip=$ip email=$contact_email adminOk=" . ($adminOk ? "1" : "0") . " confirmOk=" . ($confirmOk ? "1" : "0")
);

// Redirect
header("Location: " . $thankYouUrl);
exit;
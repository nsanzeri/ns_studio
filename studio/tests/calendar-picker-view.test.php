<?php
declare(strict_types=1);

// Render the real template with fixture data, without an account or database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path = ''): string { return '/studio/' . ltrim($path, '/'); }
function csrf_token(): string { return 'fixture-csrf-token'; }
function verify_view(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$source = file_get_contents(__DIR__ . '/../tools/calendars.php');
$template = substr($source, strpos($source, '<!doctype html>'));
$template = preg_replace('/<\?php include .*?\?>/', '', $template);
$isProUser = true;
$upgradeUrl = '/upgrade';
$existingCalendarCount = 1;
$flash = null;
$errors = [];
$googleError = null;
$commonTimezones = ['America/Chicago', 'UTC'];
$formData = ['name' => '', 'ics_url' => '', 'color' => '#D4AF37', 'timezone' => '', 'is_active' => 1];
$saved = ['id' => 10, 'name' => 'Shows', 'color' => '#D4AF37', 'ics_url' => '', 'timezone' => 'America/Chicago', 'is_active' => 1, 'is_main_gig' => 1];
$googleSources = [10 => ['google_sub' => 'fixture-google-account']];

foreach (['connected', 'disconnected', 'editing', 'reconnect'] as $variant) {
    $googleConnection = in_array($variant, ['connected', 'editing'], true) ? ['email' => 'musician@example.com', 'google_sub' => 'fixture-google-account'] : null;
    $googleChoices = [['id' => 'shows@example.com', 'name' => 'Shows <Live>', 'primary' => true]];
    $editingCalendar = $variant === 'editing' ? $saved : null;
    $calendars = $variant === 'disconnected' ? [] : [$saved];
    ob_start();
    eval('?>' . $template);
    $html = ob_get_clean();
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//form') as $form) {
        verify_view($xpath->query('.//input[@name="_csrf" and @value="fixture-csrf-token"]', $form)->length === 1, 'Every calendar form needs CSRF protection');
    }
    if ($variant === 'connected') {
        verify_view($xpath->query('//select[@name="google_calendar_id"]')->length === 1, 'Connected account must show picker');
        verify_view(str_contains($html, 'Shows &lt;Live&gt;'), 'Escape Google calendar names');
        verify_view(str_contains($html, 'name="ics_url"'), 'Retain iCal option');
    } elseif ($variant === 'editing') {
        verify_view(!str_contains($html, 'name="ics_url"'), 'Google calendar edit must not require an iCal URL');
    } elseif ($variant === 'disconnected') {
        verify_view(!str_contains($html, 'name="google_calendar_id"'), 'No picker before consent');
        verify_view(str_contains($html, 'Connect Google Calendar'), 'Offer Google connection');
    } else {
        verify_view(str_contains($html, 'Reconnect its Google account'), 'Disconnected source needs visible reconnect status');
    }
    if (isset($argv[1])) {
        $css = file_get_contents(__DIR__ . '/../../assets/css/style.css');
        $html = str_replace('<link rel="stylesheet" href="/studio/../assets/css/style.css">', '<style>' . $css . '</style>', $html);
        file_put_contents(rtrim($argv[1], '/\\') . '/calendar-' . $variant . '.html', $html);
    }
}
echo "Passed: calendar picker, iCal fallback, escaped names, editing/reconnection states, and CSRF coverage.\n";

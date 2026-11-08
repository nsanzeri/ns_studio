<?php
declare(strict_types=1);

// Run with: php studio/tests/artist-calendar.test.php
// Exercise the actual profile loader without a real account or provider request.
$source = file_get_contents(__DIR__ . '/../../artist.php');
$from = strpos($source, 'function artist_ics_unfold(');
$to = strpos($source, '$isLocal =', $from);
eval(substr($source, $from, $to - $from));

function check_artist(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rss_normalize_ical_url(?string $url): string { return trim((string)$url); }
function rss_google_calendar_source(PDO $pdo, int $calendarId, int $userId): ?array {
    $GLOBALS['sourceRequests'][] = [$calendarId, $userId];
    return $GLOBALS['hasGoogleSource'] ? ['google_sub' => 'artist-account', 'google_calendar_id' => 'shows@example.com'] : null;
}
function rss_google_calendar_events(PDO $pdo, int $userId, array $source, DateTimeInterface $start, DateTimeInterface $end, DateTimeZone $tz, bool $publicOnly = false): array {
    $GLOBALS['eventRequests'][] = [$userId, $source, $start->format(DateTime::ATOM), $end->format(DateTime::ATOM), $tz->getName(), $publicOnly];
    if ($GLOBALS['providerUnavailable']) throw new RuntimeException('fixture-provider-error-with-secret');
    return $GLOBALS['fixtureEvents'];
}

$pdo = new PDO('sqlite::memory:');
$calendar = ['id' => 10, 'user_id' => 123, 'ics_url' => '', 'timezone' => 'America/Chicago'];
$sourceRequests = [];
$eventRequests = [];
$hasGoogleSource = true;
$providerUnavailable = false;
$fixtureEvents = [];
for ($day = 15; $day >= 1; $day--) {
    $fixtureEvents[] = [
        'start' => sprintf('2026-11-%02dT19:00:00-06:00', $day),
        'end' => sprintf('2026-11-%02dT21:00:00-06:00', $day),
        'summary' => 'Public show ' . $day, 'location' => 'Music Hall',
        'uid' => 'internal-uid', 'description' => 'Internal notes',
    ];
}

$_SESSION = []; // Public visitor, with no Google login.
$events = artist_fetch_calendar_events($pdo, $calendar, '2026-11-01', '2027-05-01');
check_artist($sourceRequests === [[10, 123]], 'Look up the artist owner rather than a visitor');
check_artist(count($events) === 10 && $events[0]['summary'] === 'Public show 1' && $events[9]['summary'] === 'Public show 10', 'Show the next ten events in chronological order');
check_artist($eventRequests[0][0] === 123 && $eventRequests[0][5] === true, 'Use owner credentials and public filtering');
check_artist($eventRequests[0][2] === '2026-11-01T00:00:00-05:00' && $eventRequests[0][3] === '2027-05-01T23:59:59-05:00', 'Keep the selected window and artist timezone');
check_artist(array_keys($events[0]) === ['start', 'end', 'summary', 'location'], 'Return only approved public show fields');

$_SESSION = ['studio_user_id' => 999]; // A signed-in visitor still uses the artist's account.
artist_fetch_calendar_events($pdo, $calendar, '2026-11-01', '2027-05-01');
check_artist($eventRequests[1][0] === 123, 'Visitor login must not change the event source');

$providerUnavailable = true;
$log = tempnam(sys_get_temp_dir(), 'artist-calendar-test-');
$previousLog = ini_get('error_log');
ini_set('error_log', $log);
try {
    check_artist(artist_fetch_calendar_events($pdo, $calendar, '2026-11-01', '2027-05-01') === [], 'Revoked access must not break a public profile');
    check_artist(!str_contains(file_get_contents($log), 'fixture-provider-error-with-secret'), 'Do not log raw provider errors');
} finally {
    ini_set('error_log', $previousLog);
    unlink($log);
}

$hasGoogleSource = false;
$requestsBefore = count($eventRequests);
check_artist(artist_fetch_calendar_events($pdo, $calendar, '2026-11-01', '2027-05-01') === [], 'Legacy calendars still use the iCal validation path');
check_artist(count($eventRequests) === $requestsBefore, 'Do not request Google events for an iCal source');
echo "Passed: public and signed-in visitors use the artist account, public filtering requested, chronological top ten, approved fields, provider failure, and iCal fallback.\n";

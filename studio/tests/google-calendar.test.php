<?php
declare(strict_types=1);

// Run with: php studio/tests/google-calendar.test.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_private/_core/google_calendar.php';

function env(string $name, $default = null) {
    return ['GOOGLE_CLIENT_ID' => 'fixture-id', 'GOOGLE_CLIENT_SECRET' => 'fixture-secret'][$name] ?? $default;
}
function verify(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function fails(callable $action): void {
    try { $action(); } catch (RSSGoogleCalendarException $e) { return; }
    throw new RuntimeException('Expected a safe Google Calendar error');
}

// Use real SQL for ownership checks, with only the metadata query adapted for SQLite.
class CalendarTestPDO extends PDO {
    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        if (str_contains($query, 'information_schema.tables')) {
            return parent::query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('google_calendar_connections', 'google_calendar_sources')");
        }
        return parent::query($query);
    }
}

$pdo = new CalendarTestPDO();
verify(!rss_google_calendar_ready($pdo), 'iCal-only installations must work before migration');
verify(rss_google_calendar_connection($pdo, 1) === null, 'No Google connection before migration');
$pdo->exec('CREATE TABLE google_calendar_connections (user_id INTEGER PRIMARY KEY, google_sub TEXT, email TEXT, token_ciphertext TEXT)');
$pdo->exec('CREATE TABLE calendars (id INTEGER PRIMARY KEY, user_id INTEGER)');
$pdo->exec('CREATE TABLE google_calendar_sources (calendar_id INTEGER PRIMARY KEY, google_sub TEXT, google_calendar_id TEXT)');
$pdo->exec("INSERT INTO calendars VALUES (10, 1), (20, 2)");
$pdo->exec("INSERT INTO google_calendar_sources VALUES (10, 'sub-1', 'gigs@example.com'), (20, 'sub-2', 'private@example.com')");
verify(rss_google_calendar_source($pdo, 10, 1)['google_calendar_id'] === 'gigs@example.com', 'Owned Google calendar');
verify(rss_google_calendar_source($pdo, 20, 1) === null, 'Other user calendar must be inaccessible');
verify(rss_google_calendar_source($pdo, 10, 2) === null, 'Source lookup requires matching owner');

$token = ['access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh', 'expires_in' => 3600, 'created' => time()];
$encrypted = rss_google_calendar_encrypt($token, 1);
verify(!str_contains($encrypted, 'fixture-access'), 'Token encrypted at rest');
verify(rss_google_calendar_decrypt($encrypted, 1) === $token, 'Encrypted token round trip');
fails(static fn() => rss_google_calendar_decrypt($encrypted, 2));
$tampered = base64_decode($encrypted);
$tampered[30] = chr(ord($tampered[30]) ^ 1);
fails(static fn() => rss_google_calendar_decrypt(base64_encode($tampered), 1));
fails(static fn() => rss_google_calendar_service($pdo, 1));
$pdo->prepare('INSERT INTO google_calendar_connections VALUES (?, ?, ?, ?)')->execute([1, 'sub-1', 'fixture@example.com', $encrypted]);
fails(static fn() => rss_google_calendar_service($pdo, 1, 'other-google-account'));
fails(static fn() => rss_google_calendar_service($pdo, 2));
verify(rss_google_calendar_service($pdo, 1, 'sub-1') instanceof Google_Service_Calendar, 'Valid saved connection');
$expired = rss_google_calendar_encrypt(['access_token' => 'old', 'created' => 1, 'expires_in' => 1], 1);
$pdo->prepare('UPDATE google_calendar_connections SET token_ciphertext = ? WHERE user_id = 1')->execute([$expired]);
fails(static fn() => rss_google_calendar_service($pdo, 1));

$tz = new DateTimeZone('America/Chicago');
$options = rss_google_calendar_event_options(new DateTime('2026-10-28', $tz), new DateTime('2026-11-28 23:59:59', $tz), $tz);
verify($options['timeMin'] === '2026-10-28T00:00:00-05:00', 'Start of selected first day');
verify($options['timeMax'] === '2026-11-29T00:00:00-06:00', 'Exclusive end includes entire last day across DST');
verify($options['singleEvents'] === true && $options['showDeleted'] === false, 'Provider expands recurrence and excludes cancellations');
fails(static fn() => rss_google_calendar_event_options(new DateTime('2026-11-29', $tz), new DateTime('2026-11-28', $tz), $tz));

$timed = new Google_Service_Calendar_Event([
    'id' => 'event-1', 'iCalUID' => 'uid-1', 'summary' => 'Show', 'location' => 'Music Hall',
    'start' => ['dateTime' => '2026-11-10T01:30:00Z'], 'end' => ['dateTime' => '2026-11-10T03:30:00Z'],
]);
$event = rss_google_calendar_normalize_event($timed, $tz);
verify($event['start'] === '2026-11-09T19:30:00-06:00' && $event['uid'] === 'uid-1', 'Timed event and iCal UID preserved');
$zoned = new Google_Service_Calendar_Event(['id' => 'zoned', 'start' => ['dateTime' => '2026-11-10T19:30:00', 'timeZone' => 'America/Los_Angeles'], 'end' => ['dateTime' => '2026-11-10T21:30:00', 'timeZone' => 'America/Los_Angeles']]);
verify(rss_google_calendar_normalize_event($zoned, $tz)['start'] === '2026-11-10T21:30:00-06:00', 'Provider timezone used when no offset is supplied');
$allDay = new Google_Service_Calendar_Event(['id' => 'all-day', 'start' => ['date' => '2026-11-10'], 'end' => ['date' => '2026-11-13']]);
$event = rss_google_calendar_normalize_event($allDay, $tz);
verify($event['start'] === '2026-11-10T00:00:00-06:00' && $event['end'] === '2026-11-12T23:59:59-06:00', 'Multi-day exclusive end converted for app');
$timed->setStatus('cancelled');
verify(rss_google_calendar_normalize_event($timed, $tz) === null, 'Cancelled instance skipped');

// Exercise the actual Google SDK with mocked HTTP responses, without live credentials.
$history = [];
$mock = new GuzzleHttp\Handler\MockHandler([
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode(['items' => [], 'nextPageToken' => 'next'])),
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode(['items' => [['id' => 'fixture', 'summary' => 'Shows']]])),
]);
$stack = GuzzleHttp\HandlerStack::create($mock);
$stack->push(GuzzleHttp\Middleware::history($history));
$client = rss_google_calendar_client();
$client->setAccessToken($token);
$client->setHttpClient(new GuzzleHttp\Client(['handler' => $stack]));
$service = new Google_Service_Calendar($client);
$items = rss_google_calendar_pages(static function ($pageToken) use ($service, $options) {
    if ($pageToken) $options['pageToken'] = $pageToken;
    return $service->events->listEvents('gigs@example.com', $options);
});
verify(count($items) === 1 && count($history) === 2, 'Empty intermediate pages must not truncate results');
parse_str($history[1]['request']->getUri()->getQuery(), $sent);
verify($sent['pageToken'] === 'next' && $sent['timeMin'] === $options['timeMin'] && $sent['timeMax'] === $options['timeMax'], 'Date range retained on every API page');
verify($mock->getLastRequest()->getHeaderLine('Authorization') === 'Bearer fixture-access', 'SDK sends authorization header');

$page = new Google_Service_Calendar_Events(['nextPageToken' => 'repeated']);
fails(static fn() => rss_google_calendar_pages(static fn($pageToken) => $page));
echo "Passed: ownership, encrypted credentials, account isolation, expired access, date boundaries/DST, all-day events, cancellations, and paginated Google SDK requests.\n";

<?php
declare(strict_types=1);

class RSSGoogleCalendarException extends RuntimeException {}

const RSS_GOOGLE_CALENDAR_SCOPES = [
    'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    'https://www.googleapis.com/auth/calendar.events.readonly',
];

function rss_google_calendar_ready(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('google_calendar_connections', 'google_calendar_sources')");
    return (int)$stmt->fetchColumn() === 2;
}

function rss_google_calendar_ensure_tables(PDO $pdo): void
{
    if (!rss_google_calendar_ready($pdo)) {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/migrations/039_google_calendars.sql');
        $pdo->exec($sql);
    }
}

function rss_google_calendar_client(?string $redirectUri = null): Google_Client
{
    $clientId = (string)env('GOOGLE_CLIENT_ID', '');
    $secret = (string)env('GOOGLE_CLIENT_SECRET', '');
    if ($clientId === '' || $secret === '') {
        throw new RSSGoogleCalendarException('Google Calendar is not configured yet. You can still add an iCal link.');
    }
    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($secret);
    $client->setHttpClient(new \GuzzleHttp\Client(['timeout' => 60, 'connect_timeout' => 10]));
    if ($redirectUri !== null) {
        $client->setRedirectUri($redirectUri);
    }
    return $client;
}

/** Bind encrypted tokens to the app user; use a dedicated key when configured. */
function rss_google_calendar_token_key(): string
{
    $secret = (string)env('GOOGLE_CALENDAR_TOKEN_KEY', env('GOOGLE_CLIENT_SECRET', ''));
    if ($secret === '') {
        throw new RSSGoogleCalendarException('Google Calendar token storage is not configured.');
    }
    return hash('sha256', 'rss-google-calendar-v1|' . $secret, true);
}

function rss_google_calendar_encrypt(array $token, int $userId): string
{
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt(json_encode($token, JSON_THROW_ON_ERROR), 'aes-256-gcm', rss_google_calendar_token_key(), OPENSSL_RAW_DATA, $iv, $tag, (string)$userId);
    if ($encrypted === false) {
        throw new RSSGoogleCalendarException('Could not save the Google Calendar connection.');
    }
    return base64_encode($iv . $tag . $encrypted);
}

function rss_google_calendar_decrypt(string $encrypted, int $userId): array
{
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RSSGoogleCalendarException('Please reconnect Google Calendar.');
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', rss_google_calendar_token_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), (string)$userId);
    if ($plain === false) {
        throw new RSSGoogleCalendarException('Please reconnect Google Calendar.');
    }
    return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
}

function rss_google_calendar_connection(PDO $pdo, int $userId): ?array
{
    if (!rss_google_calendar_ready($pdo)) return null;
    $stmt = $pdo->prepare('SELECT * FROM google_calendar_connections WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function rss_google_calendar_save_connection(PDO $pdo, int $userId, Google_Client $client, array $token): void
{
    set_time_limit(150);
    $granted = preg_split('/\s+/', (string)($token['scope'] ?? ''));
    if (empty($token['access_token']) || array_diff(RSS_GOOGLE_CALENDAR_SCOPES, $granted)) {
        throw new RSSGoogleCalendarException('Please allow both calendar list and event read access to connect Google Calendar.');
    }
    $client->setAccessToken($token);
    $identity = (new Google_Service_Oauth2($client))->userinfo->get();
    $sub = (string)$identity->getId();
    $email = (string)$identity->getEmail();
    if ($sub === '' || $email === '') throw new RSSGoogleCalendarException('Google did not return an account identity.');
    $existing = rss_google_calendar_connection($pdo, $userId);
    if (empty($token['refresh_token']) && $existing && $existing['google_sub'] === $sub) {
        $previous = rss_google_calendar_decrypt($existing['token_ciphertext'], $userId);
        $token['refresh_token'] = $previous['refresh_token'] ?? null;
    }
    if (empty($token['refresh_token'])) {
        throw new RSSGoogleCalendarException('Google did not grant ongoing access. Please reconnect Google Calendar and allow access.');
    }
    $pdo->prepare('INSERT INTO google_calendar_connections (user_id, google_sub, email, token_ciphertext) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE google_sub = VALUES(google_sub), email = VALUES(email), token_ciphertext = VALUES(token_ciphertext)')
        ->execute([$userId, $sub, $email, rss_google_calendar_encrypt($token, $userId)]);
}

function rss_google_calendar_service(PDO $pdo, int $userId, ?string $expectedSub = null): Google_Service_Calendar
{
    $connection = rss_google_calendar_connection($pdo, $userId);
    if (!$connection || ($expectedSub !== null && $connection['google_sub'] !== $expectedSub)) {
        throw new RSSGoogleCalendarException('Please reconnect the Google account used for this calendar in Manage Calendars.');
    }
    $client = rss_google_calendar_client();
    $token = rss_google_calendar_decrypt($connection['token_ciphertext'], $userId);
    $client->setAccessToken($token);
    if ($client->isAccessTokenExpired()) {
        if (empty($token['refresh_token'])) {
            throw new RSSGoogleCalendarException('Please reconnect Google Calendar in Manage Calendars.');
        }
        try {
            $fresh = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
        } catch (Throwable $e) {
            throw new RSSGoogleCalendarException('Could not refresh Google Calendar access. Please reconnect in Manage Calendars.');
        }
        if (empty($fresh['access_token']) || !empty($fresh['error'])) {
            throw new RSSGoogleCalendarException('Google Calendar access has expired. Please reconnect in Manage Calendars.');
        }
        $token = array_merge($token, $fresh);
        $client->setAccessToken($token);
        $pdo->prepare('UPDATE google_calendar_connections SET token_ciphertext = ? WHERE user_id = ? AND google_sub = ?')
            ->execute([rss_google_calendar_encrypt($token, $userId), $userId, $connection['google_sub']]);
    }
    return new Google_Service_Calendar($client);
}

/** Follow every page, including empty pages with a continuation token. */
function rss_google_calendar_pages(callable $fetch): array
{
    $items = [];
    $pageToken = null;
    $seen = [];
    do {
        $page = $fetch($pageToken);
        foreach ($page->getItems() ?? [] as $item) $items[] = $item;
        $pageToken = $page->getNextPageToken();
        if ($pageToken) {
            if (isset($seen[$pageToken])) throw new RSSGoogleCalendarException('Google Calendar returned an invalid continuation token.');
            $seen[$pageToken] = true;
        }
    } while ($pageToken);
    return $items;
}

function rss_google_calendar_list(PDO $pdo, int $userId): array
{
    set_time_limit(180);
    $service = rss_google_calendar_service($pdo, $userId);
    try {
        $entries = rss_google_calendar_pages(static function ($pageToken) use ($service) {
            $options = ['maxResults' => 250, 'minAccessRole' => 'reader', 'showHidden' => true];
            if ($pageToken) $options['pageToken'] = $pageToken;
            return $service->calendarList->listCalendarList($options);
        });
        $result = [];
        foreach ($entries as $entry) {
            if ($entry->getDeleted()) continue;
            $result[] = [
                'id' => $entry->getId(),
                'name' => $entry->getSummaryOverride() ?: ($entry->getSummary() ?: 'Google Calendar'),
                'timezone' => $entry->getTimeZone(),
                'color' => $entry->getBackgroundColor(),
                'primary' => (bool)$entry->getPrimary(),
            ];
        }
        return $result;
    } catch (Throwable $e) {
        throw new RSSGoogleCalendarException('Could not list Google calendars. Please reconnect or try again shortly.');
    }
}

function rss_google_calendar_source(PDO $pdo, int $calendarId, int $userId): ?array
{
    if (!rss_google_calendar_ready($pdo)) return null;
    $stmt = $pdo->prepare('SELECT s.* FROM google_calendar_sources s JOIN calendars c ON c.id = s.calendar_id WHERE c.id = ? AND c.user_id = ?');
    $stmt->execute([$calendarId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Google uses an exclusive end bound. Expand recurrence only in this window. */
function rss_google_calendar_event_options(DateTimeInterface $start, DateTimeInterface $end, DateTimeZone $tz): array
{
    if ($end < $start) throw new RSSGoogleCalendarException('The end date must be on or after the start date.');
    return [
        'timeMin' => (new DateTimeImmutable($start->format('Y-m-d'), $tz))->format(DateTime::ATOM),
        'timeMax' => (new DateTimeImmutable($end->format('Y-m-d'), $tz))->modify('+1 day')->format(DateTime::ATOM),
        'timeZone' => $tz->getName(),
        'singleEvents' => true,
        'showDeleted' => false,
        'maxResults' => 2500,
    ];
}

function rss_google_calendar_normalize_event(Google_Service_Calendar_Event $event, DateTimeZone $tz): ?array
{
    if ($event->getStatus() === 'cancelled') return null;
    $start = $event->getStart();
    $end = $event->getEnd();
    if (!$start || !$end) return null;
    if ($start->getDate()) {
        $from = new DateTimeImmutable($start->getDate(), $tz);
        // Keep the existing app's inclusive end representation for all-day events.
        $to = (new DateTimeImmutable($end->getDate(), $tz))->modify('-1 second');
    } else {
        if (!$start->getDateTime() || !$end->getDateTime()) return null;
        $from = (new DateTimeImmutable($start->getDateTime(), new DateTimeZone($start->getTimeZone() ?: $tz->getName())))->setTimezone($tz);
        $to = (new DateTimeImmutable($end->getDateTime(), new DateTimeZone($end->getTimeZone() ?: $tz->getName())))->setTimezone($tz);
    }
    return [
        'uid' => $event->getICalUID() ?: $event->getId(),
        'start' => $from->format(DateTime::ATOM),
        'end' => $to->format(DateTime::ATOM),
        'summary' => $event->getSummary() ?: 'Untitled event',
        'location' => $event->getLocation() ?? '',
        'description' => $event->getDescription() ?? '',
    ];
}

function rss_google_calendar_events(PDO $pdo, int $userId, array $source, DateTimeInterface $start, DateTimeInterface $end, DateTimeZone $tz): array
{
    $options = rss_google_calendar_event_options($start, $end, $tz);
    $service = rss_google_calendar_service($pdo, $userId, $source['google_sub']);
    try {
        $entries = rss_google_calendar_pages(static function ($pageToken) use ($service, $source, $options) {
            if ($pageToken) $options['pageToken'] = $pageToken;
            return $service->events->listEvents($source['google_calendar_id'], $options);
        });
        $result = [];
        foreach ($entries as $entry) {
            $event = rss_google_calendar_normalize_event($entry, $tz);
            if ($event !== null) $result[] = $event;
        }
        return $result;
    } catch (Throwable $e) {
        throw new RSSGoogleCalendarException('Could not load Google Calendar events. Please check calendar access or reconnect in Manage Calendars.');
    }
}

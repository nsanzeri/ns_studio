<?php
/**
 * Drop-in replacement: about.php
 *
 * Proof-first About page for NickSanzeri.com.
 * Pulls Nick's public Google Calendar ICS feed, calculates rolling counters,
 * and uses selected testimonials as trust/proof instead of a resume-style bio.
 */
declare(strict_types=1);

$publicCalendarIcsUrl = 'https://calendar.google.com/calendar/ical/pjjfdgelvdjtuvrr89tun3nu7k%40group.calendar.google.com/public/basic.ics';
$calendarTimezone = 'America/Chicago';
$cacheFile = __DIR__ . '/cache/about_calendar_stats.json';
$cacheTtlSeconds = 6 * 60 * 60;

function ns_about_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ns_about_unfold_ics(string $raw): array
{
    $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
    $out = [];

    foreach ($lines as $line) {
        if ($out && (strpos($line, ' ') === 0 || strpos($line, "\t") === 0)) {
            $out[count($out) - 1] .= ltrim($line);
        } else {
            $out[] = $line;
        }
    }

    return $out;
}

function ns_about_ics_value(string $line): array
{
    [$rawKey, $value] = explode(':', $line, 2);
    $keyParts = explode(';', $rawKey);
    $key = strtoupper($keyParts[0]);
    $params = [];

    foreach (array_slice($keyParts, 1) as $part) {
        if (strpos($part, '=') !== false) {
            [$paramKey, $paramValue] = explode('=', $part, 2);
            $params[strtoupper($paramKey)] = trim($paramValue, '"');
        }
    }

    return [$key, $value, $params];
}

function ns_about_unescape_ics_text(string $value): string
{
    $value = str_replace(['\\n', '\\N'], ' ', $value);
    $value = str_replace(['\\,', '\\;', '\\\\'], [',', ';', '\\'], $value);
    return trim(preg_replace('/\s+/', ' ', $value) ?: $value);
}

function ns_about_parse_ics_datetime(string $raw, array $params, DateTimeZone $defaultTz): DateTime
{
    $raw = trim($raw);
    $tz = $defaultTz;

    if (!empty($params['TZID'])) {
        try {
            $tz = new DateTimeZone($params['TZID']);
        } catch (Throwable $e) {
            $tz = $defaultTz;
        }
    }

    if (($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $raw)) {
        return DateTime::createFromFormat('!Ymd', $raw, $tz) ?: new DateTime($raw, $tz);
    }

    if (str_ends_with($raw, 'Z')) {
        $dt = DateTime::createFromFormat('Ymd\THis\Z', $raw, new DateTimeZone('UTC')) ?: new DateTime($raw, new DateTimeZone('UTC'));
        return $dt->setTimezone($defaultTz);
    }

    return DateTime::createFromFormat('Ymd\THis', $raw, $tz) ?: new DateTime($raw, $tz);
}

function ns_about_parse_exdates(array $event, DateTimeZone $tz): array
{
    $dates = [];

    foreach (($event['EXDATE'] ?? []) as $exdateLine) {
        $params = $exdateLine['params'] ?? [];
        foreach (explode(',', (string)($exdateLine['value'] ?? '')) as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            try {
                $dt = ns_about_parse_ics_datetime($raw, $params, $tz);
                $dates[$dt->format('Y-m-d')] = true;
            } catch (Throwable $e) {
            }
        }
    }

    return $dates;
}

function ns_about_expand_rrule_event(array $event, DateTime $rangeStart, DateTime $rangeEnd, DateTimeZone $tz): array
{
    $results = [];

    if (empty($event['DTSTART']['value']) || empty($event['RRULE']['value'])) {
        return $results;
    }

    try {
        $baseStart = ns_about_parse_ics_datetime($event['DTSTART']['value'], $event['DTSTART']['params'] ?? [], $tz);
        $baseEnd = !empty($event['DTEND']['value'])
            ? ns_about_parse_ics_datetime($event['DTEND']['value'], $event['DTEND']['params'] ?? [], $tz)
            : clone $baseStart;
    } catch (Throwable $e) {
        return $results;
    }

    $duration = max(0, $baseEnd->getTimestamp() - $baseStart->getTimestamp());
    $allDay = preg_match('/^\d{8}$/', (string)$event['DTSTART']['value']) === 1;

    $rr = [];
    foreach (explode(';', (string)$event['RRULE']['value']) as $chunk) {
        if (strpos($chunk, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $chunk, 2);
        $rr[strtoupper($k)] = strtoupper($v);
    }

    $freq = $rr['FREQ'] ?? 'WEEKLY';
    $interval = isset($rr['INTERVAL']) ? max(1, (int)$rr['INTERVAL']) : 1;
    $countLimit = isset($rr['COUNT']) ? max(1, (int)$rr['COUNT']) : null;
    $until = clone $rangeEnd;

    if (!empty($rr['UNTIL'])) {
        try {
            $until = ns_about_parse_ics_datetime($rr['UNTIL'], [], $tz);
        } catch (Throwable $e) {
            $until = clone $rangeEnd;
        }
    }

    if ($until > $rangeEnd) {
        $until = clone $rangeEnd;
    }

    $summary = ns_about_unescape_ics_text((string)($event['SUMMARY']['value'] ?? 'No Summary'));
    $location = ns_about_unescape_ics_text((string)($event['LOCATION']['value'] ?? ''));
    $description = ns_about_unescape_ics_text((string)($event['DESCRIPTION']['value'] ?? ''));
    $exdates = ns_about_parse_exdates($event, $tz);
    $generated = 0;

    $pushOccurrence = function (DateTime $startOcc) use (&$results, &$generated, $countLimit, $duration, $allDay, $summary, $location, $description, $rangeStart, $rangeEnd, $exdates): bool {
        if ($countLimit !== null && $generated >= $countLimit) {
            return false;
        }

        $generated++;

        if (isset($exdates[$startOcc->format('Y-m-d')])) {
            return true;
        }

        $occStart = clone $startOcc;
        $occEnd = clone $startOcc;

        if ($allDay) {
            $occStart->setTime(0, 0, 0);
            $occEnd->setTime(23, 59, 59);
        } else {
            $occEnd->modify('+' . $duration . ' seconds');
        }

        if ($occEnd >= $rangeStart && $occStart <= $rangeEnd) {
            $results[] = [
                'start' => $occStart,
                'end' => $occEnd,
                'summary' => $summary,
                'location' => $location,
                'description' => $description,
            ];
        }

        return true;
    };

    if ($freq === 'DAILY') {
        for ($d = clone $baseStart; $d <= $until; $d->modify('+' . $interval . ' day')) {
            if (!$pushOccurrence($d)) {
                break;
            }
        }
    } elseif ($freq === 'WEEKLY') {
        $dowMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $bydays = !empty($rr['BYDAY']) ? explode(',', $rr['BYDAY']) : [strtoupper(substr($baseStart->format('D'), 0, 2))];

        for ($d = clone $baseStart; $d <= $until; $d->modify('+1 day')) {
            $daysDiff = (int)floor(($d->getTimestamp() - $baseStart->getTimestamp()) / 86400);
            if ($daysDiff < 0) {
                continue;
            }
            $weekIndex = intdiv($daysDiff, 7);
            $dow2 = array_search((int)$d->format('w'), $dowMap, true);

            if ($weekIndex % $interval === 0 && in_array($dow2, $bydays, true)) {
                $occ = clone $d;
                $occ->setTime((int)$baseStart->format('H'), (int)$baseStart->format('i'), (int)$baseStart->format('s'));
                if (!$pushOccurrence($occ)) {
                    break;
                }
            }
        }
    } elseif ($freq === 'MONTHLY') {
        $current = (clone $baseStart)->modify('first day of this month');
        $baseMonth = ((int)$baseStart->format('Y') * 12) + (int)$baseStart->format('n');

        while ($current <= $until) {
            $thisMonth = ((int)$current->format('Y') * 12) + (int)$current->format('n');
            $monthDiff = $thisMonth - $baseMonth;

            if ($monthDiff >= 0 && $monthDiff % $interval === 0) {
                $days = [];
                if (!empty($rr['BYMONTHDAY'])) {
                    foreach (explode(',', $rr['BYMONTHDAY']) as $md) {
                        $days[] = max(1, min((int)$md, (int)$current->format('t')));
                    }
                } else {
                    $days[] = min((int)$baseStart->format('j'), (int)$current->format('t'));
                }

                foreach ($days as $day) {
                    $occ = clone $current;
                    $occ->setDate((int)$current->format('Y'), (int)$current->format('n'), $day);
                    $occ->setTime((int)$baseStart->format('H'), (int)$baseStart->format('i'), (int)$baseStart->format('s'));
                    if ($occ >= $baseStart && !$pushOccurrence($occ)) {
                        break 2;
                    }
                }
            }

            $current->modify('first day of next month');
        }
    }

    return $results;
}

function ns_about_parse_ics_events(string $rawICS, DateTimeZone $tz): array
{
    $rangeStart = new DateTime('2000-01-01 00:00:00', $tz);
    $rangeEnd = new DateTime('+18 months', $tz);
    $lines = ns_about_unfold_ics($rawICS);
    $events = [];
    $event = null;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === 'BEGIN:VEVENT') {
            $event = [];
            continue;
        }

        if ($trim === 'END:VEVENT') {
            if ($event && !empty($event['DTSTART']['value'])) {
                if (!empty($event['RRULE']['value'])) {
                    $events = array_merge($events, ns_about_expand_rrule_event($event, $rangeStart, $rangeEnd, $tz));
                } else {
                    try {
                        $start = ns_about_parse_ics_datetime($event['DTSTART']['value'], $event['DTSTART']['params'] ?? [], $tz);
                        $end = !empty($event['DTEND']['value'])
                            ? ns_about_parse_ics_datetime($event['DTEND']['value'], $event['DTEND']['params'] ?? [], $tz)
                            : clone $start;

                        if (preg_match('/^\d{8}$/', (string)$event['DTSTART']['value'])) {
                            $end->setTime(23, 59, 59);
                        }

                        if ($end >= $rangeStart && $start <= $rangeEnd) {
                            $events[] = [
                                'start' => $start,
                                'end' => $end,
                                'summary' => ns_about_unescape_ics_text((string)($event['SUMMARY']['value'] ?? 'No Summary')),
                                'location' => ns_about_unescape_ics_text((string)($event['LOCATION']['value'] ?? '')),
                                'description' => ns_about_unescape_ics_text((string)($event['DESCRIPTION']['value'] ?? '')),
                            ];
                        }
                    } catch (Throwable $e) {
                    }
                }
            }

            $event = null;
            continue;
        }

        if ($event !== null && strpos($trim, ':') !== false) {
            [$key, $value, $params] = ns_about_ics_value($trim);
            if ($key === 'EXDATE') {
                $event['EXDATE'][] = ['value' => $value, 'params' => $params];
            } else {
                $event[$key] = ['value' => $value, 'params' => $params];
            }
        }
    }

    usort($events, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);
    return $events;
}

function ns_about_fetch_url(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'NickSanzeri.com About Page Calendar Stats',
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (is_string($raw) && $raw !== '' && $code >= 200 && $code < 400) {
            return $raw;
        }
    }

    $context = stream_context_create([
        'http' => ['timeout' => 12, 'user_agent' => 'NickSanzeri.com About Page Calendar Stats'],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function ns_about_get_calendar_stats(string $icsUrl, string $timezone, string $cacheFile, int $cacheTtlSeconds): array
{
    $fallback = [
        'total' => 1500,
        'private' => 300,
        'weddings' => 100,
        'upcoming' => 0,
        'fresh' => false,
        'updated' => null,
    ];

    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $cacheTtlSeconds) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['total'], $cached['private'], $cached['weddings'])) {
            return $cached + ['fresh' => true, 'updated' => date(DATE_ATOM, (int)filemtime($cacheFile))];
        }
    }

    try {
        $tz = new DateTimeZone($timezone);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('America/Chicago');
    }

    $raw = ns_about_fetch_url($icsUrl);
    if (!$raw) {
        return $fallback;
    }

    $events = ns_about_parse_ics_events($raw, $tz);
    if (!$events) {
        return $fallback;
    }

    $now = new DateTime('now', $tz);
    $stats = [
        'total' => count($events),
        'private' => 0,
        'weddings' => 0,
        'upcoming' => 0,
        'fresh' => true,
        'updated' => date(DATE_ATOM),
    ];

    foreach ($events as $event) {
        $title = mb_strtolower((string)($event['summary'] ?? ''), 'UTF-8');
        if (str_contains($title, 'private')) {
            $stats['private']++;
        }
        if (str_contains($title, 'wedding')) {
            $stats['weddings']++;
        }
        if (($event['start'] ?? $now) >= $now) {
            $stats['upcoming']++;
        }
    }

    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode($stats, JSON_PRETTY_PRINT));

    return $stats;
}

$stats = ns_about_get_calendar_stats($publicCalendarIcsUrl, $calendarTimezone, $cacheFile, $cacheTtlSeconds);
$proofStats = [
    ['label' => 'Total gigs tracked', 'value' => (int)$stats['total'], 'suffix' => '+'],
    ['label' => 'Private events', 'value' => (int)$stats['private'], 'suffix' => '+'],
    ['label' => 'Weddings', 'value' => (int)$stats['weddings'], 'suffix' => '+'],
    ['label' => 'Upcoming dates on the calendar', 'value' => (int)$stats['upcoming'], 'suffix' => ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | Nick Sanzeri</title>
    <meta name="description" content="Nick Sanzeri is a Chicagoland live entertainer trusted for weddings, private events, corporate events, clubs, restaurants, casinos, and parties.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" href="favicons/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .proof-hero {
            position: relative;
            overflow: hidden;
        }
        .proof-hero .page-intro {
            max-width: 850px;
        }
        .proof-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .proof-card {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 22px;
            padding: 1.25rem;
            box-shadow: 0 20px 50px rgba(0,0,0,.18);
        }
        .proof-number {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.25rem, 5vw, 4.4rem);
            line-height: .95;
            font-weight: 700;
        }
        .proof-label {
            display: block;
            margin-top: .45rem;
            font-weight: 600;
            letter-spacing: .01em;
        }
        .proof-note {
            margin-top: 1rem;
            font-size: .9rem;
            opacity: .78;
        }
        .about-proof-copy p {
            font-size: 1.05rem;
            line-height: 1.8;
        }
        .trust-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        .trust-card,
        .feature-proof-card {
            border-radius: 22px;
            padding: 1.35rem;
            background: #fff;
            box-shadow: 0 18px 45px rgba(0,0,0,.08);
            border: 1px solid rgba(0,0,0,.06);
        }
        .trust-card i {
            font-size: 1.45rem;
            margin-bottom: .75rem;
        }
        .trust-card h3,
        .feature-proof-card h3 {
            margin-top: 0;
            margin-bottom: .45rem;
        }
        .quote-feature-grid {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 1.4rem;
            align-items: stretch;
        }
        .feature-proof-card blockquote {
            margin: 0;
            font-size: clamp(1.25rem, 2.6vw, 2rem);
            line-height: 1.35;
            font-family: 'Playfair Display', serif;
        }
        .feature-proof-card cite,
        .mini-quote cite {
            display: block;
            margin-top: 1rem;
            font-style: normal;
            font-weight: 700;
            opacity: .8;
        }
        .mini-quote-stack {
            display: grid;
            gap: 1rem;
        }
        .mini-quote {
            border-radius: 20px;
            padding: 1.15rem;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.14);
        }
        .mini-quote p {
            margin: 0;
        }
        .reputation-box {
            border-radius: 28px;
            padding: clamp(1.5rem, 4vw, 3rem);
            background: linear-gradient(135deg, rgba(0,0,0,.84), rgba(0,0,0,.68));
            color: #fff;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
        }
        .reputation-box p {
            line-height: 1.8;
        }
        .reputation-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: .85rem;
        }
        .reputation-list li {
            display: flex;
            gap: .75rem;
            align-items: flex-start;
        }
        .reputation-list i {
            margin-top: .2rem;
        }
        @media (max-width: 900px) {
            .proof-grid,
            .trust-strip,
            .quote-feature-grid,
            .reputation-box {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "PerformingGroup",
      "name": "Nick Sanzeri - Midwest and Chicago Area Private Event Performer",
      "alternateName": "Live bassist and vocalist providing music for private parties, weddings, corporate events, casinos, restaurants, clubs and more.",
      "url": "https://nicksanzeri.com",
      "description": "Singer and bassist with a full-band sound specializing in private parties, corporate events, weddings, restaurants, clubs, casinos, and community events in the Chicago area and beyond.",
      "sameAs": [
        "https://www.facebook.com/nicksanzeri13",
        "https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ?si=O4zvZ3xUQyWhO1Jhsq-dnQ",
        "https://twitter.com/nick_sanzeri",
        "https://www.tiktok.com/@nicksanzeri?lang=en",
        "https://www.instagram.com/nick_sanzeri/",
        "https://www.youtube.com/channel/UCnTEOsjjmdnM0jBZyJY6jfg"
      ],
      "areaServed": {
        "@type": "Place",
        "name": "Chicago, IL"
      },
      "location": {
        "@type": "Place",
        "address": {
          "@type": "PostalAddress",
          "addressLocality": "Carol Stream",
          "addressRegion": "IL",
          "addressCountry": "US"
        }
      }
    }
    </script>
</head>
<body id="top">
<?php include __DIR__ . '/includes/header.php'; ?>

<main>
    <section class="page-hero proof-hero">
        <div class="container">
            <p class="eyebrow">About Nick Sanzeri</p>
            <h1>Trusted with the night people care about.</h1>
            <p class="page-intro">
                When someone hires music for a wedding, private party, corporate event, casino, restaurant, or community celebration, they are not just filling a time slot. They are trusting someone with the mood of the room. Nick Sanzeri has built his reputation by showing up prepared, reading the crowd, sounding great, and making the whole night feel easy.
            </p>

            <div class="proof-grid" aria-label="Calendar-based performance counters">
                <?php foreach ($proofStats as $stat): ?>
                    <div class="proof-card">
                        <span class="proof-number" data-count="<?= (int)$stat['value']; ?>" data-suffix="<?= ns_about_h($stat['suffix']); ?>">0</span>
                        <span class="proof-label"><?= ns_about_h($stat['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="proof-note">
                <?php if (!empty($stats['fresh'])): ?>
                    Counters are pulled from Nick's public performance calendar and refreshed periodically.
                <?php else: ?>
                    Counters are showing fallback career totals because the public calendar feed could not be reached during this page load.
                <?php endif; ?>
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container grid-2">
            <div class="about-image-wrap">
                <img src="assets/img/paint.png" alt="Nick Sanzeri performing" class="about-image">
            </div>
            <div class="about-proof-copy">
                <h2>The proof is in the repeat bookings, packed rooms, and rave reviews.</h2>
                <p>
                    Nick is the performer people call when they want live music with energy, polish, personality, and zero drama. His calendar tells the story better than a resume ever could: private events, weddings, clubs, restaurants, casinos, corporate functions, and community events where the job is simple but demanding — make people happy and keep the night moving.
                </p>
                <p>
                    Every event gets treated like it matters because it does. Nick brings a professional sound system, a deep song list, years of crowd-reading experience, and the kind of preparation that lets hosts relax instead of worrying about the entertainment.
                </p>
                <p>
                    The goal is not just to play songs. The goal is to make guests say, <strong>“Where did you find this guy?”</strong>
                </p>
            </div>
        </div>
    </section>

    <section class="section section-dark">
        <div class="container">
            <div class="quote-feature-grid">
                <article class="feature-proof-card">
                    <blockquote>
                        “Nick sang at our wedding and was truly amazing. His voice was absolutely beautiful and added such a special, emotional, and elegant touch to our day.”
                    </blockquote>
                    <cite>— Nancy M., bride</cite>
                </article>
                <div class="mini-quote-stack">
                    <article class="mini-quote">
                        <p>“We’ve booked Nick multiple times for our community events. Residents absolutely love him — every time the crowd gets bigger.”</p>
                        <cite>— Andy V., Club Treasurer</cite>
                    </article>
                    <article class="mini-quote">
                        <p>“Nick is the whole package — he’s a super-talented musician, knows what people like, and knows how to work the crowd!”</p>
                        <cite>— Sandy White</cite>
                    </article>
                    <article class="mini-quote">
                        <p>“You’ve got a really soulful voice and are a great bass player.”</p>
                        <cite>— Michael McDonald</cite>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="trust-strip">
                <article class="trust-card">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <h3>Hosts can relax</h3>
                    <p>Nick confirms the details, brings the right gear, arrives prepared, and keeps the music flowing from the first guest arrival through the final song.</p>
                </article>
                <article class="trust-card">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <h3>The room gets read</h3>
                    <p>Background music, dance energy, sing-alongs, dinner atmosphere, or party mode — Nick adjusts to the crowd instead of forcing a one-size-fits-all show.</p>
                </article>
                <article class="trust-card">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <h3>Reputation matters</h3>
                    <p>Most performers say they care. Nick stakes his name on it. The standard is simple: be easy to work with, sound excellent, and leave people talking.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="reputation-box">
                <div>
                    <p class="eyebrow">The extra-mile promise</p>
                    <h2>I treat your event like my reputation is on the line — because it is.</h2>
                    <p>
                        A great event is more than a good set list. It is timing, communication, setup, volume, song choices, professionalism, and knowing when to step forward or stay out of the way. Nick brings the full-band feel of a larger act with the simplicity and personal attention of hiring one reliable pro.
                    </p>
                </div>
                <ul class="reputation-list">
                    <li><i class="fas fa-check-circle" aria-hidden="true"></i><span>Professional Bose sound system and clean setup for private, corporate, wedding, and venue settings.</span></li>
                    <li><i class="fas fa-check-circle" aria-hidden="true"></i><span>Music coverage tailored to the night — from guest arrival and dinner to dancing and closing songs.</span></li>
                    <li><i class="fas fa-check-circle" aria-hidden="true"></i><span>Responsive communication before the event so there are no last-minute surprises.</span></li>
                    <li><i class="fas fa-check-circle" aria-hidden="true"></i><span>A personable, upbeat presence that helps guests feel included without taking over the event.</span></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section-cta">
        <div class="container cta-inner">
            <div>
                <h2>Want this kind of reaction at your event?</h2>
                <p>Check the public calendar, then reach out about your date. You will get a personal response from Nick.</p>
            </div>
            <div class="cta-actions">
                <a href="shows.php" class="btn btn-outline">View calendar</a>
                <a href="booking.php" class="btn btn-primary">Start a booking inquiry</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    const counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;

    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function formatNumber(num) {
        return Math.round(num).toLocaleString();
    }

    function runCounter(el) {
        const target = parseInt(el.getAttribute('data-count') || '0', 10);
        const suffix = el.getAttribute('data-suffix') || '';

        if (prefersReducedMotion || target <= 0) {
            el.textContent = formatNumber(target) + suffix;
            return;
        }

        const duration = 1200;
        const startTime = performance.now();

        function tick(now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatNumber(target * eased) + suffix;

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        }

        requestAnimationFrame(tick);
    }

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    runCounter(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.25 });

        counters.forEach(counter => observer.observe(counter));
    } else {
        counters.forEach(runCounter);
    }
})();
</script>
<script src="assets/js/main.js"></script>
</body>
</html>

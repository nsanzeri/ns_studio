<?php
declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
    ]);
    exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'User not found',
    ]);
    exit;
}

$calendarId = (int)($_GET['id'] ?? 0);
$startDate  = trim((string)($_GET['start'] ?? ''));
$endDate    = trim((string)($_GET['end'] ?? ''));

if ($calendarId <= 0 || $startDate === '' || $endDate === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Missing parameters',
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, ics_url, timezone
    FROM calendars
    WHERE id = :id
      AND user_id = :user_id
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([
    ':id' => $calendarId,
    ':user_id' => (int)$user['id'],
]);

$calendar = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$calendar) {
    echo json_encode([
        'success' => false,
        'error' => 'Calendar not found',
    ]);
    exit;
}

$icalUrl = (string)$calendar['ics_url'];
$userTimezone = $_SESSION['detected_timezone']
    ?? ($_SESSION['timezone'] ?? null)
    ?? ($calendar['timezone'] ?? null)
    ?? 'America/Chicago';

try {
    $tz = new DateTimeZone($userTimezone);
} catch (Throwable $e) {
    $tz = new DateTimeZone('America/Chicago');
}

try {
    $rangeStart = new DateTime($startDate, $tz);
    $rangeEnd   = (new DateTime($endDate, $tz))->setTime(23, 59, 59);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid date range',
    ]);
    exit;
}

$ch = curl_init($icalUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$rawICS = curl_exec($ch);

if ($rawICS === false) {
    $error = curl_error($ch);
    curl_close($ch);

    echo json_encode([
        'success' => false,
        'error' => 'Error fetching iCal: ' . $error,
    ]);
    exit;
}

curl_close($ch);

if (!$rawICS) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch iCal data',
    ]);
    exit;
}

function ics_unfold(string $raw): array
{
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];

    foreach ($lines as $line) {
        if (
            isset($out[count($out) - 1]) &&
            (strpos($line, ' ') === 0 || strpos($line, "\t") === 0)
        ) {
            $out[count($out) - 1] .= ltrim($line);
        } else {
            $out[] = $line;
        }
    }

    return $out;
}

function parse_exdates(array $event, DateTimeZone $tz): array
{
    $dates = [];

    if (!isset($event['EXDATE'])) {
        return $dates;
    }

    $rawLines = is_array($event['EXDATE']) ? $event['EXDATE'] : [$event['EXDATE']];

    foreach ($rawLines as $raw) {
        $parts = explode(',', $raw);

        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            $clean = preg_replace('/^.*:/', '', $p);
            if ($clean === '') {
                continue;
            }

            try {
                $dt = new DateTime($clean, $tz);
                $dates[$dt->format('Y-m-d')] = true;
            } catch (Throwable $e) {
            }
        }
    }

    return $dates;
}

function expand_rrule_event(array $event, DateTime $rangeStart, DateTime $rangeEnd, DateTimeZone $tz): array
{
    $results = [];

    if (!isset($event['DTSTART']) || !isset($event['RRULE'])) {
        return $results;
    }

    $dtStartRaw = preg_replace('/^.*:/', '', $event['DTSTART']);
    $dtEndRaw   = isset($event['DTEND']) ? preg_replace('/^.*:/', '', $event['DTEND']) : $dtStartRaw;

    $baseStart = new DateTime($dtStartRaw, $tz);
    $baseEnd   = new DateTime($dtEndRaw, $tz);
    $duration  = $baseEnd->getTimestamp() - $baseStart->getTimestamp();

    $allDay = (bool)preg_match('/^\d{8}$/', $dtStartRaw);

    $rr = [];
    foreach (explode(';', $event['RRULE']) as $chunk) {
        if (strpos($chunk, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $chunk, 2);
        $rr[strtoupper($k)] = strtoupper($v);
    }

    $freq     = $rr['FREQ'] ?? 'WEEKLY';
    $interval = isset($rr['INTERVAL']) ? max(1, (int)$rr['INTERVAL']) : 1;

    if (!empty($rr['UNTIL'])) {
        $until = new DateTime($rr['UNTIL'], $tz);
    } else {
        $until = clone $rangeEnd;
    }

    $exdates = parse_exdates($event, $tz);

    $summary     = $event['SUMMARY'] ?? 'No Summary';
    $location    = $event['LOCATION'] ?? '';
    $description = $event['DESCRIPTION'] ?? '';

    $pushOccurrence = function (DateTime $startOcc) use (&$results, $duration, $allDay, $summary, $location, $description): void {
        $occStart = clone $startOcc;
        $occEnd   = clone $startOcc;

        if ($allDay) {
            $occStart->setTime(0, 0, 0);
            $occEnd->setTime(23, 59, 59);
        } else {
            $occEnd->modify("+{$duration} seconds");
        }

        $results[] = [
            'start'       => $occStart->format(DateTime::ATOM),
            'end'         => $occEnd->format(DateTime::ATOM),
            'summary'     => $summary,
            'location'    => $location,
            'description' => $description,
        ];
    };

    if ($freq === 'WEEKLY') {
        $bydays = [];

        if (!empty($rr['BYDAY'])) {
            $bydays = explode(',', $rr['BYDAY']);
        } else {
            $bydays[] = strtoupper(substr($baseStart->format('D'), 0, 2));
        }

        $wkst = $rr['WKST'] ?? 'MO';
        $dowMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $offsetDow = $dowMap[$wkst] ?? 1;

        $scanStart = clone $rangeStart;
        if ($scanStart < $baseStart) {
            $scanStart = clone $baseStart;
        }

        for ($d = clone $scanStart; $d <= $until; $d->modify('+1 day')) {
            if ($d < $baseStart || $d > $rangeEnd) {
                continue;
            }

            $dow2 = strtoupper(substr($d->format('D'), 0, 2));
            if (!in_array($dow2, $bydays, true)) {
                continue;
            }

            $daysDiff = (int)floor(($d->getTimestamp() - $baseStart->getTimestamp()) / 86400);
            if ($daysDiff < 0) {
                continue;
            }

            $startDow  = (int)$baseStart->format('w');
            $shift     = ($startDow - $offsetDow + 7) % 7;
            $weekIndex = intdiv(max(0, $daysDiff - $shift), 7);

            if ($weekIndex % $interval !== 0) {
                continue;
            }

            if (isset($exdates[$d->format('Y-m-d')])) {
                continue;
            }

            $pushOccurrence($d);
        }
    }

    if ($freq === 'MONTHLY') {
        $monthDays = [];

        if (!empty($rr['BYMONTHDAY'])) {
            foreach (explode(',', $rr['BYMONTHDAY']) as $md) {
                $md = (int)$md;
                if ($md >= 1 && $md <= 31) {
                    $monthDays[] = $md;
                }
            }
        }

        if (!$monthDays) {
            $monthDays[] = (int)$baseStart->format('j');
        }

        $current = (clone $baseStart)->modify('first day of this month');
        if ($current < $rangeStart) {
            $current = (clone $rangeStart)->modify('first day of this month');
        }

        while ($current <= $until && $current <= $rangeEnd) {
            $yearDiff  = (int)$current->format('Y') - (int)$baseStart->format('Y');
            $monthDiff = $yearDiff * 12 + ((int)$current->format('n') - (int)$baseStart->format('n'));

            if ($monthDiff >= 0 && $monthDiff % $interval === 0) {
                if (!empty($rr['BYDAY']) && !empty($rr['BYSETPOS'])) {
                    $posList  = array_map('intval', explode(',', $rr['BYSETPOS']));
                    $weekdays = explode(',', $rr['BYDAY']);
                    $monthDays = [];

                    foreach ($posList as $pos) {
                        foreach ($weekdays as $wd) {
                            $scan = clone $current;
                            $scan->modify("first $wd of this month");
                            $weekCount = 1;

                            while ($scan->format('n') == $current->format('n')) {
                                if ($pos === $weekCount) {
                                    $monthDays[] = (int)$scan->format('j');
                                }
                                $scan->modify('+1 week');
                                $weekCount++;
                            }
                        }
                    }
                }

                foreach ($monthDays as $md) {
                    $occ = clone $current;
                    $occ->setDate(
                        (int)$current->format('Y'),
                        (int)$current->format('n'),
                        min($md, (int)$current->format('t'))
                    );

                    if ($occ < $baseStart || $occ < $rangeStart || $occ > $until || $occ > $rangeEnd) {
                        continue;
                    }

                    if (isset($exdates[$occ->format('Y-m-d')])) {
                        continue;
                    }

                    $pushOccurrence($occ);
                }
            }

            $current->modify('first day of next month');
        }
    }

    return $results;
}

$lines = ics_unfold($rawICS);
$events = [];
$event = null;

foreach ($lines as $line) {
    $trim = trim($line);

    if ($trim === 'BEGIN:VEVENT') {
        $event = [];
        continue;
    }

    if ($trim === 'END:VEVENT') {
        if ($event && isset($event['DTSTART'])) {
            if (isset($event['RRULE'])) {
                $expanded = expand_rrule_event($event, $rangeStart, $rangeEnd, $tz);
                foreach ($expanded as $occ) {
                    $events[] = $occ;
                }
            } else {
                $dtStartRaw = preg_replace('/^.*:/', '', $event['DTSTART']);
                $dtEndRaw   = isset($event['DTEND']) ? preg_replace('/^.*:/', '', $event['DTEND']) : $dtStartRaw;

                $start = new DateTime($dtStartRaw, $tz);
                $end   = new DateTime($dtEndRaw, $tz);

                $allDay = (bool)preg_match('/^\d{8}$/', $dtStartRaw);
                if ($allDay) {
                    $end->setTime(23, 59, 59);
                }

                if ($end >= $rangeStart && $start <= $rangeEnd) {
                    $events[] = [
                        'start'       => $start->format(DateTime::ATOM),
                        'end'         => $end->format(DateTime::ATOM),
                        'summary'     => $event['SUMMARY'] ?? 'No Summary',
                        'location'    => $event['LOCATION'] ?? '',
                        'description' => $event['DESCRIPTION'] ?? '',
                    ];
                }
            }
        }

        $event = null;
        continue;
    }

    if ($event !== null && strpos($trim, ':') !== false) {
        [$key, $value] = explode(':', $trim, 2);
        $keyUpper = strtoupper(explode(';', $key)[0]);

        if ($keyUpper === 'EXDATE') {
            if (!isset($event['EXDATE'])) {
                $event['EXDATE'] = [];
            }
            $event['EXDATE'][] = $value;
        } else {
            $event[$keyUpper] = $value;
        }
    }
}

echo json_encode([
    'success' => true,
    'events' => $events,
]);
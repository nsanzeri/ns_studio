<?php
declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/finance/index.php');
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$userId = (int)($user['id'] ?? 0);
$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();
$messages = [];
$errors = [];

function finance_table_exists(PDO $pdo, string $tableName): bool {
    static $cache = [];
    $key = strtolower(trim($tableName));
    if ($key === '') return false;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$key]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function finance_tables_ready(PDO $pdo): bool {
    return finance_table_exists($pdo, 'finance_gigs')
        && finance_table_exists($pdo, 'finance_members')
        && finance_table_exists($pdo, 'finance_gig_payouts')
        && finance_table_exists($pdo, 'calendars');
}

function finance_money(int $cents): string {
    return '$' . number_format($cents / 100, 2);
}

function finance_parse_money($value): int {
    $clean = preg_replace('/[^0-9.\-]/', '', (string)$value);
    if ($clean === '' || $clean === '-' || (float)$clean <= 0) return 0;
    return (int)round(((float)$clean) * 100);
}

function finance_clean_text($value, int $maxLength = 255): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLength);
}

function finance_date_for_sql(string $atom): string {
    return (new DateTime($atom))->format('Y-m-d H:i:s');
}

function finance_member_id_for_name(PDO $pdo, int $userId, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Member name is required for payout rows.');
    }

    $stmt = $pdo->prepare("SELECT id FROM finance_members WHERE user_id = ? AND name = ? LIMIT 1");
    $stmt->execute([$userId, $name]);
    $memberId = (int)($stmt->fetchColumn() ?: 0);
    if ($memberId > 0) return $memberId;

    $insert = $pdo->prepare("INSERT INTO finance_members (user_id, name) VALUES (?, ?)");
    $insert->execute([$userId, $name]);
    return (int)$pdo->lastInsertId();
}

function finance_event_key(int $calendarId, array $event): string {
    return hash('sha256', implode('|', [
        $calendarId,
        (string)($event['uid'] ?? ''),
        (string)($event['start'] ?? ''),
        (string)($event['summary'] ?? ''),
        (string)($event['location'] ?? ''),
    ]));
}

function finance_ics_unfold(string $raw): array {
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];
    foreach ($lines as $line) {
        if (isset($out[count($out) - 1]) && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
            $out[count($out) - 1] .= ltrim($line);
        } else {
            $out[] = $line;
        }
    }
    return $out;
}

function finance_parse_exdates(array $event, DateTimeZone $tz): array {
    $dates = [];
    if (!isset($event['EXDATE'])) return $dates;
    foreach ((array)$event['EXDATE'] as $raw) {
        foreach (explode(',', $raw) as $part) {
            $clean = preg_replace('/^.*:/', '', trim($part));
            if ($clean === '') continue;
            try {
                $dt = new DateTime($clean, $tz);
                $dates[$dt->format('Y-m-d')] = true;
            } catch (Throwable $e) {
            }
        }
    }
    return $dates;
}

function finance_push_occurrence(array &$results, array $event, DateTime $start, int $duration, bool $allDay): void {
    $occStart = clone $start;
    $occEnd = clone $start;
    if ($allDay) {
        $occStart->setTime(0, 0, 0);
        $occEnd->setTime(23, 59, 59);
    } else {
        $occEnd->modify("+{$duration} seconds");
    }
    $results[] = [
        'uid' => $event['UID'] ?? '',
        'start' => $occStart->format(DateTime::ATOM),
        'end' => $occEnd->format(DateTime::ATOM),
        'summary' => $event['SUMMARY'] ?? 'No Summary',
        'location' => $event['LOCATION'] ?? '',
        'description' => $event['DESCRIPTION'] ?? '',
    ];
}

function finance_expand_rrule_event(array $event, DateTime $rangeStart, DateTime $rangeEnd, DateTimeZone $tz): array {
    $results = [];
    if (!isset($event['DTSTART'], $event['RRULE'])) return $results;

    $dtStartRaw = preg_replace('/^.*:/', '', $event['DTSTART']);
    $dtEndRaw = isset($event['DTEND']) ? preg_replace('/^.*:/', '', $event['DTEND']) : $dtStartRaw;
    $baseStart = new DateTime($dtStartRaw, $tz);
    $baseEnd = new DateTime($dtEndRaw, $tz);
    $duration = $baseEnd->getTimestamp() - $baseStart->getTimestamp();
    $allDay = (bool)preg_match('/^\d{8}$/', $dtStartRaw);

    $rr = [];
    foreach (explode(';', $event['RRULE']) as $chunk) {
        if (!str_contains($chunk, '=')) continue;
        [$k, $v] = explode('=', $chunk, 2);
        $rr[strtoupper($k)] = strtoupper($v);
    }

    $freq = $rr['FREQ'] ?? 'WEEKLY';
    $interval = isset($rr['INTERVAL']) ? max(1, (int)$rr['INTERVAL']) : 1;
    $until = !empty($rr['UNTIL']) ? new DateTime($rr['UNTIL'], $tz) : clone $rangeEnd;
    $exdates = finance_parse_exdates($event, $tz);

    if ($freq === 'WEEKLY') {
        $bydays = !empty($rr['BYDAY']) ? explode(',', $rr['BYDAY']) : [strtoupper(substr($baseStart->format('D'), 0, 2))];
        $dowMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $offsetDow = $dowMap[$rr['WKST'] ?? 'MO'] ?? 1;
        $scanStart = $rangeStart < $baseStart ? clone $baseStart : clone $rangeStart;
        for ($d = clone $scanStart; $d <= $until && $d <= $rangeEnd; $d->modify('+1 day')) {
            $dow2 = strtoupper(substr($d->format('D'), 0, 2));
            if (!in_array($dow2, $bydays, true) || isset($exdates[$d->format('Y-m-d')])) continue;
            $daysDiff = (int)floor(($d->getTimestamp() - $baseStart->getTimestamp()) / 86400);
            $startDow = (int)$baseStart->format('w');
            $shift = ($startDow - $offsetDow + 7) % 7;
            $weekIndex = intdiv(max(0, $daysDiff - $shift), 7);
            if ($weekIndex % $interval === 0) finance_push_occurrence($results, $event, $d, $duration, $allDay);
        }
    }

    if ($freq === 'MONTHLY') {
        $monthDays = [];
        if (!empty($rr['BYMONTHDAY'])) {
            foreach (explode(',', $rr['BYMONTHDAY']) as $md) {
                $md = (int)$md;
                if ($md >= 1 && $md <= 31) $monthDays[] = $md;
            }
        }
        if (!$monthDays) $monthDays[] = (int)$baseStart->format('j');
        $current = (clone ($rangeStart > $baseStart ? $rangeStart : $baseStart))->modify('first day of this month');
        while ($current <= $until && $current <= $rangeEnd) {
            $monthDiff = (((int)$current->format('Y') - (int)$baseStart->format('Y')) * 12) + ((int)$current->format('n') - (int)$baseStart->format('n'));
            if ($monthDiff >= 0 && $monthDiff % $interval === 0) {
                foreach ($monthDays as $md) {
                    $occ = clone $current;
                    $occ->setDate((int)$current->format('Y'), (int)$current->format('n'), min($md, (int)$current->format('t')));
                    if ($occ >= $baseStart && $occ >= $rangeStart && $occ <= $until && $occ <= $rangeEnd && !isset($exdates[$occ->format('Y-m-d')])) {
                        finance_push_occurrence($results, $event, $occ, $duration, $allDay);
                    }
                }
            }
            $current->modify('first day of next month');
        }
    }

    return $results;
}

function finance_fetch_calendar_events(array $calendar, string $startDate, string $endDate): array {
    $tzName = (string)($calendar['timezone'] ?: 'America/Chicago');
    try {
        $tz = new DateTimeZone($tzName);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('America/Chicago');
    }
    $rangeStart = new DateTime($startDate, $tz);
    $rangeEnd = (new DateTime($endDate, $tz))->setTime(23, 59, 59);

    $ch = curl_init((string)$calendar['ics_url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Could not fetch iCal: ' . $error);
    }
    curl_close($ch);
    if (!$raw) throw new RuntimeException('The selected calendar did not return any events.');

    $events = [];
    $event = null;
    foreach (finance_ics_unfold((string)$raw) as $line) {
        $trim = trim($line);
        if ($trim === 'BEGIN:VEVENT') {
            $event = [];
            continue;
        }
        if ($trim === 'END:VEVENT') {
            if ($event && isset($event['DTSTART'])) {
                if (isset($event['RRULE'])) {
                    $events = array_merge($events, finance_expand_rrule_event($event, $rangeStart, $rangeEnd, $tz));
                } else {
                    $dtStartRaw = preg_replace('/^.*:/', '', $event['DTSTART']);
                    $dtEndRaw = isset($event['DTEND']) ? preg_replace('/^.*:/', '', $event['DTEND']) : $dtStartRaw;
                    $start = new DateTime($dtStartRaw, $tz);
                    $end = new DateTime($dtEndRaw, $tz);
                    if (preg_match('/^\d{8}$/', $dtStartRaw)) $end->setTime(23, 59, 59);
                    if ($end >= $rangeStart && $start <= $rangeEnd) {
                        $events[] = ['uid' => $event['UID'] ?? '', 'start' => $start->format(DateTime::ATOM), 'end' => $end->format(DateTime::ATOM), 'summary' => $event['SUMMARY'] ?? 'No Summary', 'location' => $event['LOCATION'] ?? '', 'description' => $event['DESCRIPTION'] ?? ''];
                    }
                }
            }
            $event = null;
            continue;
        }
        if ($event !== null && str_contains($trim, ':')) {
            [$key, $value] = explode(':', $trim, 2);
            $keyUpper = strtoupper(explode(';', $key)[0]);
            if ($keyUpper === 'EXDATE') {
                $event['EXDATE'][] = $value;
            } else {
                $event[$keyUpper] = str_replace(['\\,', '\\n'], [',', "\n"], $value);
            }
        }
    }
    usort($events, fn($a, $b) => strcmp((string)$a['start'], (string)$b['start']));
    return $events;
}

function finance_page_head(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .finance-shell { padding: 2rem 0 4rem; }
    .finance-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:24px; padding:1.35rem; box-shadow:0 16px 34px rgba(0,0,0,.18); }
    .finance-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:1rem; }
    .finance-two { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .finance-stack { display:grid; gap:1rem; }
    .finance-field { display:grid; gap:.45rem; }
    .finance-input, .finance-select, .finance-textarea { width:100%; padding:.72rem .8rem; border-radius:12px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .finance-select option { background:#151323; color:#fff; }
    .finance-table-wrap { overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; margin-top:1rem; }
    .finance-table { width:100%; border-collapse:collapse; min-width:1180px; }
    .finance-table th, .finance-table td { padding:.75rem; border-bottom:1px solid rgba(255,255,255,.07); text-align:left; vertical-align:top; }
    .finance-table th { color:#f4d35e; font-size:.78rem; letter-spacing:.08em; text-transform:uppercase; }
    .finance-table input, .finance-table textarea { min-width:92px; }
    .finance-table textarea { min-width:160px; min-height:42px; resize:vertical; }
    .finance-check-cell { text-align:center; }
    .finance-check { min-width:0 !important; width:18px; height:18px; accent-color:#d4af37; }
    .finance-payouts-cell { min-width:260px; }
    .finance-payouts { display:grid; gap:.55rem; }
    .finance-payouts summary { cursor:pointer; color:#ffe28a; font-weight:600; }
    .finance-payout-row { display:grid; grid-template-columns:1fr 92px; gap:.45rem; align-items:center; }
    .finance-payout-row input { min-width:0 !important; }
    .finance-stat strong { display:block; font-size:1.55rem; color:#fff; }
    .finance-muted { color:rgba(255,255,255,.72); }
    .finance-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:600; background: rgba(212,175,55,.16); color:#ffe28a; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 980px) { .finance-grid, .finance-two { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header.php'; ?>
<?php }

function finance_page_foot(): void { ?>
<?php include __DIR__ . '/../../includes/tools_footer.php'; ?>
</body>
</html>
<?php }

function finance_flash(array $messages, array $errors): void {
    foreach ($messages as $message) echo '<div class="alert alert-success">' . e($message) . '</div>';
    foreach ($errors as $error) echo '<div class="alert alert-error">' . e($error) . '</div>';
}

function finance_install_notice(): void { ?>
  <div class="finance-card">
    <h2 style="margin-top:0;">Finance setup required</h2>
    <p class="finance-muted">Run <code>migrations/013_finance_gigs.sql</code> and <code>migrations/014_finance_payouts_refactor.sql</code>, then refresh this page.</p>
  </div>
<?php }

$financeReady = finance_tables_ready($pdo);

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

function finance_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    static $cache = [];
    $key = strtolower(trim($tableName)) . '.' . strtolower(trim($columnName));
    if ($key === '.') return false;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return $cache[$key] = (bool)$stmt->fetchColumn();
}

function finance_tables_ready(PDO $pdo): bool {
    return finance_table_exists($pdo, 'finance_gigs')
        && finance_table_exists($pdo, 'finance_members')
        && finance_table_exists($pdo, 'finance_gig_payouts')
        && finance_table_exists($pdo, 'calendars')
        && finance_column_exists($pdo, 'finance_gigs', 'is_taxable')
        && finance_column_exists($pdo, 'finance_gigs', 'cash_tips_cents')
        && finance_column_exists($pdo, 'finance_gigs', 'platform_tips_cents')
        && finance_column_exists($pdo, 'finance_gig_payouts', 'payout_type');
}

function finance_ensure_commission_payout_type(PDO $pdo): void {
    if (!finance_table_exists($pdo, 'finance_gig_payouts') || !finance_column_exists($pdo, 'finance_gig_payouts', 'payout_type')) {
        return;
    }
    $stmt = $pdo->prepare("
        SELECT column_type
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'finance_gig_payouts'
          AND column_name = 'payout_type'
        LIMIT 1
    ");
    $stmt->execute();
    $columnType = (string)($stmt->fetchColumn() ?: '');
    if ($columnType !== '' && !str_contains($columnType, "'commission'")) {
        $pdo->exec("ALTER TABLE finance_gig_payouts MODIFY COLUMN payout_type enum('band_member','advertising','commission','sound','lights','insurance','travel','other') NOT NULL DEFAULT 'band_member'");
    }
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

function finance_calendar_datetime(string $raw, DateTimeZone $tz): DateTime {
    $date = new DateTime($raw, $tz);
    $date->setTimezone($tz);
    return $date;
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

function finance_payout_types(): array {
    return [
        'band_member' => 'Band member',
        'advertising' => 'Advertising',
        'commission' => 'Commission',
        'sound' => 'Sound',
        'lights' => 'Lights',
        'insurance' => 'Insurance',
        'travel' => 'Travel',
        'other' => 'Other',
    ];
}

function finance_normalize_payout_type($value): string {
    $value = (string)$value;
    return array_key_exists($value, finance_payout_types()) ? $value : 'band_member';
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
                $dt = finance_calendar_datetime($clean, $tz);
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
    $baseStart = finance_calendar_datetime($dtStartRaw, $tz);
    $baseEnd = finance_calendar_datetime($dtEndRaw, $tz);
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
    $until = !empty($rr['UNTIL']) ? finance_calendar_datetime($rr['UNTIL'], $tz) : clone $rangeEnd;
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

    $icalUrl = rss_normalize_ical_url((string)$calendar['ics_url']);
    if (!filter_var($icalUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($icalUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        throw new RuntimeException('Calendar URL must start with http, https, or webcal.');
    }

    $ch = curl_init($icalUrl);
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
                    $start = finance_calendar_datetime($dtStartRaw, $tz);
                    $end = finance_calendar_datetime($dtEndRaw, $tz);
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
    .finance-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:1rem; }
    .finance-two { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .finance-stack { display:grid; gap:1rem; }
    .finance-field { display:grid; gap:.45rem; }
    .finance-input, .finance-select, .finance-textarea { width:100%; padding:.72rem .8rem; border-radius:12px; border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.05); color:#fff; font:inherit; }
    .finance-select option { background:#151323; color:#fff; }
    .finance-table-wrap { overflow:auto; border:1px solid rgba(255,255,255,.08); border-radius:16px; margin-top:1rem; }
    .finance-table { width:100%; border-collapse:collapse; min-width:980px; }
    .finance-table th, .finance-table td { padding:.75rem; border-bottom:1px solid rgba(255,255,255,.07); text-align:left; vertical-align:top; }
    .finance-table th { color:#f4d35e; font-size:.78rem; letter-spacing:.08em; text-transform:uppercase; }
    .finance-table tfoot th { border-top:1px solid rgba(255,255,255,.14); border-bottom:none; color:#fff; background:rgba(255,255,255,.035); }
    .finance-summary-table { min-width:500px !important; table-layout:auto; }
    .finance-summary-table th, .finance-summary-table td { padding:.48rem .62rem; white-space:nowrap; }
    .finance-summary-table th:first-child, .finance-summary-table td:first-child { width:30%; }
    .finance-summary-table th:not(:first-child), .finance-summary-table td:not(:first-child) { width:17.5%; }
    .finance-positive { color:#7effbf !important; }
    .finance-negative { color:#ff8d8d !important; }
    .finance-table input, .finance-table textarea { min-width:92px; }
    .finance-table textarea { min-width:160px; min-height:42px; resize:vertical; }
    .finance-gig-ledger-table { min-width:1040px; }
    .finance-gig-ledger-table th, .finance-gig-ledger-table td { padding:.42rem .5rem; }
    .finance-gig-ledger-table .finance-input, .finance-gig-ledger-table .finance-textarea, .finance-gig-ledger-table .finance-select { padding:.48rem .58rem; border-radius:10px; font-size:.92rem; }
    .finance-gig-ledger-table .finance-textarea { min-height:34px; }
    .finance-gig-ledger-table .finance-select-col { width:34px; min-width:34px; text-align:center; padding-left:.35rem; padding-right:.35rem; }
    .finance-gig-ledger-table .finance-date-col { width:142px; min-width:142px; }
    .finance-gig-ledger-table .finance-date-input { width:132px; min-width:132px; }
    .finance-gig-ledger-table .finance-tip-col { min-width:96px; }
    .finance-gig-ledger-table .finance-moneyout-cell { min-width:150px; }
    .finance-gig-ledger-table .finance-moneyout-button { padding:.4rem .62rem; }
    .finance-check-cell { text-align:center; }
    .finance-check { min-width:0 !important; width:18px; height:18px; accent-color:#d4af37; }
    .finance-moneyout-cell { min-width:180px; }
    .finance-moneyout-button { display:inline-flex; align-items:center; gap:.45rem; border:1px solid rgba(212,175,55,.35); border-radius:999px; padding:.48rem .8rem; background:rgba(212,175,55,.12); color:#ffe28a; cursor:pointer; font:inherit; font-weight:600; }
    .finance-dialog { width:min(760px, calc(100vw - 2rem)); border:1px solid rgba(255,255,255,.12); border-radius:18px; padding:0; background:#151323; color:#fff; box-shadow:0 24px 70px rgba(0,0,0,.55); }
    .finance-dialog::backdrop { background:rgba(0,0,0,.62); backdrop-filter:blur(4px); }
    .finance-dialog-inner { padding:1.15rem; display:grid; gap:1rem; }
    .finance-dialog-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
    .finance-dialog-title { margin:0; font-size:1.15rem; }
    .finance-dialog-close { border:1px solid rgba(255,255,255,.14); border-radius:999px; width:36px; height:36px; background:rgba(255,255,255,.05); color:#fff; cursor:pointer; }
    .finance-payouts { display:grid; gap:.65rem; }
    .finance-payout-row { display:grid; grid-template-columns:1.1fr 1.1fr .8fr; gap:.45rem; align-items:center; }
    .finance-payout-row input { min-width:0 !important; }
    .finance-payout-row select { min-width:0 !important; }
    .finance-payout-row .finance-payout-notes { grid-column:1 / -1; }
    .finance-stat strong { display:block; font-size:1.55rem; color:#fff; }
    .finance-muted { color:rgba(255,255,255,.72); }
    .finance-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .75rem; border-radius:999px; font-size:.84rem; font-weight:600; background: rgba(212,175,55,.16); color:#ffe28a; }
    .finance-year-links { display:flex; gap:.45rem; flex-wrap:wrap; margin-top:1rem; }
    .finance-year-link { display:inline-flex; align-items:center; min-height:34px; padding:.38rem .72rem; border-radius:999px; border:1px solid rgba(255,255,255,.14); color:#fff; text-decoration:none; background:rgba(255,255,255,.05); font-weight:600; }
    .finance-year-link:hover, .finance-year-link.active { background:rgba(212,175,55,.18); border-color:rgba(212,175,55,.38); color:#ffe28a; }
    .finance-overview-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap; }
    .finance-range-actions { display:flex; gap:.45rem; flex-wrap:wrap; justify-content:flex-end; }
    .finance-overview-total { margin-top:1rem; display:grid; gap:.15rem; }
    .finance-overview-total span, .finance-stat-mini span { color:rgba(255,255,255,.66); font-size:.82rem; }
    .finance-overview-total strong { font-size:1.65rem; color:#fff; }
    .finance-chart-wrap { margin-top:1rem; border:1px solid rgba(255,255,255,.08); border-radius:16px; background:rgba(255,255,255,.025); padding:.8rem; }
    .finance-income-chart { display:block; width:100%; height:auto; min-height:170px; }
    .finance-chart-axis { stroke:rgba(255,255,255,.16); stroke-width:1; }
    .finance-chart-area { fill:url(#financeIncomeFill); }
    .finance-chart-line { fill:none; stroke:#55c8ff; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; }
    .finance-chart-labels { display:flex; justify-content:space-between; gap:.75rem; color:rgba(255,255,255,.6); font-size:.78rem; margin-top:.35rem; }
    .finance-chart-empty { min-height:160px; display:grid; place-items:center; color:rgba(255,255,255,.68); }
    .finance-overview-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.75rem; margin-top:1rem; }
    .finance-stat-mini { border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:.85rem; background:rgba(255,255,255,.035); display:grid; gap:.2rem; }
    .finance-stat-mini strong { color:#fff; font-size:1.12rem; }
    .finance-stat-mini em { color:rgba(255,255,255,.62); font-size:.78rem; font-style:normal; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .finance-overview-table-wrap { margin-top:1rem; }
    .finance-overview-table { width:100%; border-collapse:collapse; min-width:820px; }
    .finance-overview-table th,
    .finance-overview-table td { padding:.55rem .7rem; border-bottom:1px solid rgba(255,255,255,.07); vertical-align:top; }
    .finance-overview-table th { width:13%; color:#f4d35e; font-size:.72rem; letter-spacing:.07em; text-transform:uppercase; text-align:left; font-weight:700; background:rgba(255,255,255,.025); }
    .finance-overview-table td { width:20%; color:#fff; }
    .finance-overview-table strong { display:block; font-size:1rem; color:#fff; }
    .finance-overview-table em { display:block; margin-top:.15rem; color:rgba(255,255,255,.62); font-size:.74rem; font-style:normal; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
    .finance-compare-form { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .finance-compare-form label { color:rgba(255,255,255,.66); font-size:.78rem; text-transform:uppercase; letter-spacing:.07em; font-weight:700; }
    .finance-compare-form select { padding:.52rem .75rem; border-radius:999px; border:1px solid rgba(255,255,255,.14); background:#151323; color:#fff; font:inherit; font-weight:700; }
    .finance-doc-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
    .alert { border-radius:16px; padding:.95rem 1rem; margin-bottom:1rem; }
    .alert-success { background:rgba(51,176,102,.16); border:1px solid rgba(51,176,102,.28); }
    .alert-error { background:rgba(199,64,64,.16); border:1px solid rgba(199,64,64,.28); }
    @media (max-width: 980px) { .finance-grid, .finance-two { grid-template-columns:1fr; } .finance-overview-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .finance-range-actions { justify-content:flex-start; } }
    @media (max-width: 560px) { .finance-overview-stats { grid-template-columns:1fr; } }
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
    <p class="finance-muted">Run <code>migrations/013_finance_gigs.sql</code>, <code>migrations/014_finance_payouts_refactor.sql</code>, <code>migrations/015_finance_expense_types.sql</code>, <code>migrations/026_finance_tip_splits.sql</code>, and <code>migrations/029_finance_commission_payout_type.sql</code>, then refresh this page.</p>
  </div>
<?php }

finance_ensure_commission_payout_type($pdo);
$financeReady = finance_tables_ready($pdo);

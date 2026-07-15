<?php
require_once __DIR__ . '/_common.php';

if (!$financeReady) {
    http_response_code(503);
    echo 'Finance setup is required.';
    exit;
}

if (!finance_column_exists($pdo, 'calendars', 'is_main_gig')) {
    http_response_code(503);
    echo 'Main gig calendar setup is required.';
    exit;
}

if (!$isProUser) {
    header('Location: ' . $upgradeUrl);
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'contract')));
if (!in_array($type, ['contract', 'invoice'], true)) {
    $type = 'contract';
}

$userName = trim((string)($user['name'] ?? $user['email'] ?? 'Performer'));
$gigId = (int)($_GET['gig_id'] ?? 0);
$calendarId = (int)($_GET['calendar_id'] ?? 0);
$eventKey = trim((string)($_GET['event_key'] ?? ''));
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['start'] ?? '')) ? (string)$_GET['start'] : date('Y-m-01');
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['end'] ?? '')) ? (string)$_GET['end'] : date('Y-m-t');

$gig = null;
$event = null;
$calendarName = '';
$eventTitle = '';
$eventLocation = '';
$startsAt = null;
$guaranteeCents = 0;
$tipsCents = 0;
$hasKnownAmount = false;
$docSourceId = '';

if ($gigId > 0) {
    $stmt = $pdo->prepare("
        SELECT g.*, c.name AS calendar_name
        FROM finance_gigs g
        JOIN calendars c ON c.id = g.calendar_id AND c.user_id = g.user_id
        WHERE g.id = ?
          AND g.user_id = ?
          AND c.is_main_gig = 1
        LIMIT 1
    ");
    $stmt->execute([$gigId, $userId]);
    $gig = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$gig) {
        http_response_code(404);
        echo 'Gig not found on your main gig calendar.';
        exit;
    }
    $calendarName = (string)$gig['calendar_name'];
    $eventTitle = (string)$gig['title'];
    $eventLocation = (string)($gig['location'] ?: 'Venue / address to be confirmed');
    $startsAt = new DateTime((string)$gig['starts_at']);
    $guaranteeCents = (int)$gig['guarantee_cents'];
    $tipsCents = (int)$gig['tips_cents'];
    $hasKnownAmount = true;
    $docSourceId = (string)$gigId;
} else {
    if ($calendarId <= 0 || $eventKey === '') {
        http_response_code(404);
        echo 'Calendar event not found.';
        exit;
    }
    $calendarStmt = $pdo->prepare("
        SELECT id, name, timezone, ics_url
        FROM calendars
        WHERE id = ?
          AND user_id = ?
          AND is_active = 1
          AND is_main_gig = 1
        LIMIT 1
    ");
    $calendarStmt->execute([$calendarId, $userId]);
    $calendar = $calendarStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$calendar) {
        http_response_code(404);
        echo 'Calendar event not found on your main gig calendar.';
        exit;
    }
    foreach (finance_fetch_calendar_events($calendar, $startDate, $endDate) as $candidate) {
        if (finance_event_key($calendarId, $candidate) === $eventKey) {
            $event = $candidate;
            break;
        }
    }
    if (!$event) {
        http_response_code(404);
        echo 'Calendar event not found in this date range.';
        exit;
    }
    $ledgerStmt = $pdo->prepare("
        SELECT *
        FROM finance_gigs
        WHERE user_id = ?
          AND calendar_id = ?
          AND source_event_key = ?
        LIMIT 1
    ");
    $ledgerStmt->execute([$userId, $calendarId, $eventKey]);
    $gig = $ledgerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $calendarName = (string)$calendar['name'];
    $eventTitle = (string)($event['summary'] ?? 'Untitled event');
    $eventLocation = (string)($event['location'] ?: 'Venue / address to be confirmed');
    $startsAt = new DateTime((string)$event['start']);
    if ($gig) {
        $guaranteeCents = (int)$gig['guarantee_cents'];
        $tipsCents = (int)$gig['tips_cents'];
        $hasKnownAmount = true;
        $docSourceId = (string)$gig['id'];
    } else {
        $docSourceId = substr($eventKey, 0, 8);
    }
}

$amountCents = $guaranteeCents + $tipsCents;
$docTitle = $type === 'invoice' ? 'Invoice' : 'Performance Agreement';
$docNumber = strtoupper($type) . '-' . preg_replace('/[^A-Za-z0-9]/', '', $docSourceId) . '-' . $startsAt->format('Ymd');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($docTitle) ?> | <?= e($eventTitle) ?></title>
  <style>
    body { margin:0; background:#f4f1ea; color:#181818; font-family:Arial, sans-serif; }
    .page { width:min(860px, calc(100vw - 32px)); margin:32px auto; background:#fff; padding:42px; box-shadow:0 18px 48px rgba(0,0,0,.16); }
    .head { display:flex; justify-content:space-between; gap:24px; border-bottom:2px solid #111; padding-bottom:18px; margin-bottom:26px; }
    h1 { margin:0; font-size:34px; }
    h2 { margin:28px 0 10px; font-size:18px; }
    .muted { color:#666; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    .box { border:1px solid #ddd; padding:16px; border-radius:8px; }
    table { width:100%; border-collapse:collapse; margin-top:14px; }
    th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
    th:last-child, td:last-child { text-align:right; }
    .total { font-size:20px; font-weight:700; }
    .actions { margin:18px auto; width:min(860px, calc(100vw - 32px)); display:flex; justify-content:flex-end; }
    button { border:0; border-radius:999px; background:#111; color:#fff; padding:12px 18px; font-weight:700; cursor:pointer; }
    .signature { margin-top:34px; display:grid; grid-template-columns:1fr 1fr; gap:30px; }
    .line { border-top:1px solid #111; padding-top:8px; margin-top:50px; }
    @media print { body { background:#fff; } .page { width:auto; margin:0; box-shadow:none; padding:24px; } .actions { display:none; } }
  </style>
</head>
<body>
  <div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
  <main class="page">
    <div class="head">
      <div>
        <h1><?= e($docTitle) ?></h1>
        <div class="muted"><?= e($docNumber) ?></div>
      </div>
      <div style="text-align:right;">
        <strong><?= e($userName) ?></strong><br>
        <span class="muted"><?= e((string)($user['email'] ?? '')) ?></span>
      </div>
    </div>

    <div class="grid">
      <div class="box">
        <strong>Show</strong><br>
        <?= e($eventTitle) ?><br>
        <span class="muted"><?= e($startsAt->format('F j, Y')) ?></span>
      </div>
      <div class="box">
        <strong>Location</strong><br>
        <?= e($eventLocation) ?><br>
        <span class="muted"><?= e($calendarName) ?></span>
      </div>
    </div>

    <?php if ($type === 'invoice'): ?>
      <h2>Invoice Items</h2>
      <table>
        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
          <tr><td>Performance guarantee</td><td><?= $hasKnownAmount ? finance_money($guaranteeCents) : 'To be confirmed' ?></td></tr>
          <?php if ($tipsCents > 0): ?><tr><td>Recorded tips / additional show income</td><td><?= finance_money($tipsCents) ?></td></tr><?php endif; ?>
          <tr><td class="total">Total Due</td><td class="total"><?= $hasKnownAmount ? finance_money($amountCents) : 'To be confirmed' ?></td></tr>
        </tbody>
      </table>
      <h2>Payment Notes</h2>
      <p>Payment is due according to the agreement between the performer and client. Please include the invoice number with payment.</p>
    <?php else: ?>
      <h2>Agreement</h2>
      <p>This agreement confirms the performance listed above between the performer and the hiring party. The performer will provide live entertainment for the event date and location shown.</p>
      <table>
        <tbody>
          <tr><th>Performance date</th><td><?= e($startsAt->format('F j, Y')) ?></td></tr>
          <tr><th>Show title</th><td><?= e($eventTitle) ?></td></tr>
          <tr><th>Venue / address</th><td><?= e($eventLocation) ?></td></tr>
          <tr><th>Guaranteed fee</th><td><?= $hasKnownAmount ? finance_money($guaranteeCents) : 'To be confirmed' ?></td></tr>
        </tbody>
      </table>
      <h2>Terms</h2>
      <ol>
        <li>The performer will provide live music for the event date, time, and location listed above.</li>
        <li>The client will provide a safe performance area, reasonable access for setup and load-out, and standard power when needed.</li>
        <li>Payment is due as agreed by the performer and client. Any remaining balance should be paid no later than the event date unless both parties agree otherwise.</li>
        <li>Schedule, location, or material production changes should be confirmed by both parties in writing.</li>
        <li>Outdoor events require suitable weather protection for performers, instruments, and equipment.</li>
        <li>Cancellation or rescheduling terms may be added by the performer and client before signing.</li>
      </ol>
      <div class="signature">
        <div class="line">Performer signature</div>
        <div class="line">Client signature</div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>

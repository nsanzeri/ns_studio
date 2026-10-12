<?php
require_once __DIR__ . '/_common.php';

if (!$financeReady) {
    http_response_code(503);
    echo 'Finance setup is required.';
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
$isManualDocument = (string)($_GET['manual'] ?? '') === '1';
$isManualInvoice = $type === 'invoice' && $isManualDocument;

if (!$isManualDocument && !finance_column_exists($pdo, 'calendars', 'is_main_gig')) {
    http_response_code(503);
    echo 'Main gig calendar setup is required.';
    exit;
}

$userName = trim((string)($user['name'] ?? $user['email'] ?? 'Performer'));
$invoiceProfile = finance_artist_invoice_profile($pdo, $userId);
$artistName = trim((string)($invoiceProfile['artist_name'] ?? ''));
$payableTo = trim((string)($invoiceProfile['invoice_payable_to'] ?? ''));
if ($payableTo === '') {
    $payableTo = $artistName !== '' ? $artistName : $userName;
}
$performerAddress = trim((string)($invoiceProfile['invoice_address'] ?? ''));
$displayEmail = trim((string)($invoiceProfile['contact_email'] ?? ($user['email'] ?? '')));
$acceptsCheck = array_key_exists('invoice_accept_check', $invoiceProfile) ? !empty($invoiceProfile['invoice_accept_check']) : true;
$acceptsCash = !empty($invoiceProfile['invoice_accept_cash']);
$acceptsZelle = !empty($invoiceProfile['invoice_accept_zelle']);
$zelleDetails = trim((string)($invoiceProfile['invoice_zelle_details'] ?? ''));
$paymentTermLines = ['Due upon performance.'];
if ($acceptsCheck) {
    $paymentTermLines[] = 'Check payable to ' . $payableTo . '.';
}
if ($acceptsCash) {
    $paymentTermLines[] = 'Cash accepted.';
}
if ($acceptsZelle) {
    $paymentTermLines[] = 'Zelle accepted' . ($zelleDetails !== '' ? ': ' . $zelleDetails : '.') . ($zelleDetails !== '' ? '.' : '');
}
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
$billToBlock = '';
$clientBlock = '';
$startsAt = null;
$guaranteeCents = 0;
$tipsCents = 0;
$hasKnownAmount = false;
$docSourceId = '';

if ($isManualDocument) {
    $calendarName = '';
    $eventTitle = 'Performance';
    $billToCompany = finance_clean_text($_GET['bill_to_company'] ?? '', 190);
    $billToCareOf = finance_clean_text($_GET['bill_to_care_of'] ?? '', 190);
    $billToAddress = finance_clean_multiline($_GET['bill_to_address'] ?? '', 700);
    $billToPhone = finance_clean_text($_GET['bill_to_phone'] ?? '', 80);
    $billToEmail = finance_clean_text($_GET['bill_to_email'] ?? '', 190);
    $billToLines = array_values(array_filter([
        $billToCompany,
        $billToCareOf ? 'Care of: ' . $billToCareOf : null,
        $billToAddress,
        $billToPhone,
        $billToEmail,
    ], static fn($line) => $line !== null && trim((string)$line) !== ''));
    $billToBlock = $billToLines ? implode("\n", $billToLines) : 'Client billing details to be confirmed';
    $clientLines = array_values(array_filter([
        $billToCompany,
        $billToCareOf ? 'Care of: ' . $billToCareOf : null,
        $billToPhone,
        $billToEmail,
    ], static fn($line) => $line !== null && trim((string)$line) !== ''));
    $clientBlock = implode("\n", $clientLines);
    $invoiceDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? (string)$_GET['date'] : date('Y-m-d');
    $startsAt = new DateTime($invoiceDate);
    $eventLocation = $billToAddress ?: 'Venue / address to be confirmed';
    $guaranteeCents = finance_parse_money($_GET['amount'] ?? 0);
    $hasKnownAmount = $guaranteeCents > 0;
    $docSourceId = substr(hash('sha256', implode('|', [$userId, $eventTitle, $billToBlock, $invoiceDate, $guaranteeCents])), 0, 8);
} elseif ($gigId > 0) {
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
    $billToBlock = trim($eventTitle . "\n" . $eventLocation);
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
    $billToBlock = trim($eventTitle . "\n" . $eventLocation);
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

$amountCents = $type === 'invoice' ? $guaranteeCents : $guaranteeCents + $tipsCents;
$docTitle = $type === 'invoice' ? 'Invoice' : 'Performance Agreement';
$docNumber = strtoupper($type) . '-' . preg_replace('/[^A-Za-z0-9]/', '', $docSourceId) . '-' . $startsAt->format('Ymd');
$documentGeneratedAt = new DateTime('today');
$documentDateLabel = $type === 'invoice' ? 'Invoice Date' : 'Contract Date';
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
    .invoice-meta { margin-top:6px; color:#666; line-height:1.35; }
    .bill-to { border:1px solid #ddd; padding:16px; border-radius:8px; margin-bottom:28px; max-width:480px; }
    table { width:100%; border-collapse:collapse; margin-top:14px; }
    th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
    th:last-child, td:last-child { text-align:right; }
    .total { font-size:20px; font-weight:700; }
    .actions { margin:18px auto; width:min(860px, calc(100vw - 32px)); display:flex; justify-content:flex-end; }
    button { border:0; border-radius:999px; background:#111; color:#fff; padding:12px 18px; font-weight:700; cursor:pointer; }
    .signature { margin-top:34px; display:grid; grid-template-columns:1fr 1fr; gap:30px; }
    .line { border-top:1px solid #111; padding-top:8px; margin-top:50px; }
    .script-signature { font-family:"Brush Script MT", "Segoe Script", "Lucida Handwriting", cursive; font-size:30px; line-height:1; margin:20px 0 -36px; }
    @media print { body { background:#fff; } .page { width:auto; margin:0; box-shadow:none; padding:24px; } .actions { display:none; } }
  </style>
</head>
<body>
  <div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
  <main class="page">
    <div class="head">
      <div>
        <h1><?= e($docTitle) ?></h1>
        <div class="invoice-meta">
          <div><?= e($docNumber) ?></div>
          <div><strong><?= e($documentDateLabel) ?>:</strong> <?= e($documentGeneratedAt->format('F j, Y')) ?></div>
        </div>
      </div>
      <div style="text-align:right;">
        <strong><?= e($payableTo) ?></strong><br>
        <?php if ($performerAddress !== ''): ?>
          <span class="muted"><?= nl2br(e($performerAddress)) ?></span><br>
        <?php endif; ?>
        <span class="muted"><?= e($displayEmail) ?></span>
      </div>
    </div>

    <?php if ($type === 'invoice'): ?>
      <div class="bill-to">
        <strong>Bill To</strong><br>
        <?= nl2br(e($billToBlock)) ?>
        <?php if ($calendarName !== ''): ?><br><span class="muted"><?= e($calendarName) ?></span><?php endif; ?>
      </div>
      <h2>Services</h2>
      <table>
        <thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
          <tr><td><?= e($startsAt->format('F j, Y')) ?></td><td>Performance</td><td><?= $hasKnownAmount ? finance_money($guaranteeCents) : 'To be confirmed' ?></td></tr>
          <tr><td></td><td class="total">Total Due</td><td class="total"><?= $hasKnownAmount ? finance_money($amountCents) : 'To be confirmed' ?></td></tr>
        </tbody>
      </table>
      <h2>Payment Terms</h2>
      <p><?= e(implode(' ', $paymentTermLines)) ?></p>
    <?php else: ?>
      <h2>Agreement</h2>
      <p>This agreement confirms the performance between the performer and the hiring party. The performer will provide live entertainment for the event date and location shown.</p>
      <table>
        <tbody>
          <?php if ($clientBlock !== ''): ?><tr><th>Client</th><td><?= nl2br(e($clientBlock)) ?></td></tr><?php endif; ?>
          <tr><th>Performance date</th><td><?= e($startsAt->format('F j, Y')) ?></td></tr>
          <tr><th>Venue / address</th><td><?= e($eventLocation) ?></td></tr>
          <tr><th>Guaranteed fee</th><td><?= $hasKnownAmount ? finance_money($guaranteeCents) : 'To be confirmed' ?></td></tr>
        </tbody>
      </table>
      <h2>Terms</h2>
      <ol>
        <li>The performer will provide live music for the event date, time, and location listed above.</li>
        <li>The client will provide a safe performance area, reasonable access for setup and load-out, and standard power when needed.</li>
        <li>Any remaining balance should be paid no later than the event date unless both parties agree otherwise.</li>
      </ol>
      <div class="signature">
        <div>
          <div class="script-signature"><?= e($payableTo) ?></div>
          <div class="line">Performer signature</div>
        </div>
        <div class="line">Client signature</div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>

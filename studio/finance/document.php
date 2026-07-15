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

$gigId = (int)($_GET['gig_id'] ?? 0);
if ($gigId <= 0) {
    http_response_code(404);
    echo 'Gig not found.';
    exit;
}

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

$userName = trim((string)($user['name'] ?? $user['email'] ?? 'Performer'));
$startsAt = new DateTime((string)$gig['starts_at']);
$amountCents = (int)$gig['guarantee_cents'] + (int)$gig['tips_cents'];
$docTitle = $type === 'invoice' ? 'Invoice' : 'Performance Agreement';
$docNumber = strtoupper($type) . '-' . (int)$gig['id'] . '-' . $startsAt->format('Ymd');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($docTitle) ?> | <?= e((string)$gig['title']) ?></title>
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
        <?= e((string)$gig['title']) ?><br>
        <span class="muted"><?= e($startsAt->format('F j, Y')) ?></span>
      </div>
      <div class="box">
        <strong>Location</strong><br>
        <?= e((string)($gig['location'] ?: 'Venue / address to be confirmed')) ?><br>
        <span class="muted"><?= e((string)$gig['calendar_name']) ?></span>
      </div>
    </div>

    <?php if ($type === 'invoice'): ?>
      <h2>Invoice Items</h2>
      <table>
        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
          <tr><td>Performance guarantee</td><td><?= finance_money((int)$gig['guarantee_cents']) ?></td></tr>
          <?php if ((int)$gig['tips_cents'] > 0): ?><tr><td>Recorded tips / additional show income</td><td><?= finance_money((int)$gig['tips_cents']) ?></td></tr><?php endif; ?>
          <tr><td class="total">Total Due</td><td class="total"><?= finance_money($amountCents) ?></td></tr>
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
          <tr><th>Show title</th><td><?= e((string)$gig['title']) ?></td></tr>
          <tr><th>Guaranteed fee</th><td><?= finance_money((int)$gig['guarantee_cents']) ?></td></tr>
        </tbody>
      </table>
      <h2>Terms</h2>
      <p>Any schedule, production, hospitality, cancellation, or payment terms may be added by the performer and client before signing.</p>
      <div class="signature">
        <div class="line">Performer signature</div>
        <div class="line">Client signature</div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>

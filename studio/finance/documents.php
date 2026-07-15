<?php
require_once __DIR__ . '/_common.php';

$today = new DateTime('today');
$defaultStart = (clone $today)->modify('-7 days')->format('Y-m-d');
$defaultEnd = (clone $today)->modify('+6 months')->format('Y-m-d');
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['start'] ?? '')) ? (string)$_GET['start'] : $defaultStart;
$endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['end'] ?? '')) ? (string)$_GET['end'] : $defaultEnd;

$mainGigCalendar = null;
$events = [];
$ledgerByEventKey = [];

if ($financeReady && finance_column_exists($pdo, 'calendars', 'is_main_gig')) {
    $mainCalStmt = $pdo->prepare("
        SELECT id, name, color, timezone, ics_url
        FROM calendars
        WHERE user_id = ? AND is_active = 1 AND is_main_gig = 1
        LIMIT 1
    ");
    $mainCalStmt->execute([$userId]);
    $mainGigCalendar = $mainCalStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($mainGigCalendar) {
        try {
            $events = finance_fetch_calendar_events($mainGigCalendar, $startDate, $endDate);
            foreach ($events as &$event) {
                $event['event_key'] = finance_event_key((int)$mainGigCalendar['id'], $event);
            }
            unset($event);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        $keys = array_values(array_filter(array_map(static fn($event) => (string)($event['event_key'] ?? ''), $events)));
        if ($keys) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $ledgerStmt = $pdo->prepare("
                SELECT id, source_event_key, guarantee_cents, tips_cents
                FROM finance_gigs
                WHERE user_id = ?
                  AND calendar_id = ?
                  AND source_event_key IN ({$placeholders})
            ");
            $ledgerStmt->execute(array_merge([$userId, (int)$mainGigCalendar['id']], $keys));
            foreach ($ledgerStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ledgerByEventKey[(string)$row['source_event_key']] = $row;
            }
        }
    }
}

finance_page_head('Contracts & Invoices | Ready Set Shows');
?>
<main class="container finance-shell">
  <?php finance_flash($messages, $errors); ?>

  <section class="finance-card" style="margin-bottom:1rem;">
    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
      <div>
        <h1 style="margin:0 0 .35rem;">Contracts &amp; invoices</h1>
        <p class="finance-muted" style="margin:0;">Generate simple show paperwork from events on your main gig calendar.</p>
      </div>
      <div style="display:flex; gap:.65rem; flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= e(base_url('/finance/index.php')) ?>">Finance</a>
        <a class="btn btn-outline" href="<?= e(base_url('/tools/calendars.php')) ?>">Calendars</a>
      </div>
    </div>
  </section>

  <?php if (!$financeReady): ?>
    <?php finance_install_notice(); ?>
  <?php elseif (!finance_column_exists($pdo, 'calendars', 'is_main_gig')): ?>
    <section class="finance-card"><p class="finance-muted">Run the main gig calendar migration, then refresh this page.</p></section>
  <?php elseif (!$mainGigCalendar): ?>
    <section class="finance-card">
      <h2 style="margin-top:0;">Choose a main gig calendar</h2>
      <p class="finance-muted">Contracts and invoices use the events, dates, and addresses from whichever calendar is marked as your main gig calendar.</p>
      <a class="btn btn-primary" href="<?= e(base_url('/tools/calendars.php')) ?>">Set main gig calendar</a>
    </section>
  <?php elseif (!$isProUser): ?>
    <section class="finance-card">
      <h2 style="margin-top:0;">Pro paperwork tools</h2>
      <p class="finance-muted">Contract and invoice generation is part of Ready Set Shows Pro.</p>
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to Pro</a>
    </section>
  <?php else: ?>
    <section class="finance-card" style="margin-bottom:1rem;">
      <form class="finance-grid" method="get" action="<?= e(base_url('/finance/documents.php')) ?>" style="align-items:end;">
        <div class="finance-field">
          <label for="start">From</label>
          <input class="finance-input" id="start" name="start" type="date" value="<?= e($startDate) ?>">
        </div>
        <div class="finance-field">
          <label for="end">To</label>
          <input class="finance-input" id="end" name="end" type="date" value="<?= e($endDate) ?>">
        </div>
        <div class="finance-field">
          <label>Main gig calendar</label>
          <div class="finance-input" style="display:flex; align-items:center; gap:.55rem;">
            <span style="width:12px; height:12px; border-radius:4px; background:<?= e((string)($mainGigCalendar['color'] ?? '#d4af37')) ?>; display:inline-block;"></span>
            <?= e((string)$mainGigCalendar['name']) ?>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Refresh events</button>
      </form>
    </section>

    <section class="finance-card">
      <h2 style="margin-top:0;">Calendar events</h2>
      <?php if (!$events): ?>
        <p class="finance-muted">No events found in this date range.</p>
      <?php else: ?>
        <div class="finance-table-wrap">
          <table class="finance-table" style="min-width:860px;">
            <thead><tr><th>Date</th><th>Event</th><th>Address</th><th>Ledger</th><th>Documents</th></tr></thead>
            <tbody>
              <?php foreach ($events as $event): ?>
                <?php
                  $eventKey = (string)($event['event_key'] ?? '');
                  $startsAt = new DateTime((string)$event['start']);
                  $ledger = $ledgerByEventKey[$eventKey] ?? null;
                  $docQuery = http_build_query([
                      'calendar_id' => (int)$mainGigCalendar['id'],
                      'event_key' => $eventKey,
                      'start' => $startDate,
                      'end' => $endDate,
                  ]);
                  $ledgerUrl = base_url('/finance/gigs.php?calendar_id=' . (int)$mainGigCalendar['id'] . '&start=' . rawurlencode($startsAt->format('Y-m-d')) . '&end=' . rawurlencode($startsAt->format('Y-m-d')) . '&preview=1');
                ?>
                <tr>
                  <td><?= e($startsAt->format('M j, Y')) ?></td>
                  <td>
                    <strong><?= e((string)($event['summary'] ?? 'Untitled event')) ?></strong>
                    <?php if ($ledger): ?><div class="finance-muted">Ledger amount: <?= finance_money((int)$ledger['guarantee_cents'] + (int)$ledger['tips_cents']) ?></div><?php endif; ?>
                  </td>
                  <td><?= e((string)($event['location'] ?: 'Address to be confirmed')) ?></td>
                  <td><a class="btn btn-outline" href="<?= e($ledgerUrl) ?>">Open ledger</a></td>
                  <td>
                    <div class="finance-doc-actions">
                      <a class="btn btn-outline" href="<?= e(base_url('/finance/document.php?type=contract&' . $docQuery)) ?>" target="_blank" rel="noopener">Contract</a>
                      <a class="btn btn-outline" href="<?= e(base_url('/finance/document.php?type=invoice&' . $docQuery)) ?>" target="_blank" rel="noopener">Invoice</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
<?php finance_page_foot(); ?>

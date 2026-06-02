<?php
require_once __DIR__ . '/_common.php';

$defaultStart = (new DateTimeImmutable('first day of January this year'))->format('Y-m-d');
$defaultEnd = (new DateTimeImmutable('last day of December this year'))->format('Y-m-d');
$startDate = trim((string)($_GET['start'] ?? $_POST['start'] ?? $defaultStart));
$endDate = trim((string)($_GET['end'] ?? $_POST['end'] ?? $defaultEnd));
$calendarId = (int)($_GET['calendar_id'] ?? $_POST['calendar_id'] ?? 0);
$previewEvents = [];

$calStmt = $pdo->prepare("SELECT id, name, color, ics_url, timezone, is_active FROM calendars WHERE user_id = ? ORDER BY is_active DESC, name ASC");
$calStmt->execute([$userId]);
$calendars = $calStmt->fetchAll(PDO::FETCH_ASSOC);
if ($calendarId <= 0 && $calendars) $calendarId = (int)$calendars[0]['id'];

if (!empty($_SESSION['finance_messages']) && is_array($_SESSION['finance_messages'])) {
    $messages = array_merge($messages, $_SESSION['finance_messages']);
    unset($_SESSION['finance_messages']);
}
if (!empty($_SESSION['finance_errors']) && is_array($_SESSION['finance_errors'])) {
    $errors = array_merge($errors, $_SESSION['finance_errors']);
    unset($_SESSION['finance_errors']);
}

if ($financeReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Finance is included with the paid tools plan. Upgrade to continue.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_gigs') {
                $rows = $_POST['gigs'] ?? [];
                if (!is_array($rows)) throw new RuntimeException('No gig rows were submitted.');
                $payoutRows = $_POST['payouts'] ?? [];
                if (!is_array($payoutRows)) $payoutRows = [];
                $ownGig = $pdo->prepare("SELECT id FROM finance_gigs WHERE id = ? AND user_id = ? LIMIT 1");
                $update = $pdo->prepare("
                    UPDATE finance_gigs
                    SET title = ?, venue_name = ?, location = ?, starts_at = ?, guarantee_cents = ?, tips_cents = ?,
                        is_taxable = ?, advertising_cents = ?, miles = ?, notes = ?
                    WHERE id = ? AND user_id = ?
                ");
                $deletePayouts = $pdo->prepare("
                    DELETE p
                    FROM finance_gig_payouts p
                    JOIN finance_gigs g ON g.id = p.gig_id
                    WHERE p.gig_id = ? AND g.user_id = ?
                ");
                $insertPayout = $pdo->prepare("
                    INSERT INTO finance_gig_payouts (gig_id, member_id, amount_cents, notes)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents), notes = VALUES(notes)
                ");
                $saved = 0;
                $pdo->beginTransaction();
                foreach ($rows as $gigId => $row) {
                    if (!is_array($row)) continue;
                    $gigId = (int)$gigId;
                    $title = finance_clean_text($row['title'] ?? '');
                    if ($gigId <= 0 || $title === null) continue;
                    $ownGig->execute([$gigId, $userId]);
                    if (!$ownGig->fetchColumn()) continue;
                    $startsAt = trim((string)($row['starts_at'] ?? ''));
                    $dt = new DateTime($startsAt);
                    $update->execute([
                        $title,
                        finance_clean_text($row['venue_name'] ?? '', 190),
                        finance_clean_text($row['location'] ?? ''),
                        $dt->format('Y-m-d H:i:s'),
                        finance_parse_money($row['guarantee'] ?? ''),
                        finance_parse_money($row['tips'] ?? ''),
                        !empty($row['is_taxable']) ? 1 : 0,
                        finance_parse_money($row['advertising'] ?? ''),
                        max(0, (float)($row['miles'] ?? 0)),
                        finance_clean_text($row['notes'] ?? '', 2000),
                        $gigId,
                        $userId,
                    ]);

                    $deletePayouts->execute([$gigId, $userId]);
                    $gigPayoutRows = $payoutRows[$gigId] ?? [];
                    if (is_array($gigPayoutRows)) {
                        foreach ($gigPayoutRows as $payoutRow) {
                            if (!is_array($payoutRow)) continue;
                            $memberName = trim((string)($payoutRow['member_name'] ?? ''));
                            $amountCents = finance_parse_money($payoutRow['amount'] ?? '');
                            if ($memberName === '' || $amountCents <= 0) continue;
                            $memberId = finance_member_id_for_name($pdo, $userId, mb_substr($memberName, 0, 190));
                            $insertPayout->execute([
                                $gigId,
                                $memberId,
                                $amountCents,
                                finance_clean_text($payoutRow['notes'] ?? '', 255),
                            ]);
                        }
                    }
                    $saved++;
                }
                $pdo->commit();
                $messages[] = $saved . ' gig ' . ($saved === 1 ? 'row' : 'rows') . ' saved.';
            } elseif ($action === 'delete_selected') {
                $selected = $_POST['selected_gig_ids'] ?? [];
                if (!is_array($selected)) throw new RuntimeException('No gigs were selected.');
                $selected = array_values(array_unique(array_filter(array_map('intval', $selected), fn($id) => $id > 0)));
                if (!$selected) throw new RuntimeException('No gigs were selected.');
                $placeholders = implode(',', array_fill(0, count($selected), '?'));
                $stmt = $pdo->prepare("DELETE FROM finance_gigs WHERE user_id = ? AND id IN ({$placeholders})");
                $stmt->execute(array_merge([$userId], $selected));
                $messages[] = $stmt->rowCount() . ' selected ' . ($stmt->rowCount() === 1 ? 'gig was' : 'gigs were') . ' deleted.';
            } elseif ($action === 'import_selected') {
                $selected = $_POST['selected_events'] ?? [];
                if (!is_array($selected) || !$selected) throw new RuntimeException('Choose at least one calendar event to import.');
                $calendar = null;
                foreach ($calendars as $cal) {
                    if ((int)$cal['id'] === $calendarId) $calendar = $cal;
                }
                if (!$calendar) throw new RuntimeException('Calendar not found.');
                $events = finance_fetch_calendar_events($calendar, $startDate, $endDate);
                $eventsByKey = [];
                foreach ($events as $event) $eventsByKey[finance_event_key($calendarId, $event)] = $event;
                $insert = $pdo->prepare("
                    INSERT IGNORE INTO finance_gigs
                      (user_id, calendar_id, source_event_key, title, venue_name, location, starts_at, ends_at, imported_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $added = 0;
                foreach ($selected as $key) {
                    $key = (string)$key;
                    if (!isset($eventsByKey[$key])) continue;
                    $event = $eventsByKey[$key];
                    $insert->execute([
                        $userId,
                        $calendarId,
                        $key,
                        finance_clean_text($event['summary'] ?? 'Gig') ?? 'Gig',
                        finance_clean_text($event['location'] ?? '', 190),
                        finance_clean_text($event['location'] ?? ''),
                        finance_date_for_sql((string)$event['start']),
                        finance_date_for_sql((string)$event['end']),
                    ]);
                    $added += $insert->rowCount();
                }
                $messages[] = $added . ' calendar ' . ($added === 1 ? 'event' : 'events') . ' imported.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
    $_SESSION['finance_messages'] = $messages;
    $_SESSION['finance_errors'] = $errors;
    header('Location: ' . base_url('/finance/gigs.php?calendar_id=' . $calendarId . '&start=' . rawurlencode($startDate) . '&end=' . rawurlencode($endDate)));
    exit;
}

if ($financeReady && isset($_GET['preview']) && $calendarId > 0) {
    try {
        $calendar = null;
        foreach ($calendars as $cal) {
            if ((int)$cal['id'] === $calendarId) $calendar = $cal;
        }
        if ($calendar) $previewEvents = finance_fetch_calendar_events($calendar, $startDate, $endDate);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$gigs = [];
$payoutsByGig = [];
if ($financeReady) {
    $stmt = $pdo->prepare("
        SELECT g.*,
               COALESCE(p.payout_cents, 0) AS payout_cents,
               COALESCE(p.payout_count, 0) AS payout_count
        FROM finance_gigs g
        LEFT JOIN (
          SELECT gig_id, SUM(amount_cents) AS payout_cents, COUNT(*) AS payout_count
          FROM finance_gig_payouts
          GROUP BY gig_id
        ) p ON p.gig_id = g.id
        WHERE g.user_id = ? AND g.starts_at >= ? AND g.starts_at <= ?
        ORDER BY g.starts_at DESC, g.title ASC
    ");
    $stmt->execute([$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $gigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($gigs) {
        $gigIds = array_map(fn($gig) => (int)$gig['id'], $gigs);
        $placeholders = implode(',', array_fill(0, count($gigIds), '?'));
        $payoutStmt = $pdo->prepare("
            SELECT p.gig_id, p.amount_cents, p.notes, m.name AS member_name
            FROM finance_gig_payouts p
            JOIN finance_members m ON m.id = p.member_id
            JOIN finance_gigs g ON g.id = p.gig_id
            WHERE g.user_id = ? AND p.gig_id IN ({$placeholders})
            ORDER BY m.name ASC
        ");
        $payoutStmt->execute(array_merge([$userId], $gigIds));
        foreach ($payoutStmt->fetchAll(PDO::FETCH_ASSOC) as $payout) {
            $payoutsByGig[(int)$payout['gig_id']][] = $payout;
        }
    }
}

finance_page_head('Finance | Gig Ledger');
?>
<main class="container finance-shell">
  <?php finance_flash($messages, $errors); ?>
  <section class="finance-card" style="margin-bottom:1rem;">
    <div class="finance-pill">Finance</div>
    <h1 style="margin:.8rem 0 .35rem;">Gig ledger</h1>
    <p class="finance-muted" style="margin:0;">Bring in calendar events, then edit the money details like a working ledger.</p>
  </section>

  <?php if (!$financeReady): ?>
    <?php finance_install_notice(); ?>
  <?php else: ?>
    <section class="finance-card">
      <h2 style="margin-top:0;">Import from calendar</h2>
      <form method="get" class="finance-stack" action="">
        <div class="finance-two">
          <div class="finance-field">
            <label for="calendar_id">Calendar</label>
            <select class="finance-select" id="calendar_id" name="calendar_id">
              <?php foreach ($calendars as $calendar): ?>
                <option value="<?= (int)$calendar['id'] ?>" <?= (int)$calendar['id'] === $calendarId ? 'selected' : '' ?>><?= e($calendar['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="finance-two">
            <div class="finance-field"><label for="start">From</label><input class="finance-input" id="start" name="start" type="date" value="<?= e($startDate) ?>"></div>
            <div class="finance-field"><label for="end">To</label><input class="finance-input" id="end" name="end" type="date" value="<?= e($endDate) ?>"></div>
          </div>
        </div>
        <div><button class="btn btn-primary" type="submit" name="preview" value="1" <?= $isProUser ? '' : 'disabled' ?>>Preview calendar gigs</button></div>
      </form>

      <?php if ($previewEvents): ?>
        <form method="post" action="" style="margin-top:1rem;">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="import_selected">
          <input type="hidden" name="calendar_id" value="<?= (int)$calendarId ?>">
          <input type="hidden" name="start" value="<?= e($startDate) ?>">
          <input type="hidden" name="end" value="<?= e($endDate) ?>">
          <div class="finance-table-wrap">
            <table class="finance-table" style="min-width:760px;">
              <thead><tr><th></th><th>Date</th><th>Event</th><th>Location</th></tr></thead>
              <tbody>
                <?php foreach ($previewEvents as $event): $key = finance_event_key($calendarId, $event); ?>
                  <tr>
                    <td><input type="checkbox" name="selected_events[]" value="<?= e($key) ?>" checked></td>
                    <td><?= e((new DateTime((string)$event['start']))->format('M j, Y g:i A')) ?></td>
                    <td><?= e((string)$event['summary']) ?></td>
                    <td><?= e((string)$event['location']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="margin-top:1rem;"><button class="btn btn-primary" type="submit">Import selected</button></div>
        </form>
      <?php endif; ?>
    </section>

    <form method="post" class="finance-card" style="margin-top:1rem;" action="">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_gigs">
      <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <h2 style="margin:0;">Editable gig rows</h2>
          <div class="finance-muted"><?= count($gigs) ?> rows between <?= e($startDate) ?> and <?= e($endDate) ?>.</div>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
          <button class="btn btn-outline" type="submit" name="action" value="delete_selected" <?= $gigs ? '' : 'disabled' ?>>Delete selected</button>
          <button class="btn btn-primary" type="submit" <?= $gigs ? '' : 'disabled' ?>>Save gigs</button>
        </div>
      </div>
      <?php if (!$gigs): ?>
        <p class="finance-muted">No imported gigs in this date range yet.</p>
      <?php else: ?>
        <div class="finance-table-wrap">
          <table class="finance-table">
            <thead><tr><th></th><th>Date</th><th>Title</th><th>Venue</th><th>Guarantee</th><th>Tips</th><th>Taxable</th><th>Ads</th><th>Miles</th><th>Payouts</th><th>Net</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($gigs as $gig): $gigId = (int)$gig['id']; $gigPayouts = $payoutsByGig[$gigId] ?? []; $payoutTotal = (int)($gig['payout_cents'] ?? 0); $net = (int)$gig['guarantee_cents'] + (int)$gig['tips_cents'] - (int)$gig['advertising_cents'] - $payoutTotal; ?>
                <tr>
                  <td><input type="checkbox" name="selected_gig_ids[]" value="<?= $gigId ?>"></td>
                  <td><input class="finance-input" type="datetime-local" name="gigs[<?= $gigId ?>][starts_at]" value="<?= e((new DateTime((string)$gig['starts_at']))->format('Y-m-d\TH:i')) ?>"></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][title]" value="<?= e($gig['title']) ?>"></td>
                  <td>
                    <input class="finance-input" name="gigs[<?= $gigId ?>][venue_name]" value="<?= e((string)$gig['venue_name']) ?>" placeholder="Venue">
                    <input class="finance-input" name="gigs[<?= $gigId ?>][location]" value="<?= e((string)$gig['location']) ?>" placeholder="Location" style="margin-top:.35rem;">
                  </td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][guarantee]" value="<?= e(number_format((int)$gig['guarantee_cents'] / 100, 2, '.', '')) ?>"></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][tips]" value="<?= e(number_format((int)$gig['tips_cents'] / 100, 2, '.', '')) ?>"></td>
                  <td class="finance-check-cell"><input class="finance-check" type="checkbox" name="gigs[<?= $gigId ?>][is_taxable]" value="1" <?= !empty($gig['is_taxable']) ? 'checked' : '' ?>></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][advertising]" value="<?= e(number_format((int)$gig['advertising_cents'] / 100, 2, '.', '')) ?>"></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][miles]" value="<?= e((string)$gig['miles']) ?>"></td>
                  <td class="finance-payouts-cell">
                    <details class="finance-payouts">
                      <summary><?= (int)($gig['payout_count'] ?? 0) ?> people &middot; <?= finance_money($payoutTotal) ?></summary>
                      <?php $payoutIndex = 0; ?>
                      <?php foreach ($gigPayouts as $payout): ?>
                        <div class="finance-payout-row">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][member_name]" value="<?= e((string)$payout['member_name']) ?>" placeholder="Member">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][amount]" value="<?= e(number_format((int)$payout['amount_cents'] / 100, 2, '.', '')) ?>" placeholder="Amount">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][notes]" value="<?= e((string)$payout['notes']) ?>" placeholder="Notes" style="grid-column:1 / -1;">
                        </div>
                        <?php $payoutIndex++; ?>
                      <?php endforeach; ?>
                      <?php for ($blank = 0; $blank < 2; $blank++, $payoutIndex++): ?>
                        <div class="finance-payout-row">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][member_name]" placeholder="Member">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][amount]" placeholder="Amount">
                          <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][notes]" placeholder="Notes" style="grid-column:1 / -1;">
                        </div>
                      <?php endfor; ?>
                    </details>
                  </td>
                  <td><?= finance_money($net) ?></td>
                  <td><textarea class="finance-textarea" name="gigs[<?= $gigId ?>][notes]"><?= e((string)$gig['notes']) ?></textarea></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</main>
<?php finance_page_foot(); ?>

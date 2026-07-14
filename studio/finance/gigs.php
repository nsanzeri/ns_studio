<?php
require_once __DIR__ . '/_common.php';

$defaultStart = (new DateTimeImmutable('first day of January this year'))->format('Y-m-d');
$defaultEnd = (new DateTimeImmutable('last day of December this year'))->format('Y-m-d');
$startDate = trim((string)($_GET['start'] ?? $_POST['start'] ?? $defaultStart));
$endDate = trim((string)($_GET['end'] ?? $_POST['end'] ?? $defaultEnd));
$selectedYear = (int)($_GET['year'] ?? 0);
if ($selectedYear >= 2000 && $selectedYear <= 2100) {
    $startDate = sprintf('%04d-01-01', $selectedYear);
    $endDate = sprintf('%04d-12-31', $selectedYear);
} else {
    $selectedYear = 0;
}
$calendarId = (int)($_GET['calendar_id'] ?? $_POST['calendar_id'] ?? 0);
$previewEvents = [];

$calStmt = $pdo->prepare("SELECT id, name, color, ics_url, timezone, is_active FROM calendars WHERE user_id = ? ORDER BY is_active DESC, name ASC");
$calStmt->execute([$userId]);
$calendars = $calStmt->fetchAll(PDO::FETCH_ASSOC);
if ($calendarId <= 0 && $calendars) $calendarId = (int)$calendars[0]['id'];

function finance_import_normalize_header(string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
}

function finance_import_header_map(array $header): array {
    $aliases = [
        'date' => ['date', 'eventdate', 'gigdate', 'start', 'startdate', 'starts', 'startsat'],
        'title' => ['title', 'event', 'eventtitle', 'gigevent', 'gigname', 'gigtitle', 'name'],
        'guarantee' => ['guarantee', 'guaranteed', 'guaranteedpay', 'fee', 'pay', 'basepay', 'guaranteeamount'],
        'tips' => ['tips', 'tip', 'tipamount'],
        'cash_tips' => ['cashtips', 'cashtip', 'cashgratuity', 'cashtipamount'],
        'platform_tips' => ['platformtips', 'platformtip', 'onlinetips', 'onlinetip', 'digitaltips', 'digitaltip', 'cardtips', 'cardtip'],
    ];
    $map = [];
    foreach ($header as $index => $label) {
        $clean = finance_import_normalize_header((string)$label);
        foreach ($aliases as $field => $fieldAliases) {
            if (!isset($map[$field]) && in_array($clean, $fieldAliases, true)) {
                $map[$field] = (int)$index;
            }
        }
    }
    return $map;
}

function finance_import_row_value(array $row, array $map, string $field): string {
    if (!array_key_exists($field, $map)) return '';
    return trim((string)($row[$map[$field]] ?? ''));
}

function finance_import_parse_date($value): ?DateTimeImmutable {
    $raw = trim((string)$value);
    if ($raw === '') return null;
    if (is_numeric($raw) && (float)$raw > 20000 && (float)$raw < 80000) {
        $days = (int)floor((float)$raw);
        $seconds = (int)round((((float)$raw) - $days) * 86400);
        return (new DateTimeImmutable('1899-12-30 00:00:00'))->modify("+{$days} days")->modify("+{$seconds} seconds");
    }
    try {
        return new DateTimeImmutable($raw);
    } catch (Throwable $e) {
        return null;
    }
}

function finance_import_csv_rows(string $path): array {
    $rows = [];
    $raw = (string)file_get_contents($path);
    $raw = preg_replace('/,""\$?([0-9]+)","([0-9]{3}\.[0-9]{2})","/', ',"$$1$2",""', $raw) ?? $raw;
    $raw = preg_replace('/"(\$?[0-9]+(?:\.[0-9])?)"([0-9])(?=\r?\n|$)/', '"$1$2"', $raw) ?? $raw;
    $handle = fopen('php://temp', 'r+');
    if (!$handle) throw new RuntimeException('The uploaded file could not be opened.');
    fwrite($handle, $raw);
    rewind($handle);
    $sample = (string)fgets($handle);
    rewind($handle);
    $delimiter = substr_count($sample, "\t") > substr_count($sample, ',') ? "\t" : ',';
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        if (!$row || !array_filter($row, fn($value) => trim((string)$value) !== '')) continue;
        if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function finance_import_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string {
    $type = (string)($cell['t'] ?? '');
    if ($type === 'inlineStr') {
        return trim((string)($cell->is->t ?? ''));
    }
    $value = trim((string)($cell->v ?? ''));
    if ($type === 's') {
        return (string)($sharedStrings[(int)$value] ?? '');
    }
    return $value;
}

function finance_import_xlsx_rows(string $path): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('XLSX import is not available on this server. Save the file as CSV and try again.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('The XLSX file could not be opened.');

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $item) {
                $parts = [];
                if (isset($item->t)) {
                    $parts[] = (string)$item->t;
                } else {
                    foreach ($item->r as $run) $parts[] = (string)$run->t;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new RuntimeException('The first worksheet could not be read.');
    $xml = simplexml_load_string($sheetXml);
    if (!$xml) throw new RuntimeException('The first worksheet could not be parsed.');

    $rows = [];
    foreach ($xml->sheetData->row as $sheetRow) {
        $row = [];
        foreach ($sheetRow->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?: 'A';
            $index = 0;
            for ($i = 0; $i < strlen($letters); $i++) {
                $index = ($index * 26) + (ord($letters[$i]) - 64);
            }
            $row[$index - 1] = finance_import_xlsx_cell_value($cell, $sharedStrings);
        }
        if (!$row || !array_filter($row, fn($value) => trim((string)$value) !== '')) continue;
        ksort($row);
        $rows[] = array_values($row);
    }
    return $rows;
}

function finance_import_uploaded_rows(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Choose a CSV or XLSX file to import.');
    $name = strtolower((string)($file['name'] ?? ''));
    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) throw new RuntimeException('The uploaded file was not received.');
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Import files must be under 5 MB.');
    if (preg_match('/\.xlsx$/', $name)) return finance_import_xlsx_rows($tmpPath);
    if (preg_match('/\.(csv|tsv|txt)$/', $name)) return finance_import_csv_rows($tmpPath);
    if (preg_match('/\.xls$/', $name)) throw new RuntimeException('Old .xls files are not supported yet. Save as CSV or XLSX and import that file.');
    throw new RuntimeException('Use a CSV, TSV, or XLSX file.');
}

function finance_import_gig_records(PDO $pdo, int $userId, array $rows): array {
    if (!$rows) throw new RuntimeException('The import file did not contain any rows.');
    $headerMap = finance_import_header_map($rows[0]);
    $hasHeader = isset($headerMap['date'], $headerMap['title']);
    $map = $hasHeader ? $headerMap : ['date' => 0, 'title' => 1, 'guarantee' => 2, 'tips' => 3];
    $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
    $findExisting = $pdo->prepare("SELECT id FROM finance_gigs WHERE user_id = ? AND starts_at = ? AND title = ? AND guarantee_cents = ? AND tips_cents = ? LIMIT 1");
    $insert = $pdo->prepare("
        INSERT INTO finance_gigs
          (user_id, title, starts_at, ends_at, guarantee_cents, tips_cents, cash_tips_cents, platform_tips_cents, imported_at)
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NOW())
    ");
    $update = $pdo->prepare("
        UPDATE finance_gigs
        SET guarantee_cents = ?, tips_cents = ?, cash_tips_cents = ?, platform_tips_cents = ?, imported_at = COALESCE(imported_at, NOW())
        WHERE id = ? AND user_id = ?
    ");
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $years = [];
    foreach ($dataRows as $row) {
        if (!is_array($row) || !array_filter($row, fn($value) => trim((string)$value) !== '')) continue;
        $date = finance_import_parse_date(finance_import_row_value($row, $map, 'date'));
        $title = finance_clean_text(finance_import_row_value($row, $map, 'title'));
        if (!$date || $title === null) {
            $skipped++;
            continue;
        }
        $startsAt = $date->format('Y-m-d H:i:s');
        $rowYear = (int)$date->format('Y');
        $years[$rowYear] = ($years[$rowYear] ?? 0) + 1;
        $guaranteeCents = finance_parse_money(finance_import_row_value($row, $map, 'guarantee'));
        $cashTipsCents = finance_parse_money(finance_import_row_value($row, $map, 'cash_tips'));
        $platformTipsCents = finance_parse_money(finance_import_row_value($row, $map, 'platform_tips'));
        $legacyTipsCents = finance_parse_money(finance_import_row_value($row, $map, 'tips'));
        if ($cashTipsCents <= 0 && $platformTipsCents <= 0 && $legacyTipsCents > 0) {
            $platformTipsCents = $legacyTipsCents;
        }
        $tipsCents = $cashTipsCents + $platformTipsCents;

        $findExisting->execute([$userId, $startsAt, $title, $guaranteeCents, $tipsCents]);
        $existingId = (int)($findExisting->fetchColumn() ?: 0);
        if ($existingId > 0) {
            $update->execute([$guaranteeCents, $tipsCents, $cashTipsCents, $platformTipsCents, $existingId, $userId]);
            $updated++;
        } else {
            $insert->execute([$userId, $title, $startsAt, $guaranteeCents, $tipsCents, $cashTipsCents, $platformTipsCents]);
            $imported++;
        }
    }
    ksort($years, SORT_NUMERIC);
    return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'years' => array_keys($years), 'year_counts' => $years];
}

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
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'import_spreadsheet' && !$isProUser) {
                throw new RuntimeException('Spreadsheet import is included with the paid tools plan. Upgrade to import CSV or XLSX files.');
            }

            if ($action === 'save_gigs') {
                $rows = $_POST['gigs'] ?? [];
                if (!is_array($rows)) throw new RuntimeException('No gig rows were submitted.');
                $payoutRows = $_POST['payouts'] ?? [];
                if (!is_array($payoutRows)) $payoutRows = [];
                $ownGig = $pdo->prepare("SELECT id FROM finance_gigs WHERE id = ? AND user_id = ? LIMIT 1");
                $update = $pdo->prepare("
                    UPDATE finance_gigs
                    SET title = ?, starts_at = ?, guarantee_cents = ?, tips_cents = ?,
                        cash_tips_cents = ?, platform_tips_cents = ?,
                        is_taxable = ?, miles = ?, notes = ?
                    WHERE id = ? AND user_id = ?
                ");
                $deletePayouts = $pdo->prepare("
                    DELETE p
                    FROM finance_gig_payouts p
                    JOIN finance_gigs g ON g.id = p.gig_id
                    WHERE p.gig_id = ? AND g.user_id = ?
                ");
                $insertPayout = $pdo->prepare("
                    INSERT INTO finance_gig_payouts (gig_id, member_id, payout_type, amount_cents, notes)
                    VALUES (?, ?, ?, ?, ?)
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
                    $cashTipsCents = finance_parse_money($row['cash_tips'] ?? '');
                    $platformTipsCents = finance_parse_money($row['platform_tips'] ?? '');
                    $update->execute([
                        $title,
                        $dt->format('Y-m-d H:i:s'),
                        finance_parse_money($row['guarantee'] ?? ''),
                        $cashTipsCents + $platformTipsCents,
                        $cashTipsCents,
                        $platformTipsCents,
                        !empty($row['is_taxable']) ? 1 : 0,
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
                                finance_normalize_payout_type($payoutRow['payout_type'] ?? ''),
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
                        null,
                        null,
                        finance_date_for_sql((string)$event['start']),
                        finance_date_for_sql((string)$event['end']),
                    ]);
                    $added += $insert->rowCount();
                }
                $messages[] = $added . ' calendar ' . ($added === 1 ? 'event' : 'events') . ' imported.';
            } elseif ($action === 'import_spreadsheet') {
                $rows = finance_import_uploaded_rows($_FILES['finance_import_file'] ?? []);
                $pdo->beginTransaction();
                $result = finance_import_gig_records($pdo, $userId, $rows);
                $pdo->commit();
                $parts = [];
                if ($result['imported'] > 0) $parts[] = $result['imported'] . ' new ' . ($result['imported'] === 1 ? 'gig' : 'gigs');
                if ($result['updated'] > 0) $parts[] = $result['updated'] . ' updated';
                if ($result['skipped'] > 0) $parts[] = $result['skipped'] . ' skipped';
                $messages[] = $parts ? 'Spreadsheet import finished: ' . implode(', ', $parts) . '.' : 'No importable rows were found.';
                $importYears = array_values(array_unique(array_filter(array_map('intval', $result['years'] ?? []), fn($year) => $year >= 2000 && $year <= 2100)));
                if (count($importYears) === 1) {
                    $startDate = sprintf('%04d-01-01', $importYears[0]);
                    $endDate = sprintf('%04d-12-31', $importYears[0]);
                } elseif (count($importYears) > 1) {
                    $yearParts = [];
                    foreach (($result['year_counts'] ?? []) as $rowYear => $count) {
                        $rowYear = (int)$rowYear;
                        if ($rowYear < 2000 || $rowYear > 2100) continue;
                        $yearParts[] = $rowYear . ': ' . (int)$count;
                    }
                    if ($yearParts) {
                        $messages[] = 'Imported rows span multiple years (' . implode(', ', $yearParts) . '). Check for date typos if that was not intentional.';
                    }
                }
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
$availableYears = [];
if ($financeReady) {
    $yearStmt = $pdo->prepare("
        SELECT DISTINCT YEAR(starts_at) AS gig_year
        FROM finance_gigs
        WHERE user_id = ?
        ORDER BY gig_year DESC
    ");
    $yearStmt->execute([$userId]);
    $availableYears = array_values(array_filter(array_map('intval', $yearStmt->fetchAll(PDO::FETCH_COLUMN)), fn($year) => $year > 0));

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
            SELECT p.gig_id, p.payout_type, p.amount_cents, p.notes, m.name AS member_name
            FROM finance_gig_payouts p
            JOIN finance_members m ON m.id = p.member_id
            JOIN finance_gigs g ON g.id = p.gig_id
            WHERE g.user_id = ? AND p.gig_id IN ({$placeholders})
            ORDER BY FIELD(p.payout_type, 'band_member', 'sound', 'lights', 'advertising', 'insurance', 'travel', 'other'), m.name ASC
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
    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
      <div>
        <div class="finance-pill">Finance</div>
        <h1 style="margin:.8rem 0 .35rem;">Gig ledger</h1>
        <p class="finance-muted" style="margin:0;">Bring in calendar events, then edit the money details like a working ledger.</p>
      </div>
      <a class="btn btn-outline" href="<?= e(base_url('/finance/index.php')) ?>">View summary</a>
    </div>
  </section>

  <?php if (!$financeReady): ?>
    <?php finance_install_notice(); ?>
  <?php else: ?>
    <section class="finance-card" style="margin-bottom:1rem;">
      <h2 style="margin-top:0;">View year</h2>
      <div class="finance-muted">Showing <?= e($startDate) ?> through <?= e($endDate) ?>.</div>
      <?php if ($availableYears): ?>
        <div class="finance-year-links" aria-label="Years with gigs">
          <?php foreach ($availableYears as $year): ?>
            <a class="finance-year-link <?= (int)substr($startDate, 0, 4) === (int)$year && $startDate === sprintf('%04d-01-01', $year) && $endDate === sprintf('%04d-12-31', $year) ? 'active' : '' ?>" href="<?= e(base_url('/finance/gigs.php?calendar_id=' . (int)$calendarId . '&year=' . (int)$year)) ?>"><?= (int)$year ?></a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="finance-muted" style="margin:.85rem 0 0;">No gig years yet. Import calendar events or a spreadsheet to build the list.</p>
      <?php endif; ?>
    </section>

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
        <div><button class="btn btn-primary" type="submit" name="preview" value="1">Preview calendar gigs</button></div>
      </form>

      <?php if ($previewEvents): ?>
        <form method="post" action="" style="margin-top:1rem;">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="import_selected">
          <input type="hidden" name="calendar_id" value="<?= (int)$calendarId ?>">
          <input type="hidden" name="start" value="<?= e($startDate) ?>">
          <input type="hidden" name="end" value="<?= e($endDate) ?>">
          <div class="finance-table-wrap">
            <table class="finance-table" style="min-width:640px;">
              <thead><tr><th></th><th>Date</th><th>Event</th></tr></thead>
              <tbody>
                <?php foreach ($previewEvents as $event): $key = finance_event_key($calendarId, $event); ?>
                  <tr>
                    <td><input type="checkbox" name="selected_events[]" value="<?= e($key) ?>" checked></td>
                    <td><?= e((new DateTime((string)$event['start']))->format('M j, Y g:i A')) ?></td>
                    <td><?= e((string)$event['summary']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="margin-top:1rem;"><button class="btn btn-primary" type="submit">Import selected</button></div>
        </form>
      <?php endif; ?>
    </section>

    <section class="finance-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Import from spreadsheet</h2>
      <form method="post" enctype="multipart/form-data" class="finance-stack" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="import_spreadsheet">
        <input type="hidden" name="calendar_id" value="<?= (int)$calendarId ?>">
        <input type="hidden" name="start" value="<?= e($startDate) ?>">
        <input type="hidden" name="end" value="<?= e($endDate) ?>">
        <div class="finance-two">
          <div class="finance-field">
            <label for="finance_import_file">Spreadsheet file</label>
            <input class="finance-input" id="finance_import_file" name="finance_import_file" type="file" accept=".csv,.tsv,.txt,.xlsx,text/csv,text/tab-separated-values,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
          </div>
          <div class="finance-field">
            <label>Columns</label>
          <div class="finance-muted">Use headers: date, event title, guarantee, cash tips, platform tips. A legacy tips column still imports as platform tips. Headerless files are read as date, event title, guarantee, tips.</div>
          </div>
        </div>
        <?php if (!$isProUser): ?>
          <p class="finance-muted" style="margin:0;">Spreadsheet import is a paid feature. You can still add gigs from calendars and edit the ledger manually.</p>
        <?php endif; ?>
        <div><button class="btn btn-primary" type="submit" <?= $isProUser ? '' : 'disabled' ?>>Import spreadsheet</button></div>
      </form>
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
          <table class="finance-table finance-gig-ledger-table">
            <thead><tr><th class="finance-select-col"><input class="finance-check" type="checkbox" data-finance-select-all aria-label="Select all gigs"></th><th class="finance-date-col">Date</th><th>Event title</th><th>Guarantee</th><th>Cash tips</th><th>Platform tips</th><th>Taxable</th><th>Miles</th><th>Money out</th><th>Net</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($gigs as $gig): $gigId = (int)$gig['id']; $gigPayouts = $payoutsByGig[$gigId] ?? []; $payoutTotal = (int)($gig['payout_cents'] ?? 0); $cashTipsCents = (int)($gig['cash_tips_cents'] ?? 0); $platformTipsCents = (int)($gig['platform_tips_cents'] ?? 0); $net = (int)$gig['guarantee_cents'] + $cashTipsCents + $platformTipsCents - $payoutTotal; ?>
                <tr>
                  <td class="finance-select-col"><input class="finance-check" type="checkbox" name="selected_gig_ids[]" value="<?= $gigId ?>"></td>
                  <td class="finance-date-col"><input class="finance-input finance-date-input" type="date" name="gigs[<?= $gigId ?>][starts_at]" value="<?= e((new DateTime((string)$gig['starts_at']))->format('Y-m-d')) ?>"></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][title]" value="<?= e($gig['title']) ?>"></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][guarantee]" value="<?= e(number_format((int)$gig['guarantee_cents'] / 100, 2, '.', '')) ?>"></td>
                  <td class="finance-tip-col"><input class="finance-input" name="gigs[<?= $gigId ?>][cash_tips]" value="<?= e(number_format($cashTipsCents / 100, 2, '.', '')) ?>"></td>
                  <td class="finance-tip-col"><input class="finance-input" name="gigs[<?= $gigId ?>][platform_tips]" value="<?= e(number_format($platformTipsCents / 100, 2, '.', '')) ?>"></td>
                  <td class="finance-check-cell"><input class="finance-check" type="checkbox" name="gigs[<?= $gigId ?>][is_taxable]" value="1" <?= !empty($gig['is_taxable']) ? 'checked' : '' ?>></td>
                  <td><input class="finance-input" name="gigs[<?= $gigId ?>][miles]" value="<?= e((string)$gig['miles']) ?>"></td>
                  <td class="finance-moneyout-cell">
                    <button class="finance-moneyout-button" type="button" data-finance-open-dialog="financeMoneyOut<?= $gigId ?>">
                      <?= (int)($gig['payout_count'] ?? 0) ?> rows &middot; <?= finance_money($payoutTotal) ?>
                    </button>
                    <dialog class="finance-dialog" id="financeMoneyOut<?= $gigId ?>">
                      <div class="finance-dialog-inner">
                        <div class="finance-dialog-head">
                          <div>
                            <h3 class="finance-dialog-title">Money out</h3>
                            <div class="finance-muted"><?= e($gig['title']) ?></div>
                          </div>
                          <button class="finance-dialog-close" type="button" data-finance-close-dialog aria-label="Close">&times;</button>
                        </div>
                        <div class="finance-payouts" data-finance-payout-list data-gig-id="<?= $gigId ?>" data-next-index="<?= count($gigPayouts) ?>">
                          <?php $payoutIndex = 0; ?>
                          <?php foreach ($gigPayouts as $payout): ?>
                            <div class="finance-payout-row">
                              <select class="finance-select" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][payout_type]">
                                <?php foreach (finance_payout_types() as $typeValue => $typeLabel): ?>
                                  <option value="<?= e($typeValue) ?>" <?= (string)$payout['payout_type'] === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option>
                                <?php endforeach; ?>
                              </select>
                              <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][member_name]" value="<?= e((string)$payout['member_name']) ?>" placeholder="Name">
                              <input class="finance-input" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][amount]" value="<?= e(number_format((int)$payout['amount_cents'] / 100, 2, '.', '')) ?>" placeholder="Amount">
                              <input class="finance-input finance-payout-notes" name="payouts[<?= $gigId ?>][<?= $payoutIndex ?>][notes]" value="<?= e((string)$payout['notes']) ?>" placeholder="Notes">
                            </div>
                            <?php $payoutIndex++; ?>
                          <?php endforeach; ?>
                        </div>
                        <div style="display:flex; gap:.75rem; justify-content:space-between; flex-wrap:wrap;">
                          <button class="btn btn-outline" type="button" data-finance-add-payout>Add row</button>
                          <button class="btn btn-primary" type="button" data-finance-close-dialog>Done</button>
                        </div>
                      </div>
                    </dialog>
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
    <template id="financePayoutRowTemplate">
      <div class="finance-payout-row">
        <select class="finance-select" data-name-template="payouts[__GIG_ID__][__INDEX__][payout_type]">
          <?php foreach (finance_payout_types() as $typeValue => $typeLabel): ?>
            <option value="<?= e($typeValue) ?>"><?= e($typeLabel) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="finance-input" data-name-template="payouts[__GIG_ID__][__INDEX__][member_name]" placeholder="Name">
        <input class="finance-input" data-name-template="payouts[__GIG_ID__][__INDEX__][amount]" placeholder="Amount">
        <input class="finance-input finance-payout-notes" data-name-template="payouts[__GIG_ID__][__INDEX__][notes]" placeholder="Notes">
      </div>
    </template>
    <script>
      document.addEventListener('click', function (event) {
        var selectAll = event.target.closest('[data-finance-select-all]');
        if (selectAll) {
          document.querySelectorAll('input[name="selected_gig_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
          });
          return;
        }

        var openButton = event.target.closest('[data-finance-open-dialog]');
        if (openButton) {
          var dialog = document.getElementById(openButton.getAttribute('data-finance-open-dialog'));
          if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
          return;
        }

        var closeButton = event.target.closest('[data-finance-close-dialog]');
        if (closeButton) {
          var openDialog = closeButton.closest('dialog');
          if (openDialog) openDialog.close();
          return;
        }

        var addButton = event.target.closest('[data-finance-add-payout]');
        if (addButton) {
          var dialog = addButton.closest('dialog');
          var list = dialog ? dialog.querySelector('[data-finance-payout-list]') : null;
          var template = document.getElementById('financePayoutRowTemplate');
          if (!list || !template) return;

          var gigId = list.getAttribute('data-gig-id');
          var index = parseInt(list.getAttribute('data-next-index') || '0', 10);
          var clone = template.content.cloneNode(true);
          clone.querySelectorAll('[data-name-template]').forEach(function (field) {
            field.name = field.getAttribute('data-name-template')
              .replace('__GIG_ID__', gigId)
              .replace('__INDEX__', String(index));
            field.removeAttribute('data-name-template');
          });
          list.appendChild(clone);
          list.setAttribute('data-next-index', String(index + 1));
        }
      });

      document.addEventListener('change', function (event) {
        if (!event.target.matches('input[name="selected_gig_ids[]"]')) return;
        var checkboxes = Array.from(document.querySelectorAll('input[name="selected_gig_ids[]"]'));
        var selectAll = document.querySelector('[data-finance-select-all]');
        if (!selectAll || !checkboxes.length) return;
        var checked = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        selectAll.checked = checked === checkboxes.length;
        selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
      });
    </script>
  <?php endif; ?>
</main>
<?php finance_page_foot(); ?>

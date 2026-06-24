<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../finance/_common.php';

$defaultStart = (new DateTimeImmutable('today'))->format('Y-m-d');
$defaultEnd = (new DateTimeImmutable('+90 days'))->format('Y-m-d');
$startDate = trim((string)($_GET['start'] ?? $_POST['start'] ?? $defaultStart));
$endDate = trim((string)($_GET['end'] ?? $_POST['end'] ?? $defaultEnd));
$calendarId = (int)($_GET['calendar_id'] ?? $_POST['calendar_id'] ?? 0);
$tone = trim((string)($_POST['tone'] ?? 'warm'));
$cta = trim((string)($_POST['cta'] ?? 'Come hang, bring a friend, and enjoy some live music.'));
$link = trim((string)($_POST['link'] ?? ''));
$selectedKeys = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['show_keys'] ?? [])), fn($key) => trim($key) !== '')));
$shows = [];
$selectedShows = [];
$outputs = [];
$calendars = [];

if (publishing_table_exists($pdo, 'calendars')) {
    $calStmt = $pdo->prepare("SELECT id, name, color, ics_url, timezone, is_active FROM calendars WHERE user_id = ? ORDER BY is_active DESC, name ASC");
    $calStmt->execute([$userId]);
    $calendars = $calStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($calendarId <= 0 && $calendars) $calendarId = (int)$calendars[0]['id'];
}

function publishing_fetch_calendar_shows(array $calendar, string $startDate, string $endDate): array {
    $calendarId = (int)($calendar['id'] ?? 0);
    $events = finance_fetch_calendar_events($calendar, $startDate, $endDate);
    return array_map(function (array $event) use ($calendarId): array {
        $key = 'calendar:' . finance_event_key($calendarId, $event);
        return [
            'id' => $key,
            'select_key' => $key,
            'title' => (string)($event['summary'] ?? 'Live music'),
            'venue_name' => '',
            'location' => (string)($event['location'] ?? ''),
            'starts_at' => finance_date_for_sql((string)($event['start'] ?? 'now')),
        ];
    }, $events);
}

function publishing_tone_phrase(string $tone): string {
    return match ($tone) {
        'upbeat' => 'It is going to be a fun one.',
        'polished' => 'Join us for an evening of live music.',
        'casual' => 'Swing by if you are around.',
        default => 'It should be a great night of live music.',
    };
}

function publishing_generate_outputs(array $shows, string $tone, string $cta, string $link): array {
    $count = count($shows);
    if ($count <= 0) return [];

    $phrase = publishing_tone_phrase($tone);
    $linkLine = $link !== '' ? "\nMore info: {$link}" : '';
    $cta = $cta !== '' ? $cta : 'Come hang, bring a friend, and enjoy some live music.';

    if ($count === 1) {
        $show = $shows[0];
        $place = publishing_show_place($show);
        $dateLong = publishing_show_date($show, 'l, F j');
        $dateShort = publishing_show_date($show, 'M j');
        return [
            'Facebook event copy' => "Live music at {$place} on {$dateLong}.\n\n{$phrase} {$cta}{$linkLine}",
            'Instagram caption' => "{$dateShort} at {$place}. {$cta}{$linkLine}",
            'Newsletter blurb' => "I will be at {$place} on {$dateLong}. {$phrase} {$cta}",
            'Venue blurb' => "Live music with " . trim((string)($GLOBALS['user']['display_name'] ?? 'Nick Sanzeri')) . " on {$dateLong}.",
        ];
    }

    $lines = array_map('publishing_show_line', $shows);
    $lineBlock = implode("\n", array_map(fn($line) => '- ' . $line, $lines));
    $first = publishing_show_date($shows[0], 'M j');
    $last = publishing_show_date($shows[$count - 1], 'M j');

    return [
        'Facebook run copy' => "Upcoming live music dates from {$first} through {$last}:\n\n{$lineBlock}\n\n{$cta}{$linkLine}",
        'Instagram run caption' => "Upcoming shows:\n{$lineBlock}\n\n{$cta}{$linkLine}",
        'Newsletter list' => "Here is where you can catch live music next:\n\n{$lineBlock}\n\n{$cta}",
        'Short website blurb' => "Upcoming dates:\n{$lineBlock}",
    ];
}

if (!in_array($tone, ['warm', 'upbeat', 'polished', 'casual'], true)) $tone = 'warm';

$calendar = null;
foreach ($calendars as $cal) {
    if ((int)$cal['id'] === $calendarId) $calendar = $cal;
}
if ($calendar) {
    try {
        $shows = publishing_fetch_calendar_shows($calendar, $startDate, $endDate);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Publishing is included with the paid tools plan. Upgrade to continue.';
    } else {
        $showById = [];
        foreach ($shows as $show) $showById[(string)($show['select_key'] ?? '')] = $show;
        foreach ($selectedKeys as $key) {
            if (isset($showById[$key])) $selectedShows[] = $showById[$key];
        }
        if (!$selectedShows) {
            $errors[] = 'Choose at least one show to generate copy.';
        } else {
            $outputs = publishing_generate_outputs($selectedShows, $tone, $cta, $link);
        }
    }
}

publishing_page_head('Publishing | Gig Promo Writer');
?>
<main class="container publishing-shell">
  <?php publishing_flash($messages, $errors); ?>
  <section class="publishing-card" style="margin-bottom:1rem;">
    <span class="publishing-pill">Publishing</span>
    <h1 style="margin:.8rem 0 .35rem;">Gig Promo Writer</h1>
    <p class="publishing-muted" style="margin:0;">Generate usable promo copy for one show or a group of dates from a connected calendar.</p>
  </section>

  <form id="publishingGenerateForm" method="post" action="">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="start" value="<?= e($startDate) ?>">
    <input type="hidden" name="end" value="<?= e($endDate) ?>">
    <input type="hidden" name="calendar_id" value="<?= (int)$calendarId ?>">
  </form>

  <section class="publishing-grid">
    <section class="publishing-card">
      <h2 style="margin-top:0;">Choose shows</h2>
      <form method="get" class="publishing-stack" action="">
        <div class="publishing-field">
          <label for="calendar_id">Calendar</label>
          <select class="publishing-select" id="calendar_id" name="calendar_id" <?= $calendars ? '' : 'disabled' ?>>
            <?php foreach ($calendars as $calendar): ?>
              <option value="<?= (int)$calendar['id'] ?>" <?= (int)$calendar['id'] === $calendarId ? 'selected' : '' ?>>
                <?= e((string)$calendar['name']) ?><?= empty($calendar['is_active']) ? ' (inactive)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="publishing-grid">
          <div class="publishing-field">
            <label for="start">From</label>
            <input class="publishing-input" id="start" name="start" type="date" value="<?= e($startDate) ?>">
          </div>
          <div class="publishing-field">
            <label for="end">To</label>
            <input class="publishing-input" id="end" name="end" type="date" value="<?= e($endDate) ?>">
          </div>
        </div>
        <div><button class="btn btn-primary" type="submit">Find shows</button></div>
      </form>

      <div class="publishing-stack" style="margin-top:1rem;">
        <?php if (!$shows): ?>
          <p class="publishing-muted">No calendar events found for this calendar and date range.</p>
          <?php if (!$calendars): ?>
            <p><a class="btn btn-outline" href="<?= e(base_url('/tools/calendars.php')) ?>">Connect a calendar</a></p>
          <?php endif; ?>
        <?php else: ?>
          <div class="publishing-show-list">
            <?php foreach ($shows as $show): $key = (string)($show['select_key'] ?? ''); ?>
              <label class="publishing-show-row">
                <input form="publishingGenerateForm" type="checkbox" name="show_keys[]" value="<?= e($key) ?>" <?= in_array($key, $selectedKeys, true) ? 'checked' : '' ?>>
                <span>
                  <strong><?= e(publishing_show_line($show)) ?></strong>
                  <span class="publishing-muted" style="display:block; font-size:.9rem;"><?= e(publishing_clean_show_title((string)$show['title'])) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="publishing-card">
      <h2 style="margin-top:0;">Copy settings</h2>
      <div class="publishing-stack">
        <div class="publishing-field">
          <label for="tone">Tone</label>
          <select form="publishingGenerateForm" class="publishing-select" id="tone" name="tone">
            <?php foreach (['warm' => 'Warm', 'upbeat' => 'Upbeat', 'polished' => 'Polished', 'casual' => 'Casual'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $tone === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="publishing-field">
          <label for="cta">Call to action</label>
          <textarea form="publishingGenerateForm" class="publishing-textarea" id="cta" name="cta"><?= e($cta) ?></textarea>
        </div>
        <div class="publishing-field">
          <label for="link">Optional link</label>
          <input form="publishingGenerateForm" class="publishing-input" id="link" name="link" value="<?= e($link) ?>" placeholder="https://...">
        </div>
        <div class="publishing-actions">
          <button form="publishingGenerateForm" class="btn btn-primary" type="submit" <?= $shows ? '' : 'disabled' ?>>Generate promo copy</button>
          <span class="publishing-muted"><?= count($selectedKeys) ?> selected</span>
        </div>
      </div>
    </section>
  </section>

  <?php if ($outputs): ?>
    <section class="publishing-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Generated copy</h2>
      <div class="publishing-output-grid">
        <?php foreach ($outputs as $label => $copy): ?>
          <div class="publishing-output">
            <label><?= e($label) ?></label>
            <textarea class="publishing-textarea" readonly><?= e($copy) ?></textarea>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php publishing_page_foot(); ?>

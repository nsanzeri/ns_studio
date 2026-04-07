<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

if (!Auth::isLoggedIn()) {
	$_SESSION['login_next'] = base_url('/tools/index.php');
	header('Location: ' . base_url('/member/login.php'));
	exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
	header('Location: ' . base_url('/member/login.php'));
	exit;
}

$today = new DateTime('today');
$oneMonthOut = (clone $today)->modify('+1 month');

$dateFrom = $_GET['date_from'] ?? $today->format('Y-m-d');
$dateTo   = $_GET['date_to'] ?? $oneMonthOut->format('Y-m-d');

$userTimezone = $user['timezone'] ?? 'America/Chicago';

$calStmt = $pdo->prepare("
    SELECT
        id,
        name,
        color,
        timezone,
        ics_url,
        is_active,
        is_default,
        sync_status,
        last_sync_at
    FROM calendars
    WHERE user_id = :user_id
      AND is_active = 1
    ORDER BY is_default DESC, name ASC
");
$calStmt->execute([
		':user_id' => (int)$user['id'],
]);
$connectedCalendars = $calStmt->fetchAll(PDO::FETCH_ASSOC);

$hasCalendars = !empty($connectedCalendars);

$selectedCalendarIds = array_map('intval', $_GET['calendar_ids'] ?? []);
if (!$selectedCalendarIds && $hasCalendars) {
	$selectedCalendarIds = array_map(
			fn($c) => (int)$c['id'],
			$connectedCalendars
			);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar Tools | Nick Sanzeri</title>
  <meta name="description" content="Check shared availability across multiple calendars, print useful views, and export dates for Bandsintown.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .tools-shell{
      padding: 2rem 0 4rem;
    }

    .tools-topbar{
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      gap:1.25rem;
      margin-bottom:1.25rem;
    }

    .tools-topbar h1{
      margin:0 0 .35rem;
    }

    .tools-topbar p{
      margin:0;
      color:rgba(255,255,255,.74);
    }

    .tools-subnav{
      display:flex;
      flex-wrap:wrap;
      gap:.75rem;
      margin-bottom:1.5rem;
      padding:.9rem;
      border-radius:18px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
    }

    .tools-subnav a{
      display:inline-flex;
      align-items:center;
      gap:.5rem;
      padding:.7rem .95rem;
      border-radius:999px;
      text-decoration:none;
      color:rgba(255,255,255,.82);
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
      transition:.2s ease;
    }

    .tools-subnav a:hover,
    .tools-subnav a.active{
      color:#111;
      background:#d4af37;
      border-color:#d4af37;
    }

    .tools-layout{
      display:grid;
      grid-template-columns: 360px minmax(0, 1fr);
      gap:1.5rem;
      align-items:start;
    }

    .tools-card{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:22px;
      padding:1.35rem;
      box-shadow:0 16px 34px rgba(0,0,0,.18);
    }

    .tools-card h2,
    .tools-card h3{
      margin-top:0;
      margin-bottom:.8rem;
    }

    .tools-muted{
      color:rgba(255,255,255,.72);
    }

    .tools-stack{
      display:grid;
      gap:1rem;
    }

    .tools-field{
      display:grid;
      gap:.45rem;
    }

    .tools-field label{
      font-size:.95rem;
      font-weight:500;
      color:#fff;
    }

    .tools-input,
    .tools-select{
      width:100%;
      padding:.85rem .95rem;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.1);
      background:rgba(255,255,255,.05);
      color:#fff;
      font:inherit;
    }

    .tools-input::placeholder{
      color:rgba(255,255,255,.45);
    }

    .tools-checkbox-list{
      display:grid;
      gap:.65rem;
      margin-top:.25rem;
    }

    .tools-check{
      display:flex;
      align-items:center;
      gap:.7rem;
      padding:.75rem .85rem;
      border-radius:14px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
    }

    .tools-check input{
      accent-color:#d4af37;
    }

    .day-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:.65rem;
    }

    .day-chip{
      display:flex;
      align-items:center;
      gap:.55rem;
      padding:.75rem .8rem;
      border-radius:14px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
      color:rgba(255,255,255,.88);
    }

    .tools-actions{
      display:grid;
      gap:.75rem;
      margin-top:.5rem;
    }

    .tools-actions .btn{
      justify-content:center;
    }

    .quick-links{
      display:grid;
      gap:.75rem;
      margin-top:1rem;
    }

    .quick-links a{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:1rem;
      padding:.95rem 1rem;
      border-radius:16px;
      text-decoration:none;
      color:#fff;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
    }

    .quick-links a span{
      color:rgba(255,255,255,.66);
      font-size:.92rem;
    }

    .results-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:1rem;
      margin-bottom:1rem;
    }

    .result-toolbar{
      display:flex;
      flex-wrap:wrap;
      gap:.6rem;
    }

    .result-toolbar a{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.65rem .9rem;
      border-radius:999px;
      text-decoration:none;
      color:rgba(255,255,255,.85);
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
    }

    .results-grid{
      display:grid;
      gap:.9rem;
    }

    .result-row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:1rem;
      padding:1rem 1.1rem;
      border-radius:18px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
    }

    .result-row.available{
      border-color:rgba(93, 201, 126, .28);
      background:rgba(93, 201, 126, .06);
    }

    .result-row.conflict{
      border-color:rgba(255, 159, 67, .25);
      background:rgba(255, 159, 67, .06);
    }

    .result-date{
      font-weight:600;
      color:#fff;
      font-size:1.05rem;
    }

    .result-note{
      margin-top:.2rem;
      color:rgba(255,255,255,.72);
      font-size:.95rem;
    }

    .status-pill{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.45rem .7rem;
      border-radius:999px;
      font-size:.82rem;
      font-weight:600;
      letter-spacing:.04em;
      text-transform:uppercase;
      white-space:nowrap;
    }

    .status-pill.open{
      background:rgba(93, 201, 126, .14);
      color:#7fe29b;
    }

    .status-pill.partial{
      background:rgba(255, 159, 67, .14);
      color:#ffbf78;
    }

    .empty-state{
      display:grid;
      gap:1rem;
      text-align:left;
    }

    .empty-hero{
      padding:1.2rem;
      border-radius:18px;
      background:
        linear-gradient(145deg, rgba(255,255,255,.07), rgba(255,255,255,.03)),
        radial-gradient(circle at top left, rgba(212,175,55,.18), transparent 35%),
        #111;
      border:1px solid rgba(255,255,255,.08);
    }

    .empty-steps{
      margin:0;
      padding-left:1.15rem;
      color:rgba(255,255,255,.82);
      line-height:1.8;
    }

    .demo-box{
      margin-top:1rem;
      padding:1rem;
      border-radius:16px;
      background:rgba(255,255,255,.04);
      border:1px dashed rgba(255,255,255,.16);
    }

    .small-note{
      font-size:.9rem;
      color:rgba(255,255,255,.62);
    }

    .error-box{
      padding:1rem 1.1rem;
      border-radius:16px;
      background:rgba(255,107,107,.1);
      border:1px solid rgba(255,107,107,.25);
      color:#ffb3b3;
    }

    .result-month{
      margin:1.25rem 0 .35rem;
      color:#d4af37;
      font-weight:700;
      letter-spacing:.04em;
      text-transform:uppercase;
      font-size:.9rem;
    }

    .result-summary{
      margin-bottom:1rem;
      color:rgba(255,255,255,.72);
    }

    @media (max-width: 980px){
      .tools-layout{
        grid-template-columns:1fr;
      }

      .tools-topbar{
        flex-direction:column;
        align-items:flex-start;
      }

      .results-header{
        flex-direction:column;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="tools-shell">
  <div class="container">

    <div class="tools-topbar">
      <div>
        <p class="eyebrow">Ready Set Shows</p>
        <h1>Calendar Tools</h1>
        <p>Check shared availability, print useful date views, and keep your booking workflow moving.</p>
      </div>
      <div class="tools-muted">
        Signed in as <?= e($user['email'] ?? 'your account') ?>
      </div>
    </div>

    <nav class="tools-subnav" aria-label="Calendar tools navigation">
      <a class="active" href="<?= e(base_url('/tools/index.php')) ?>">
        <i class="fa-regular fa-calendar-check"></i> Availability
      </a>
      <a href="<?= e(base_url('/tools/pretty-print.php')) ?>">
        <i class="fa-solid fa-print"></i> Print Views
      </a>
      <a href="<?= e(base_url('/tools/bandsintown.php')) ?>">
        <i class="fa-solid fa-file-csv"></i> Bandsintown Export
      </a>
      <a href="<?= e(base_url('/tools/calendars.php')) ?>">
        <i class="fa-solid fa-link"></i> Manage Calendars
      </a>
      <a href="<?= e(base_url('/tools/settings.php')) ?>">
        <i class="fa-solid fa-gear"></i> Settings
      </a>
    </nav>

    <div class="tools-layout">
      <aside class="tools-stack">
        <section class="tools-card">
          <h2>Check Availability</h2>
          <p class="tools-muted" style="margin-top:-.25rem; margin-bottom:1rem;">
            Pick your calendars, choose a date range, and see which dates are truly open.
          </p>

          <form id="availabilityForm" method="get" action="<?= e(base_url('/tools/index.php')) ?>">
            <div class="tools-stack">
              <div class="tools-field">
                <label for="date_from">Date From</label>
                <input class="tools-input" type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
              </div>

              <div class="tools-field">
                <label for="date_to">Date To</label>
                <input class="tools-input" type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
              </div>

              <div class="tools-field">
                <label>Calendars</label>

                <?php if ($hasCalendars): ?>
                  <div class="tools-checkbox-list">
                    <?php foreach ($connectedCalendars as $calendar): ?>
                      <?php
                        $calendarId = (int)$calendar['id'];
                        $isChecked = in_array($calendarId, $selectedCalendarIds, true);
                        $dotColor = !empty($calendar['color']) ? $calendar['color'] : '#d4af37';
                      ?>
                      <label class="tools-check">
                        <input
                          type="checkbox"
                          name="calendar_ids[]"
                          value="<?= $calendarId ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                        >
                        <span style="display:inline-flex;align-items:center;gap:.6rem;">
                          <span style="width:10px;height:10px;border-radius:999px;background:<?= e($dotColor) ?>;display:inline-block;"></span>
                          <span><?= e($calendar['name']) ?></span>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="demo-box">
                    <strong style="color:#fff;">No calendars connected yet</strong>
                    <p class="small-note" style="margin:.45rem 0 0;">
                      Connect one or more iCal feeds to start checking real availability.
                    </p>
                  </div>
                <?php endif; ?>
              </div>

              <div class="tools-field">
                <label>Days of Week</label>
                <div class="day-grid">
                  <?php
                    $days = [
                      'sun' => 'Sunday',
                      'mon' => 'Monday',
                      'tue' => 'Tuesday',
                      'wed' => 'Wednesday',
                      'thu' => 'Thursday',
                      'fri' => 'Friday',
                      'sat' => 'Saturday',
                    ];
                    foreach ($days as $value => $label):
                  ?>
                    <label class="day-chip">
                      <input type="checkbox" name="days[]" value="<?= e($value) ?>" <?= empty($_GET['days']) || in_array($value, $_GET['days'] ?? [], true) ? 'checked' : '' ?>>
                      <span><?= e($label) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="tools-actions">
                <button class="btn btn-primary" type="submit">
                  <i class="fa-solid fa-magnifying-glass"></i>&nbsp; Check Availability
                </button>

                <?php if (!$hasCalendars): ?>
                  <a class="btn btn-secondary" href="<?= e(base_url('/tools/calendars.php')) ?>">
                    <i class="fa-solid fa-link"></i>&nbsp; Connect Calendars
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </form>
        </section>

        <section class="tools-card">
          <h3>Quick Links</h3>
          <div class="quick-links">
            <a href="<?= e(base_url('/tools/calendars.php')) ?>">
              <div>
                <strong>Manage Calendars</strong><br>
                <span>Add or remove iCal feeds</span>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="<?= e(base_url('/tools/pretty-print.php')) ?>">
              <div>
                <strong>Printable Views</strong><br>
                <span>Create clean date lists and schedules</span>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>

            <a href="<?= e(base_url('/tools/bandsintown.php')) ?>">
              <div>
                <strong>Bandsintown Export</strong><br>
                <span>Generate a CSV for upload workflows</span>
              </div>
              <i class="fa-solid fa-chevron-right"></i>
            </a>
          </div>
        </section>
      </aside>

      <section class="tools-card">
        <div class="results-header">
          <div>
            <p class="eyebrow" style="margin-bottom:.45rem;">Results</p>
            <h2 style="margin-bottom:.4rem;">Open dates</h2>
            <p class="tools-muted" style="margin:0;">
              <?php if ($hasCalendars): ?>
                Shared availability across your selected calendars will appear here.
              <?php else: ?>
                This is where matching open dates will appear once calendars are connected.
              <?php endif; ?>
            </p>
          </div>

          <div class="result-toolbar">
            <a href="<?= e(base_url('/tools/pretty-print.php')) ?>">
              <i class="fa-solid fa-print"></i> Print
            </a>
            <a href="<?= e(base_url('/tools/bandsintown.php')) ?>">
              <i class="fa-solid fa-file-export"></i> Export
            </a>
          </div>
        </div>

        <div id="resultsEmpty" class="empty-state">
          <?php if ($hasCalendars): ?>
            <div class="empty-hero">
              <h3 style="margin-top:0;">Ready to check availability</h3>
              <p class="tools-muted" style="margin-bottom:0;">
                Choose your calendars and date range, then click <strong>Check Availability</strong>.
              </p>
            </div>
          <?php else: ?>
            <div class="empty-hero">
              <h3 style="margin-top:0;">Start by connecting your calendars</h3>
              <p class="tools-muted" style="margin-bottom:0;">
                Once your iCal feeds are connected, this page becomes your command center for checking shared availability fast.
              </p>
            </div>

            <div class="tools-card" style="padding:1.1rem; background:rgba(255,255,255,.03);">
              <h3 style="margin-top:0;">How it works</h3>
              <ol class="empty-steps">
                <li>Add one or more iCal links</li>
                <li>Select a date range and calendars</li>
                <li>Find real open dates across the group</li>
                <li>Print or export the results</li>
              </ol>
            </div>

            <div class="demo-box">
              <strong style="color:#fff;">Ready to get started?</strong>
              <p class="small-note" style="margin:.45rem 0 .9rem;">
                Add your first calendar feed and this page will start showing real shared availability.
              </p>
              <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?= e(base_url('/tools/calendars.php')) ?>">Connect Calendars</a>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div id="resultsLoading" class="demo-box" style="display:none;">
          Loading availability…
        </div>

        <div id="resultsError" class="error-box" style="display:none;"></div>

        <div id="resultsGrid" class="results-grid" style="display:none;"></div>
      </section>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
const USER_TIMEZONE = <?= json_encode($userTimezone) ?>;

function formatPretty(date) {
  return date.toLocaleDateString("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    timeZone: USER_TIMEZONE
  });
}

function formatMonthHeader(date) {
  return date.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
    timeZone: USER_TIMEZONE
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

async function getICalEvents(calendarId, startDate, endDate) {
  const url = `<?= e(base_url('/api/fetch_ics.php')) ?>?id=${encodeURIComponent(calendarId)}&start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
  const res = await fetch(url, { credentials: "same-origin" });

  if (!res.ok) {
    throw new Error(`Failed to fetch calendar ${calendarId}`);
  }

  const data = await res.json();

  if (!data.success) {
    throw new Error(data.error || "Unknown error");
  }

  return data.events.map(ev => ({
    start: new Date(ev.start),
    end: new Date(ev.end)
  }));
}

function renderResults(freeDates, startStr, endStr) {
  const resultsGrid = document.getElementById("resultsGrid");
  const resultsEmpty = document.getElementById("resultsEmpty");
  const resultsError = document.getElementById("resultsError");
  const resultsLoading = document.getElementById("resultsLoading");

  resultsLoading.style.display = "none";
  resultsError.style.display = "none";
  resultsGrid.style.display = "none";
  resultsGrid.innerHTML = "";

  if (!freeDates.length) {
    resultsEmpty.style.display = "block";
    resultsEmpty.innerHTML = `
      <div class="empty-hero">
        <h3 style="margin-top:0;">No open dates found</h3>
        <p class="tools-muted" style="margin-bottom:0;">
          No shared availability was found between ${escapeHtml(startStr)} and ${escapeHtml(endStr)} for the calendars and weekdays you selected.
        </p>
      </div>
    `;
    return;
  }

  resultsEmpty.style.display = "none";

  let currentMonth = "";
  let html = `<div class="result-summary">Found ${freeDates.length} open date${freeDates.length === 1 ? "" : "s"}.</div>`;

  for (const date of freeDates) {
    const monthHeader = formatMonthHeader(date);
    if (monthHeader !== currentMonth) {
      currentMonth = monthHeader;
      html += `<div class="result-month">${escapeHtml(monthHeader)}</div>`;
    }

    html += `
      <article class="result-row available">
        <div>
          <div class="result-date">${escapeHtml(formatPretty(date))}</div>
          <div class="result-note">All selected calendars are open</div>
        </div>
        <div class="status-pill open">
          <i class="fa-solid fa-circle-check"></i> Available
        </div>
      </article>
    `;
  }

  resultsGrid.innerHTML = html;
  resultsGrid.style.display = "grid";
}

async function findAvailableDates(event) {
  if (event) {
    event.preventDefault();
  }

  const resultsGrid = document.getElementById("resultsGrid");
  const resultsEmpty = document.getElementById("resultsEmpty");
  const resultsError = document.getElementById("resultsError");
  const resultsLoading = document.getElementById("resultsLoading");

  const startStr = document.getElementById("date_from").value;
  const endStr   = document.getElementById("date_to").value;

  if (!startStr || !endStr) {
    resultsEmpty.style.display = "none";
    resultsGrid.style.display = "none";
    resultsLoading.style.display = "none";
    resultsError.style.display = "block";
    resultsError.textContent = "Please select both dates.";
    return;
  }

  const selectedDays = Array.from(
    document.querySelectorAll("input[name='days[]']:checked")
  ).map(c => {
    const map = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };
    return map[c.value];
  });

  if (!selectedDays.length) {
    resultsEmpty.style.display = "none";
    resultsGrid.style.display = "none";
    resultsLoading.style.display = "none";
    resultsError.style.display = "block";
    resultsError.textContent = "Select at least one weekday.";
    return;
  }

  const selectedCalendars = Array.from(
    document.querySelectorAll("input[name='calendar_ids[]']:checked")
  ).map(c => c.value);

  if (!selectedCalendars.length) {
    resultsEmpty.style.display = "none";
    resultsGrid.style.display = "none";
    resultsLoading.style.display = "none";
    resultsError.style.display = "block";
    resultsError.textContent = "Select at least one calendar.";
    return;
  }

  resultsError.style.display = "none";
  resultsGrid.style.display = "none";
  resultsEmpty.style.display = "none";
  resultsLoading.style.display = "block";

  try {
    const calendarEvents = await Promise.all(
      selectedCalendars.map(id => getICalEvents(id, startStr, endStr))
    );

    const startDate = new Date(startStr + "T00:00:00");
    const endDate = new Date(endStr + "T00:00:00");
    const freeDates = [];

    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
      if (!selectedDays.includes(d.getDay())) {
        continue;
      }

      const dayStart = new Date(d);
      dayStart.setHours(0, 0, 0, 0);

      const dayEnd = new Date(d);
      dayEnd.setHours(23, 59, 59, 999);

      let allFree = true;

      for (const calEvents of calendarEvents) {
        const busy = calEvents.some(ev => {
          const s = new Date(ev.start);
          const e = new Date(ev.end);

          const evtStart = new Date(s.getFullYear(), s.getMonth(), s.getDate(), 0, 0, 0);
          const evtEnd   = new Date(e.getFullYear(), e.getMonth(), e.getDate(), 23, 59, 59);

          return evtStart <= dayEnd && evtEnd >= dayStart;
        });

        if (busy) {
          allFree = false;
          break;
        }
      }

      if (allFree) {
        freeDates.push(new Date(d));
      }
    }

    renderResults(freeDates, startStr, endStr);
  } catch (err) {
    resultsLoading.style.display = "none";
    resultsGrid.style.display = "none";
    resultsEmpty.style.display = "none";
    resultsError.style.display = "block";
    resultsError.textContent = "Error: " + err.message;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("availabilityForm");

  form.addEventListener("submit", findAvailableDates);

  const hasCalendars = <?= $hasCalendars ? 'true' : 'false' ?>;
  if (hasCalendars) {
    findAvailableDates();
  }
});
</script>
</body>
</html>
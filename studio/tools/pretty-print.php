<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

if (!Auth::isLoggedIn()) {
	$_SESSION['login_next'] = base_url('/tools/pretty-print.php');
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
$selectedCalendarId = isset($_GET['calendar_id']) ? (int)$_GET['calendar_id'] : 0;
if (!$selectedCalendarId && $hasCalendars) {
	$selectedCalendarId = (int)$connectedCalendars[0]['id'];
}

$selectedFormat = $_GET['format'] ?? 'newsletter';
if (!in_array($selectedFormat, ['newsletter', 'spreadsheet', 'print'], true)) {
	$selectedFormat = 'newsletter';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Printable Views | Nick Sanzeri</title>
  <meta name="description" content="Pretty-print actual calendar events from one selected calendar in multiple useful formats.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .tools-shell{
      padding:2rem 0 4rem;
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
      grid-template-columns:360px minmax(0, 1fr);
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

    .tools-input{
      width:100%;
      padding:.85rem .95rem;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.1);
      background:rgba(255,255,255,.05);
      color:#fff;
      font:inherit;
    }

    .tools-radio-list{
      display:grid;
      gap:.7rem;
      margin-top:.25rem;
    }

    .tools-radio{
      display:flex;
      align-items:center;
      gap:.7rem;
      padding:.8rem .9rem;
      border-radius:14px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
      color:#fff;
    }

    .tools-radio input{
      accent-color:#d4af37;
    }

    .calendar-dot{
      width:12px;
      height:12px;
      border-radius:999px;
      display:inline-block;
      flex:0 0 12px;
      box-shadow:0 0 0 2px rgba(255,255,255,.08);
    }

    .format-box{
      display:grid;
      gap:.65rem;
    }

    .format-option{
      display:flex;
      align-items:center;
      gap:.7rem;
      padding:.8rem .9rem;
      border-radius:14px;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.06);
      color:#fff;
    }

    .format-option input{
      accent-color:#d4af37;
    }

    .tools-actions{
      display:grid;
      gap:.75rem;
      margin-top:.5rem;
    }

    .tools-actions .btn{
      justify-content:center;
    }

    .preview-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:1rem;
      margin-bottom:1rem;
    }

    .preview-toolbar{
      display:flex;
      flex-wrap:wrap;
      gap:.6rem;
    }

    .preview-toolbar .btn{
      justify-content:center;
    }

	.output-wrap{
	  background:rgba(255,255,255,.04);
	  border:1px solid rgba(255,255,255,.08);
	  border-radius:18px;
	  padding:1rem;
	}
	
	.pretty-output{
	  margin:0;
	  width:100%;
	  min-height:420px;
	  max-height:520px;
	  overflow:auto;
	  white-space:pre-wrap;
	  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	  font-size:.98rem;
	  line-height:1.65;
	  color:#fff;
	  background:transparent;
	  border:none;
	}

    .button-row{
      display:flex;
      flex-wrap:wrap;
      gap:.75rem;
      margin-top:1rem;
    }

    .empty-state{
      display:grid;
      gap:1rem;
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
      margin-bottom:1rem;
    }

    @media (max-width:980px){
      .tools-layout{
        grid-template-columns:1fr;
      }

      .tools-topbar{
        flex-direction:column;
        align-items:flex-start;
      }

      .preview-header{
        flex-direction:column;
      }
    }

    @media print{
      body{
        background:#fff !important;
        color:#111 !important;
      }

      header,
      footer,
      .tools-topbar,
      .tools-subnav,
      .tools-layout > aside,
      .preview-toolbar{
        display:none !important;
      }

      .tools-shell{
        padding:0 !important;
      }

      .container{
        width:100% !important;
        max-width:none !important;
        padding:0 !important;
        margin:0 !important;
      }

      .tools-layout{
        display:block !important;
      }

      .tools-card{
        background:transparent !important;
        border:none !important;
        box-shadow:none !important;
        padding:0 !important;
      }

      .output-wrap{
        border:none !important;
        box-shadow:none !important;
        padding:0 !important;
      }

      .pretty-output{
        max-height:none !important;
        overflow:visible !important;
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
        <h1>Pretty Print Calendar Events</h1>
        <p>Pull the actual contents of one calendar and format them for newsletters, printouts, or simple exports.</p>
      </div>
      <div class="tools-muted">
        Signed in as <?= e($user['email'] ?? 'your account') ?>
      </div>
    </div>

    <nav class="tools-subnav" aria-label="Calendar tools navigation">
      <a href="<?= e(base_url('/tools/index.php')) ?>">
        <i class="fa-regular fa-calendar-check"></i> Availability
      </a>
      <a class="active" href="<?= e(base_url('/tools/pretty-print.php')) ?>">
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
          <h2>Pretty Print</h2>
          <p class="tools-muted" style="margin-top:-.25rem; margin-bottom:1rem;">
            Choose a calendar, set the date range, pick an output style, and generate formatted event text.
          </p>

          <form id="prettyPrintForm" method="get" action="<?= e(base_url('/tools/pretty-print.php')) ?>">
            <div class="tools-stack">
              <div class="tools-field">
                <label for="startDate">Start Date</label>
                <input class="tools-input" type="date" id="startDate" name="date_from" value="<?= e($dateFrom) ?>">
              </div>

              <div class="tools-field">
                <label for="endDate">End Date</label>
                <input class="tools-input" type="date" id="endDate" name="date_to" value="<?= e($dateTo) ?>">
              </div>

              <div class="tools-field">
                <label>Select a Calendar</label>

                <?php if ($hasCalendars): ?>
                  <div class="tools-radio-list">
                    <?php foreach ($connectedCalendars as $calendar): ?>
                      <?php
                        $calendarId = (int)$calendar['id'];
                        $dotColor = !empty($calendar['color']) ? $calendar['color'] : '#d4af37';
                        $isChecked = ($calendarId === $selectedCalendarId);
                      ?>
                      <label class="tools-radio">
                        <input
                          type="radio"
                          name="calendar_id"
                          value="<?= $calendarId ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                        >
                        <span class="calendar-dot" style="background:<?= e($dotColor) ?>;"></span>
                        <span><?= e($calendar['name']) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="demo-box">
                    <strong style="color:#fff;">No calendars connected yet</strong>
                    <p class="small-note" style="margin:.45rem 0 0;">
                      Add one or more iCal feeds to generate formatted calendar output.
                    </p>
                  </div>
                <?php endif; ?>
              </div>

              <div class="tools-field">
                <label>Select Output Format</label>
                <div class="format-box">
                  <label class="format-option">
                    <input type="radio" name="format" value="newsletter" <?= $selectedFormat === 'newsletter' ? 'checked' : '' ?>>
                    <span>Newsletter</span>
                  </label>
                  <label class="format-option">
                    <input type="radio" name="format" value="spreadsheet" <?= $selectedFormat === 'spreadsheet' ? 'checked' : '' ?>>
                    <span>Spreadsheet (CSV)</span>
                  </label>
                  <label class="format-option">
                    <input type="radio" name="format" value="print" <?= $selectedFormat === 'print' ? 'checked' : '' ?>>
                    <span>Print Format</span>
                  </label>
                </div>
              </div>

              <div class="tools-actions">
                <button class="btn btn-primary" type="submit">
                  <i class="fa-solid fa-wand-magic-sparkles"></i>&nbsp; Generate
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
      </aside>

      <section class="tools-card">
        <div class="preview-header">
          <div>
            <p class="eyebrow" style="margin-bottom:.45rem;">Preview</p>
            <h2 style="margin-bottom:.4rem;">Formatted output</h2>
            <p class="tools-muted" style="margin:0;">
              Generate text from a single calendar and use it however you need.
            </p>
          </div>

          <?php if ($hasCalendars): ?>
            <div class="preview-toolbar">
              <button class="btn btn-secondary" type="button" onclick="copyOutput()">
                <i class="fa-regular fa-copy"></i>&nbsp; Copy
              </button>
              <button class="btn btn-secondary" type="button" onclick="exportCSV()">
                <i class="fa-solid fa-file-csv"></i>&nbsp; Export CSV
              </button>
              <button class="btn btn-secondary" type="button" onclick="exportTXT()">
                <i class="fa-regular fa-file-lines"></i>&nbsp; Export TXT
              </button>
              <button class="btn btn-secondary" type="button" onclick="window.print()">
                <i class="fa-solid fa-print"></i>&nbsp; Print
              </button>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$hasCalendars): ?>
          <div class="empty-state">
            <div class="empty-hero">
              <h3 style="margin-top:0;">Start by connecting your calendars</h3>
              <p class="tools-muted" style="margin-bottom:0;">
                Once your iCal feeds are connected, this page can turn real calendar events into clean formatted text.
              </p>
            </div>

            <div class="demo-box">
              <strong style="color:#fff;">Ready to get started?</strong>
              <p class="small-note" style="margin:.45rem 0 .9rem;">
                Add your first calendar feed and come back here to generate event copy.
              </p>
              <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?= e(base_url('/tools/calendars.php')) ?>">Connect Calendars</a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div id="errorBox" class="error-box" style="display:none;"></div>

          <div class="output-wrap">
            <pre class="pretty-output" id="output">Choose a calendar and click Generate.</pre>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php if ($hasCalendars): ?>
<script>
const USER_TIMEZONE = <?= json_encode($userTimezone) ?>;

async function fetchEvents(calendarId, start, end) {
  const url = `<?= e(base_url('/api/fetch_ics.php')) ?>?id=${encodeURIComponent(calendarId)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
  const r = await fetch(url, { credentials: "same-origin" });

  if (!r.ok) {
    throw new Error("Failed to fetch calendar events.");
  }

  const data = await r.json();
  if (!data.success) {
    throw new Error(data.error || "Failed to fetch calendar events.");
  }

  return data.events || [];
}

function formatNewsletterDate(d) {
  const weekday = d.toLocaleDateString("en-US", { weekday: "short", timeZone: USER_TIMEZONE });
  const month = d.toLocaleString("en-US", { month: "long", timeZone: USER_TIMEZONE });
  const day = d.toLocaleDateString("en-US", { day: "numeric", timeZone: USER_TIMEZONE });
  const time = d.toLocaleString("en-US", {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    timeZone: USER_TIMEZONE
  }).toLowerCase();

  return `${weekday}, ${month} ${day} at ${time}`;
}

function formatSpreadsheetDate(d) {
  const parts = d.toLocaleDateString("en-US", {
    month: "numeric",
    day: "numeric",
    year: "2-digit",
    timeZone: USER_TIMEZONE
  }).split("/");
  return `${parts[0]}/${parts[1]}/${parts[2]}`;
}

function formatPrintDate(d) {
  const weekday = d.toLocaleDateString("en-US", { weekday: "short", timeZone: USER_TIMEZONE });
  const month = d.toLocaleString("en-US", { month: "short", timeZone: USER_TIMEZONE });
  const day = d.toLocaleDateString("en-US", { day: "numeric", timeZone: USER_TIMEZONE });
  return `${weekday} ${month} ${day}`;
}

function monthHeader(d) {
  return d.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
    timeZone: USER_TIMEZONE
  });
}

function unescapeICal(str) {
  if (!str || typeof str !== "string") return "";
  return str
    .replace(/\\,/g, ",")
    .replace(/\\;/g, ";")
    .replace(/\\\\/g, "\\")
    .replace(/\\n/g, "\n");
}

function extractCity(location) {
  if (!location || typeof location !== "string") return "";
  const parts = location.split(",");
  return unescapeICal(parts[parts.length - 3]?.trim() || "");
}

function extractSt(location) {
  if (!location || typeof location !== "string") return "";
  const parts = location.split(",");
  return unescapeICal(parts[parts.length - 2]?.trim() || "");
}

function csvEscape(value) {
  const str = String(value ?? "");
  if (/[",\n]/.test(str)) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}

async function generatePrettyPrint(event) {
  if (event) {
    event.preventDefault();
  }

  const output = document.getElementById("output");
  const errorBox = document.getElementById("errorBox");
  output.textContent = "Loading...";
  errorBox.style.display = "none";
  errorBox.textContent = "";

  const cal = document.querySelector("input[name='calendar_id']:checked");
  if (!cal) {
    output.textContent = "";
    errorBox.style.display = "block";
    errorBox.textContent = "Please select a calendar.";
    return;
  }

  const start = document.getElementById("startDate").value;
  const end = document.getElementById("endDate").value;
  if (!start || !end) {
    output.textContent = "";
    errorBox.style.display = "block";
    errorBox.textContent = "Please select a start and end date.";
    return;
  }

  const format = document.querySelector("input[name='format']:checked").value;

  try {
    const events = await fetchEvents(cal.value, start, end);
    events.sort((a, b) => new Date(a.start) - new Date(b.start));

    if (!events.length) {
      output.textContent = "No events found for the selected range.";
      return;
    }

    let text = "";
    let currentMonth = "";

	if (format === "spreadsheet") {
	  for (const ev of events) {
	    const startObj = new Date(ev.start);
	    const month = monthHeader(startObj);
	    const summary = unescapeICal(ev.summary || "(No Summary)");
	    const dateStr = formatSpreadsheetDate(startObj);
	
		if (month !== currentMonth) {
		  currentMonth = month;
		  if (text.trim() !== "") {
		    text += `\n`;
		  }
		  text += `===== ${currentMonth} =====\n`;
		}
	
	    text += `${dateStr}, ${summary}\n`;
	  }
	
	  output.textContent = text.trim();
	  return;
	}

    for (const ev of events) {
      const startObj = new Date(ev.start);
      const month = monthHeader(startObj);
      const summary = unescapeICal(ev.summary || "(No Summary)");
      const location = unescapeICal(ev.location || "");
      const city = extractCity(location);
      const state = extractSt(location);

      if (month !== currentMonth) {
        currentMonth = month;
        text += `\n===== ${currentMonth} =====\n`;
      }

      if (format === "newsletter") {
        text += `${formatNewsletterDate(startObj)}\n`;
        text += `${summary}\n`;
        if (location) {
          text += `${location}\n`;
        }
        text += `\n`;
      } else if (format === "print") {
        text += `${formatPrintDate(startObj)}\t${summary}`;
        if (city || state) {
          text += ` - ${[city, state].filter(Boolean).join(", ")}`;
        }
        text += `\n`;
      }
    }

    output.textContent = text.trim();
  } catch (err) {
    output.textContent = "";
    errorBox.style.display = "block";
    errorBox.textContent = "Error: " + err.message;
  }
}

function copyOutput() {
  const text = document.getElementById("output").textContent;
  if (!text.trim()) return;
  navigator.clipboard.writeText(text);
}

function exportCSV() {
  const text = document.getElementById("output").textContent;
  if (!text.trim()) return;

  const blob = new Blob([text], { type: "text/csv;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "calendar_export.csv";
  a.click();
  URL.revokeObjectURL(a.href);
}

function exportTXT() {
  const text = document.getElementById("output").textContent;
  if (!text.trim()) return;

  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "calendar_export.txt";
  a.click();
  URL.revokeObjectURL(a.href);
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("prettyPrintForm");
  form.addEventListener("submit", generatePrettyPrint);

  if (document.querySelector("input[name='calendar_id']:checked")) {
    generatePrettyPrint();
  }
});
</script>
<?php endif; ?>
</body>
</html>
<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

if (!Auth::isLoggedIn()) {
	$_SESSION['login_next'] = base_url('/tools/bandsintown.php');
	header('Location: ' . base_url('/member/login.php'));
	exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
	header('Location: ' . base_url('/member/login.php'));
	exit;
}

$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();

$today = new DateTime('today');
$oneMonthOut = (clone $today)->modify('+1 month');

$defaultDateFrom = $today->format('Y-m-d');
$defaultDateTo   = $oneMonthOut->format('Y-m-d');

$dateFrom = $isProUser ? ($_GET['date_from'] ?? $defaultDateFrom) : $defaultDateFrom;
$dateTo   = $isProUser ? ($_GET['date_to'] ?? $defaultDateTo) : $defaultDateTo;

$userTimezone = $user['timezone'] ?? 'America/Chicago';
$defaultArtistName = $user['display_name'] ?? 'Nick Sanzeri';

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bands In Town Export | Nick Sanzeri</title>
  <meta name="description" content="Generate a Bands In Town-formatted CSV from your calendar events.">
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

    .error-box{
      padding:1rem 1.1rem;
      border-radius:16px;
      background:rgba(255,107,107,.1);
      border:1px solid rgba(255,107,107,.25);
      color:#ffb3b3;
      margin-bottom:1rem;
      display:none;
    }

    .preview-wrap{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:18px;
      padding:1rem;
      overflow:auto;
    }

    .preview-table{
      width:100%;
      border-collapse:collapse;
      min-width:980px;
      color:#fff;
      font-size:.92rem;
    }

    .preview-table th,
    .preview-table td{
      padding:.7rem .75rem;
      border-bottom:1px solid rgba(255,255,255,.08);
      text-align:left;
      vertical-align:top;
    }

    .preview-table th{
      font-size:.82rem;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#d4af37;
      background:rgba(255,255,255,.03);
      position:sticky;
      top:0;
    }

    .preview-note{
      color:rgba(255,255,255,.72);
      margin-bottom:1rem;
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

    
    .upgrade-banner{
      margin-bottom:1rem;
      padding:.9rem 1rem;
      border-radius:16px;
      background:rgba(212,175,55,.10);
      border:1px solid rgba(212,175,55,.22);
      color:#fff;
    }

    .upgrade-banner a{
      color:#f2d67c;
      text-decoration:none;
      font-weight:600;
    }

    .upgrade-modal[hidden]{display:none;}
    .upgrade-modal{position:fixed;inset:0;z-index:9999;}
    .upgrade-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);}
    .upgrade-modal-card{position:relative;z-index:2;width:min(560px, calc(100% - 2rem));margin:8vh auto 0;padding:1.5rem;border-radius:22px;background:#111;border:1px solid rgba(255,255,255,.1);box-shadow:0 24px 60px rgba(0,0,0,.4);}
    .upgrade-modal-close{position:absolute;top:.85rem;right:.95rem;background:none;border:none;color:#fff;font-size:1.8rem;cursor:pointer;}


    .pro-locked-output,
    .pro-locked-output *{
      user-select:none;
      -webkit-user-select:none;
    }

    .pro-locked-note{
      margin-top:.85rem;
      color:rgba(255,255,255,.62);
      font-size:.9rem;
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
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header.php'; ?>

<main class="tools-shell">
  <div class="container">

    <div class="tools-topbar">
      <div>
        <p class="eyebrow">Ready Set Shows</p>
        <h1>Bands In Town Export</h1>
        <p>Generate a Bands In Town-formatted CSV from the events in one selected calendar.</p>
      </div>
      <div class="tools-muted">
        Signed in as <?= e($user['email'] ?? 'your account') ?>
      </div>
    </div>

    <?php if (!$isProUser): ?>
      <div class="upgrade-banner">
        <strong>Founder Pricing:</strong> Upgrade to Pro for $5/month to unlock premium exports, multiple calendars, and 5% off shop purchases.
        <a href="<?= e($upgradeUrl) ?>">Upgrade now</a>
      </div>
    <?php endif; ?>

    <div class="tools-layout">
      <aside class="tools-stack">
        <section class="tools-card">
          <h2>Build Export</h2>
          <p class="tools-muted" style="margin-top:-.25rem; margin-bottom:1rem;">
            Choose one calendar, enter the artist name, and generate a CSV preview ready for Bands In Town.
          </p>

          <form id="bitForm" method="get" action="<?= e(base_url('/tools/bandsintown.php')) ?>">
            <div class="tools-stack">
              <div class="tools-field">
                <label for="artistName">Artist Name</label>
                <input
                  class="tools-input"
                  type="text"
                  id="artistName"
                  name="artist_name"
                  value="<?= e($_GET['artist_name'] ?? $defaultArtistName) ?>"
                  placeholder="Artist or Band Name"
                >
              </div>

              <div class="tools-field">
                <label for="startDate">Start Date</label>
                <input class="tools-input" type="date" id="startDate" name="date_from" value="<?= e($dateFrom) ?>">
              </div>

              <div class="tools-field">
                <label for="endDate">End Date</label>
                <input class="tools-input" type="date" id="endDate" name="date_to" value="<?= e($dateTo) ?>">
                <?php if (!$isProUser): ?>
                  <p class="pro-locked-note">Free accounts can preview the next month. Upgrade to Pro to choose a custom date range.</p>
                <?php endif; ?>
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
                      Add one or more iCal feeds to generate a Bands In Town export.
                    </p>
                  </div>
                <?php endif; ?>
              </div>

              <div class="tools-actions">
                <button class="btn btn-primary" type="submit">
                  <i class="fa-solid fa-wand-magic-sparkles"></i>&nbsp; Generate Preview
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
            <h2 style="margin-bottom:.4rem;">Bands In Town CSV</h2>
            <p class="tools-muted" style="margin:0;">
              Review the rows before downloading or copying the CSV.
            </p>
          </div>

          <?php if ($hasCalendars): ?>
            <div class="preview-toolbar">
              <button class="btn btn-secondary" type="button" onclick="downloadCSV()">
                <i class="fa-solid fa-download"></i>&nbsp; Download CSV
              </button>
              <button class="btn btn-secondary" type="button" onclick="copyCSV()">
                <i class="fa-regular fa-copy"></i>&nbsp; Copy CSV
              </button>
              <button class="btn btn-secondary" type="button" onclick="openCSV()">
                <i class="fa-regular fa-window-restore"></i>&nbsp; Open in New Window
              </button>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$hasCalendars): ?>
          <div class="empty-state">
            <div class="empty-hero">
              <h3 style="margin-top:0;">Start by connecting your calendars</h3>
              <p class="tools-muted" style="margin-bottom:0;">
                Once your iCal feeds are connected, this page can turn calendar events into a Bands In Town-friendly CSV.
              </p>
            </div>

            <div class="demo-box">
              <strong style="color:#fff;">Ready to get started?</strong>
              <p class="small-note" style="margin:.45rem 0 .9rem;">
                Add your first calendar feed and come back here to generate a CSV.
              </p>
              <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?= e(base_url('/tools/calendars.php')) ?>">Connect Calendars</a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div id="errorBox" class="error-box"></div>

          <p class="preview-note">
            This preview follows the same basic export logic as your working Sir Gigz version.
          </p>

          <div class="preview-wrap">
            <table class="preview-table" id="previewTable">
              <thead></thead>
              <tbody></tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

<div id="upgradeModal" class="upgrade-modal" hidden>
  <div class="upgrade-modal-backdrop" onclick="closeUpgradeModal()"></div>
  <div class="upgrade-modal-card" role="dialog" aria-modal="true" aria-labelledby="upgradeModalTitle">
    <button type="button" class="upgrade-modal-close" onclick="closeUpgradeModal()" aria-label="Close">&times;</button>
    <p class="eyebrow">Pro Feature</p>
    <h2 id="upgradeModalTitle">Upgrade to Pro to unlock this feature</h2>
    <p class="tools-muted" style="margin-bottom:1rem;">
      Get the full Ready Set Shows workflow with founder pricing.
    </p>
    <ul style="margin:0 0 1.2rem 1.1rem; color:rgba(255,255,255,.82); line-height:1.8;">
      <li>Multiple calendars</li>
      <li>Bands In Town export</li>
      <li>Pretty print views</li>
      <li>Full date range access</li>
      <li><strong>5% off all shop purchases</strong></li>
    </ul>
    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to Pro — $5/mo</a>
      <button type="button" class="btn btn-secondary" onclick="closeUpgradeModal()">Keep Exploring</button>
    </div>
    <p class="small-note" style="margin-top:1rem;">Founder pricing is available now for early users.</p>
  </div>
</div>

<?php include __DIR__ . '/../../includes/tools_footer.php'; ?>

<?php if ($hasCalendars): ?>
<script>
const USER_TIMEZONE = <?= json_encode($userTimezone) ?>;
const IS_PRO_USER = <?= $isProUser ? 'true' : 'false' ?>;
let FINAL_CSV = "";


function openUpgradeModal() {
  const modal = document.getElementById("upgradeModal");
  if (modal) modal.hidden = false;
}

function closeUpgradeModal() {
  const modal = document.getElementById("upgradeModal");
  if (modal) modal.hidden = true;
}

function requirePro() {
  if (IS_PRO_USER) return true;
  openUpgradeModal();
  return false;
}

function guardLockedInteraction(event) {
  if (IS_PRO_USER) return true;
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  openUpgradeModal();
  return false;
}

function installLockedDateRange(fieldIds) {
  if (IS_PRO_USER) return;

  fieldIds.forEach(id => {
    const field = document.getElementById(id);
    if (!field) return;

    field.setAttribute('readonly', 'readonly');
    field.setAttribute('aria-disabled', 'true');

    ['click', 'focus', 'mousedown', 'keydown', 'touchstart'].forEach(evtName => {
      field.addEventListener(evtName, guardLockedInteraction);
    });
  });
}

function protectOutputElement(element) {
  if (IS_PRO_USER || !element) return;

  element.classList.add('pro-locked-output');

  ['copy', 'cut', 'contextmenu', 'selectstart'].forEach(evtName => {
    element.addEventListener(evtName, guardLockedInteraction);
  });
}

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

function unescapeICal(str) {
  if (!str || typeof str !== "string") return "";
  return str
    .replace(/\\,/g, ",")
    .replace(/\\;/g, ";")
    .replace(/\\\\/g, "\\")
    .replace(/\\n/g, "\n");
}

function extractParts(location, fallbackSummary = "") {
  const result = {
    venue: fallbackSummary || "",
    addr: "",
    city: "",
    region: "",
    postalCode: "",
    country: "United States"
  };

  if (!location || typeof location !== "string") {
    return result;
  }

  const parts = location
    .split(",")
    .map(p => unescapeICal(p.trim()))
    .filter(Boolean);

  if (!parts.length) {
    return result;
  }

  const looksLikeStreet = (value) => /^\d+\s+/.test(value || "");

  const normalizeCountry = (value) => {
    if (!value) return "United States";
    if (/^(usa|us|united states)$/i.test(value.trim())) {
      return "United States";
    }
    return value.trim();
  };

  const parseRegionPostal = (value) => {
    const out = { region: "", postalCode: "" };
    if (!value) return out;

    const cleaned = value.trim();
    const m = cleaned.match(/^([A-Za-z]{2}|[A-Za-z\s]+?)(?:\s+(\d{5}(?:-\d{4})?))?$/);
    if (m) {
      out.region = (m[1] || "").trim().substring(0, 2).toUpperCase();
      out.postalCode = (m[2] || "").trim();
    }

    return out;
  };

  if (parts.length >= 4) {
    let idx = 0;

    if (looksLikeStreet(parts[0])) {
      result.venue = fallbackSummary || "";
      result.addr = parts[0] || "";
      idx = 1;
    } else {
      result.venue = parts[0] || fallbackSummary || "";
      result.addr = parts[1] || "";
      idx = 2;
    }

    result.city = parts[idx] || "";

    const regionPostal = parseRegionPostal(parts[idx + 1] || "");
    result.region = regionPostal.region;
    result.postalCode = regionPostal.postalCode;

    result.country = normalizeCountry(parts[idx + 2] || "United States");

    return result;
  }

  if (parts.length === 3) {
    result.venue = fallbackSummary || "";
    result.addr = parts[0] || "";
    result.city = parts[1] || "";

    const regionPostal = parseRegionPostal(parts[2] || "");
    result.region = regionPostal.region;
    result.postalCode = regionPostal.postalCode;

    return result;
  }

  return result;
}

function formatDate(d) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: USER_TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit"
  }).formatToParts(d);

  const year = parts.find(p => p.type === "year")?.value || "";
  const month = parts.find(p => p.type === "month")?.value || "";
  const day = parts.find(p => p.type === "day")?.value || "";

  return `${year}-${month}-${day}`;
}

function formatTime(d) {
  return d.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
    timeZone: USER_TIMEZONE
  });
}

function csvEscape(value) {
  const str = String(value ?? "");
  if (/[",\n]/.test(str)) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}

function showError(message) {
  const box = document.getElementById("errorBox");
  box.textContent = message;
  box.style.display = "block";
}

function hideError() {
  const box = document.getElementById("errorBox");
  box.textContent = "";
  box.style.display = "none";
}

function renderPreview(headers, rows) {
  const thead = document.querySelector("#previewTable thead");
  const tbody = document.querySelector("#previewTable tbody");

  let headHtml = "<tr>";
  headers.forEach(h => {
    headHtml += `<th>${h}</th>`;
  });
  headHtml += "</tr>";
  thead.innerHTML = headHtml;

  let bodyHtml = "";
  rows.forEach(row => {
    bodyHtml += "<tr>";
    row.forEach(cell => {
      bodyHtml += `<td>${String(cell ?? "").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</td>`;
    });
    bodyHtml += "</tr>";
  });
  tbody.innerHTML = bodyHtml;
}

async function generateBIT(event) {
  if (event) {
    event.preventDefault();
  }

  hideError();

  const artist = document.getElementById("artistName").value.trim();
  const calendar = document.querySelector("input[name='calendar_id']:checked");
  const start = document.getElementById("startDate").value;
  const end   = document.getElementById("endDate").value;

  if (!artist || !calendar || !start || !end) {
    showError("Please complete all fields.");
    return;
  }

  try {
    const events = await fetchEvents(calendar.value, start, end);
    events.sort((a, b) => new Date(a.start) - new Date(b.start));

    const headers = [
      "Artist Name",
      "Venue*",
      "Country*",
      "Address",
      "City*",
      "Region*",
      "Postal Code",
      "Timezone*",
      "Start Date* (yyyy-mm-dd)",
      "Start Time* (HH:MM)",
      "End Date",
      "End Time",
      "Streaming Link",
      "Ticket Link",
      "Ticket Type",
      "Ticket Link 2",
      "Ticket Type 2",
      "On-Sale Date",
      "On-Sale Time",
      "Lineup",
      "Event Name",
      "Event Display Format",
      "Description",
      "Schedule Date",
      "Schedule Time",
      "Do Not Announce",
      "Setlist",
      "Event Image"
    ];

    const previewRows = [];
    const csvRows = [headers.map(csvEscape).join(",")];

    for (const ev of events) {
      const summary = unescapeICal(ev.summary || "");
      const location = unescapeICal(ev.location || "");
      const description = unescapeICal(ev.description || "");
      const startObj = new Date(ev.start);

      const parts = extractParts(location, summary);

		const row = [
		  artist,
		  parts.venue,
		  parts.country || "United States",
		  parts.addr,
		  parts.city,
		  parts.region,
		  parts.postalCode || "",
		  "",
		  formatDate(startObj),
		  formatTime(startObj),
		  "",
		  "",
		  "",
		  "",
		  "",
		  "",
		  "",
		  "",
		  "",
		  "",
		  summary,
		  artist,
		  description,
		  "",
		  "",
		  "",
		  "",
		  ""
		];

      previewRows.push(row);
      csvRows.push(row.map(csvEscape).join(","));
    }

    FINAL_CSV = csvRows.join("\n");
    renderPreview(headers, previewRows);

    if (!previewRows.length) {
      showError("No events found for the selected range.");
    }
  } catch (err) {
    showError("Error: " + err.message);
  }
}

function downloadCSV() {
  if (!FINAL_CSV.trim()) return;
  if (!requirePro()) return;
  const blob = new Blob([FINAL_CSV], { type: "text/csv;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "bandsintown_export.csv";
  a.click();
  URL.revokeObjectURL(a.href);
}

function copyCSV() {
  if (!FINAL_CSV.trim()) return;
  if (!requirePro()) return;
  navigator.clipboard.writeText(FINAL_CSV);
}

function openCSV() {
  if (!FINAL_CSV.trim()) return;
  if (!requirePro()) return;
  const w = window.open("", "_blank");
  if (!w) return;
  w.document.write("<pre>" + FINAL_CSV.replace(/</g, "&lt;") + "</pre>");
  w.document.close();
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("bitForm");
  form.addEventListener("submit", generateBIT);

  installLockedDateRange(['startDate', 'endDate']);
  protectOutputElement(document.querySelector('.preview-wrap'));

  if (document.querySelector("input[name='calendar_id']:checked")) {
    generateBIT();
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const toInput = document.querySelector('input[name="to_date"]');
  if (!toInput) return;

  const maxDate = getMaxToDate();
  toInput.max = maxDate;

  if (!IS_PRO_USER) {
    toInput.addEventListener("focus", function () {
      openUpgradeModal();
      this.blur();
    });

    toInput.addEventListener("click", function (e) {
      openUpgradeModal();
      e.preventDefault();
    });
  }
});

function getMaxToDate() {
  const d = new Date();
  d.setMonth(d.getMonth() + 1);
  return d.toISOString().split('T')[0];
}
</script>
<?php endif; ?>
</body>
</html>
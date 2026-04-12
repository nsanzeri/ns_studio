<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

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



$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();



$today = new DateTime('today');
$oneMonthOut = (clone $today)->modify('+1 month');

$defaultDateFrom = $today->format('Y-m-d');
$defaultDateTo   = $oneMonthOut->format('Y-m-d');

$dateFrom = $isProUser ? ($_GET['date_from'] ?? $defaultDateFrom) : $defaultDateFrom;
$dateTo   = $isProUser ? ($_GET['date_to'] ?? $defaultDateTo) : $defaultDateTo;

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

if (!$isProUser) {
	$maxDate = (new DateTime())->modify('+1 month')->format('Y-m-d');
	if ($dateTo > $maxDate) {
		$dateTo = $maxDate;
	}
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Availability | Ready Set Shows</title>
  <meta name="description" content="Check shared availability across multiple calendars, print useful views, and export dates for Bands In Town.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

    .output-wrap{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:18px;
      padding:1rem;
    }

    .pretty-output{
      margin:0;
      width:100%;
      min-height:220px;
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

	.ical-help-link{
	  margin-left: .35rem;
	  font-size: .92em;
	  text-decoration: underline;
	  color: #f2d67c;
	}
	
	.ical-help-link:hover{
	  color: #fff;
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
<?php include __DIR__ . '/../../includes/tools_header.php'; ?>

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
    <?php if (!$isProUser): ?>
      <div class="upgrade-banner">
        <strong>Founder Pricing:</strong> Upgrade to Pro for $5/month to unlock premium exports, multiple calendars, and 5% off shop purchases.
        <a href="<?= e($upgradeUrl) ?>">Upgrade now</a>
      </div>
    <?php endif; ?>
	<?php if (isset($_GET['trial_started'])): ?>
	  <div class="upgrade-banner">
	    <strong>Trial Active:</strong> Your 30-day Pro access has started. Explore everything.
	  </div>
	<?php endif; ?>

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
                <?php if (!$isProUser): ?>
                  <p class="pro-locked-note">Free accounts can view the next month. Upgrade to Pro to choose a custom date range.</p>
                <?php endif; ?>
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
            <button class="btn btn-secondary" type="button" onclick="copyAvailabilityOutput()">
              <i class="fa-regular fa-copy"></i>&nbsp; Copy
            </button>
            <button class="btn btn-secondary" type="button" onclick="exportAvailabilityTXT()">
              <i class="fa-regular fa-file-lines"></i>&nbsp; Export TXT
            </button>
            <button class="btn btn-secondary" type="button" onclick="printAvailabilityOutput()">
              <i class="fa-solid fa-print"></i>&nbsp; Print
            </button>
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
                <li>
				  Add one or more iCal links
				  <a href="#" id="openIcalHelp" class="ical-help-link">(Where do I find this?)</a>
				</li>
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

        <div id="availabilityTextWrap" class="output-wrap" style="display:none; margin-top:1rem;">
          <pre class="pretty-output" id="availabilityTextOutput"></pre>
        </div>
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

<script>
const USER_TIMEZONE = <?= json_encode($userTimezone) ?>;
const IS_PRO_USER = <?= $isProUser ? 'true' : 'false' ?>;
const TOOL_USAGE_ENDPOINT = <?= json_encode(base_url('/api/log_tool_usage.php')) ?>;
let LAST_AVAILABILITY_RESULT_COUNT = 0;

function trackToolUsage(payload) {
  try {
    fetch(TOOL_USAGE_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload || {})
    }).catch(() => {});
  } catch (e) {
    // no-op
  }
}

function getSelectedCalendarIds() {
  return Array.from(document.querySelectorAll("input[name='calendar_ids[]']:checked")).map(c => c.value);
}

function getSelectedDayValues() {
  return Array.from(document.querySelectorAll("input[name='days[]']:checked")).map(c => c.value);
}

function getAvailabilityInputContext() {
  return {
    date_from: document.getElementById("date_from")?.value || null,
    date_to: document.getElementById("date_to")?.value || null,
    calendar_ids: getSelectedCalendarIds(),
    days: getSelectedDayValues()
  };
}

function trackAvailabilityUsage(actionKey, status = "success", extra = {}) {
  trackToolUsage({
    feature_key: "availability_check",
    action_key: actionKey,
    status,
    calendar_count: getSelectedCalendarIds().length,
    result_count: LAST_AVAILABILITY_RESULT_COUNT,
    input: getAvailabilityInputContext(),
    ...extra
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


function openUpgradeModal() {
  const modal = document.getElementById("upgradeModal");
  if (modal) modal.hidden = false;
}

function closeUpgradeModal() {
  const modal = document.getElementById("upgradeModal");
  if (modal) modal.hidden = true;
}

function requirePro(actionKey = "pro_locked_action", note = "Pro feature attempted", extra = {}) {
  if (IS_PRO_USER) return true;
  trackAvailabilityUsage(actionKey, "blocked", { note, ...extra });
  openUpgradeModal();
  return false;
}

function guardLockedInteraction(event, actionKey = "locked_interaction", note = "Locked interaction attempted") {
  if (IS_PRO_USER) return true;
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  trackAvailabilityUsage(actionKey, "blocked", {
    note,
    input: {
      ...getAvailabilityInputContext(),
      target_id: event?.target?.id || null,
      target_name: event?.target?.name || null
    }
  });
  openUpgradeModal();
  return false;
}

function installLockedDateRange() {
  const fromInput = document.getElementById("date_from");
  const toInput = document.getElementById("date_to");
  if (!fromInput || !toInput) return;

  // From date should always be editable and unrestricted
  fromInput.removeAttribute("readonly");
  fromInput.removeAttribute("aria-disabled");
  fromInput.removeAttribute("min");
  fromInput.removeAttribute("max");

  // To date should always be editable, but capped at one month out for free users
  toInput.removeAttribute("readonly");
  toInput.removeAttribute("aria-disabled");

  if (IS_PRO_USER) {
    toInput.removeAttribute("max");
    return;
  }

  const maxDate = getMaxToDate();
  toInput.max = maxDate;

  if (toInput.value && toInput.value > maxDate) {
    toInput.value = maxDate;
  }
}

function protectOutputElement(element) {
  if (IS_PRO_USER || !element) return;

  element.classList.add('pro-locked-output');

  ['copy', 'cut', 'contextmenu', 'selectstart'].forEach(evtName => {
    element.addEventListener(evtName, guardLockedInteraction);
  });
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

function formatAvailabilityMonth(date) {
  const month = date.toLocaleDateString("en-US", {
    month: "long",
    timeZone: USER_TIMEZONE
  });
  const year = date.toLocaleDateString("en-US", {
    year: "numeric",
    timeZone: USER_TIMEZONE
  });
  return `${month} – ${year}`;
}

function formatAvailabilityLine(date) {
  const weekday = date.toLocaleDateString("en-US", {
    weekday: "short",
    timeZone: USER_TIMEZONE
  });
  const month = date.toLocaleDateString("en-US", {
    month: "short",
    timeZone: USER_TIMEZONE
  });
  const day = date.toLocaleDateString("en-US", {
    day: "numeric",
    timeZone: USER_TIMEZONE
  });
  return `${weekday} ${month} ${day}`;
}

function renderResults(freeDates, startStr, endStr) {
  const resultsEmpty = document.getElementById("resultsEmpty");
  const resultsError = document.getElementById("resultsError");
  const resultsLoading = document.getElementById("resultsLoading");
  const availabilityTextWrap = document.getElementById("availabilityTextWrap");
  const availabilityTextOutput = document.getElementById("availabilityTextOutput");

  resultsLoading.style.display = "none";
  resultsError.style.display = "none";
  availabilityTextWrap.style.display = "none";
  availabilityTextOutput.textContent = "";

  LAST_AVAILABILITY_RESULT_COUNT = freeDates.length;

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

  let text = "";
  let currentMonth = "";

  for (const date of freeDates) {
    const monthHeader = formatAvailabilityMonth(date);

    if (monthHeader !== currentMonth) {
      if (text.trim() !== "") {
        text += `\n`;
      }
      currentMonth = monthHeader;
      text += `${monthHeader}\n-------------\n`;
    }

    text += `${formatAvailabilityLine(date)}\n`;
  }

  availabilityTextOutput.textContent = text.trim();
  availabilityTextWrap.style.display = "block";
}

function copyAvailabilityOutput() {
  const text = document.getElementById("availabilityTextOutput").textContent;
  if (!text.trim()) return;
  if (!requirePro("copy_output", "Free user attempted to copy availability output")) return;
  navigator.clipboard.writeText(text);
  trackAvailabilityUsage("copy_output", "success");
}

function exportAvailabilityTXT() {
  const text = document.getElementById("availabilityTextOutput").textContent;
  if (!text.trim()) return;
  if (!requirePro("export_txt", "Free user attempted to export availability TXT")) return;

  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "available_dates.txt";
  a.click();
  URL.revokeObjectURL(a.href);
  trackAvailabilityUsage("export_txt", "success");
}

function printAvailabilityOutput() {
  if (!requirePro("print_output", "Free user attempted to print availability output")) return;
  trackAvailabilityUsage("print_output", "success");
  window.print();
}

async function findAvailableDates(event) {
  if (event) {
    event.preventDefault();
  }

  const resultsEmpty = document.getElementById("resultsEmpty");
  const resultsError = document.getElementById("resultsError");
  const resultsLoading = document.getElementById("resultsLoading");
  const availabilityTextWrap = document.getElementById("availabilityTextWrap");
  const availabilityTextOutput = document.getElementById("availabilityTextOutput");

  const startStr = document.getElementById("date_from").value;
  const endStr   = document.getElementById("date_to").value;

  if (!startStr || !endStr) {
    resultsEmpty.style.display = "none";
    resultsLoading.style.display = "none";
    availabilityTextWrap.style.display = "none";
    availabilityTextOutput.textContent = "";
    resultsError.style.display = "block";
    resultsError.textContent = "Please select both dates.";
    trackAvailabilityUsage("run", "error", {
      note: "Missing start or end date"
    });
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
    resultsLoading.style.display = "none";
    availabilityTextWrap.style.display = "none";
    availabilityTextOutput.textContent = "";
    resultsError.style.display = "block";
    resultsError.textContent = "Select at least one weekday.";
    trackAvailabilityUsage("run", "error", {
      note: "No weekdays selected"
    });
    return;
  }

  const selectedCalendars = Array.from(
    document.querySelectorAll("input[name='calendar_ids[]']:checked")
  ).map(c => c.value);

  if (!selectedCalendars.length) {
    resultsEmpty.style.display = "none";
    resultsLoading.style.display = "none";
    availabilityTextWrap.style.display = "none";
    availabilityTextOutput.textContent = "";
    resultsError.style.display = "block";
    resultsError.textContent = "Select at least one calendar.";
    trackAvailabilityUsage("run", "error", {
      note: "No calendars selected"
    });
    return;
  }

  resultsError.style.display = "none";
  resultsEmpty.style.display = "none";
  availabilityTextWrap.style.display = "none";
  availabilityTextOutput.textContent = "";
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
    trackAvailabilityUsage("run", "success");
  } catch (err) {
    resultsLoading.style.display = "none";
    availabilityTextWrap.style.display = "none";
    availabilityTextOutput.textContent = "";
    resultsEmpty.style.display = "none";
    resultsError.style.display = "block";
    resultsError.textContent = "Error: " + err.message;
    trackAvailabilityUsage("run", "error", {
      note: (err && err.message) ? String(err.message).slice(0, 255) : "Unknown error"
    });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("availabilityForm");
  if (form) {
    form.addEventListener("submit", findAvailableDates);
  }

  const hasCalendars = <?= $hasCalendars ? 'true' : 'false' ?>;
  if (hasCalendars) {
    findAvailableDates();
  }

  // 🔒 Apply Pro locks cleanly
  installLockedDateRange(['date_from', 'date_to']);

  const output = document.getElementById("availabilityTextOutput");
  protectOutputElement(output);
});

document.addEventListener("DOMContentLoaded", function () {
  const fromInput = document.getElementById("date_from");
  const toInput = document.getElementById("date_to");
  if (!toInput) return;

  if (IS_PRO_USER) {
    toInput.removeAttribute("max");
    toInput.removeAttribute("readonly");
    toInput.removeAttribute("aria-disabled");

    if (fromInput) {
      fromInput.removeAttribute("readonly");
      fromInput.removeAttribute("aria-disabled");
    }
    return;
  }

  const maxDate = getMaxToDate();
  toInput.max = maxDate;

  if (toInput.value && toInput.value > maxDate) {
    toInput.value = maxDate;
  }

  toInput.addEventListener("focus", function (e) {
    guardLockedInteraction(e, "locked_date_range", "Free user attempted to change the end date");
    this.blur();
  });

  toInput.addEventListener("click", function (e) {
    guardLockedInteraction(e, "locked_date_range", "Free user attempted to change the end date");
  });
});

function getMaxToDate() {
  const d = new Date();
  d.setMonth(d.getMonth() + 1);
  return d.toISOString().split('T')[0];
}


$(function () {
  $('#openIcalHelp').on('click', function (e) {
    e.preventDefault();
    $('#icalHelpModal').prop('hidden', false);
  });

  $('#closeIcalHelpModal, #icalHelpModal .upgrade-modal-backdrop').on('click', function () {
    $('#icalHelpModal').prop('hidden', true);
  });

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') {
      $('#icalHelpModal').prop('hidden', true);
    }
  });
});

</script>

<div id="icalHelpModal" class="upgrade-modal" hidden>
  <div class="upgrade-modal-backdrop"></div>
  <div class="upgrade-modal-card" role="dialog" aria-modal="true" aria-labelledby="icalHelpTitle">
    <button type="button" class="upgrade-modal-close" id="closeIcalHelpModal" aria-label="Close">&times;</button>

    <p class="eyebrow">Calendar Help</p>
    <h2 id="icalHelpTitle">Where do I find my iCal link?</h2>
    <p class="tools-muted" style="margin-bottom:1rem;">
      You can connect your calendar by copying its iCal/ICS link.
    </p>

    <h3 style="margin-bottom:.5rem;">Google Calendar</h3>
    <ol style="margin:0 0 1rem 1.1rem; color:rgba(255,255,255,.82); line-height:1.8;">
      <li>Open Google Calendar</li>
      <li>In the left sidebar, hover over your calendar and click the 3 dots</li>
      <li>Choose <strong>Settings and sharing</strong></li>
      <li>Scroll to <strong>Integrate calendar</strong></li>
      <li>Copy the <strong>Secret address in iCal format</strong> for private use, or the public iCal link if you intentionally made it public</li>
    </ol>

    <h3 style="margin-bottom:.5rem;">Apple Calendar (Mac)</h3>
    <ol style="margin:0 0 1rem 1.1rem; color:rgba(255,255,255,.82); line-height:1.8;">
      <li>Open Calendar on your Mac</li>
      <li>Control-click the calendar in the sidebar</li>
      <li>Choose <strong>Share Calendar</strong></li>
      <li>Enable <strong>Public Calendar</strong> if needed</li>
      <li>Copy the calendar link</li>
    </ol>

    <p class="small-note" style="margin-top:1rem;">
      Paste that link into your calendar connection page and we’ll read the events from it.
    </p>
  </div>
</div>
</body>
</html>
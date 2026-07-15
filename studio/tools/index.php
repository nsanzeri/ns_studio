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

$dateFrom = $_GET['date_from'] ?? $defaultDateFrom;
$dateTo   = $_GET['date_to'] ?? $defaultDateTo;

$userTimezone = $user['timezone'] ?? 'America/Chicago';

function tools_calendar_column_exists(PDO $pdo, string $columnName): bool {
    static $cache = [];
    if (array_key_exists($columnName, $cache)) return $cache[$columnName];
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'calendars' AND column_name = ? LIMIT 1");
    $stmt->execute([$columnName]);
    return $cache[$columnName] = (bool)$stmt->fetchColumn();
}

$hasMainGigColumn = tools_calendar_column_exists($pdo, 'is_main_gig');
$mainGigSelect = $hasMainGigColumn ? 'is_main_gig' : '0 AS is_main_gig';

$calStmt = $pdo->prepare("
    SELECT
        id,
        name,
        color,
        timezone,
        ics_url,
        is_active,
        is_default,
        {$mainGigSelect},
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

$calendarMonthRaw = (string)($_GET['month'] ?? $today->format('Y-m-01'));
try {
    $calendarMonth = new DateTime($calendarMonthRaw);
} catch (Throwable $e) {
    $calendarMonth = new DateTime($today->format('Y-m-01'));
}
$calendarMonth->modify('first day of this month');
$monthStart = $calendarMonth->format('Y-m-d');
$monthEnd = (clone $calendarMonth)->modify('last day of this month')->format('Y-m-d');
$monthPrev = (clone $calendarMonth)->modify('-1 month')->format('Y-m-01');
$monthNext = (clone $calendarMonth)->modify('+1 month')->format('Y-m-01');
$monthTitle = $calendarMonth->format('F Y');
$monthDays = (int)$calendarMonth->format('t');
$firstWeekday = (int)$calendarMonth->format('w');
$selectedCalendarQuery = '';
foreach ($selectedCalendarIds as $selectedCalendarId) {
    $selectedCalendarQuery .= '&calendar_ids%5B%5D=' . (int)$selectedCalendarId;
}
$calendarMeta = array_map(static fn($calendar) => [
    'id' => (int)$calendar['id'],
    'name' => (string)$calendar['name'],
    'color' => (string)($calendar['color'] ?: '#d4af37'),
    'is_main_gig' => (int)($calendar['is_main_gig'] ?? 0) === 1,
], $connectedCalendars);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar | Ready Set Shows</title>
  <meta name="description" content="View your connected calendars by month, check shared availability, and create show paperwork from your main gig calendar.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .tools-shell{
      padding: 1.25rem 0 4rem;
    }

    .tools-topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:1.25rem;
      margin-bottom:1rem;
    }

    .tools-topbar h1{
      margin:0;
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

    .date-range-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:.75rem;
    }

    .tools-field{
      display:grid;
      gap:.45rem;
      min-width:0;
    }

    .tools-field label{
      font-size:.95rem;
      font-weight:500;
      color:#fff;
    }

    .tools-input,
    .tools-select{
      width:100%;
      min-width:0;
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
      grid-template-columns:repeat(auto-fit, minmax(74px, 1fr));
      gap:.5rem;
    }

    .day-chip{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:.4rem;
      min-height:44px;
      padding:.55rem .5rem;
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
      flex-wrap:nowrap;
      gap:.45rem;
    }

    .result-toolbar .btn{
      min-height:40px;
      padding:.65rem .85rem;
      font-size:.84rem;
      white-space:nowrap;
    }

    .calendar-month-card{
      margin-bottom:1.5rem;
    }

    .calendar-month-head{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:1rem;
      margin-bottom:1rem;
      flex-wrap:wrap;
    }

    .calendar-month-actions{
      display:flex;
      gap:.5rem;
      flex-wrap:wrap;
    }

    .calendar-month-grid{
      display:grid;
      grid-template-columns:repeat(7, minmax(0, 1fr));
      border:1px solid rgba(255,255,255,.08);
      border-radius:16px;
      overflow:hidden;
      background:rgba(255,255,255,.025);
    }

    .calendar-weekday,
    .calendar-day{
      border-right:1px solid rgba(255,255,255,.07);
      border-bottom:1px solid rgba(255,255,255,.07);
    }

    .calendar-weekday:nth-child(7n),
    .calendar-day:nth-child(7n){
      border-right:0;
    }

    .calendar-weekday{
      padding:.65rem .5rem;
      color:#f4d35e;
      font-size:.78rem;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
      background:rgba(255,255,255,.035);
    }

    .calendar-day{
      min-height:112px;
      padding:.55rem;
      display:grid;
      align-content:start;
      gap:.35rem;
      min-width:0;
      overflow:hidden;
    }

    .calendar-day.is-muted{
      background:rgba(255,255,255,.018);
    }

    .calendar-day-number{
      color:#fff;
      font-weight:700;
      font-size:.9rem;
    }

    .calendar-event-badge{
      display:flex;
      align-items:center;
      gap:.4rem;
      width:100%;
      max-width:100%;
      border:0;
      border-radius:6px;
      padding:.18rem .1rem;
      background:transparent;
      color:#fff;
      font:inherit;
      font-size:.78rem;
      text-align:left;
      cursor:pointer;
      min-width:0;
      overflow:hidden;
    }

    .calendar-event-badge:hover{
      background:rgba(255,255,255,.06);
    }

    .calendar-event-badge span:last-child{
      display:block;
      min-width:0;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }

    .calendar-color-square{
      width:10px;
      height:10px;
      border-radius:3px;
      flex:0 0 auto;
      box-shadow:0 0 0 1px rgba(255,255,255,.25);
    }

    .calendar-modal[hidden]{display:none;}
    .calendar-modal{position:fixed; inset:0; z-index:9998;}
    .calendar-modal-backdrop{position:absolute; inset:0; background:rgba(0,0,0,.62); backdrop-filter:blur(4px);}
    .calendar-modal-card{position:relative; z-index:2; width:min(560px, calc(100% - 2rem)); margin:10vh auto 0; padding:1.35rem; border-radius:22px; background:#151323; border:1px solid rgba(255,255,255,.12); box-shadow:0 24px 70px rgba(0,0,0,.45);}
    .calendar-modal-close{position:absolute; top:.75rem; right:.85rem; width:36px; height:36px; border-radius:999px; border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.05); color:#fff; cursor:pointer; font-size:1.4rem;}
    .calendar-map-link{color:rgba(255,255,255,.72); text-decoration:none;}
    .calendar-map-link:hover{color:#f4d35e; text-decoration:underline;}
    .calendar-modal-actions{display:flex; gap:.55rem; flex-wrap:wrap; margin-top:1rem;}

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
        gap:.75rem;
      }

      .results-header{
        flex-direction:column;
      }

      .calendar-month-grid{
        grid-template-columns:repeat(7, minmax(0, 1fr));
      }
    }

    @media (max-width: 520px){
      .tools-shell{
        padding-top:.9rem;
      }

      .tools-card{
        padding:1rem;
        border-radius:18px;
      }

      .calendar-month-card{
        padding:.75rem;
      }

      .calendar-month-head{
        gap:.65rem;
      }

      .calendar-month-actions{
        width:100%;
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:.35rem;
      }

      .calendar-month-actions .btn{
        justify-content:center;
        padding:.55rem .25rem;
        font-size:.68rem;
        letter-spacing:.08em;
      }

      .calendar-weekday{
        padding:.42rem .12rem;
        font-size:.58rem;
        text-align:center;
      }

      .calendar-day{
        min-height:54px;
        padding:.22rem;
        gap:.12rem;
      }

      .calendar-day-number{
        font-size:.72rem;
      }

      .calendar-event-badge{
        gap:.18rem;
        padding:.08rem 0;
        font-size:.58rem;
        line-height:1.15;
      }

      .calendar-color-square{
        width:7px;
        height:7px;
        border-radius:2px;
      }

      .tools-topbar{
        margin-bottom:.75rem;
      }

      .tools-topbar h1{
        font-size:2rem;
      }

      .tools-input,
      .tools-select{
        padding:.75rem .7rem;
        font-size:.92rem;
      }

      .date-range-grid{
        gap:.55rem;
      }

      .tools-check{
        padding:.65rem .7rem;
      }

      .day-grid{
        grid-template-columns:repeat(4, minmax(0, 1fr));
      }

      .day-chip{
        border-radius:12px;
        font-size:.86rem;
      }

      .result-toolbar{
        width:100%;
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:.4rem;
      }

      .result-toolbar .btn{
        width:100%;
        justify-content:center;
        padding:.62rem .35rem;
        font-size:.76rem;
        letter-spacing:.08em;
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
        <h1>Calendar</h1>
      </div>
    </div>
	<?php if (isset($_GET['trial_started'])): ?>
	  <div class="upgrade-banner">
	    <strong>Trial Active:</strong> Your 30-day Pro access has started. Explore everything.
	  </div>
	<?php endif; ?>

    <section class="tools-card calendar-month-card">
      <div class="calendar-month-head">
        <div>
          <p class="eyebrow" style="margin-bottom:.45rem;">Calendar</p>
          <h2 style="margin:0;"><?= e($monthTitle) ?></h2>
          <p class="tools-muted" style="margin:.35rem 0 0;">Colored badges show events from your selected calendars.</p>
        </div>
        <div class="calendar-month-actions">
          <a class="btn btn-secondary" href="<?= e(base_url('/tools/index.php?month=' . rawurlencode($monthPrev) . $selectedCalendarQuery)) ?>">Previous</a>
          <a class="btn btn-secondary" href="<?= e(base_url('/tools/index.php?month=' . rawurlencode($today->format('Y-m-01')) . $selectedCalendarQuery)) ?>">Today</a>
          <a class="btn btn-secondary" href="<?= e(base_url('/tools/index.php?month=' . rawurlencode($monthNext) . $selectedCalendarQuery)) ?>">Next</a>
        </div>
      </div>
      <?php if (!$hasCalendars): ?>
        <div class="demo-box" style="margin-top:0;">
          <strong style="color:#fff;">No calendars connected yet</strong>
          <p class="small-note" style="margin:.45rem 0 .9rem;">Connect one or more iCal feeds to see a working month view.</p>
          <a class="btn btn-primary" href="<?= e(base_url('/tools/calendars.php')) ?>">Connect Calendars</a>
        </div>
      <?php else: ?>
        <div class="calendar-month-grid" id="calendarMonthGrid" aria-label="<?= e($monthTitle) ?> event calendar">
          <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
            <div class="calendar-weekday"><?= e($weekday) ?></div>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
            <div class="calendar-day is-muted" aria-hidden="true"></div>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $monthDays; $day++): ?>
            <?php $dateValue = $calendarMonth->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT); ?>
            <div class="calendar-day" data-date="<?= e($dateValue) ?>">
              <div class="calendar-day-number"><?= (int)$day ?></div>
              <div class="calendar-day-events" data-events-for="<?= e($dateValue) ?>"></div>
            </div>
          <?php endfor; ?>
        </div>
        <div id="calendarMonthStatus" class="small-note" style="margin-top:.75rem;">Loading calendar events...</div>
      <?php endif; ?>
    </section>

    <div class="tools-layout">
      <aside class="tools-stack">
        <section class="tools-card">
          <form id="availabilityForm" method="get" action="<?= e(base_url('/tools/index.php')) ?>">
            <input type="hidden" name="month" value="<?= e($monthStart) ?>">
            <div class="tools-stack">
              <div class="date-range-grid">
                <div class="tools-field">
                  <label for="date_from">From</label>
                  <input class="tools-input" type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div class="tools-field">
                  <label for="date_to">To</label>
                  <input class="tools-input" type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
                </div>
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
                      'sun' => 'Sun',
                      'mon' => 'Mon',
                      'tue' => 'Tue',
                      'wed' => 'Wed',
                      'thu' => 'Thu',
                      'fri' => 'Fri',
                      'sat' => 'Sat',
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

<div id="calendarEventModal" class="calendar-modal" hidden>
  <div class="calendar-modal-backdrop" onclick="closeCalendarEventModal()"></div>
  <div class="calendar-modal-card" role="dialog" aria-modal="true" aria-labelledby="calendarEventTitle">
    <button type="button" class="calendar-modal-close" onclick="closeCalendarEventModal()" aria-label="Close">&times;</button>
    <p class="eyebrow" id="calendarEventCalendar" style="margin-bottom:.45rem;"></p>
    <h2 id="calendarEventTitle" style="margin:0 2rem .5rem 0;"></h2>
    <p class="tools-muted" id="calendarEventDate" style="margin:.35rem 0;"></p>
    <a class="calendar-map-link" id="calendarEventLocation" style="display:block; margin:.35rem 0;" target="_blank" rel="noopener"></a>
    <div class="calendar-modal-actions" id="calendarEventActions"></div>
  </div>
</div>

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
      <li>Bands In Town export</li>
      <li><strong>5% off all shop purchases</strong></li>
    </ul>
    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to Pro — $10/mo</a>
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
const CALENDAR_META = <?= json_encode($calendarMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const CALENDAR_MONTH_START = <?= json_encode($monthStart) ?>;
const CALENDAR_MONTH_END = <?= json_encode($monthEnd) ?>;
const FINANCE_DOCUMENT_URL = <?= json_encode(base_url('/finance/document.php')) ?>;
const FINANCE_GIGS_URL = <?= json_encode(base_url('/finance/gigs.php')) ?>;
const UPGRADE_URL = <?= json_encode($upgradeUrl) ?>;
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

  fromInput.removeAttribute("readonly");
  fromInput.removeAttribute("aria-disabled");
  fromInput.removeAttribute("min");
  fromInput.removeAttribute("max");

  toInput.removeAttribute("readonly");
  toInput.removeAttribute("aria-disabled");
  toInput.removeAttribute("max");
}

function protectOutputElement(element) {
  // Copying, cutting, right-clicking, and text selection are allowed for all users.
  // Keep this function as a harmless compatibility hook for older page code.
  return;
}

async function fetchCalendarEvents(calendarId, startDate, endDate) {
  const url = `<?= e(base_url('/api/fetch_ics.php')) ?>?id=${encodeURIComponent(calendarId)}&start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
  const res = await fetch(url, { credentials: "same-origin" });

  if (!res.ok) {
    throw new Error(`Failed to fetch calendar ${calendarId}`);
  }

  const data = await res.json();

  if (!data.success) {
    throw new Error(data.error || "Unknown error");
  }

  return data.events || [];
}

async function getICalEvents(calendarId, startDate, endDate) {
  const events = await fetchCalendarEvents(calendarId, startDate, endDate);
  return events.map(ev => ({
    start: new Date(ev.start),
    end: new Date(ev.end)
  }));
}

function calendarDateKey(value) {
  return String(value || "").slice(0, 10);
}

function formatCalendarEventDate(event) {
  const start = new Date(event.start);
  return start.toLocaleString("en-US", {
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    timeZone: USER_TIMEZONE
  });
}

function closeCalendarEventModal() {
  const modal = document.getElementById("calendarEventModal");
  if (modal) modal.hidden = true;
}

function openCalendarEventModal(event, calendar) {
  const modal = document.getElementById("calendarEventModal");
  if (!modal) return;

  document.getElementById("calendarEventCalendar").textContent = calendar.name || "Calendar";
  document.getElementById("calendarEventTitle").textContent = event.summary || "Untitled event";
  document.getElementById("calendarEventDate").textContent = formatCalendarEventDate(event);
  const locationLink = document.getElementById("calendarEventLocation");
  const location = (event.location || "").trim();
  locationLink.textContent = location || "Address to be confirmed";
  if (location) {
    locationLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location)}`;
    locationLink.removeAttribute("aria-disabled");
  } else {
    locationLink.removeAttribute("href");
    locationLink.setAttribute("aria-disabled", "true");
  }

  const actions = document.getElementById("calendarEventActions");
  actions.innerHTML = "";
  if (calendar.is_main_gig) {
    const day = calendarDateKey(event.start);
    const baseQuery = new URLSearchParams({
      calendar_id: String(calendar.id),
      event_key: event.event_key || "",
      start: CALENDAR_MONTH_START,
      end: CALENDAR_MONTH_END
    });
    const ledgerQuery = new URLSearchParams({
      calendar_id: String(calendar.id),
      start: day,
      end: day,
      preview: "1"
    });
    const links = [
      ["Contract", IS_PRO_USER ? `${FINANCE_DOCUMENT_URL}?type=contract&${baseQuery.toString()}` : UPGRADE_URL, true],
      ["Invoice", IS_PRO_USER ? `${FINANCE_DOCUMENT_URL}?type=invoice&${baseQuery.toString()}` : UPGRADE_URL, true],
      ["Ledger", `${FINANCE_GIGS_URL}?${ledgerQuery.toString()}`, false]
    ];
    for (const [label, href, blank] of links) {
      const a = document.createElement("a");
      a.className = label === "Ledger" ? "btn btn-secondary" : "btn btn-primary";
      a.href = href;
      a.textContent = label;
      if (blank) {
        a.target = "_blank";
        a.rel = "noopener";
      }
      actions.appendChild(a);
    }
  } else {
    const note = document.createElement("p");
    note.className = "tools-muted";
    note.style.margin = "0";
    note.textContent = "Document and ledger shortcuts are available for events on your main gig calendar.";
    actions.appendChild(note);
  }

  modal.hidden = false;
}

async function renderCalendarMonth() {
  const grid = document.getElementById("calendarMonthGrid");
  const status = document.getElementById("calendarMonthStatus");
  if (!grid || !CALENDAR_META.length) return;

  const selected = new Set(getSelectedCalendarIds().map(String));
  const calendars = CALENDAR_META.filter(calendar => selected.has(String(calendar.id)));
  document.querySelectorAll(".calendar-day-events").forEach(el => { el.innerHTML = ""; });

  if (!calendars.length) {
    if (status) status.textContent = "Select at least one calendar to show events.";
    return;
  }

  let rendered = 0;
  try {
    const eventGroups = await Promise.all(calendars.map(async calendar => ({
      calendar,
      events: await fetchCalendarEvents(calendar.id, CALENDAR_MONTH_START, CALENDAR_MONTH_END)
    })));

    for (const group of eventGroups) {
      for (const event of group.events) {
        const dateKey = calendarDateKey(event.start);
        const slot = document.querySelector(`[data-events-for="${dateKey}"]`);
        if (!slot) continue;
        const button = document.createElement("button");
        button.type = "button";
        button.className = "calendar-event-badge";
        const square = document.createElement("span");
        square.className = "calendar-color-square";
        square.style.background = group.calendar.color || "#d4af37";
        const label = document.createElement("span");
        label.textContent = event.summary || group.calendar.name;
        button.append(square, label);
        button.addEventListener("click", () => openCalendarEventModal(event, group.calendar));
        slot.appendChild(button);
        rendered++;
      }
    }
    if (status) status.textContent = rendered ? `${rendered} event${rendered === 1 ? "" : "s"} shown.` : "No events on selected calendars this month.";
  } catch (err) {
    if (status) status.textContent = "Could not load one or more calendars: " + (err?.message || "Unknown error");
  }
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
  navigator.clipboard.writeText(text);
  trackAvailabilityUsage("copy_output", "success");
}

function exportAvailabilityTXT() {
  const text = document.getElementById("availabilityTextOutput").textContent;
  if (!text.trim()) return;

  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "available_dates.txt";
  a.click();
  URL.revokeObjectURL(a.href);
  trackAvailabilityUsage("export_txt", "success");
}

function printAvailabilityOutput() {
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
  renderCalendarMonth();
  document.querySelectorAll("input[name='calendar_ids[]']").forEach(input => {
    input.addEventListener("change", renderCalendarMonth);
  });

  installLockedDateRange(['date_from', 'date_to']);

  const output = document.getElementById("availabilityTextOutput");
  protectOutputElement(output);
});

document.addEventListener("DOMContentLoaded", function () {
  const fromInput = document.getElementById("date_from");
  const toInput = document.getElementById("date_to");
  if (!toInput) return;

  toInput.removeAttribute("max");
  toInput.removeAttribute("readonly");
  toInput.removeAttribute("aria-disabled");

  if (fromInput) {
    fromInput.removeAttribute("readonly");
    fromInput.removeAttribute("aria-disabled");
  }
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

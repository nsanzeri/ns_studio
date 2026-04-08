<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = base_url('/tools/calendars.php');
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$user = Auth::currentUser($pdo);
if (!$user) {
    header('Location: ' . base_url('/member/login.php'));
    exit;
}

$userId = (int)($user['id'] ?? 0);
$errors = [];
$flash = $_SESSION['tools_flash'] ?? null;
unset($_SESSION['tools_flash']);

function redirect_tools_calendars(): void
{
    header('Location: ' . base_url('/tools/calendars.php'));
    exit;
}

function normalize_hex_color(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if ($value[0] !== '#') {
        $value = '#' . $value;
    }

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return strtoupper($value);
    }

    return null;
}

function normalize_timezone(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return in_array($value, timezone_identifiers_list(), true) ? $value : null;
}

function posted_calendar_defaults(): array
{
    return [
        'name' => trim((string)($_POST['name'] ?? '')),
        'ics_url' => trim((string)($_POST['ics_url'] ?? '')),
        'color' => trim((string)($_POST['color'] ?? '')),
        'timezone' => trim((string)($_POST['timezone'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
}

$formData = posted_calendar_defaults();
$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingCalendar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $calendarId = (int)($_POST['calendar_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $icsUrl = trim((string)($_POST['ics_url'] ?? ''));
        $color = normalize_hex_color($_POST['color'] ?? '');
        $timezone = normalize_timezone($_POST['timezone'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $errors[] = 'Please give this calendar a name.';
        }

        if ($icsUrl === '') {
            $errors[] = 'Please enter an iCal URL.';
        } elseif (!filter_var($icsUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid URL.';
        }

        if (trim((string)($_POST['color'] ?? '')) !== '' && $color === null) {
            $errors[] = 'Calendar color must be a 6-digit hex value like #D4AF37.';
        }

        if (trim((string)($_POST['timezone'] ?? '')) !== '' && $timezone === null) {
            $errors[] = 'Timezone is not recognized.';
        }

        if (!$errors) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO calendars
                        (user_id, name, color, ics_url, timezone, is_active, is_default, sync_status, created_at)
                    VALUES
                        (:user_id, :name, :color, :ics_url, :timezone, :is_active, 0, 'never', NOW())
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':name' => $name,
                    ':color' => $color,
                    ':ics_url' => $icsUrl,
                    ':timezone' => $timezone,
                    ':is_active' => $isActive,
                ]);

                $_SESSION['tools_flash'] = 'Calendar added.';
                redirect_tools_calendars();
            }

            if ($action === 'update') {
                $ownStmt = $pdo->prepare("
                    SELECT id
                    FROM calendars
                    WHERE id = :id AND user_id = :user_id
                    LIMIT 1
                ");
                $ownStmt->execute([
                    ':id' => $calendarId,
                    ':user_id' => $userId,
                ]);

                if (!$ownStmt->fetch()) {
                    $errors[] = 'Calendar not found.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE calendars
                        SET
                            name = :name,
                            color = :color,
                            ics_url = :ics_url,
                            timezone = :timezone,
                            is_active = :is_active,
                            updated_at = NOW()
                        WHERE id = :id AND user_id = :user_id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':id' => $calendarId,
                        ':user_id' => $userId,
                        ':name' => $name,
                        ':color' => $color,
                        ':ics_url' => $icsUrl,
                        ':timezone' => $timezone,
                        ':is_active' => $isActive,
                    ]);

                    $_SESSION['tools_flash'] = 'Calendar updated.';
                    redirect_tools_calendars();
                }
            }
        }

        $editingId = $action === 'update' ? $calendarId : 0;
        $formData = [
            'name' => $name,
            'ics_url' => $icsUrl,
            'color' => trim((string)($_POST['color'] ?? '')),
            'timezone' => trim((string)($_POST['timezone'] ?? '')),
            'is_active' => $isActive,
        ];
    }

    if ($action === 'toggle') {
        $calendarId = (int)($_POST['calendar_id'] ?? 0);

        $stmt = $pdo->prepare("
            UPDATE calendars
            SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END,
                updated_at = NOW()
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $calendarId,
            ':user_id' => $userId,
        ]);

        $_SESSION['tools_flash'] = 'Calendar status updated.';
        redirect_tools_calendars();
    }

    if ($action === 'delete') {
        $calendarId = (int)($_POST['calendar_id'] ?? 0);

        $stmt = $pdo->prepare("
            DELETE FROM calendars
            WHERE id = :id AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $calendarId,
            ':user_id' => $userId,
        ]);

        $_SESSION['tools_flash'] = 'Calendar deleted.';
        redirect_tools_calendars();
    }
}

$calStmt = $pdo->prepare("
    SELECT
        id,
        name,
        color,
        ics_url,
        timezone,
        is_active,
        is_default,
        last_sync_at,
        sync_status,
        sync_error_message,
        created_at,
        updated_at
    FROM calendars
    WHERE user_id = :user_id
    ORDER BY is_default DESC, is_active DESC, name ASC
");
$calStmt->execute([':user_id' => $userId]);
$calendars = $calStmt->fetchAll(PDO::FETCH_ASSOC);

if ($editingId > 0) {
    foreach ($calendars as $calendar) {
        if ((int)$calendar['id'] === $editingId) {
            $editingCalendar = $calendar;
            break;
        }
    }

    if ($editingCalendar && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formData = [
            'name' => (string)$editingCalendar['name'],
            'ics_url' => (string)$editingCalendar['ics_url'],
            'color' => (string)($editingCalendar['color'] ?? ''),
            'timezone' => (string)($editingCalendar['timezone'] ?? ''),
            'is_active' => (int)($editingCalendar['is_active'] ?? 0),
        ];
    }
}

$commonTimezones = [
    'America/Chicago',
    'America/New_York',
    'America/Denver',
    'America/Los_Angeles',
    'America/Phoenix',
    'UTC',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Calendars | Nick Sanzeri</title>
  <meta name="description" content="Manage your iCal calendar feeds for Ready Set Shows Calendar Tools.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .tools-shell{padding:2rem 0 4rem;}
    .tools-topbar{display:flex;justify-content:space-between;align-items:flex-end;gap:1.25rem;margin-bottom:1.25rem;}
    .tools-topbar h1{margin:0 0 .35rem;}
    .tools-topbar p{margin:0;color:rgba(255,255,255,.74);}
    .tools-subnav{display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem;padding:.9rem;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);}
    .tools-subnav a{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem .95rem;border-radius:999px;text-decoration:none;color:rgba(255,255,255,.82);background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);transition:.2s ease;}
    .tools-subnav a:hover,.tools-subnav a.active{color:#111;background:#d4af37;border-color:#d4af37;}
    .tools-layout{display:grid;grid-template-columns:420px minmax(0,1fr);gap:1.5rem;align-items:start;}
    .tools-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:1.35rem;box-shadow:0 16px 34px rgba(0,0,0,.18);}
    .tools-card h2,.tools-card h3{margin-top:0;margin-bottom:.8rem;}
    .tools-muted{color:rgba(255,255,255,.72);}
    .tools-stack{display:grid;gap:1rem;}
    .tools-field{display:grid;gap:.45rem;}
    .tools-field label{font-size:.95rem;font-weight:500;color:#fff;}
    .tools-input,.tools-select,.tools-textarea{width:100%;padding:.85rem .95rem;border-radius:14px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#fff;font:inherit;}
    .tools-input::placeholder,.tools-textarea::placeholder{color:rgba(255,255,255,.45);}
    .tools-check{display:flex;align-items:center;gap:.7rem;padding:.75rem .85rem;border-radius:14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);}
    .tools-check input{accent-color:#d4af37;}
    .tools-actions{display:flex;flex-wrap:wrap;gap:.75rem;}
    .tools-actions .btn{justify-content:center;}
    .flash,.error-box{padding:1rem 1.1rem;border-radius:16px;margin-bottom:1rem;}
    .flash{background:rgba(93,201,126,.12);border:1px solid rgba(93,201,126,.28);color:#9af0b3;}
    .error-box{background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.25);color:#ffb3b3;}
    .error-box ul{margin:.45rem 0 0 1.1rem;}
    .calendar-list{display:grid;gap:1rem;}
    .calendar-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;padding:1.1rem;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);}
    .calendar-main{display:grid;gap:.45rem;}
    .calendar-title{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;}
    .calendar-dot{width:12px;height:12px;border-radius:999px;display:inline-block;flex:0 0 12px;background:#d4af37;box-shadow:0 0 0 2px rgba(255,255,255,.08);}
    .calendar-title strong{font-size:1.05rem;color:#fff;}
    .calendar-meta{display:flex;flex-wrap:wrap;gap:.55rem;}
    .pill{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .6rem;border-radius:999px;font-size:.78rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;}
    .pill.ok{background:rgba(93,201,126,.14);color:#8ee8a9;}
    .pill.error{background:rgba(255,107,107,.14);color:#ffb3b3;}
    .pill.never{background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);}
    .pill.active{background:rgba(212,175,55,.14);color:#f2d67c;}
    .pill.inactive{background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);}
    .calendar-url{word-break:break-all;color:rgba(255,255,255,.72);font-size:.93rem;}
    .calendar-extra{display:grid;gap:.2rem;color:rgba(255,255,255,.66);font-size:.9rem;}
    .calendar-actions{display:flex;flex-direction:column;gap:.55rem;min-width:140px;}
    .calendar-actions form,.calendar-actions a{margin:0;}
    .calendar-actions .btn{width:100%;justify-content:center;}
    .helper-list{margin:0;padding-left:1.1rem;color:rgba(255,255,255,.82);line-height:1.8;}
    .empty-state{padding:1.2rem;border-radius:18px;background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.14);}
    .color-field-wrap{
	  display:flex;
	  align-items:center;
	  gap:.75rem;
	}
	
	.tools-color-input{
	  appearance:none;
	  -webkit-appearance:none;
	  width:72px;
	  min-width:72px;
	  height:48px;
	  padding:.35rem;
	  border-radius:14px;
	  cursor:pointer;
	  background:rgba(255,255,255,.05);
	}
	
	.tools-color-input::-webkit-color-swatch-wrapper{
	  padding:0;
	}
	
	.tools-color-input::-webkit-color-swatch{
	  border:none;
	  border-radius:10px;
	}
	
	.tools-color-input::-moz-color-swatch{
	  border:none;
	  border-radius:10px;
	}
	
	.color-value{
	  display:inline-flex;
	  align-items:center;
	  min-height:48px;
	  padding:0 .9rem;
	  border-radius:14px;
	  background:rgba(255,255,255,.05);
	  border:1px solid rgba(255,255,255,.1);
	  color:rgba(255,255,255,.82);
	  font-size:.95rem;
	  letter-spacing:.04em;
	}
    @media (max-width: 980px){
      .tools-layout{grid-template-columns:1fr;}
      .tools-topbar{flex-direction:column;align-items:flex-start;}
      .calendar-row{grid-template-columns:1fr;}
      .calendar-actions{min-width:0;}
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
        <h1>Manage Calendars</h1>
        <p>Connect the iCal feeds you want to use for availability checks, print views, and exports.</p>
      </div>
      <div class="tools-muted">
        Signed in as <?= e($user['email'] ?? '') ?>
      </div>
    </div>

    <nav class="tools-subnav" aria-label="Calendar tools navigation">
      <a href="<?= e(base_url('/tools/index.php')) ?>">
        <i class="fa-regular fa-calendar-check"></i> Availability
      </a>
      <a href="<?= e(base_url('/tools/pretty-print.php')) ?>">
        <i class="fa-solid fa-print"></i> Print Views
      </a>
      <a href="<?= e(base_url('/tools/bandsintown.php')) ?>">
        <i class="fa-solid fa-file-csv"></i> Bandsintown Export
      </a>
      <a class="active" href="<?= e(base_url('/tools/calendars.php')) ?>">
        <i class="fa-solid fa-link"></i> Manage Calendars
      </a>
    </nav>

    <?php if ($flash): ?>
      <div class="flash"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="error-box">
        <strong>Please fix the following:</strong>
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="tools-layout">
      <section class="tools-card">
        <p class="eyebrow"><?= $editingCalendar ? 'Edit Calendar' : 'Add Calendar' ?></p>
        <h2><?= $editingCalendar ? 'Update this calendar feed' : 'Add a new iCal feed' ?></h2>
        <p class="tools-muted" style="margin-top:-.25rem;margin-bottom:1rem;">
          Paste an iCal URL from Google Calendar, Apple Calendar, Outlook, or another calendar service that provides ICS feeds.
        </p>

        <form method="post" action="<?= e(base_url('/tools/calendars.php' . ($editingCalendar ? '?edit=' . (int)$editingCalendar['id'] : ''))) ?>">
          <input type="hidden" name="action" value="<?= $editingCalendar ? 'update' : 'create' ?>">
          <?php if ($editingCalendar): ?>
            <input type="hidden" name="calendar_id" value="<?= (int)$editingCalendar['id'] ?>">
          <?php endif; ?>

          <div class="tools-stack">
            <div class="tools-field">
              <label for="name">Calendar Name</label>
              <input
                class="tools-input"
                type="text"
                id="name"
                name="name"
                maxlength="255"
                placeholder="Nick - Personal Calendar"
                value="<?= e($formData['name'] ?? '') ?>"
                required
              >
            </div>

            <div class="tools-field">
              <label for="ics_url">iCal URL</label>
              <input
                class="tools-input"
                type="url"
                id="ics_url"
                name="ics_url"
                maxlength="512"
                placeholder="https://calendar.google.com/calendar/ical/..."
                value="<?= e($formData['ics_url'] ?? '') ?>"
                required
              >
            </div>

			<div class="tools-field">
			  <label for="color">Color</label>
			  <div class="color-field-wrap">
			    <input
			      class="tools-input tools-color-input"
			      type="color"
			      id="color"
			      name="color"
			      value="<?= e(!empty($formData['color']) ? $formData['color'] : '#D4AF37') ?>"
			    >
			    <span class="color-value" id="colorValue">
			      <?= e(!empty($formData['color']) ? strtoupper($formData['color']) : '#D4AF37') ?>
			    </span>
			  </div>
			</div>

            <div class="tools-field">
              <label for="timezone">Timezone (optional)</label>
              <select class="tools-select" id="timezone" name="timezone">
                <option value="">Use feed or account timezone</option>
                <?php foreach ($commonTimezones as $tz): ?>
                  <option value="<?= e($tz) ?>" <?= (($formData['timezone'] ?? '') === $tz) ? 'selected' : '' ?>>
                    <?= e($tz) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <label class="tools-check">
              <input type="checkbox" name="is_active" value="1" <?= !empty($formData['is_active']) ? 'checked' : '' ?>>
              <span>Calendar is active and available for checks</span>
            </label>

            <div class="tools-actions">
              <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-save"></i>&nbsp;
                <?= $editingCalendar ? 'Update Calendar' : 'Add Calendar' ?>
              </button>

              <?php if ($editingCalendar): ?>
                <a class="btn btn-secondary" href="<?= e(base_url('/tools/calendars.php')) ?>">
                  Cancel Edit
                </a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </section>

      <section class="tools-card">
        <p class="eyebrow">Your Calendars</p>
        <h2>Connected calendar feeds</h2>
        <p class="tools-muted" style="margin-top:-.25rem;margin-bottom:1rem;">
          These are the feeds your availability tools will read from.
        </p>

        <?php if (!$calendars): ?>
          <div class="empty-state">
            <h3 style="margin-top:0;">No calendars connected yet</h3>
            <p class="tools-muted" style="margin-bottom:0;">
              Add your first iCal feed on the left to get started.
            </p>
          </div>
        <?php else: ?>
          <div class="calendar-list">
            <?php foreach ($calendars as $calendar): ?>
              <?php
                $dotColor = !empty($calendar['color']) ? $calendar['color'] : '#D4AF37';
                $syncStatus = (string)($calendar['sync_status'] ?? 'never');
                $statusClass = in_array($syncStatus, ['ok', 'error', 'never'], true) ? $syncStatus : 'never';
              ?>
              <article class="calendar-row">
                <div class="calendar-main">
                  <div class="calendar-title">
                    <span class="calendar-dot" style="background: <?= e($dotColor) ?>;"></span>
                    <strong><?= e($calendar['name']) ?></strong>

                    <span class="pill <?= ((int)$calendar['is_active'] === 1) ? 'active' : 'inactive' ?>">
                      <?= ((int)$calendar['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                    </span>

                  </div>

                  <div class="calendar-url"><?= e($calendar['ics_url']) ?></div>

                  <div class="calendar-extra">
                    <?php if (!empty($calendar['timezone'])): ?>
                      <div><strong>Timezone:</strong> <?= e($calendar['timezone']) ?></div>
                    <?php endif; ?>

                  </div>
                </div>

                <div class="calendar-actions">
                  <a class="btn btn-secondary" href="<?= e(base_url('/tools/calendars.php?edit=' . (int)$calendar['id'])) ?>">
                    Edit
                  </a>

                  <form method="post" action="<?= e(base_url('/tools/calendars.php')) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="calendar_id" value="<?= (int)$calendar['id'] ?>">
                    <button class="btn btn-secondary" type="submit">
                      <?= ((int)$calendar['is_active'] === 1) ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>

                  <form method="post" action="<?= e(base_url('/tools/calendars.php')) ?>" onsubmit="return confirm('Delete this calendar?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="calendar_id" value="<?= (int)$calendar['id'] ?>">
                    <button class="btn btn-secondary" type="submit">
                      Delete
                    </button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div style="margin-top:1.25rem;">
          <h3 style="margin-bottom:.75rem;">Tips</h3>
          <ul class="helper-list">
            <li>Use one calendar per person or per source you want included.</li>
            <li>Inactive calendars stay saved, but won’t be used in availability checks.</li>
            <li>You can leave timezone blank unless a feed needs a specific fallback.</li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>
  (function () {
    const colorInput = document.getElementById('color');
    const colorValue = document.getElementById('colorValue');

    if (colorInput && colorValue) {
      const syncColorLabel = () => {
        colorValue.textContent = (colorInput.value || '').toUpperCase();
      };

      colorInput.addEventListener('input', syncColorLabel);
      syncColorLabel();
    }
  })();
</script>
</body>
</html>
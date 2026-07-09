<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';

function booking_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function booking_table_exists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function booking_table_ready(PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('booking_requests', 'booking_invites', 'setmaxx_public_profiles', 'users')");
    $stmt->execute();
    return (int)$stmt->fetchColumn() === 4;
}

function booking_selected_artist_ids(array $source): array {
    $raw = $source['artists'] ?? [];
    if (!is_array($raw)) $raw = [$raw];
    $ids = [];
    foreach ($raw as $id) {
        $id = (int)$id;
        if ($id > 0) $ids[$id] = $id;
    }
    return array_values($ids);
}

function booking_fetch_directory_artists(PDO $pdo, bool $profilesReady): array {
    $profileSelect = $profilesReady ? 'p.id AS profile_id' : 'NULL AS profile_id';
    $profileJoin = $profilesReady ? "
        LEFT JOIN (
            SELECT user_id, MIN(id) AS id
            FROM profiles
            WHERE profile_type = 'artist'
              AND is_active = 1
              AND deleted_at IS NULL
            GROUP BY user_id
        ) p ON p.user_id = pp.user_id
    " : '';

    $sql = "
        SELECT
            pp.user_id,
            pp.directory_state,
            pp.artist_name,
            pp.website_url,
            pp.logo_path,
            u.display_name,
            {$profileSelect}
        FROM setmaxx_public_profiles pp
        JOIN users u ON u.id = pp.user_id
        {$profileJoin}
        WHERE pp.directory_visible = 1
          AND COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) IS NOT NULL
        ORDER BY
            CASE WHEN pp.directory_state IS NULL OR pp.directory_state = '' THEN 1 ELSE 0 END,
            pp.directory_state ASC,
            COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, '')) ASC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function booking_clean_text(string $value, int $maxLen): string {
    $value = trim(preg_replace('/\s+/', ' ', $value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return mb_strlen($value) > $maxLen ? mb_substr($value, 0, $maxLen) : $value;
}

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$ready = booking_table_ready($pdo);
$targetUserReady = $ready && booking_column_exists($pdo, 'booking_invites', 'target_user_id');
$profilesReady = booking_table_exists($pdo, 'profiles');

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = $siteBase . '/booking-request.php';
    $_SESSION['registration_account_type'] = 'customer';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $_SESSION['login_next'] .= '?' . (string)$_SERVER['QUERY_STRING'];
    }
    redirect($siteBase . '/studio/member/login.php');
}

$currentUser = Auth::currentUser($pdo);
$artists = $ready ? booking_fetch_directory_artists($pdo, $profilesReady) : [];
$artistByUserId = [];
foreach ($artists as $artist) {
    $artistByUserId[(int)$artist['user_id']] = $artist;
}

$myRequests = [];
if ($ready) {
    $requestListStmt = $pdo->prepare("
        SELECT br.id, br.event_title, br.event_date, br.city, br.state, br.status, br.created_at, COUNT(bi.id) AS invite_count
        FROM booking_requests br
        LEFT JOIN booking_invites bi ON bi.request_id = br.id
        WHERE br.requester_user_id = ?
        GROUP BY br.id, br.event_title, br.event_date, br.city, br.state, br.status, br.created_at
        ORDER BY br.created_at DESC
        LIMIT 5
    ");
    $requestListStmt->execute([(int)$currentUser['id']]);
    $myRequests = $requestListStmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedIds = booking_selected_artist_ids($_GET);
$errors = [];
$successRequestId = 0;

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Security check failed. Please try again.';
    }

    $selectedIds = booking_selected_artist_ids($_POST);
    $selectedIds = array_values(array_filter($selectedIds, fn($id) => isset($artistByUserId[$id])));
    if (!$selectedIds) {
        $errors[] = 'Choose at least one band to invite.';
    }
    if (!$ready || !$targetUserReady) {
        $errors[] = 'The booking bid tables need the latest database update before requests can be saved.';
    }

    $contactName = booking_clean_text((string)($_POST['contact_name'] ?? ''), 120);
    $contactEmail = strtolower(booking_clean_text((string)($_POST['contact_email'] ?? ''), 190));
    $contactPhone = booking_clean_text((string)($_POST['contact_phone'] ?? ''), 64);
    $eventTitle = booking_clean_text((string)($_POST['event_title'] ?? ''), 190);
    $eventType = booking_clean_text((string)($_POST['event_type'] ?? ''), 80);
    $eventDate = booking_clean_text((string)($_POST['event_date'] ?? ''), 10);
    $startTime = booking_clean_text((string)($_POST['start_time'] ?? ''), 5);
    $venueName = booking_clean_text((string)($_POST['venue_name'] ?? ''), 190);
    $city = booking_clean_text((string)($_POST['city'] ?? ''), 120);
    $state = strtoupper(booking_clean_text((string)($_POST['state'] ?? ''), 2));
    $budgetMax = (float)($_POST['budget_max'] ?? 0);
    $guestCount = (int)($_POST['guest_count'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    if (mb_strlen($notes) > 4000) $notes = mb_substr($notes, 0, 4000);

    if ($contactName === '') $errors[] = 'Add your name.';
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Add a valid email address.';
    if ($eventTitle === '') $errors[] = 'Add a short event title.';
    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) $errors[] = 'Use a valid event date.';
    if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) $errors[] = 'Use a valid start time.';
    if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) $errors[] = 'Use a two-letter state.';

    if (!$errors) {
        $detailNotes = [];
        if ($eventType !== '') $detailNotes[] = 'Event type: ' . $eventType;
        if ($guestCount > 0) $detailNotes[] = 'Estimated guests: ' . $guestCount;
        if ($notes !== '') $detailNotes[] = $notes;

        $pdo->beginTransaction();
        try {
            $requestStmt = $pdo->prepare("
                INSERT INTO booking_requests (
                    requester_user_id, requester_type, contact_name, contact_email, contact_phone,
                    event_title, event_date, start_time, venue_name, city, state, budget_max, notes, status
                ) VALUES (?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $requestStmt->execute([
                (int)$currentUser['id'],
                $contactName,
                $contactEmail,
                $contactPhone !== '' ? $contactPhone : null,
                $eventTitle,
                $eventDate !== '' ? $eventDate : null,
                $startTime !== '' ? $startTime . ':00' : null,
                $venueName !== '' ? $venueName : null,
                $city !== '' ? $city : null,
                $state !== '' ? $state : null,
                $budgetMax > 0 ? $budgetMax : null,
                implode("\n", $detailNotes),
            ]);
            $successRequestId = (int)$pdo->lastInsertId();

            $inviteStmt = $pdo->prepare("
                INSERT INTO booking_invites (request_id, target_user_id, target_profile_id, target_type, message)
                VALUES (?, ?, ?, 'artist', ?)
            ");
            foreach ($selectedIds as $userId) {
                $artist = $artistByUserId[$userId];
                $inviteStmt->execute([
                    $successRequestId,
                    $userId,
                    !empty($artist['profile_id']) ? (int)$artist['profile_id'] : null,
                    'Customer requested a bid from the public artist directory.',
                ]);
            }

            $pdo->commit();
            flash_set('success', 'Your event request was created and sent to the selected bands.');
            redirect($siteBase . '/booking-request.php?sent=' . $successRequestId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$sentId = (int)($_GET['sent'] ?? 0);
$successMessage = $sentId > 0 ? flash_get('success') : null;
if (!$selectedIds && count($artists) === 1) {
    $selectedIds = [(int)$artists[0]['user_id']];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Request Band Bids | Ready Set Shows</title>
  <meta name="description" content="Create an event request and invite multiple Ready Set Shows bands to bid on your date.">
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .booking-bid-shell { padding:3rem 0 4rem; }
    .booking-bid-hero { display:grid; gap:.75rem; max-width:840px; margin-bottom:1.5rem; }
    .booking-bid-kicker { color:#d4af37; font-weight:700; letter-spacing:.08em; text-transform:uppercase; font-size:.82rem; }
    .booking-bid-hero p { color:rgba(255,255,255,.76); }
    .booking-bid-layout { display:grid; grid-template-columns:minmax(0, 1.1fr) minmax(280px, .9fr); gap:1.25rem; align-items:start; }
    .booking-bid-panel { padding:1rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.045); }
    .booking-bid-panel h2 { margin-top:0; font-size:1.2rem; }
    .booking-band-list { display:grid; gap:.65rem; max-height:620px; overflow:auto; padding-right:.25rem; }
    .booking-band-option { display:grid; grid-template-columns:auto 1fr; gap:.7rem; align-items:start; padding:.75rem; border:1px solid rgba(255,255,255,.1); border-radius:8px; background:rgba(255,255,255,.035); cursor:pointer; }
    .booking-band-option:has(input:checked) { border-color:rgba(212,175,55,.48); background:rgba(212,175,55,.12); }
    .booking-band-option input { margin-top:.25rem; width:18px; height:18px; }
    .booking-band-option strong { display:block; line-height:1.25; }
    .booking-band-option span { color:rgba(255,255,255,.68); font-size:.9rem; }
    .booking-request-list { display:grid; gap:.65rem; margin:0 0 1.25rem; }
    .booking-request-item { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:.85rem 1rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); }
    .booking-request-item strong { display:block; }
    .booking-request-item span { color:rgba(255,255,255,.68); font-size:.92rem; }
    .booking-status { display:inline-flex; align-items:center; min-height:28px; padding:.25rem .65rem; border-radius:999px; background:rgba(212,175,55,.14); color:#f4d57a; font-weight:700; font-size:.82rem; text-transform:capitalize; }
    .booking-alert { margin:1rem 0; padding:.85rem 1rem; border-radius:8px; border:1px solid rgba(212,175,55,.28); background:rgba(212,175,55,.12); color:rgba(255,255,255,.9); }
    .booking-alert.error { border-color:rgba(255,104,104,.35); background:rgba(255,104,104,.1); }
    .booking-actions { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-top:1rem; }
    @media (max-width: 860px) { .booking-bid-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container booking-bid-shell">
  <section class="booking-bid-hero">
    <div class="booking-bid-kicker">Book Bands</div>
    <h1>Create one event request. Invite multiple bands to bid.</h1>
    <p>Tell artists what you are planning, choose the bands you want to hear from, and they can follow up with availability and pricing.</p>
  </section>

  <?php if ($successMessage): ?>
    <div class="booking-alert"><?= e($successMessage) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="booking-alert error">
      <?= e(implode(' ', $errors)) ?>
    </div>
  <?php endif; ?>

  <?php if (!$ready || !$targetUserReady): ?>
    <div class="booking-alert error">The booking bid flow needs the latest database migration before requests can be saved.</div>
  <?php endif; ?>

  <?php if ($myRequests): ?>
    <section class="booking-request-list" aria-label="Recent booking requests">
      <?php foreach ($myRequests as $request): ?>
        <?php
          $eventDate = !empty($request['event_date']) ? date('M j, Y', strtotime((string)$request['event_date'])) : 'Date TBD';
          $location = trim((string)($request['city'] ?? '') . (!empty($request['state']) ? ', ' . (string)$request['state'] : ''));
        ?>
        <div class="booking-request-item">
          <div>
            <strong><?= e((string)($request['event_title'] ?: 'Untitled event')) ?></strong>
            <span><?= e($eventDate) ?><?= $location !== '' ? ' · ' . e($location) : '' ?> · <?= (int)$request['invite_count'] ?> band<?= (int)$request['invite_count'] === 1 ? '' : 's' ?> invited</span>
          </div>
          <span class="booking-status"><?= e((string)$request['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <form method="post" class="booking-bid-layout">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <section class="form booking-bid-panel">
      <h2 class="form-title">Event Details</h2>
      <div class="form-grid">
        <div class="form-field">
          <label for="contact_name">Your name*</label>
          <input id="contact_name" name="contact_name" required value="<?= e((string)($_POST['contact_name'] ?? ($currentUser['display_name'] ?? ''))) ?>">
        </div>
        <div class="form-field">
          <label for="contact_email">Email*</label>
          <input id="contact_email" name="contact_email" type="email" required value="<?= e((string)($_POST['contact_email'] ?? ($currentUser['email'] ?? ''))) ?>">
        </div>
        <div class="form-field">
          <label for="contact_phone">Phone</label>
          <input id="contact_phone" name="contact_phone" type="tel" value="<?= e((string)($_POST['contact_phone'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="event_title">Event title*</label>
          <input id="event_title" name="event_title" required placeholder="e.g. Company holiday party" value="<?= e((string)($_POST['event_title'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="event_type">Event type</label>
          <select id="event_type" name="event_type">
            <?php foreach (['' => 'Select one', 'Wedding' => 'Wedding', 'Private Party' => 'Private Party', 'Corporate Event' => 'Corporate Event', 'Venue Event' => 'Venue Event', 'Festival' => 'Festival', 'Other' => 'Other'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string)($_POST['event_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="event_date">Event date</label>
          <input id="event_date" name="event_date" type="date" value="<?= e((string)($_POST['event_date'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="start_time">Approx. start time</label>
          <input id="start_time" name="start_time" type="time" value="<?= e((string)($_POST['start_time'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="guest_count">Estimated guests</label>
          <input id="guest_count" name="guest_count" type="number" min="1" value="<?= e((string)($_POST['guest_count'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="venue_name">Venue name</label>
          <input id="venue_name" name="venue_name" value="<?= e((string)($_POST['venue_name'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="city">City</label>
          <input id="city" name="city" value="<?= e((string)($_POST['city'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="state">State</label>
          <input id="state" name="state" maxlength="2" value="<?= e((string)($_POST['state'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label for="budget_max">Budget up to</label>
          <input id="budget_max" name="budget_max" type="number" min="0" step="50" value="<?= e((string)($_POST['budget_max'] ?? '')) ?>">
        </div>
      </div>
      <div class="form-field">
        <label for="notes">Notes for the bands</label>
        <textarea id="notes" name="notes" rows="5" placeholder="Share the vibe, set length, load-in details, special songs, or anything else that affects the quote."><?= e((string)($_POST['notes'] ?? '')) ?></textarea>
      </div>
      <div class="booking-actions">
        <button class="btn btn-primary" type="submit">Send Bid Requests</button>
        <a class="text-link" href="<?= e($siteBase . '/directory.php') ?>">Browse directory</a>
      </div>
    </section>

    <aside class="booking-bid-panel">
      <h2>Select Bands</h2>
      <?php if (!$artists): ?>
        <p class="muted">No directory bands are available yet.</p>
      <?php else: ?>
        <div class="booking-band-list">
          <?php foreach ($artists as $artist): ?>
            <?php
              $artistUserId = (int)$artist['user_id'];
              $name = trim((string)($artist['artist_name'] ?: $artist['display_name']));
              $state = trim((string)($artist['directory_state'] ?? ''));
            ?>
            <label class="booking-band-option">
              <input type="checkbox" name="artists[]" value="<?= $artistUserId ?>" <?= in_array($artistUserId, $selectedIds, true) ? 'checked' : '' ?>>
              <span>
                <strong><?= e($name) ?></strong>
                <span><?= e($state !== '' ? $state : 'State not set') ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>
  </form>
</main>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>

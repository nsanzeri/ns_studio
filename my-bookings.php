<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = $siteBase . '/my-bookings.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . (string)$_SERVER['QUERY_STRING'] : '');
    $_SESSION['registration_account_type'] = 'customer';
    redirect($siteBase . '/studio/member/login.php?brand=rss');
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$errors = [];
$successMessage = flash_get('success');

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Security check failed. Please try again.';
    }

    $action = (string)($_POST['action'] ?? '');
    if (!$errors && $action === 'delete_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $errors[] = 'Choose a booking request to delete.';
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM booking_requests WHERE id = ? AND requester_user_id = ? LIMIT 1");
            $deleteStmt->execute([$requestId, $userId]);
            if ($deleteStmt->rowCount() > 0) {
                flash_set('success', 'Booking request deleted.');
                redirect($siteBase . '/my-bookings.php');
            }
            $errors[] = 'That booking request could not be found.';
        }
    }
}

$selectedId = (int)($_GET['id'] ?? 0);

$requestsStmt = $pdo->prepare("
    SELECT br.id, br.event_title, br.event_date, br.start_time, br.venue_name, br.city, br.state, br.budget_max, br.notes, br.status, br.created_at, COUNT(bi.id) AS invite_count
    FROM booking_requests br
    LEFT JOIN booking_invites bi ON bi.request_id = br.id
    WHERE br.requester_user_id = ?
    GROUP BY br.id, br.event_title, br.event_date, br.start_time, br.venue_name, br.city, br.state, br.budget_max, br.notes, br.status, br.created_at
    ORDER BY br.created_at DESC
");
$requestsStmt->execute([$userId]);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($selectedId <= 0 && $requests) {
    $selectedId = (int)$requests[0]['id'];
}

$selectedRequest = null;
foreach ($requests as $request) {
    if ((int)$request['id'] === $selectedId) {
        $selectedRequest = $request;
        break;
    }
}

$invites = [];
if ($selectedRequest) {
    $inviteStmt = $pdo->prepare("
        SELECT
            bi.id,
            bi.status,
            bi.quote_amount,
            bi.quote_message,
            bi.sent_at,
            COALESCE(NULLIF(pp.artist_name, ''), NULLIF(u.display_name, ''), u.email, 'Artist') AS artist_name,
            u.email AS artist_email,
            bb.amount AS bid_amount,
            bb.message AS bid_message,
            bb.created_at AS bid_created_at
        FROM booking_invites bi
        LEFT JOIN users u ON u.id = bi.target_user_id
        LEFT JOIN setmaxx_public_profiles pp ON pp.user_id = bi.target_user_id
        LEFT JOIN booking_bids bb ON bb.invite_id = bi.id AND bb.status IN ('sent', 'accepted')
        WHERE bi.request_id = ?
        ORDER BY artist_name ASC
    ");
    $inviteStmt->execute([$selectedId]);
    $invites = $inviteStmt->fetchAll(PDO::FETCH_ASSOC);
}

$loginUrl = $siteBase . '/studio/member/login.php?brand=rss';
$trialUrl = $siteBase . '/studio/member/pricing.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Bookings | Ready Set Shows</title>
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .bookings-shell { padding:3rem 0 4rem; }
    .bookings-layout { display:grid; grid-template-columns:minmax(260px,.85fr) minmax(0,1.35fr); gap:1rem; align-items:start; }
    .booking-panel { border:1px solid rgba(255,255,255,.08); border-radius:8px; background:rgba(255,255,255,.045); padding:1rem; }
    .booking-list { display:grid; gap:.6rem; }
    .booking-list-item { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; align-items:center; padding:.2rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); }
    .booking-list-item.active, .booking-list-item:hover { border-color:rgba(212,175,55,.45); background:rgba(212,175,55,.1); }
    .booking-list a { display:block; min-width:0; padding:.6rem; text-decoration:none; color:rgba(255,255,255,.9); }
    .booking-list-item.active a, .booking-list-item:hover a { color:#f4d57a; }
    .booking-delete-button { border:1px solid rgba(255,255,255,.16); background:transparent; color:rgba(255,255,255,.75); border-radius:999px; min-height:34px; padding:.25rem .7rem; font:inherit; font-size:.78rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; cursor:pointer; }
    .booking-delete-button:hover { border-color:rgba(255,104,104,.55); color:#ffb0b0; }
    .booking-meta { color:rgba(255,255,255,.68); font-size:.92rem; }
    .bid-row { display:grid; gap:.45rem; padding:.85rem 0; border-top:1px solid rgba(255,255,255,.08); }
    .bid-row:first-child { border-top:0; }
    .bid-amount { color:#f4d57a; font-weight:800; }
    .booking-alert { margin:0 0 1rem; padding:.85rem 1rem; border-radius:8px; border:1px solid rgba(212,175,55,.28); background:rgba(212,175,55,.12); color:rgba(255,255,255,.9); }
    .booking-alert.error { border-color:rgba(255,104,104,.35); background:rgba(255,104,104,.1); }
    @media (max-width: 860px) { .bookings-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container bookings-shell">
  <div style="display:flex; justify-content:space-between; gap:1rem; align-items:end; flex-wrap:wrap; margin-bottom:1.25rem;">
    <div>
      <h1>My Bookings</h1>
      <p class="muted">Track bid requests and see which artists have responded.</p>
    </div>
    <a class="btn btn-primary" href="<?= e($siteBase . '/booking-request.php') ?>">Create Booking</a>
  </div>

  <?php if ($successMessage): ?>
    <div class="booking-alert"><?= e($successMessage) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="booking-alert error"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!$requests): ?>
    <section class="booking-panel">
      <p class="muted">No booking requests yet.</p>
      <a class="btn btn-primary" href="<?= e($siteBase . '/booking-request.php') ?>">Start a Booking</a>
    </section>
  <?php else: ?>
    <div class="bookings-layout">
      <aside class="booking-panel">
        <h2 class="form-title">Requests</h2>
        <div class="booking-list">
          <?php foreach ($requests as $request): ?>
            <?php $dateText = !empty($request['event_date']) ? date('M j, Y', strtotime((string)$request['event_date'])) : 'Date TBD'; ?>
            <div class="booking-list-item <?= (int)$request['id'] === $selectedId ? 'active' : '' ?>">
              <a href="<?= e($siteBase . '/my-bookings.php?id=' . (int)$request['id']) ?>">
                <strong><?= e((string)($request['event_title'] ?: 'Untitled event')) ?></strong>
                <div class="booking-meta"><?= e($dateText) ?> &middot; <?= (int)$request['invite_count'] ?> invited</div>
              </a>
              <form method="post" onsubmit="return confirm('Delete this booking request?');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_request">
                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                <button class="booking-delete-button" type="submit">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </aside>

      <section class="booking-panel">
        <?php if (!$selectedRequest): ?>
          <p class="muted">Choose a booking request.</p>
        <?php else: ?>
          <?php $dateText = !empty($selectedRequest['event_date']) ? date('M j, Y', strtotime((string)$selectedRequest['event_date'])) : 'Date TBD'; ?>
          <h2><?= e((string)($selectedRequest['event_title'] ?: 'Untitled event')) ?></h2>
          <p class="booking-meta">
            <?= e($dateText) ?>
            <?php if (!empty($selectedRequest['venue_name'])): ?> &middot; <?= e((string)$selectedRequest['venue_name']) ?><?php endif; ?>
            <?php if (!empty($selectedRequest['city']) || !empty($selectedRequest['state'])): ?> &middot; <?= e(trim((string)$selectedRequest['city'] . ', ' . (string)$selectedRequest['state'], ' ,')) ?><?php endif; ?>
            <?php if (!empty($selectedRequest['budget_max'])): ?> &middot; Budget up to $<?= e(number_format((float)$selectedRequest['budget_max'], 0)) ?><?php endif; ?>
          </p>
          <?php if (!empty($selectedRequest['notes'])): ?>
            <p><?= nl2br(e((string)$selectedRequest['notes'])) ?></p>
          <?php endif; ?>

          <h3 style="margin-top:1.5rem;">Artist Responses</h3>
          <?php if (!$invites): ?>
            <p class="muted">No artists were invited.</p>
          <?php else: ?>
            <?php foreach ($invites as $invite): ?>
              <div class="bid-row">
                <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                  <strong><?= e((string)$invite['artist_name']) ?></strong>
                  <span class="booking-meta"><?= e((string)$invite['status']) ?></span>
                </div>
                <?php if (!empty($invite['bid_amount'])): ?>
                  <div class="bid-amount">$<?= e(number_format((float)$invite['bid_amount'], 2)) ?></div>
                  <?php if (!empty($invite['bid_message'])): ?><p style="margin:0;"><?= nl2br(e((string)$invite['bid_message'])) ?></p><?php endif; ?>
                  <p class="booking-meta">Continue with <?= e((string)$invite['artist_email']) ?> when you are ready to take the conversation offline.</p>
                <?php else: ?>
                  <p class="muted" style="margin:0;">Waiting for response.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>

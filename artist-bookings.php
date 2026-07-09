<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = $siteBase . '/artist-bookings.php';
    $_SESSION['registration_account_type'] = 'artist';
    redirect($siteBase . '/studio/member/login.php?brand=rss');
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$err = null;
$ok = flash_get('success');
$declineReasons = [
    'not_available' => 'Not available',
    'too_far' => 'Too far',
    'price_too_low' => 'Too low a price',
    'not_a_fit' => 'Not a fit',
    'other' => 'Other',
];

function artist_booking_invite(PDO $pdo, int $inviteId, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT
            bi.id AS invite_id,
            bi.status AS invite_status,
            bi.decline_reason,
            bi.decline_message,
            bi.quote_amount,
            bi.quote_message,
            br.id AS request_id,
            br.contact_name,
            br.event_title,
            br.event_date,
            br.start_time,
            br.venue_name,
            br.city,
            br.state,
            br.budget_max,
            br.notes,
            bb.amount AS bid_amount,
            bb.message AS bid_message
        FROM booking_invites bi
        JOIN booking_requests br ON br.id = bi.request_id
        LEFT JOIN booking_bids bb ON bb.invite_id = bi.id AND bb.bidder_user_id = bi.target_user_id AND bb.status IN ('sent', 'accepted')
        WHERE bi.id = ?
          AND bi.target_user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$inviteId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if (is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $err = 'Security check failed. Please try again.';
    } else {
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $invite = artist_booking_invite($pdo, $inviteId, $userId);
        if (!$invite) {
            $err = 'That booking request was not found for your account.';
        } else {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'quote') {
                $amount = (float)($_POST['amount'] ?? 0);
                $message = trim((string)($_POST['message'] ?? ''));
                if ($amount <= 0) {
                    $err = 'Add a quote amount.';
                } else {
                    $existingStmt = $pdo->prepare('SELECT id FROM booking_bids WHERE invite_id = ? AND bidder_user_id = ? LIMIT 1');
                    $existingStmt->execute([$inviteId, $userId]);
                    $existingBidId = (int)($existingStmt->fetchColumn() ?: 0);
                    if ($existingBidId > 0) {
                        $pdo->prepare("UPDATE booking_bids SET amount = ?, message = ?, status = 'sent', updated_at = NOW() WHERE id = ?")->execute([$amount, $message, $existingBidId]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO booking_bids (request_id, invite_id, bidder_user_id, bidder_profile_id, amount, status, message)
                            VALUES (?, ?, ?, NULL, ?, 'sent', ?)
                        ")->execute([(int)$invite['request_id'], $inviteId, $userId, $amount, $message]);
                    }
                    $pdo->prepare("UPDATE booking_invites SET status = 'accepted', quote_amount = ?, quote_message = ?, decline_reason = NULL, decline_message = NULL, declined_at = NULL, responded_at = NOW() WHERE id = ?")->execute([$amount, $message, $inviteId]);
                    $ok = 'Quote sent.';
                }
            }

            if ($action === 'decline') {
                $reasonKey = (string)($_POST['decline_reason'] ?? 'other');
                $reason = $declineReasons[$reasonKey] ?? $declineReasons['other'];
                $message = trim((string)($_POST['decline_message'] ?? ''));
                $pdo->prepare("UPDATE booking_invites SET status = 'declined', decline_reason = ?, decline_message = ?, declined_at = NOW(), responded_at = NOW() WHERE id = ?")->execute([$reason, $message, $inviteId]);
                $pdo->prepare("UPDATE booking_bids SET status = 'withdrawn', updated_at = NOW() WHERE invite_id = ? AND bidder_user_id = ?")->execute([$inviteId, $userId]);
                $ok = 'Request declined.';
            }
        }
    }
}

$selectedId = (int)($_GET['invite'] ?? $_POST['invite_id'] ?? 0);
$listStmt = $pdo->prepare("
    SELECT
        bi.id,
        bi.status,
        br.event_title,
        br.event_date,
        br.city,
        br.state,
        br.budget_max,
        COALESCE(bb.amount, bi.quote_amount) AS bid_amount
    FROM booking_invites bi
    JOIN booking_requests br ON br.id = bi.request_id
    LEFT JOIN booking_bids bb ON bb.invite_id = bi.id AND bb.bidder_user_id = bi.target_user_id AND bb.status IN ('sent', 'accepted')
    WHERE bi.target_user_id = ?
    ORDER BY
        CASE bi.status WHEN 'pending' THEN 1 WHEN 'accepted' THEN 2 WHEN 'declined' THEN 3 ELSE 4 END,
        br.event_date IS NULL,
        br.event_date ASC,
        bi.sent_at DESC
");
$listStmt->execute([$userId]);
$invites = $listStmt->fetchAll(PDO::FETCH_ASSOC);

if ($selectedId <= 0 && $invites) {
    $selectedId = (int)$invites[0]['id'];
}
$selectedInvite = $selectedId > 0 ? artist_booking_invite($pdo, $selectedId, $userId) : null;

$loginUrl = $siteBase . '/studio/member/login.php?brand=rss';
$trialUrl = $siteBase . '/studio/member/pricing.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Booking Requests | Ready Set Shows</title>
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .artist-booking-shell { padding:3rem 0 4rem; }
    .artist-booking-layout { display:grid; grid-template-columns:minmax(260px,.85fr) minmax(0,1.35fr); gap:1rem; align-items:start; }
    .artist-panel { border:1px solid rgba(255,255,255,.08); border-radius:8px; background:rgba(255,255,255,.045); padding:1rem; }
    .artist-invite-list { display:grid; gap:.6rem; }
    .artist-invite-list a { display:block; padding:.8rem; border-radius:8px; border:1px solid rgba(255,255,255,.08); text-decoration:none; color:rgba(255,255,255,.9); }
    .artist-invite-list a.active, .artist-invite-list a:hover { border-color:rgba(212,175,55,.45); background:rgba(212,175,55,.1); color:#f4d57a; }
    .artist-meta { color:rgba(255,255,255,.68); font-size:.92rem; }
    .artist-action-buttons { display:flex; gap:.7rem; flex-wrap:wrap; margin-top:1.1rem; }
    .artist-action-panel { display:none; margin-top:1rem; }
    .artist-action-panel.is-open { display:block; }
    .artist-response-summary { margin:1rem 0; padding:1rem; border-radius:8px; border:1px solid rgba(212,175,55,.22); background:rgba(212,175,55,.08); }
    .artist-response-summary h3 { margin:0 0 .55rem; }
    .artist-response-summary p { margin:.35rem 0 0; }
    .artist-response-amount { color:#f4d57a; font-size:1.35rem; font-weight:800; }
    .artist-quote-input { appearance:textfield; -moz-appearance:textfield; }
    .artist-quote-input::-webkit-outer-spin-button,
    .artist-quote-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    @media (max-width: 860px) { .artist-booking-layout { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container artist-booking-shell">
  <h1>Booking Requests</h1>
  <p class="muted">Review event requests, send a quote, or decline when it is not the right fit.</p>

  <?php if ($err): ?><div class="alert" style="margin:1rem 0;"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert" style="margin:1rem 0;"><?= e($ok) ?></div><?php endif; ?>

  <?php if (!$invites): ?>
    <section class="artist-panel">
      <p class="muted">No booking requests yet.</p>
    </section>
  <?php else: ?>
    <div class="artist-booking-layout">
      <aside class="artist-panel">
        <h2 class="form-title">Requests</h2>
        <div class="artist-invite-list">
          <?php foreach ($invites as $invite): ?>
            <?php $dateText = !empty($invite['event_date']) ? date('M j, Y', strtotime((string)$invite['event_date'])) : 'Date TBD'; ?>
            <a class="<?= (int)$invite['id'] === $selectedId ? 'active' : '' ?>" href="<?= e($siteBase . '/artist-bookings.php?invite=' . (int)$invite['id']) ?>">
              <strong><?= e((string)($invite['event_title'] ?: 'Untitled event')) ?></strong>
              <div class="artist-meta"><?= e($dateText) ?> &middot; <?= e((string)$invite['status']) ?><?= !empty($invite['bid_amount']) ? ' &middot; $' . e(number_format((float)$invite['bid_amount'], 0)) : '' ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>

      <section class="artist-panel">
        <?php if (!$selectedInvite): ?>
          <p class="muted">Choose a request.</p>
        <?php else: ?>
          <?php $dateText = !empty($selectedInvite['event_date']) ? date('M j, Y', strtotime((string)$selectedInvite['event_date'])) : 'Date TBD'; ?>
          <?php $inviteStatus = (string)$selectedInvite['invite_status']; ?>
          <h2><?= e((string)($selectedInvite['event_title'] ?: 'Untitled event')) ?></h2>
          <p class="artist-meta">
            <?= e($dateText) ?>
            <?php if (!empty($selectedInvite['venue_name'])): ?> &middot; <?= e((string)$selectedInvite['venue_name']) ?><?php endif; ?>
            <?php if (!empty($selectedInvite['city']) || !empty($selectedInvite['state'])): ?> &middot; <?= e(trim((string)$selectedInvite['city'] . ', ' . (string)$selectedInvite['state'], ' ,')) ?><?php endif; ?>
            <?php if (!empty($selectedInvite['budget_max'])): ?> &middot; Budget up to $<?= e(number_format((float)$selectedInvite['budget_max'], 0)) ?><?php endif; ?>
          </p>
          <?php if (!empty($selectedInvite['notes'])): ?><p><?= nl2br(e((string)$selectedInvite['notes'])) ?></p><?php endif; ?>

          <?php if ($inviteStatus === 'accepted'): ?>
            <?php
              $quoteAmount = $selectedInvite['bid_amount'] ?? $selectedInvite['quote_amount'] ?? null;
              $quoteMessage = trim((string)($selectedInvite['bid_message'] ?? $selectedInvite['quote_message'] ?? ''));
            ?>
            <div class="artist-response-summary">
              <h3>Quote sent</h3>
              <?php if (!empty($quoteAmount)): ?><div class="artist-response-amount">$<?= e(number_format((float)$quoteAmount, 0)) ?></div><?php endif; ?>
              <p><?= $quoteMessage !== '' ? nl2br(e($quoteMessage)) : '<span class="muted">No message added.</span>' ?></p>
            </div>
          <?php elseif ($inviteStatus === 'declined'): ?>
            <div class="artist-response-summary">
              <h3>Request declined</h3>
              <p><strong>Reason:</strong> <?= e((string)($selectedInvite['decline_reason'] ?: 'Other')) ?></p>
              <p><?= !empty($selectedInvite['decline_message']) ? nl2br(e((string)$selectedInvite['decline_message'])) : '<span class="muted">No note added.</span>' ?></p>
            </div>
          <?php endif; ?>

          <div class="artist-action-buttons">
            <button class="btn btn-primary" type="button" data-artist-action="quote"><?= $inviteStatus === 'accepted' ? 'Edit Quote' : 'Quote' ?></button>
            <button class="btn btn-outline" type="button" data-artist-action="decline"><?= $inviteStatus === 'declined' ? 'Edit Decline' : 'Decline' ?></button>
          </div>

          <div class="artist-action-panel <?= $inviteStatus === 'pending' ? 'is-open' : '' ?>" data-artist-panel="quote">
            <form class="form card" method="post" style="padding:1.15rem;">
              <h3 class="form-title"><?= $inviteStatus === 'accepted' ? 'Edit Quote' : 'Send Quote' ?></h3>
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="quote">
              <input type="hidden" name="invite_id" value="<?= (int)$selectedInvite['invite_id'] ?>">
              <div class="form-field">
                <label>Quote amount</label>
                <input class="artist-quote-input" type="number" name="amount" min="0" step="1" inputmode="numeric" required value="<?= e((string)($selectedInvite['bid_amount'] ?? $selectedInvite['quote_amount'] ?? '')) ?>">
              </div>
              <div class="form-field">
                <label style="margin-top:1rem;">Message</label>
                <textarea name="message" rows="5" placeholder="What is included? Any notes or questions?"><?= e((string)($selectedInvite['bid_message'] ?? $selectedInvite['quote_message'] ?? '')) ?></textarea>
              </div>
              <button class="btn btn-primary" style="margin-top:1rem;"><?= $inviteStatus === 'accepted' ? 'Save Quote' : 'Send Quote' ?></button>
            </form>
          </div>

          <div class="artist-action-panel" data-artist-panel="decline">
            <form class="form card" method="post" style="padding:1.15rem;">
              <h3 class="form-title"><?= $inviteStatus === 'declined' ? 'Edit Decline' : 'Decline' ?></h3>
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="decline">
              <input type="hidden" name="invite_id" value="<?= (int)$selectedInvite['invite_id'] ?>">
              <div class="form-field">
                <label>Reason</label>
                <select name="decline_reason">
                  <?php foreach ($declineReasons as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (string)($selectedInvite['decline_reason'] ?? '') === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field">
                <label style="margin-top:1rem;">Optional note</label>
                <textarea name="decline_message" rows="5" placeholder="Optional note for your own record or the booker."><?= e((string)($selectedInvite['decline_message'] ?? '')) ?></textarea>
              </div>
              <button class="btn btn-outline" style="margin-top:1rem;"><?= $inviteStatus === 'declined' ? 'Save Decline' : 'Decline Request' ?></button>
            </form>
          </div>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
</main>
<script>
document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-artist-action]');
  if (!button) return;
  const action = button.getAttribute('data-artist-action');
  document.querySelectorAll('[data-artist-panel]').forEach(function (panel) {
    panel.classList.toggle('is-open', panel.getAttribute('data-artist-panel') === action);
  });
});
</script>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>

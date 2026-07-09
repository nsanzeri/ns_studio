<?php
require __DIR__ . '/studio/_private/_core/bootstrap.php';

$isLocal = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/ns_studio/');
$siteBase = $isLocal ? '/ns_studio' : '';
$inviteId = (int)($_GET['invite'] ?? $_POST['invite_id'] ?? 0);

if (!Auth::isLoggedIn()) {
    $_SESSION['login_next'] = $siteBase . '/artist-bid.php?invite=' . $inviteId;
    $_SESSION['registration_account_type'] = 'artist';
    redirect($siteBase . '/studio/member/login.php?brand=rss');
}

$user = Auth::currentUser($pdo);
$userId = (int)($user['id'] ?? 0);
$err = null;
$ok = null;

$stmt = $pdo->prepare("
    SELECT
        bi.id AS invite_id,
        bi.status AS invite_status,
        br.id AS request_id,
        br.event_title,
        br.event_date,
        br.start_time,
        br.venue_name,
        br.city,
        br.state,
        br.budget_max,
        br.notes,
        br.contact_name,
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
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    http_response_code(404);
    $err = 'This booking invite was not found for your account.';
}

if ($invite && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $err = 'Security check failed. Please try again.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));
        if ($amount <= 0) {
            $err = 'Add a bid amount.';
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
            $pdo->prepare("UPDATE booking_invites SET status = 'accepted', quote_amount = ?, quote_message = ?, responded_at = NOW() WHERE id = ?")->execute([$amount, $message, $inviteId]);
            $ok = 'Your bid was sent.';
            $stmt->execute([$inviteId, $userId]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

$loginUrl = $siteBase . '/studio/member/login.php?brand=rss';
$trialUrl = $siteBase . '/studio/member/pricing.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send Bid | Ready Set Shows</title>
  <link rel="stylesheet" href="<?= e($siteBase . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/includes/tools_header.php'; ?>
<main class="container" style="padding:3rem 0; max-width:820px;">
  <h1>Send a Bid</h1>
  <p class="muted">Reply to this booking request with your quote. You can take the detailed conversation offline after the booker sees your response.</p>

  <?php if ($err): ?><div class="alert" style="margin:1rem 0;"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert" style="margin:1rem 0;"><?= e($ok) ?></div><?php endif; ?>

  <?php if ($invite): ?>
    <section class="card" style="padding:1.25rem; margin:1.25rem 0;">
      <h2><?= e((string)($invite['event_title'] ?: 'Untitled event')) ?></h2>
      <p class="muted">
        <?= !empty($invite['event_date']) ? e(date('M j, Y', strtotime((string)$invite['event_date']))) : 'Date TBD' ?>
        <?php if (!empty($invite['venue_name'])): ?> &middot; <?= e((string)$invite['venue_name']) ?><?php endif; ?>
        <?php if (!empty($invite['city']) || !empty($invite['state'])): ?> &middot; <?= e(trim((string)$invite['city'] . ', ' . (string)$invite['state'], ' ,')) ?><?php endif; ?>
        <?php if (!empty($invite['budget_max'])): ?> &middot; Budget up to $<?= e(number_format((float)$invite['budget_max'], 0)) ?><?php endif; ?>
      </p>
      <?php if (!empty($invite['notes'])): ?><p><?= nl2br(e((string)$invite['notes'])) ?></p><?php endif; ?>
    </section>

    <form class="form card" method="post" style="padding:1.25rem;">
      <h2 class="form-title">Your Bid</h2>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="invite_id" value="<?= (int)$inviteId ?>">
      <div class="form-field">
        <label>Bid amount*</label>
        <input type="number" name="amount" min="0" step="1" required value="<?= e((string)($_POST['amount'] ?? $invite['bid_amount'] ?? '')) ?>">
      </div>
      <div class="form-field">
        <label style="margin-top:1rem;">Message</label>
        <textarea name="message" rows="5" placeholder="Share availability, package details, what is included, or any questions."><?= e((string)($_POST['message'] ?? $invite['bid_message'] ?? '')) ?></textarea>
      </div>
      <button class="btn btn-primary" style="margin-top:1.25rem;">Send Bid</button>
    </form>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/tools_footer_lite.php'; ?>
</body>
</html>

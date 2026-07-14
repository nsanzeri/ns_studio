<?php
require_once __DIR__ . '/_common.php';

$songCount = 0;
$sessionCount = 0;
$liveSession = null;
$lifetimeDollarsCents = 0;
$averagePlatformTipsPerSession = 0;
$recentRequests = [];

if ($tablesReady) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM setmaxx_songs WHERE user_id = ?");
    $stmt->execute([$userId]);
    $songCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM setmaxx_gig_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $sessionCount = (int)$stmt->fetchColumn();

    if (
        setmaxx_table_exists($pdo, 'finance_gigs')
        && setmaxx_column_exists($pdo, 'finance_gigs', 'platform_tips_cents')
        && $sessionCount > 0
    ) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(platform_tips_cents), 0) FROM finance_gigs WHERE user_id = ?");
        $stmt->execute([$userId]);
        $averagePlatformTipsPerSession = (int)round(((int)$stmt->fetchColumn()) / $sessionCount);
    }

    if (setmaxx_column_exists($pdo, 'setmaxx_requests', 'payment_method')) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(r.amount_cents), 0)
             FROM setmaxx_requests r
             JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
             WHERE gs.user_id = ?
               AND r.amount_cents > 0
               AND r.status <> 'canceled'
               AND (
                    (r.payment_method = 'stripe' AND r.stripe_payment_intent_id IS NOT NULL AND r.stripe_payment_intent_id <> '')
                    OR r.payment_method = 'venmo'
               )"
        );
        $stmt->execute([$userId]);
        $lifetimeDollarsCents += (int)$stmt->fetchColumn();
    }

    if (setmaxx_table_exists($pdo, 'setmaxx_general_tips') && setmaxx_column_exists($pdo, 'setmaxx_general_tips', 'payment_method')) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount_cents), 0)
             FROM setmaxx_general_tips
             WHERE user_id = ?
               AND status = 'paid'"
        );
        $stmt->execute([$userId]);
        $lifetimeDollarsCents += (int)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT id, title, venue_name, public_token, status, starts_at FROM setmaxx_gig_sessions WHERE user_id = ? AND status = 'live' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $liveSession = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($liveSession) {
        $recentStmt = $pdo->prepare(
            "SELECT r.id, r.requester_name, r.amount_cents, r.status, r.created_at, s.title, s.artist, gs.title AS session_title
             FROM setmaxx_requests r
             JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
             JOIN setmaxx_songs s ON s.id = r.song_id
             WHERE gs.user_id = ?
               AND r.gig_session_id = ?
             ORDER BY r.created_at DESC
         LIMIT 6"
        );
        $recentStmt->execute([$userId, (int)$liveSession['id']]);
        $recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

setmaxx_page_head('Set Maxx | Dashboard');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>

  <section class="setmaxx-hero">
    <div class="setmaxx-card">
      <h1 style="margin:.8rem 0 .45rem;">Set Maxx</h1>
      <p class="setmaxx-help" style="font-size:1rem; margin:0 0 1rem;">Build your song catalog and generate stronger setlists. Upgrade when you are ready to launch live public request pages.</p>
      <div class="setmaxx-actions">
        <?php if (!$isProUser): ?>
          <span class="setmaxx-pill">Catalog and setlists are free</span>
          <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade for live request pages</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Show fuel</h2>
      <?php if (!$tablesReady): ?>
        <p class="setmaxx-help">Database setup is required before Set Maxx can run.</p>
      <?php else: ?>
        <div class="setmaxx-list">
          <div class="setmaxx-row"><strong><?= (int)$songCount ?></strong><span class="setmaxx-meta">songs in catalog</span></div>
          <div class="setmaxx-row"><strong><?= (int)$sessionCount ?></strong><span class="setmaxx-meta">gig sessions created</span></div>
          <div class="setmaxx-row"><strong><?= e(setmaxx_money($averagePlatformTipsPerSession)) ?></strong><span class="setmaxx-meta">avg platform tips per saved session</span></div>
          <div class="setmaxx-row"><strong><?= e(setmaxx_money($lifetimeDollarsCents)) ?></strong><span class="setmaxx-meta">lifetime request and tip dollars</span></div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php else: ?>
    <section class="setmaxx-module-grid" style="margin-bottom:1.25rem;">
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/songs.php')) ?>">
        <div class="setmaxx-pill">Free</div>
        <h2>Song Catalog</h2>
        <p class="setmaxx-help">Add, enrich, edit, export, and manage your performance catalog.</p>
      </a>
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/setlists.php')) ?>">
        <div class="setmaxx-pill">Free</div>
        <h2>Setlist Generator</h2>
        <p class="setmaxx-help">Build timed sets from your filtered catalog.</p>
      </a>
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/sessions.php')) ?>">
        <div class="setmaxx-pill">Pro</div>
        <h2>Gig Sessions</h2>
        <p class="setmaxx-help">Create a public request page for each show.</p>
      </a>
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/requests.php')) ?>">
        <div class="setmaxx-pill">Pro</div>
        <h2>Request Dashboard</h2>
        <p class="setmaxx-help">Queue, play, decline, or cancel requests.</p>
      </a>
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/most_requested.php')) ?>">
        <div class="setmaxx-pill">Pro</div>
        <h2>Most Requested</h2>
        <p class="setmaxx-help">See which songs audiences ask for most and which requests earn best.</p>
      </a>
      <a class="setmaxx-card setmaxx-module-card" href="<?= e(base_url('/setmaxx/payments.php')) ?>">
        <div class="setmaxx-pill">Pro</div>
        <h2>Payments</h2>
        <p class="setmaxx-help">Connect Stripe for paid request payouts.</p>
      </a>
    </section>

    <section class="setmaxx-grid">
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">Live session</h2>
        <?php if (!$liveSession): ?>
          <p class="setmaxx-help">No live session right now. Live public request pages are part of Pro.</p>
          <a class="btn btn-primary" href="<?= e($isProUser ? base_url('/setmaxx/sessions.php') : $upgradeUrl) ?>"><?= $isProUser ? 'Create Session' : 'Upgrade for Live Sessions' ?></a>
        <?php else: ?>
          <?php $publicUrl = $sessionLinkBase . rawurlencode((string)$liveSession['public_token']); ?>
          <div style="font-weight:600;"><?= e($liveSession['title']) ?></div>
          <div class="setmaxx-meta"><?= e((string)($liveSession['venue_name'] ?: 'Venue not set')) ?></div>
          <div class="setmaxx-link-box" style="margin-top:1rem;">
            <strong>Public page</strong>
            <code><?= e($publicUrl) ?></code>
            <a class="btn btn-outline" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open</a>
          </div>
        <?php endif; ?>
      </div>
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">Live session activity</h2>
        <div class="setmaxx-list">
          <?php if (!$recentRequests): ?>
            <div class="setmaxx-row"><div class="setmaxx-meta"><?= $liveSession ? 'No requests yet for the live session.' : 'No live session right now.' ?></div></div>
          <?php else: ?>
            <?php foreach ($recentRequests as $request): ?>
              <div class="setmaxx-row">
                <div>
                  <div style="font-weight:600;"><?= e($request['title']) ?></div>
                  <div class="setmaxx-meta"><?= e($request['session_title']) ?> · <?= e((string)($request['requester_name'] ?: 'Anonymous')) ?></div>
                </div>
                <div>
                  <div class="setmaxx-request-amount"><?= e(setmaxx_money((int)$request['amount_cents'])) ?></div>
                  <div class="setmaxx-status <?= e((string)$request['status']) ?>"><?= e((string)$request['status']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>

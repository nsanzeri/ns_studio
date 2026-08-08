<?php
require_once __DIR__ . '/_common.php';

$songCount = 0;
$sessionCount = 0;
$lifetimeDollarsCents = 0;
$averagePlatformTipsPerSession = 0;

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

    if (
        setmaxx_column_exists($pdo, 'setmaxx_requests', 'payment_method')
        && setmaxx_column_exists($pdo, 'setmaxx_requests', 'stripe_payment_intent_id')
    ) {
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

}

setmaxx_page_head('Set Maxx | Dashboard');
?>
<style>
  .setmaxx-dashboard .setmaxx-card {
    min-width:0;
  }
  .setmaxx-dashboard .setmaxx-module-card h2 {
    margin:.65rem 0 .35rem;
  }
  @media (max-width: 560px) {
    .setmaxx-dashboard {
      padding-top:1rem;
    }
    .setmaxx-dashboard .setmaxx-hero {
      gap:.75rem;
      margin-bottom:.8rem;
    }
    .setmaxx-dashboard .setmaxx-card {
      border-radius:18px;
      padding:1rem;
    }
    .setmaxx-dashboard .setmaxx-hero h1 {
      margin:0 0 .25rem !important;
      font-size:1.55rem;
    }
    .setmaxx-dashboard .setmaxx-hero h2 {
      margin:0 0 .65rem !important;
      font-size:1.2rem;
    }
    .setmaxx-dashboard .setmaxx-hero .setmaxx-help {
      margin-bottom:.6rem !important;
      font-size:.9rem !important;
      line-height:1.45;
    }
    .setmaxx-dashboard .setmaxx-list {
      gap:.45rem;
    }
    .setmaxx-dashboard .setmaxx-fuel-list {
      grid-template-columns:repeat(2, minmax(0, 1fr));
    }
    .setmaxx-dashboard .setmaxx-fuel-list .setmaxx-row {
      display:grid;
      align-content:center;
      gap:.2rem;
      min-height:74px;
    }
    .setmaxx-dashboard .setmaxx-row {
      align-items:center;
      flex-wrap:nowrap;
      gap:.75rem;
      padding:.55rem .7rem;
      border-radius:12px;
    }
    .setmaxx-dashboard .setmaxx-row strong {
      flex:0 0 auto;
      font-size:1rem;
    }
    .setmaxx-dashboard .setmaxx-meta {
      font-size:.82rem;
      line-height:1.35;
      text-align:right;
    }
    .setmaxx-dashboard .setmaxx-fuel-list .setmaxx-meta {
      text-align:left;
    }
    .setmaxx-dashboard .setmaxx-module-grid {
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:.55rem;
      margin-bottom:.8rem !important;
    }
    .setmaxx-dashboard .setmaxx-module-card {
      display:grid;
      align-content:start;
      min-height:96px;
    }
    .setmaxx-dashboard .setmaxx-module-card .setmaxx-pill {
      width:max-content;
      padding:.2rem .55rem;
      font-size:.72rem;
    }
    .setmaxx-dashboard .setmaxx-module-card h2 {
      margin:.5rem 0 0;
      font-size:.98rem;
      line-height:1.18;
    }
    .setmaxx-dashboard .setmaxx-module-card .setmaxx-help {
      display:none;
    }
    .setmaxx-dashboard .setmaxx-grid {
      gap:.75rem;
    }
  }
</style>
<main class="container setmaxx-shell setmaxx-dashboard">
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
        <div class="setmaxx-list setmaxx-fuel-list">
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
        <h2>Gig Config</h2>
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

  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>

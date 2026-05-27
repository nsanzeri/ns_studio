<?php
require_once __DIR__ . '/_common.php';

$stripeReady = true;
$connectAccount = null;
$isDirectPlatformUser = setmaxx_user_uses_direct_platform_tips($userId);
$needsPlatformProfile = false;
$stripeSetupUrl = 'https://dashboard.stripe.com/settings/connect/platform-profile';
$platformStripeAccountId = '';

if (isset($_GET['stripe_error'])) {
    $stripeError = trim((string)($_SESSION['setmaxx_stripe_error'] ?? ''));
    unset($_SESSION['setmaxx_stripe_error']);
    $needsPlatformProfile = stripos($stripeError, 'platform-profile') !== false
        || stripos($stripeError, 'responsibilities') !== false
        || stripos($stripeError, 'accounts/overview') !== false
        || stripos($stripeError, 'questionnaire') !== false;
    if (stripos($stripeError, 'accounts/overview') !== false || stripos($stripeError, 'questionnaire') !== false) {
        $stripeSetupUrl = 'https://dashboard.stripe.com/connect/accounts/overview';
    }
    $errors[] = $stripeError !== ''
        ? 'Stripe could not start onboarding: ' . $stripeError
        : 'Stripe could not start onboarding. Please check the Stripe setup and try again.';
}

if (isset($_GET['stripe_return'])) {
    $messages[] = 'Stripe setup returned successfully. The current account status is shown below.';
}

try {
    require_once __DIR__ . '/../_private/config/stripe.php';
} catch (Throwable $e) {
    $stripeReady = false;
    $errors[] = 'Stripe is not configured yet. Add the Stripe keys before starting payouts onboarding.';
}

if ($stripeReady) {
    try {
        try {
            $platformAccount = \Stripe\Account::retrieve();
            $platformStripeAccountId = (string)($platformAccount->id ?? '');
        } catch (Throwable $e) {
            $platformStripeAccountId = '';
        }

        $connectAccount = setmaxx_connect_account_row($pdo, $userId);
        if ($connectAccount && !empty($connectAccount['stripe_account_id'])) {
            $stripeAccount = \Stripe\Account::retrieve((string)$connectAccount['stripe_account_id']);
            setmaxx_upsert_connect_account($pdo, $userId, (string)$stripeAccount->id, $stripeAccount);
            $connectAccount = setmaxx_connect_account_row($pdo, $userId);
        }
    } catch (Throwable $e) {
        $errors[] = 'Stripe account status could not be refreshed right now. You can try again in a moment.';
    }
}

$connectReady = setmaxx_connect_ready($connectAccount);
$feePercent = setmaxx_tip_platform_fee_percent();
$paymentTotals = [
    'tonight' => ['label' => 'Tonight', 'gross_cents' => 0],
    'last_30' => ['label' => 'Last 30 days', 'gross_cents' => 0],
    'all_time' => ['label' => 'All time', 'gross_cents' => 0],
];

if ($tablesReady) {
    try {
        $totalsStmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(r.created_at) = CURDATE() THEN r.amount_cents ELSE 0 END), 0) AS tonight_cents,
                COALESCE(SUM(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN r.amount_cents ELSE 0 END), 0) AS last_30_cents,
                COALESCE(SUM(r.amount_cents), 0) AS all_time_cents
             FROM setmaxx_requests r
             JOIN setmaxx_gig_sessions gs ON gs.id = r.gig_session_id
             WHERE gs.user_id = ?
               AND r.amount_cents > 0
               AND r.status <> 'canceled'
               AND r.stripe_payment_intent_id IS NOT NULL
               AND r.stripe_payment_intent_id <> ''"
        );
        $totalsStmt->execute([$userId]);
        $totalsRow = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $paymentTotals['tonight']['gross_cents'] = (int)($totalsRow['tonight_cents'] ?? 0);
        $paymentTotals['last_30']['gross_cents'] = (int)($totalsRow['last_30_cents'] ?? 0);
        $paymentTotals['all_time']['gross_cents'] = (int)($totalsRow['all_time_cents'] ?? 0);

        if (setmaxx_table_exists($pdo, 'setmaxx_general_tips')) {
            $tipsTotalsStmt = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN amount_cents ELSE 0 END), 0) AS tonight_cents,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount_cents ELSE 0 END), 0) AS last_30_cents,
                    COALESCE(SUM(amount_cents), 0) AS all_time_cents
                 FROM setmaxx_general_tips
                 WHERE user_id = ?
                   AND status = 'paid'"
            );
            $tipsTotalsStmt->execute([$userId]);
            $tipsTotalsRow = $tipsTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $paymentTotals['tonight']['gross_cents'] += (int)($tipsTotalsRow['tonight_cents'] ?? 0);
            $paymentTotals['last_30']['gross_cents'] += (int)($tipsTotalsRow['last_30_cents'] ?? 0);
            $paymentTotals['all_time']['gross_cents'] += (int)($tipsTotalsRow['all_time_cents'] ?? 0);
        }
    } catch (Throwable $e) {
        $errors[] = 'Payment totals could not be loaded right now.';
    }
}

foreach ($paymentTotals as $key => $total) {
    $grossCents = (int)$total['gross_cents'];
    $feeCents = $isDirectPlatformUser ? 0 : (int)floor($grossCents * ($feePercent / 100));
    $paymentTotals[$key]['fee_cents'] = $feeCents;
    $paymentTotals[$key]['payout_cents'] = max(0, $grossCents - $feeCents);
}

setmaxx_page_head('Set Maxx | Payments');
?>
<main class="container setmaxx-shell">
  <?php setmaxx_flash($messages, $errors); ?>

  <?php if ($needsPlatformProfile): ?>
    <div class="setmaxx-card" style="margin-bottom:1rem;">
      <h2 style="margin-top:0;">Stripe Connect setup needed</h2>
      <p class="setmaxx-help">Stripe needs the Connect account questionnaire completed before it will create live performer onboarding links.</p>
      <div class="setmaxx-actions">
        <a class="btn btn-primary" href="<?= e($stripeSetupUrl) ?>" target="_blank" rel="noopener">Open Stripe Connect Setup</a>
        <span class="setmaxx-pill">Mode: <?= e((string)env('STRIPE_MODE', 'test')) ?></span>
        <?php if ($platformStripeAccountId !== ''): ?>
          <span class="setmaxx-pill">Platform: <?= e($platformStripeAccountId) ?></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <section class="setmaxx-hero">
    <div class="setmaxx-card">
      <div class="setmaxx-pill">Payments</div>
      <h1 style="margin:.8rem 0 .45rem;">Tips and Payouts</h1>
      <p class="setmaxx-help" style="font-size:1rem; margin:0;">Connect Stripe once, then paid song requests can be routed to the performer account for each show.</p>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Tip split</h2>
      <?php if ($isDirectPlatformUser): ?>
        <div class="setmaxx-row">
          <div>
            <strong>Platform performer mode</strong>
            <div class="setmaxx-meta">Paid requests for this account will stay in the platform Stripe account.</div>
          </div>
          <span class="setmaxx-pill">No split</span>
        </div>
      <?php else: ?>
        <div class="setmaxx-row">
          <div>
            <strong><?= (int)$feePercent ?>% platform fee</strong>
            <div class="setmaxx-meta">The remainder is sent to the connected performer Stripe account.</div>
          </div>
          <span class="setmaxx-pill"><?= $connectReady ? 'Connected' : 'Connect required' ?></span>
        </div>
        <?php if ($platformStripeAccountId !== ''): ?>
          <div class="setmaxx-row">
            <span class="setmaxx-meta">Stripe platform</span>
            <strong><?= e($platformStripeAccountId) ?></strong>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$tablesReady): ?>
    <?php setmaxx_install_notice(); ?>
  <?php elseif (!$isProUser): ?>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Set Maxx subscription required</h2>
      <p class="setmaxx-help">Payments are part of the Ready Set Shows tools plan.</p>
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade to unlock Set Maxx</a>
    </div>
  <?php elseif ($isDirectPlatformUser): ?>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">No onboarding needed</h2>
      <p class="setmaxx-help">This user ID is listed in <code>SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS</code>, so paid requests can charge through the platform account without creating a connected performer account.</p>
    </div>
    <section class="setmaxx-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Payment totals</h2>
      <div class="setmaxx-module-grid">
        <?php foreach ($paymentTotals as $total): ?>
          <div class="setmaxx-row" style="display:grid; gap:.55rem;">
            <strong><?= e($total['label']) ?></strong>
            <div class="setmaxx-meta">Gross <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['gross_cents'])) ?></span></div>
            <div class="setmaxx-meta">Platform fees <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['fee_cents'])) ?></span></div>
            <div class="setmaxx-meta">Performer payouts <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['payout_cents'])) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="setmaxx-grid">
      <div class="setmaxx-card">
        <h2 style="margin-top:0;">Stripe connection</h2>
        <div class="setmaxx-list">
          <div class="setmaxx-row">
            <span class="setmaxx-meta">Account</span>
            <strong><?= e((string)($connectAccount['stripe_account_id'] ?? 'Not connected')) ?></strong>
          </div>
          <div class="setmaxx-row">
            <span class="setmaxx-meta">Charges</span>
            <strong><?= !empty($connectAccount['charges_enabled']) ? 'Enabled' : 'Not ready' ?></strong>
          </div>
          <div class="setmaxx-row">
            <span class="setmaxx-meta">Payouts</span>
            <strong><?= !empty($connectAccount['payouts_enabled']) ? 'Enabled' : 'Not ready' ?></strong>
          </div>
          <div class="setmaxx-row">
            <span class="setmaxx-meta">Details</span>
            <strong><?= !empty($connectAccount['details_submitted']) ? 'Submitted' : 'Needs setup' ?></strong>
          </div>
        </div>
      </div>

      <div class="setmaxx-card">
        <h2 style="margin-top:0;"><?= $connectReady ? 'Ready for paid requests' : 'Finish onboarding' ?></h2>
        <?php if ($connectReady): ?>
          <p class="setmaxx-help">This account is ready to receive paid request payouts when the public request checkout is turned on.</p>
        <?php else: ?>
          <p class="setmaxx-help">Stripe will collect the performer payout details securely. Set Maxx only stores the connected account ID and readiness status.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('/setmaxx/connect_start.php')) ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn <?= $connectReady ? 'btn-outline' : 'btn-primary' ?>" type="submit" <?= $stripeReady ? '' : 'disabled' ?>>
            <?= $connectReady ? 'Update Stripe Details' : ($connectAccount ? 'Continue Stripe Setup' : 'Connect Stripe') ?>
          </button>
        </form>
      </div>
    </section>
    <section class="setmaxx-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Payment totals</h2>
      <div class="setmaxx-module-grid">
        <?php foreach ($paymentTotals as $total): ?>
          <div class="setmaxx-row" style="display:grid; gap:.55rem;">
            <strong><?= e($total['label']) ?></strong>
            <div class="setmaxx-meta">Gross <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['gross_cents'])) ?></span></div>
            <div class="setmaxx-meta">Platform fees <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['fee_cents'])) ?></span></div>
            <div class="setmaxx-meta">Performer payouts <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['payout_cents'])) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>

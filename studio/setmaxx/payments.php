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
          <span class="setmaxx-pill">Connect required</span>
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
          <button class="btn btn-primary" type="submit" <?= $stripeReady ? '' : 'disabled' ?>>
            <?= $connectAccount ? 'Continue Stripe Setup' : 'Connect Stripe' ?>
          </button>
        </form>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>

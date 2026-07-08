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

if ($tablesReady && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!$isProUser) {
        $errors[] = 'Payments are included with Pro.';
    } elseif ($isDirectPlatformUser) {
        $errors[] = 'This account uses platform performer mode, so there is no connected Stripe account to reset.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'reset_connect_account') {
            try {
                setmaxx_ensure_connect_accounts_table($pdo);
                $pdo->prepare('DELETE FROM setmaxx_connect_accounts WHERE user_id = ?')->execute([$userId]);
                $_SESSION['setmaxx_payments_messages'] = ['Stripe connection reset. You can connect a fresh Stripe account now.'];
                header('Location: ' . base_url('/setmaxx/payments.php'));
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Stripe connection could not be reset right now.';
            }
        }
    }
}

if (!empty($_SESSION['setmaxx_payments_messages']) && is_array($_SESSION['setmaxx_payments_messages'])) {
    $messages = array_merge($messages, $_SESSION['setmaxx_payments_messages']);
    unset($_SESSION['setmaxx_payments_messages']);
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
            try {
                $stripeAccount = \Stripe\Account::retrieve((string)$connectAccount['stripe_account_id']);
                setmaxx_upsert_connect_account($pdo, $userId, (string)$stripeAccount->id, $stripeAccount);
                $connectAccount = setmaxx_connect_account_row($pdo, $userId);
            } catch (Throwable $e) {
                $errors[] = 'Stripe could not refresh this connected account. If it was closed, reset the connection below and connect a fresh Stripe account.';
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Stripe account status could not be refreshed right now. You can try again in a moment.';
    }
}

$connectReady = setmaxx_connect_ready($connectAccount);
$paymentTotals = [
    'tonight' => ['label' => 'Tonight', 'stripe_cents' => 0, 'venmo_cents' => 0],
    'this_week' => ['label' => 'This week', 'stripe_cents' => 0, 'venmo_cents' => 0],
    'last_30' => ['label' => 'Last 30 days', 'stripe_cents' => 0, 'venmo_cents' => 0],
    'all_time' => ['label' => 'All time', 'stripe_cents' => 0, 'venmo_cents' => 0],
];

function setmaxx_payments_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function setmaxx_payments_ensure_payment_method_columns(PDO $pdo): void {
    if (setmaxx_table_exists($pdo, 'setmaxx_requests') && !setmaxx_payments_column_exists($pdo, 'setmaxx_requests', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_requests ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
    if (setmaxx_table_exists($pdo, 'setmaxx_general_tips') && !setmaxx_payments_column_exists($pdo, 'setmaxx_general_tips', 'payment_method')) {
        $pdo->exec("ALTER TABLE setmaxx_general_tips ADD COLUMN payment_method varchar(24) NOT NULL DEFAULT 'stripe' AFTER status");
    }
}

if ($tablesReady) {
    try {
        setmaxx_payments_ensure_payment_method_columns($pdo);
        $totalsStmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(r.created_at) = CURDATE() AND r.payment_method = 'stripe' THEN r.amount_cents ELSE 0 END), 0) AS tonight_stripe_cents,
                COALESCE(SUM(CASE WHEN DATE(r.created_at) = CURDATE() AND r.payment_method = 'venmo' THEN r.amount_cents ELSE 0 END), 0) AS tonight_venmo_cents,
                COALESCE(SUM(CASE WHEN r.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND r.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AND r.payment_method = 'stripe' THEN r.amount_cents ELSE 0 END), 0) AS this_week_stripe_cents,
                COALESCE(SUM(CASE WHEN r.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND r.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AND r.payment_method = 'venmo' THEN r.amount_cents ELSE 0 END), 0) AS this_week_venmo_cents,
                COALESCE(SUM(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND r.payment_method = 'stripe' THEN r.amount_cents ELSE 0 END), 0) AS last_30_stripe_cents,
                COALESCE(SUM(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND r.payment_method = 'venmo' THEN r.amount_cents ELSE 0 END), 0) AS last_30_venmo_cents,
                COALESCE(SUM(CASE WHEN r.payment_method = 'stripe' THEN r.amount_cents ELSE 0 END), 0) AS all_time_stripe_cents,
                COALESCE(SUM(CASE WHEN r.payment_method = 'venmo' THEN r.amount_cents ELSE 0 END), 0) AS all_time_venmo_cents
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
        $totalsStmt->execute([$userId]);
        $totalsRow = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $paymentTotals['tonight']['stripe_cents'] = (int)($totalsRow['tonight_stripe_cents'] ?? 0);
        $paymentTotals['tonight']['venmo_cents'] = (int)($totalsRow['tonight_venmo_cents'] ?? 0);
        $paymentTotals['this_week']['stripe_cents'] = (int)($totalsRow['this_week_stripe_cents'] ?? 0);
        $paymentTotals['this_week']['venmo_cents'] = (int)($totalsRow['this_week_venmo_cents'] ?? 0);
        $paymentTotals['last_30']['stripe_cents'] = (int)($totalsRow['last_30_stripe_cents'] ?? 0);
        $paymentTotals['last_30']['venmo_cents'] = (int)($totalsRow['last_30_venmo_cents'] ?? 0);
        $paymentTotals['all_time']['stripe_cents'] = (int)($totalsRow['all_time_stripe_cents'] ?? 0);
        $paymentTotals['all_time']['venmo_cents'] = (int)($totalsRow['all_time_venmo_cents'] ?? 0);

        if (setmaxx_table_exists($pdo, 'setmaxx_general_tips')) {
            $tipsTotalsStmt = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND payment_method = 'stripe' THEN amount_cents ELSE 0 END), 0) AS tonight_stripe_cents,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND payment_method = 'venmo' THEN amount_cents ELSE 0 END), 0) AS tonight_venmo_cents,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AND payment_method = 'stripe' THEN amount_cents ELSE 0 END), 0) AS this_week_stripe_cents,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AND payment_method = 'venmo' THEN amount_cents ELSE 0 END), 0) AS this_week_venmo_cents,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_method = 'stripe' THEN amount_cents ELSE 0 END), 0) AS last_30_stripe_cents,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_method = 'venmo' THEN amount_cents ELSE 0 END), 0) AS last_30_venmo_cents,
                    COALESCE(SUM(CASE WHEN payment_method = 'stripe' THEN amount_cents ELSE 0 END), 0) AS all_time_stripe_cents,
                    COALESCE(SUM(CASE WHEN payment_method = 'venmo' THEN amount_cents ELSE 0 END), 0) AS all_time_venmo_cents
                 FROM setmaxx_general_tips
                 WHERE user_id = ?
                   AND status = 'paid'"
            );
            $tipsTotalsStmt->execute([$userId]);
            $tipsTotalsRow = $tipsTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $paymentTotals['tonight']['stripe_cents'] += (int)($tipsTotalsRow['tonight_stripe_cents'] ?? 0);
            $paymentTotals['tonight']['venmo_cents'] += (int)($tipsTotalsRow['tonight_venmo_cents'] ?? 0);
            $paymentTotals['this_week']['stripe_cents'] += (int)($tipsTotalsRow['this_week_stripe_cents'] ?? 0);
            $paymentTotals['this_week']['venmo_cents'] += (int)($tipsTotalsRow['this_week_venmo_cents'] ?? 0);
            $paymentTotals['last_30']['stripe_cents'] += (int)($tipsTotalsRow['last_30_stripe_cents'] ?? 0);
            $paymentTotals['last_30']['venmo_cents'] += (int)($tipsTotalsRow['last_30_venmo_cents'] ?? 0);
            $paymentTotals['all_time']['stripe_cents'] += (int)($tipsTotalsRow['all_time_stripe_cents'] ?? 0);
            $paymentTotals['all_time']['venmo_cents'] += (int)($tipsTotalsRow['all_time_venmo_cents'] ?? 0);
        }
    } catch (Throwable $e) {
        $errors[] = 'Payment totals could not be loaded right now.';
    }
}

setmaxx_page_head('Set Maxx | Payments');
?>
<style>
  .setmaxx-payment-total-grid {
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:1rem;
  }
  @media (max-width: 1180px) {
    .setmaxx-payment-total-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 640px) {
    .setmaxx-payment-total-grid { grid-template-columns:1fr; }
  }
</style>
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
      <p class="setmaxx-help" style="font-size:1rem; margin:0;">Connect Stripe for verified card payments, or add a global Venmo handle to record direct Venmo requests and tips.</p>
    </div>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">Tip routing</h2>
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
            <strong>You keep the money</strong>
            <div class="setmaxx-meta">Stripe payments go straight to your connected Stripe account. Venmo amounts are tracked separately when you send guests to your Venmo.</div>
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
      <h2 style="margin-top:0;">Pro required for live payments</h2>
      <p class="setmaxx-help">Song catalogs and setlists are free. Tips, paid requests, and performer payouts are part of the live request workflow in Pro.</p>
      <a class="btn btn-primary" href="<?= e($upgradeUrl) ?>">Upgrade for live payments</a>
    </div>
  <?php elseif ($isDirectPlatformUser): ?>
    <div class="setmaxx-card">
      <h2 style="margin-top:0;">No onboarding needed</h2>
      <p class="setmaxx-help">This user ID is listed in <code>SETMAXX_DIRECT_PLATFORM_TIP_USER_IDS</code>, so paid requests can charge through the platform account without creating a connected performer account.</p>
    </div>
    <section class="setmaxx-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Payment totals</h2>
      <div class="setmaxx-payment-total-grid">
        <?php foreach ($paymentTotals as $total): ?>
          <div class="setmaxx-row" style="display:grid; gap:.55rem;">
            <strong><?= e($total['label']) ?></strong>
            <div class="setmaxx-meta">Stripe collected <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['stripe_cents'])) ?></span></div>
            <div class="setmaxx-meta">Venmo recorded <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['venmo_cents'])) ?></span></div>
            <div class="setmaxx-meta">Total tracked <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['stripe_cents'] + (int)$total['venmo_cents'])) ?></span></div>
            <div class="setmaxx-meta">Stripe sends your payout after its normal processing fee.</div>
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
          <p class="setmaxx-help">This account is ready to process tips and paid requests. The money goes to your connected Stripe account, and Stripe sends your payout after its normal processing fee.</p>
        <?php else: ?>
          <p class="setmaxx-help">Stripe will collect the performer payout details securely. Set Maxx only stores the connected account ID and readiness status.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('/setmaxx/connect_start.php')) ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn <?= $connectReady ? 'btn-outline' : 'btn-primary' ?>" type="submit" <?= $stripeReady ? '' : 'disabled' ?>>
            <?= $connectReady ? 'Update Stripe Details' : ($connectAccount ? 'Continue Stripe Setup' : 'Connect Stripe') ?>
          </button>
        </form>
        <?php if ($connectAccount && !empty($connectAccount['stripe_account_id'])): ?>
          <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08);">
            <h3 style="margin:.1rem 0 .35rem;">Need a fresh Stripe account?</h3>
            <p class="setmaxx-help">Use this only if this Stripe account was closed or connected by mistake. Past payment history stays in SetMaxx, but the saved Stripe connection will be removed.</p>
            <form method="post" action="" onsubmit="return confirm('Reset this Stripe connection? You will need to connect Stripe again before card payments work.');">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="reset_connect_account">
              <button class="btn btn-outline" type="submit">Reset Stripe Connection</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <section class="setmaxx-card" style="margin-top:1rem;">
      <h2 style="margin-top:0;">Payment totals</h2>
      <div class="setmaxx-payment-total-grid">
        <?php foreach ($paymentTotals as $total): ?>
          <div class="setmaxx-row" style="display:grid; gap:.55rem;">
            <strong><?= e($total['label']) ?></strong>
            <div class="setmaxx-meta">Stripe collected <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['stripe_cents'])) ?></span></div>
            <div class="setmaxx-meta">Venmo recorded <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['venmo_cents'])) ?></span></div>
            <div class="setmaxx-meta">Total tracked <span style="float:right; color:#fff; font-weight:700;"><?= e(setmaxx_money((int)$total['stripe_cents'] + (int)$total['venmo_cents'])) ?></span></div>
            <div class="setmaxx-meta">Stripe sends your payout after its normal processing fee.</div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php setmaxx_page_foot(); ?>

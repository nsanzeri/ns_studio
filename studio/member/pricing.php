<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$user = Auth::currentUser($pdo);
$isProUser = $user ? rss_current_user_is_pro($pdo) : false;
$upgradeUrl = base_url('/member/login.php');
$sessionPending = isset($_GET['upgraded']) || isset($_GET['session_id']);
$flash = null;

if (isset($_GET['canceled'])) {
    $flash = ['type' => 'muted', 'text' => 'Checkout was canceled. Your free account is still available.'];
} elseif ($sessionPending && !$isProUser) {
    $flash = ['type' => 'muted', 'text' => 'Thanks — your Pro access is being activated by webhook. If it does not show up in a few seconds, refresh once.'];
} elseif ($sessionPending && $isProUser) {
    $flash = ['type' => 'success', 'text' => 'Pro is active. Your premium tools are unlocked.'];
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ready Set Shows Pricing | Nick Sanzeri</title>
  <meta name="description" content="Start free, then upgrade to Pro to unlock the full Ready Set Shows calendar workflow.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .pricing-shell{padding:2.5rem 0 4rem;}
    .pricing-hero{max-width:760px;margin:0 auto 2rem;text-align:center;}
    .pricing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem;align-items:stretch;}
    .pricing-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:1.5rem;box-shadow:0 16px 34px rgba(0,0,0,.18);}
    .pricing-card.featured{border-color:rgba(212,175,55,.45);box-shadow:0 20px 44px rgba(0,0,0,.24);}
    .pricing-badge{display:inline-flex;padding:.4rem .7rem;border-radius:999px;background:rgba(212,175,55,.14);color:#f2d67c;font-size:.78rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:1rem;}
    .price-line{display:flex;align-items:flex-end;gap:.45rem;margin:.7rem 0 1rem;}
    .price{font-size:2.6rem;line-height:1;font-weight:700;color:#fff;}
    .price-unit{color:rgba(255,255,255,.68);}
    .pricing-subtitle,.muted{color:rgba(255,255,255,.72);}
    .pricing-card ul{margin:1rem 0 1.25rem 1.15rem;color:rgba(255,255,255,.84);line-height:1.85;}
    .small-pricing-note{margin-top:.85rem;color:rgba(255,255,255,.62);font-size:.9rem;line-height:1.5;}
    .why-card{max-width:860px;margin:2rem auto 0;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:1.5rem;}
    .why-card ul{margin:1rem 0 0 1.15rem;color:rgba(255,255,255,.84);line-height:1.85;}
    .pricing-alert{max-width:860px;margin:0 auto 1.25rem;border-radius:16px;padding:1rem 1.15rem;border:1px solid rgba(255,255,255,.1);}
    .pricing-alert.success{background:rgba(42,138,74,.18);color:#dff7e5;border-color:rgba(73,183,108,.45);}
    .pricing-alert.muted{background:rgba(255,255,255,.05);color:rgba(255,255,255,.85);}
    .checkout-note{margin-top:.9rem;font-size:.92rem;color:rgba(255,255,255,.6);}
    .btn[disabled]{opacity:.72;cursor:not-allowed;}
    @media (max-width:980px){.pricing-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="pricing-shell">
  <div class="container">
    <section class="pricing-hero">
      <p class="eyebrow">Ready Set Shows</p>
      <h1>Start free. Upgrade when it starts saving you time.</h1>
      <p class="muted">Explore the tools with a free account, then unlock the full workflow with Pro. Early users can lock in founder pricing.</p>
    </section>

    <?php if ($flash): ?>
      <div class="pricing-alert <?= e($flash['type']) ?>">
        <?= e($flash['text']) ?>
      </div>
    <?php endif; ?>

    <section class="pricing-grid">
      <article class="pricing-card">
        <div class="pricing-badge">Free</div>
        <h3>Free Account</h3>
        <p class="pricing-subtitle">A simple way to explore the tools risk-free.</p>
        <div class="price-line"><div class="price">$0</div><div class="price-unit">/ month</div></div>
        <ul>
          <li>1 connected calendar</li>
          <li>Basic availability view</li>
          <li>Limited preview access</li>
          <li>See how the system works before upgrading</li>
        </ul>
        <a class="btn btn-secondary" href="<?= e(base_url('/member/login.php')) ?>">Get Started Free</a>
      </article>

      <article class="pricing-card featured">
        <div class="pricing-badge">Founder Pricing</div>
        <h3>Pro</h3>
        <p class="pricing-subtitle">For working musicians who want the full workflow.</p>
        <div class="price-line"><div class="price">$5</div><div class="price-unit">/ month</div></div>
        <ul>
          <li>Unlimited availability checks</li>
          <li>Multiple connected calendars</li>
          <li>Printable calendar views</li>
          <li>Bands In Town CSV export</li>
          <li>Full date range access</li>
          <li><strong>5% off all shop purchases</strong></li>
        </ul>

        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e(base_url('/member/login.php')) ?>">Log in to upgrade</a>
          <p class="checkout-note">Pro is tied to your member account so the existing tool soft-lock can unlock instantly.</p>
        <?php elseif ($isProUser): ?>
          <button class="btn btn-primary" disabled>You already have Pro</button>
          <p class="checkout-note">Your account already has active Pro access.</p>
        <?php else: ?>
          <button id="upgradeButton" class="btn btn-primary" type="button">Upgrade to Pro</button>
          <p class="checkout-note">Checkout uses Stripe subscription mode and access unlocks from the webhook.</p>
        <?php endif; ?>

        <p class="small-pricing-note">Lock in founder pricing now. Future users may pay more.</p>
      </article>
    </section>

    <section class="why-card">
      <p class="eyebrow">Why Pro makes sense</p>
      <h3>This pays for itself fast.</h3>
      <ul>
        <li>Book one extra gig and it is covered</li>
        <li>Save even a little time every week and it is covered</li>
        <li>Buy from the shop and your member discount helps offset the cost</li>
        <li>Look more organized when venues, clients, or bandmates need answers quickly</li>
      </ul>
    </section>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php if ($user && !$isProUser): ?>
<script>
const upgradeButton = document.getElementById('upgradeButton');

async function startProCheckout() {
  if (!upgradeButton) return;

  const original = upgradeButton.textContent;
  upgradeButton.disabled = true;
  upgradeButton.textContent = 'Redirecting…';

  try {
    const res = await fetch('<?= e(base_url('/api/create_checkout_session.php')) ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: new URLSearchParams({ plan_key: 'rss-pro' }).toString()
    });

    const data = await res.json();

    if (!res.ok || !data.url) {
      throw new Error(data.error || 'Unable to start checkout.');
    }

    window.location.href = data.url;
  } catch (err) {
    alert(err.message || 'Unable to start checkout.');
    upgradeButton.disabled = false;
    upgradeButton.textContent = original;
  }
}

upgradeButton?.addEventListener('click', startProCheckout);
</script>
<?php endif; ?>
</body>
</html>

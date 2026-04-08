<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

$user = Auth::currentUser($pdo);
$isProUser = $user ? rss_current_user_is_pro($pdo) : false;
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
          <li>Bandsintown CSV export</li>
          <li>Full date range access</li>
          <li><strong>5% off all shop purchases</strong></li>
        </ul>
        <a class="btn btn-primary" href="#" onclick="alert('Next step: wire this button to Stripe Checkout for calendar-tools-pro.'); return false;"><?= $isProUser ? 'You already have Pro' : 'Upgrade to Pro' ?></a>
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
</body>
</html>

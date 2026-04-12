<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

$user = Auth::currentUser($pdo);
$access = $user ? rss_tools_access_badge($pdo) : [
    'state' => 'free',
    'label' => 'Free plan',
    'description' => 'Create an account to use the free version.',
];

$flash = flash_get('pricing_notice');
$loginUrl = rss_studio_root_url() . '/member/login.php';
$registerUrl = rss_studio_root_url() . '/member/register.php';
$libraryUrl = rss_tool_library_url();
$trialUrl = rss_tool_trial_url();
$launchUrl = rss_tool_launch_url();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar Tools Pricing | Nick Sanzeri Studio</title>
  <meta name="description" content="Free, trial, and Pro access for Nick Sanzeri's Calendar Tools.">
  <link rel="stylesheet" href="<?= e(rss_public_root_url() . '/assets/css/style.css') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .pricing-shell{padding:2.5rem 0 4rem;}
    .pricing-hero{max-width:760px;margin:0 auto 2rem;text-align:center;}
    .pricing-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem;align-items:stretch;}
    .pricing-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:1.5rem;box-shadow:0 16px 34px rgba(0,0,0,.18);display:flex;flex-direction:column;}
    .pricing-card.featured{border-color:rgba(212,175,55,.45);box-shadow:0 20px 44px rgba(0,0,0,.24);}
    .pricing-badge{display:inline-flex;padding:.4rem .7rem;border-radius:999px;background:rgba(212,175,55,.14);color:#f2d67c;font-size:.78rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:1rem;}
    .price-line{display:flex;align-items:flex-end;gap:.45rem;margin:.7rem 0 1rem;}
    .price{font-size:2.6rem;line-height:1;font-weight:700;color:#fff;}
    .price-unit,.muted{color:rgba(255,255,255,.72);}
    .pricing-card ul{margin:1rem 0 1.25rem 1.15rem;color:rgba(255,255,255,.84);line-height:1.85;}
    .small-pricing-note{margin-top:.85rem;color:rgba(255,255,255,.62);font-size:.9rem;line-height:1.5;}
    .pricing-card .btn{margin-top:auto;text-align:center;}
    .pricing-alert{max-width:860px;margin:0 auto 1.25rem;border-radius:16px;padding:1rem 1.15rem;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);}
    .state-pill{display:inline-flex;padding:.35rem .7rem;border-radius:999px;background:rgba(255,255,255,.08);font-size:.82rem;font-weight:600;}
    @media (max-width:980px){.pricing-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="pricing-shell">
  <div class="container">
    <section class="pricing-hero">
      <p class="eyebrow">Ready Set Shows</p>
      <h1 style="margin-bottom:.6rem;">Calendar Tools Access</h1>
      <p class="muted" style="max-width:58ch;margin:0 auto;">Every logged-in user gets the free version. Start a 30-day trial when you want the full Pro workflow, then upgrade when it proves itself.</p>
      <div style="margin-top:1rem; display:flex; justify-content:center; gap:.7rem; flex-wrap:wrap;">
        <span class="state-pill">Current status: <?= e($access['label']) ?></span>
        <?php if ($user): ?>
          <a class="btn btn-outline" href="<?= e($libraryUrl) ?>">Go to Library</a>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= e($loginUrl) ?>">Log In</a>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($flash): ?>
      <div class="pricing-alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <section class="pricing-grid">
      <article class="pricing-card">
        <div class="pricing-badge">Free</div>
        <div class="price-line">
          <div class="price">$0</div>
          <div class="price-unit">forever</div>
        </div>
        <p class="muted">Good for getting inside the tool and proving the concept before you pay.</p>
        <ul>
          <li>Visible in every logged-in user's library</li>
          <li>Open the Calendar Tools right away</li>
          <li>Free limits stay in place until you trial or upgrade</li>
        </ul>
        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($registerUrl) ?>">Create Free Account</a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Use Free Version</a>
        <?php endif; ?>
      </article>

      <article class="pricing-card featured">
        <div class="pricing-badge">30-Day Trial</div>
        <div class="price-line">
          <div class="price">$0</div>
          <div class="price-unit">for 30 days</div>
        </div>
        <p class="muted">This is the bridge between free and paid. Let people feel the full Pro experience before asking them to commit.</p>
        <ul>
          <li>Unlock the Pro version for 30 days</li>
          <li>Perfect for free users who are actually engaged</li>
          <li>Shows up as <strong>Trial active</strong> in the library</li>
        </ul>
        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Start Trial</a>
        <?php elseif ($access['state'] === 'trial'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Trial Is Active</a>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Pro Already Active</a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e($trialUrl) ?>">Start Free Trial</a>
        <?php endif; ?>
        <div class="small-pricing-note">This endpoint grants a temporary manual entitlement, so it works even before your subscription automation is fully finished.</div>
      </article>

      <article class="pricing-card">
        <div class="pricing-badge">Pro</div>
        <div class="price-line">
          <div class="price">$5</div>
          <div class="price-unit">/ month</div>
        </div>
        <p class="muted">For musicians using the tool regularly enough that the limits are costing them time.</p>
        <ul>
          <li>Full Pro workflow</li>
          <li>The paid state shows as <strong>Pro active</strong> in the library</li>
          <li>Use trial first, then send serious users here</li>
        </ul>
        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In First</a>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Open Pro Tools</a>
        <?php else: ?>
          <a class="btn btn-primary" href="#" onclick="alert('Wire this button to your Pro checkout flow once your Stripe price is live.'); return false;">Upgrade to Pro</a>
        <?php endif; ?>
        <div class="small-pricing-note">This page is ready for the three states now: free, trial, and paid.</div>
      </article>
    </section>
  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

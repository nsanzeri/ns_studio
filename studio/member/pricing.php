<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';

$loginUrl = base_url('member/login.php');
$registerUrl = base_url('member/register.php');
$pricingUrl = base_url('member/pricing.php');
$libraryUrl = rss_tool_library_url();
$launchUrl = rss_tool_launch_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'start_trial')) {
	Auth::requireLogin($pricingUrl);
	
	$user = Auth::currentUser($pdo);
	$userId = (int) ($user['id'] ?? 0);
	
	if ($userId <= 0) {
		redirect($loginUrl);
	}
	
	$state = rss_current_tools_access_state($pdo);
	
	if ($state === 'paid') {
		flash_set('pricing_notice', 'Pro is already active on your account.');
		redirect($pricingUrl);
	}
	
	if ($state === 'trial') {
		flash_set('pricing_notice', 'Your free trial is already active.');
		redirect($pricingUrl);
	}
	
	if (!rss_table_exists($pdo, 'entitlements') || !rss_table_exists($pdo, 'products')) {
		flash_set('pricing_notice', 'The trial could not be started because the tools product has not been set up yet.');
		redirect($pricingUrl);
	}
	
	$productStmt = $pdo->prepare(
			'SELECT id, slug, name
         FROM products
         WHERE slug IN (?, ?, ?, ?, ?)
         ORDER BY FIELD(slug, ?, ?, ?, ?, ?)
         LIMIT 1'
			);
	
	$slugs = rss_tools_product_slugs();
	$productStmt->execute(array_merge($slugs, $slugs));
	$product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;
	
	if (!$product) {
		flash_set('pricing_notice', 'The trial could not be started because no Calendar Tools product was found in products.');
		redirect($pricingUrl);
	}
	
	$expiresAt = (new DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');
	
	$pdo->beginTransaction();
	try {
		$insert = $pdo->prepare("
            INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
            VALUES (?, ?, 'manual_grant', 'active', ?)
        ");
		$insert->execute([$userId, (int) $product['id'], $expiresAt]);
		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}
	
	flash_set('pricing_notice', 'Your 30-day Calendar Tools trial is active now.');
	redirect($libraryUrl);
}

$user = Auth::currentUser($pdo);
$access = $user ? rss_tools_access_badge($pdo) : [
		'state' => 'free',
		'label' => 'Free plan',
		'description' => 'Create an account to use the free version.',
];

$flash = flash_get('pricing_notice');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar Tools Pricing | Nick Sanzeri Studio</title>
  <meta name="description" content="Free, trial, and Pro access for Nick Sanzeri's Calendar Tools.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
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
    .trial-form{margin-top:auto;}
    .trial-form .btn{width:100%;border:none;cursor:pointer;}
    @media (max-width:980px){.pricing-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header_lite.php'; ?>

<main class="pricing-shell">
  <div class="container">
    <section class="pricing-hero">
      <p class="eyebrow">Ready Set Shows</p>
      <h1 style="margin-bottom:.4rem;">The System I Use to Manage 100+ Gigs a Year</h1>

      <p style="font-size:.95rem; color:rgba(255,255,255,.6); margin-bottom:.8rem;">
        by Nick Sanzeri — Live Musician (140+ gigs/year)
      </p>

      <p class="muted" style="max-width: 58ch; margin: 0 auto;">
        Stop double-booking, send availability in seconds, and stay consistent on Bandsintown without extra work.
        This is the exact system I use to keep everything organized and running smoothly.
      </p>

      <div style="margin-top:1rem; display:flex; justify-content:center; gap:.7rem; flex-wrap:wrap;">
        <span class="state-pill">Current status: <?= e($access['label']) ?></span>
        <?php if ($user): ?>
          <a class="btn btn-outline" href="<?= e($libraryUrl) ?>">Go to Library</a>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= e($loginUrl) ?>">Log In</a>
        <?php endif; ?>
      </div>
    </section>

    <p class="muted" style="margin-top:1rem;">
      Used by working musicians to:
      <br>
      • Combine multiple calendars into one clear view<br>
      • Send availability in seconds<br>
      • Keep Bandsintown and clients in sync<br>
    </p>

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
        <p class="muted">Perfect if you're just getting started or want to test how the tools fit into your workflow.</p>
        <ul>
          <li>Check availability across your calendar</li>
          <li>Preview your schedule in a clean, readable format</li>
          <li>Get a feel for how the system works before upgrading</li>
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
        <p class="muted">Unlock everything and see how much time you actually save when the limits are gone.</p>
        <ul>
          <li>Unlimited calendars — see your full schedule in one place</li>
          <li>Export clean availability for email, text, or print</li>
          <li>Bandsintown-ready CSV for quick uploads</li>
          <li>Run real gigs through the system with no limits</li>
        </ul>

        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Start Trial</a>
        <?php elseif ($access['state'] === 'trial'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Trial Is Active</a>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Pro Already Active</a>
        <?php else: ?>
          <form method="post" action="<?= e($pricingUrl) ?>" class="trial-form">
            <input type="hidden" name="action" value="start_trial">
            <button class="btn btn-primary" type="submit">Start Free Trial</button>
          </form>
        <?php endif; ?>

        <div class="small-pricing-note">No risk — if it doesn’t make your life easier, don’t keep it.</div>
      </article>

      <article class="pricing-card">
        <div class="pricing-badge">Pro</div>
        <div class="price-line">
          <div class="price">$5</div>
          <div class="price-unit">/ month</div>
        </div>
        <p class="muted">For working musicians who are booking regularly and don’t want to waste time juggling calendars, emails, and availability.</p>
        <ul>
          <li>Unlimited calendars — no more juggling sources</li>
          <li>Instant availability output for email, text, or printed sheets</li>
          <li>Bandsintown CSV export to stay consistent everywhere</li>
          <li>Respond to booking requests faster and more professionally</li>
        </ul>
        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In First</a>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Open Pro Tools</a>
        <?php else: ?>
          <a class="btn btn-primary" href="#" onclick="alert('Wire this button to your Pro checkout flow once your Stripe price is live.'); return false;">Upgrade to Pro</a>
        <?php endif; ?>
        <div class="small-pricing-note">If you're playing regularly, this pays for itself quickly.</div>
      </article>
    </section>
  </div>
</main>

<?php include __DIR__ . '/../../includes/tools_footer_lite.php'; ?>
</body>
</html>
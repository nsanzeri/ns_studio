<?php

declare(strict_types=1);

require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$loginUrl = base_url('member/login.php');
$registerUrl = base_url('member/register.php');
$pricingUrl = base_url('member/pricing.php');
$libraryUrl = rss_tool_library_url();
$launchUrl = rss_tool_launch_url();
$checkoutUrl = base_url('api/create_checkout_session.php');
$planKey = 'rss-pro';

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
		flash_set('pricing_notice', 'Your free trial is already active. You can upgrade to the monthly plan anytime.');
		redirect($pricingUrl);
	}
	
	if (!rss_table_exists($pdo, 'entitlements') || !rss_table_exists($pdo, 'products')) {
		flash_set('pricing_notice', 'The trial could not be started because the tools product has not been set up yet.');
		redirect($pricingUrl);
	}
	
	$slugs = rss_tools_product_slugs();
	$productStmt = $pdo->prepare(
			'SELECT id, slug, name
         FROM products
         WHERE slug IN (?, ?, ?, ?, ?)
         ORDER BY FIELD(slug, ?, ?, ?, ?, ?)
         LIMIT 1'
			);
	$productStmt->execute(array_merge($slugs, $slugs));
	$product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;
	
	if (!$product) {
		flash_set('pricing_notice', 'The trial could not be started because no Calendar Tools product was found in products.');
		redirect($pricingUrl);
	}
	
	$expiresAt = (new DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');
	
	$pdo->beginTransaction();
	try {
		$insert = $pdo->prepare(
				"INSERT INTO entitlements (user_id, product_id, source, status, expires_at)
             VALUES (?, ?, 'manual_grant', 'active', ?)"
				);
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

$planMeta = find_subscription_plan_meta($planKey);
$checkoutEnabled = $user && $access['state'] !== 'paid' && !empty($planMeta['price_id']);
$flash = flash_get('pricing_notice');
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);

if (isset($_GET['upgraded'])) {
	$flash = 'Thanks — your checkout completed. Stripe is processing your subscription now.';
} elseif (isset($_GET['canceled'])) {
	$flash = 'No problem — your checkout was canceled. You can still use the free version or start your trial.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isReadySetShowsHost ? 'Pricing | Ready Set Shows' : 'Ready Set Shows Pricing | Nick Sanzeri Studio' ?></title>
  <meta name="description" content="Free, trial, and Pro access for Ready Set Shows calendar, setlist, request, finance, and publishing tools.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .pricing-shell{padding:2.5rem 0 4rem;}
    .pricing-hero{max-width:760px;margin:0 auto 2rem;text-align:center;}
    .pricing-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem;align-items:stretch;}

    .demo-feature{max-width:1040px;margin:0 auto 2rem;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:1.25rem;align-items:center;background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.09);border-radius:24px;padding:1.25rem;box-shadow:0 18px 42px rgba(0,0,0,.22);}
    .demo-video{position:relative;width:100%;aspect-ratio:16/9;border-radius:18px;overflow:hidden;background:#000;box-shadow:0 14px 32px rgba(0,0,0,.28);}
    .demo-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}
    .demo-copy{padding:.35rem .35rem .35rem .15rem;}
    .demo-copy h2{margin:.2rem 0 .65rem;font-size:clamp(1.45rem,2vw,2.1rem);}
    .demo-copy p{color:rgba(255,255,255,.78);line-height:1.65;margin:0 0 .85rem;}
    .demo-points{display:grid;gap:.45rem;margin-top:.9rem;color:rgba(255,255,255,.84);}
    .demo-points span{display:block;}
    .suite-included{max-width:1040px;margin:0 auto 2rem;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;}
    .suite-tile{background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:1rem;}
    .suite-tile h3{margin:.2rem 0 .35rem;font-size:1rem;}
    .suite-tile p{margin:0;color:rgba(255,255,255,.68);font-size:.9rem;line-height:1.5;}
    .pricing-section-heading{max-width:760px;margin:0 auto 1.2rem;text-align:center;}
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
    .pricing-error{max-width:860px;margin:0 auto 1.25rem;border-radius:16px;padding:1rem 1.15rem;border:1px solid rgba(255,120,120,.22);background:rgba(120,0,0,.15);color:#ffd2d2;display:none;}
    .state-pill{display:inline-flex;padding:.35rem .7rem;border-radius:999px;background:rgba(255,255,255,.08);font-size:.82rem;font-weight:600;}
    .trial-form{margin-top:auto;}
    .trial-form .btn{width:100%;border:none;cursor:pointer;}
    .stack-actions{margin-top:auto;display:grid;gap:.75rem;}
    .stack-actions .btn{width:100%;}
    .pricing-note-panel{max-width:860px;margin:2rem auto 0;padding:1.25rem;border-radius:20px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.74);line-height:1.65;}
    @media (max-width:980px){.pricing-grid{grid-template-columns:1fr;}.demo-feature{grid-template-columns:1fr;}.demo-copy{padding:.25rem;}.suite-included{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:620px){.suite-included{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header_lite.php'; ?>

<main class="pricing-shell">
  <div class="container">
    <section class="pricing-hero">
      <p class="eyebrow">Ready Set Shows</p>
      <h1 style="margin-bottom:.4rem;">One simple suite for working performers.</h1>

      <p style="font-size:.95rem; color:rgba(255,255,255,.6); margin-bottom:.8rem;">
        Calendar tools, song catalogs, setlists, live requests, and performer-first show utilities.
      </p>

      <p class="muted" style="max-width:58ch; margin:0 auto;">
        Start with the free tools that help you organize your show. Upgrade when you are ready to take paid live requests,
        collect tips, and run the full SetMaxx workflow at gigs.
      </p>

      <div style="margin-top:1rem; display:flex; justify-content:center; gap:.7rem; flex-wrap:wrap;">
        <span class="state-pill">Current status: <?= e($access['label']) ?></span>
        <?php if ($user): ?>
          <a class="btn btn-outline" href="<?= e($libraryUrl) ?>">Go to My Products</a>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= e($loginUrl) ?>">Log In</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="demo-feature" aria-label="Ready Set Shows video demo">
      <div class="demo-video">
        <iframe
          src="https://www.youtube-nocookie.com/embed/DXlyDfra99o"
          title="Ready Set Shows product demo"
          loading="lazy"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen></iframe>
      </div>
      <div class="demo-copy">
        <p class="eyebrow" style="margin-bottom:.35rem;">Watch the demo</p>
        <h2>See the workflow in action.</h2>
        <p>
          Watch how Ready Set Shows helps a performer move from booking prep to set planning to live audience requests.
          A shorter overview can drop into this spot later.
        </p>
        <div class="demo-points">
          <span>Check availability and clean up booking communication</span>
          <span>Build song catalogs and generate better setlists</span>
          <span>Open QR-friendly requests, tips, and song suggestions at shows</span>
        </div>
      </div>
    </section>

    <section class="suite-included" aria-label="Ready Set Shows modules">
      <article class="suite-tile">
        <p class="eyebrow">Calendar</p>
        <h3>Booking prep</h3>
        <p>Check shared availability, print useful date views, and export clean show data.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">SetMaxx</p>
        <h3>Set planning</h3>
        <p>Manage songs, generate setlists, and prepare crowd-friendly live request sessions.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">Finance · Soon</p>
        <h3>Gig tracking</h3>
        <p>Coming-soon tools for deposits, balances, totals, averages, and tip reporting.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">Publish · Soon</p>
        <h3>Promo support</h3>
        <p>Roadmap tools for announcements, captions, newsletters, and event copy.</p>
      </article>
    </section>

    <?php if ($flash): ?>
      <div class="pricing-alert"><?= e($flash) ?></div>
    <?php endif; ?>
    <div class="pricing-error" id="checkoutErr"></div>

    <section class="pricing-section-heading">
      <p class="eyebrow">Pricing</p>
      <h2>Keep the planning tools free. Upgrade when the audience joins in.</h2>
      <p class="muted">That gives performers a useful home base first, then makes the paid plan about live value at the gig.</p>
    </section>

    <section class="pricing-grid">
      <article class="pricing-card">
        <div class="pricing-badge">Free</div>
        <div class="price-line">
          <div class="price">$0</div>
          <div class="price-unit">forever</div>
        </div>
        <p class="muted">A useful home base for performers who want to get organized before adding live request features.</p>
        <ul>
          <li>Basic calendar availability tools</li>
          <li>Song catalog management</li>
          <li>Setlist creation and planning</li>
          <li>Public song list basics without paid request checkout</li>
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
        <p class="muted">Try the full live workflow before committing.</p>
        <ul>
          <li>Unlimited calendar tools and exports</li>
          <li>Live request sessions with a stable QR link</li>
          <li>Paid requests, tips, and song suggestions</li>
          <li>Stripe Connect onboarding for performer payouts</li>
        </ul>

        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Start Trial</a>
        <?php elseif ($access['state'] === 'trial'): ?>
          <div class="stack-actions">
            <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Trial Is Active</a>
          </div>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Pro Already Active</a>
        <?php else: ?>
          <form method="post" action="<?= e($pricingUrl) ?>" class="trial-form">
            <input type="hidden" name="action" value="start_trial">
            <button class="btn btn-primary" type="submit">Start Free Trial</button>
          </form>
        <?php endif; ?>

        <div class="small-pricing-note">No risk. Try it at a rehearsal, a livestream, or a real gig.</div>
      </article>

      <article class="pricing-card">
        <div class="pricing-badge">Pro</div>
        <div class="price-line">
          <div class="price">$10</div>
          <div class="price-unit">/ month</div>
        </div>
        <p class="muted">For performers who want the full Ready Set Shows workflow on stage and behind the scenes.</p>
        <ul>
          <li>Everything in Free</li>
          <li>Unlimited calendar output and Bandsintown-ready exports</li>
          <li>SetMaxx live request sessions with QR sharing</li>
          <li>Tips, paid song requests, and performer payout routing</li>
          <li>Finance and publishing tools as they roll out</li>
        </ul>

        <?php if (!$user): ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Upgrade</a>
        <?php elseif ($access['state'] === 'paid'): ?>
          <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Open Pro Tools</a>
        <?php elseif ($checkoutEnabled): ?>
          <button class="btn btn-primary" type="button" id="upgradeBtn">
            <?= $access['state'] === 'trial' ? 'Upgrade to Pro Now' : 'Upgrade to Pro' ?>
          </button>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Upgrade</a>
        <?php endif; ?>

        <div class="small-pricing-note">
          <?php if ($access['state'] === 'trial'): ?>
            Your free trial is active, but you can still go straight to the monthly subscription checkout now.
          <?php else: ?>
            Built to stay affordable while keeping tips and paid requests artist-friendly.
          <?php endif; ?>
        </div>
      </article>
    </section>

    <section class="pricing-note-panel">
      <strong>About tips and paid requests:</strong>
      Ready Set Shows does not take a platform fee from SetMaxx tips or paid song requests.
      Stripe processing fees are handled by the performer's connected Stripe account.
    </section>
  </div>
</main>

<?php include __DIR__ . '/../../includes/tools_footer_lite.php'; ?>

<?php if ($checkoutEnabled): ?>
<script>
const upgradeBtn = document.getElementById('upgradeBtn');
const checkoutErr = document.getElementById('checkoutErr');
const checkoutUrl = <?= json_encode($checkoutUrl) ?>;
const planKey = <?= json_encode($planKey) ?>;
const defaultUpgradeLabel = upgradeBtn ? upgradeBtn.textContent : 'Upgrade to Pro';

async function startUpgradeCheckout() {
  if (!upgradeBtn) {
    return;
  }

  checkoutErr.style.display = 'none';
  checkoutErr.textContent = '';
  upgradeBtn.disabled = true;
  upgradeBtn.textContent = 'Loading checkout...';

  try {
    const response = await fetch(checkoutUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json'
      },
      body: new URLSearchParams({ plan_key: planKey })
    });

    const text = await response.text();
    let data = {};

    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Checkout returned an invalid response.');
    }

    if (response.status === 401 && data.login_url) {
      window.location.href = data.login_url;
      return;
    }

    if (data.url) {
      window.location.href = data.url;
      return;
    }

    throw new Error(data.error || data.detail || 'Checkout error');
  } catch (e) {
    checkoutErr.textContent = e.message || 'Something went wrong starting checkout.';
    checkoutErr.style.display = 'block';
    upgradeBtn.disabled = false;
    upgradeBtn.textContent = defaultUpgradeLabel;
  }
}

upgradeBtn.addEventListener('click', startUpgradeCheckout);
</script>
<?php endif; ?>
</body>
</html>

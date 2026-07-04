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

$comparisonFeatures = [
	['module' => 'Calendar', 'label' => 'Create and manage multiple calendars', 'free' => true, 'pro' => true],
	['module' => 'Calendar', 'label' => 'Unlimited calendars', 'free' => true, 'pro' => true],
	['module' => 'Calendar', 'label' => 'Availability lookup across any date range', 'free' => true, 'pro' => true],
	['module' => 'Calendar', 'label' => 'Pretty print calendar views across any date range', 'free' => true, 'pro' => true],
	['module' => 'Calendar', 'label' => 'TXT, CSV, print, and client-friendly calendar outputs', 'free' => true, 'pro' => true],
	['module' => 'Calendar', 'label' => 'Bandsintown bulk export', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Song catalog add, edit, delete, and import tools', 'free' => true, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Song metadata enrichment', 'free' => true, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Song catalog export and print tools', 'free' => true, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Setlist generator and planning tools', 'free' => true, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Live gig sessions', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Public request pages with stable QR links', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Live request dashboard with queue, played, and decline workflow', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Most requested song analytics and paid request rankings', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Tips, paid song requests, audience song suggestions, email sign-up, reviews, and more', 'free' => false, 'pro' => true],
	['module' => 'SetMaxx', 'label' => 'Stripe Connect onboarding and performer payout routing', 'free' => false, 'pro' => true],
	['module' => 'Finance', 'label' => 'Dashboard and manual gig ledger', 'free' => true, 'pro' => true],
	['module' => 'Finance', 'label' => 'Calendar import and gig preview tools', 'free' => true, 'pro' => true],
	['module' => 'Finance', 'label' => 'Gig totals, year comparisons, and average gig value', 'free' => true, 'pro' => true],
	['module' => 'Finance', 'label' => 'Spreadsheet import', 'free' => false, 'pro' => true],
	['module' => 'Publishing', 'label' => 'Blurbs, social captions, newsletters, and date-list copy', 'free' => true, 'pro' => true],
];

function rss_pricing_feature_mark(bool $included): string {
	$class = $included ? 'included' : 'excluded';
	$symbol = $included ? '&#10003;' : '&times;';
	$text = $included ? 'Included' : 'Pro only';
	return '<span class="feature-mark ' . $class . '" aria-label="' . $text . '"><span aria-hidden="true">' . $symbol . '</span></span>';
}

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
    .pricing-grid{max-width:1040px;margin:0 auto;}

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
    .comparison-panel{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:22px;box-shadow:0 16px 34px rgba(0,0,0,.18);overflow:hidden;}
    .comparison-row{display:grid;grid-template-columns:minmax(260px,1fr) minmax(150px,190px) minmax(150px,190px);align-items:stretch;border-bottom:1px solid rgba(255,255,255,.075);}
    .comparison-row:last-child{border-bottom:0;}
    .comparison-head{background:rgba(255,255,255,.055);}
    .comparison-cell{padding:.95rem 1rem;display:flex;align-items:center;}
    .comparison-plan{justify-content:center;text-align:center;border-left:1px solid rgba(255,255,255,.075);}
    .comparison-plan-title{display:grid;gap:.25rem;justify-items:center;}
    .comparison-plan-title strong{color:#fff;font-size:1.05rem;}
    .comparison-plan-title span{color:rgba(255,255,255,.64);font-size:.85rem;}
    .module-heading-row{background:rgba(212,175,55,.09);}
    .module-heading{grid-column:1 / -1;color:#f2d67c;font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;}
    .feature-with-module{padding-left:1rem;border-left:2px solid rgba(212,175,55,.24);}
    .feature-text{color:rgba(255,255,255,.84);line-height:1.45;font-size:.94rem;}
    .feature-mark{width:1.45rem;height:1.45rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:.98rem;line-height:1;margin-top:.05rem;}
    .feature-mark.included{background:rgba(48,196,110,.14);border:1px solid rgba(73,220,130,.5);color:#63e08c;}
    .feature-mark.excluded{background:rgba(255,88,88,.12);border:1px solid rgba(255,110,110,.48);color:#ff8585;}
    .comparison-cta-row{background:rgba(255,255,255,.035);}
    .comparison-cta{display:grid;gap:.5rem;align-content:start;justify-items:stretch;}
    .comparison-cta .btn{width:100%;text-align:center;}
    .pricing-alert{max-width:860px;margin:0 auto 1.25rem;border-radius:16px;padding:1rem 1.15rem;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);}
    .pricing-error{max-width:860px;margin:0 auto 1.25rem;border-radius:16px;padding:1rem 1.15rem;border:1px solid rgba(255,120,120,.22);background:rgba(120,0,0,.15);color:#ffd2d2;display:none;}
    .state-pill{display:inline-flex;padding:.35rem .7rem;border-radius:999px;background:rgba(255,255,255,.08);font-size:.82rem;font-weight:600;}
    .trial-form{margin-top:auto;}
    .trial-form .btn{width:100%;border:none;cursor:pointer;}
    .stack-actions{margin-top:auto;display:grid;gap:.75rem;}
    .stack-actions .btn{width:100%;}
    .pricing-note-panel{max-width:860px;margin:2rem auto 0;padding:1.25rem;border-radius:20px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.74);line-height:1.65;}
    @media (max-width:980px){.demo-feature{grid-template-columns:1fr;}.demo-copy{padding:.25rem;}.suite-included{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:620px){.suite-included{grid-template-columns:1fr;}.comparison-row{grid-template-columns:minmax(0,1fr) 74px 74px;}.comparison-cell{padding:.8rem .65rem;}.comparison-plan-title span{display:none;}.feature-text{font-size:.88rem;}.feature-with-module{padding-left:.65rem;}.comparison-cta-row{grid-template-columns:1fr;}.comparison-cta-row .comparison-plan{border-left:0;border-top:1px solid rgba(255,255,255,.075);}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/tools_header_lite.php'; ?>

<main class="pricing-shell">
  <div class="container">
    <section class="pricing-hero">
      <p class="eyebrow">Ready Set Shows</p>
      <h1 style="margin-bottom:.4rem;">The subscription built to make gigs pay better.</h1>

      <p style="font-size:.95rem; color:rgba(255,255,255,.6); margin-bottom:.8rem;">
        Get booked faster, earn more from every room, and spend less time buried in admin.
      </p>

      <p class="muted" style="max-width:58ch; margin:0 auto;">
        Ready Set Shows keeps the business side moving so you can think less about paperwork, spreadsheets, and promo panic,
        and more about the music.
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
        <h2>Stop leaving money on the table.</h2>
        <p>
          See how faster replies, cleaner booking tools, smarter show prep, and live request pages turn ordinary admin
          into more booked dates, better-paid nights, and a fan list you can bring back to the next show.
        </p>
        <div class="demo-points">
          <span>Reply quickly and accurately when a buyer asks for dates</span>
          <span>Turn every gig into requests, tips, emails, reviews, and future show data</span>
          <span>Promote more consistently without staring at a blank screen</span>
        </div>
      </div>
    </section>

    <section class="suite-included" aria-label="Ready Set Shows modules">
      <article class="suite-tile">
        <p class="eyebrow">Calendar</p>
        <h3>Land the date</h3>
        <p>Respond quickly, cleanly, and accurately when the buyer is ready to book. Faster answers help you look pro, win the gig, and make more money.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">SetMaxx</p>
        <h3>Max the room</h3>
        <p>Make more per gig with requests, tips, cards, Venmo, and crowd interaction. Collect emails, reviews, and song suggestions for future shows too.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">Finance</p>
        <h3>Know the numbers</h3>
        <p>Track income, import past gigs, reconcile payouts between everyone involved, and see how your music business is actually doing.</p>
      </article>
      <article class="suite-tile">
        <p class="eyebrow">Publishing</p>
        <h3>Never go quiet</h3>
        <p>Never skimp on promotion. Generate posts, captions, newsletters, and date-list copy for all your upcoming shows in a few clicks.</p>
      </article>
    </section>

    <?php if ($flash): ?>
      <div class="pricing-alert"><?= e($flash) ?></div>
    <?php endif; ?>
    <div class="pricing-error" id="checkoutErr"></div>

    <section class="pricing-section-heading">
      <p class="eyebrow">Pricing</p>
      <h2>Free tools to get organized. Pro tools to make the gig pay.</h2>
      <p class="muted">Use the planning tools now, then upgrade when you want the audience-facing features that collect money, leads, reviews, and momentum.</p>
    </section>

    <section class="pricing-grid" aria-label="Free and Pro feature comparison">
      <div class="comparison-panel">
        <div class="comparison-row comparison-head">
          <div class="comparison-cell">
            <div>
              <div class="pricing-badge">What Makes You Money</div>
              <p class="muted" style="margin:0;">The tools that keep you booked, paid, promoted, and out of admin mode.</p>
            </div>
          </div>
          <div class="comparison-cell comparison-plan">
            <div class="comparison-plan-title">
              <strong>Free</strong>
              <span>$0 forever</span>
            </div>
          </div>
          <div class="comparison-cell comparison-plan">
            <div class="comparison-plan-title">
              <strong>Pro</strong>
              <span>$10 / month</span>
            </div>
          </div>
        </div>

        <?php $currentModule = ''; ?>
        <?php foreach ($comparisonFeatures as $feature): ?>
          <?php if ($currentModule !== (string)$feature['module']): ?>
            <?php $currentModule = (string)$feature['module']; ?>
            <div class="comparison-row module-heading-row">
              <div class="comparison-cell module-heading"><?= e($currentModule) ?></div>
            </div>
          <?php endif; ?>
          <div class="comparison-row">
            <div class="comparison-cell">
              <div class="feature-with-module">
                <div class="feature-text"><?= e($feature['label']) ?></div>
              </div>
            </div>
            <div class="comparison-cell comparison-plan"><?= rss_pricing_feature_mark((bool)$feature['free']) ?></div>
            <div class="comparison-cell comparison-plan"><?= rss_pricing_feature_mark((bool)$feature['pro']) ?></div>
          </div>
        <?php endforeach; ?>

        <div class="comparison-row comparison-cta-row">
          <div class="comparison-cell">
            <p class="muted" style="margin:0;">Start with the tools that keep you organized. Upgrade when you are ready to turn the room into tips, requests, emails, reviews, and repeat business.</p>
          </div>
          <div class="comparison-cell comparison-plan">
            <div class="comparison-cta">
              <?php if (!$user): ?>
                <a class="btn btn-primary" href="<?= e($registerUrl) ?>">Create Free Account</a>
              <?php else: ?>
                <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Use Free Version</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="comparison-cell comparison-plan">
            <div class="comparison-cta">
              <?php if (!$user): ?>
                <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Buy Pro</a>
              <?php elseif ($access['state'] === 'paid'): ?>
                <a class="btn btn-primary" href="<?= e($launchUrl) ?>">Open Pro Tools</a>
              <?php elseif ($checkoutEnabled): ?>
                <button class="btn btn-primary" type="button" id="upgradeBtn">
                  <?= $access['state'] === 'trial' ? 'Buy Pro Now' : 'Buy Pro' ?>
                </button>
              <?php else: ?>
                <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Log In to Buy Pro</a>
              <?php endif; ?>
              <div class="small-pricing-note">
                <?php if ($access['state'] === 'trial'): ?>
                  Trial active.
                <?php else: ?>
                  Artist-friendly pricing.
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
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

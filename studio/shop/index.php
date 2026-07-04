<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$products = product_file_map();
$user = Auth::currentUser($pdo);
$toolsAccess = $user ? rss_tools_access_badge($pdo) : ['state' => 'free', 'label' => 'Free plan'];
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$loginUrl = rss_studio_root_url() . '/member/login.php';
$trialUrl = rss_tool_trial_url();
$readySetShowsUrl = rss_tool_launch_url();
$screensBase = base_url('../assets/img/readysetshows');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ready Set Shows | Shop</title>
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('../assets/favicons/rss-favicon-32.png')) ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('../assets/favicons/rss-favicon-16.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('../assets/favicons/rss-favicon-180.png')) ?>">
  <style>
    :root {
      --rss-bg: #080910;
      --rss-panel: #141625;
      --rss-border: rgba(255,255,255,.1);
      --rss-gold: #e7c75a;
      --rss-muted: rgba(255,255,255,.72);
    }
    .rss-store-hero {
      position: relative;
      overflow: hidden;
      padding: 5rem 0 4rem;
      background:
        linear-gradient(90deg, rgba(8,9,16,.98) 0%, rgba(8,9,16,.84) 50%, rgba(8,9,16,.52) 100%),
        linear-gradient(135deg, #080910 0%, #161424 48%, #080910 100%);
      color: #fff;
      border-bottom: 1px solid var(--rss-border);
    }
    .rss-store-hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, .95fr);
      gap: 2.5rem;
      align-items: center;
    }
    .rss-store-kicker {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .35rem .7rem;
      border: 1px solid rgba(255,255,255,.22);
      border-radius: 999px;
      color: var(--rss-gold);
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      margin-bottom: 1rem;
    }
    .rss-store-hero h1 {
      font-size: clamp(2.35rem, 5vw, 4.8rem);
      line-height: .98;
      margin: 0 0 1rem;
      max-width: 760px;
    }
    .rss-store-hero p {
      color: rgba(255,255,255,.82);
      font-size: 1.08rem;
      max-width: 720px;
      margin-bottom: 1.3rem;
    }
    .rss-store-proof {
      display: flex;
      gap: .7rem;
      flex-wrap: wrap;
      margin-top: 1rem;
      color: rgba(255,255,255,.76);
      font-size: .94rem;
    }
    .rss-store-proof span {
      display: inline-flex;
      gap: .4rem;
      align-items: center;
    }
    .rss-store-proof i {
      color: #65d58a;
    }
    .rss-store-actions,
    .rss-card-actions {
      display: flex;
      gap: .7rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .rss-hero-image-card {
      position: relative;
      min-height: 460px;
    }
    .rss-hero-shot {
      position: absolute;
      border: 1px solid rgba(255,255,255,.16);
      border-radius: 16px;
      overflow: hidden;
      background: #10121c;
      box-shadow: 0 24px 80px rgba(0,0,0,.38);
    }
    .rss-hero-shot img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .rss-hero-shot-main {
      inset: 0 5% 20% 0;
    }
    .rss-hero-shot-small {
      right: 0;
      bottom: 0;
      width: 54%;
      height: 48%;
    }
    .rss-hero-shot-tiny {
      left: 3%;
      bottom: 3%;
      width: 42%;
      height: 36%;
    }
    .rss-section-heading {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      align-items: flex-end;
      margin-bottom: 1.4rem;
    }
    .rss-section-heading h2 {
      margin-bottom: .25rem;
    }
    .rss-section-heading .muted {
      max-width: 780px;
      line-height: 1.65;
    }
    .rss-suite-card {
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 22px;
      overflow: hidden;
      background: linear-gradient(180deg, #141625 0%, #10111c 100%);
      box-shadow:
        0 16px 44px rgba(0,0,0,.34),
        inset 0 1px 0 rgba(255,255,255,.045);
      margin-bottom: 1rem;
      transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .rss-suite-card:hover {
      transform: translateY(-2px);
      border-color: rgba(238,194,74,.34);
      box-shadow:
        0 20px 58px rgba(0,0,0,.42),
        0 0 0 1px rgba(238,194,74,.08),
        inset 0 1px 0 rgba(255,255,255,.055);
    }
    .rss-suite-card summary {
      cursor: pointer;
      list-style: none;
      padding: 1.35rem 1.35rem;
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 1rem;
      align-items: center;
    }
    .rss-suite-card summary::-webkit-details-marker { display: none; }
    .rss-icon {
      width: 46px;
      height: 46px;
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(238,194,74,.12);
      border: 1px solid rgba(238,194,74,.2);
      color: #f1c84f;
      font-size: 1.15rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
    }
    .rss-suite-card h3 {
      margin: 0 0 .25rem;
      font-size: 1.25rem;
      color: #ffffff;
    }
    .rss-suite-card p,
    .rss-suite-card .muted {
      margin: 0;
      color: rgba(255,255,255,.72);
      line-height: 1.58;
    }
    .rss-chevron {
      color: rgba(255,255,255,.62);
      transition: transform .18s ease, color .18s ease;
    }
    .rss-suite-card:hover .rss-chevron {
      color: #f1c84f;
    }
    .rss-suite-card[open] .rss-chevron {
      transform: rotate(180deg);
    }
    .rss-suite-body {
      padding: 0 1.35rem 1.35rem 5.3rem;
      color: rgba(255,255,255,.78);
    }
    .rss-suite-copy {
      max-width: 860px;
      line-height: 1.65;
      margin: .2rem 0 1rem;
      color: rgba(255,255,255,.78);
    }
    .rss-feature-list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .55rem 1.1rem;
      padding: 0;
      margin: 1rem 0 1.15rem;
      list-style: none;
    }
    .rss-feature-list li {
      color: rgba(255,255,255,.8);
    }
    .rss-feature-list li::before {
      content: "✓";
      color: #f1c84f;
      font-weight: 700;
      margin-right: .45rem;
    }
    .rss-sizzle-band {
      margin: 2rem 0;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
    }
    .rss-sizzle-card {
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 18px;
      background: rgba(255,255,255,.045);
      padding: 1rem;
      color: #fff;
    }
    .rss-sizzle-card strong {
      display: block;
      font-size: 1.05rem;
      margin-bottom: .35rem;
    }
    .rss-sizzle-card span {
      color: rgba(255,255,255,.7);
      line-height: 1.55;
      display: block;
    }

    .rss-suite-card .btn-outline {
      border-color: rgba(255,255,255,.22);
      color: #ffffff;
      background: rgba(255,255,255,.035);
    }
    .rss-suite-card .btn-outline:hover {
      border-color: rgba(238,194,74,.55);
      color: #f1c84f;
    }
    .rss-other-products {
      margin-top: 2.25rem;
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(240px, 340px);
      gap: 1.5rem;
      align-items: center;
      padding: 1.25rem;
      border-radius: 22px;
      border: 1px solid rgba(255,255,255,.08);
      background: linear-gradient(180deg, #141625 0%, #10111c 100%);
      box-shadow:
        0 16px 44px rgba(0,0,0,.34),
        inset 0 1px 0 rgba(255,255,255,.045);
      color: #fff;
    }
    .rss-other-products img {
      width: 100%;
      border-radius: 16px;
      display: block;
    }
    .rss-other-products h2,
    .rss-other-products h2 a {
      color: #ffffff;
    }
    .rss-other-products .product-description,
    .rss-other-products .muted {
      color: rgba(255,255,255,.72);
    }
    @media (max-width: 800px) {
      .rss-store-hero-grid,
      .rss-other-products {
        grid-template-columns: 1fr;
      }
      .rss-hero-image-card {
        min-height: 360px;
      }
      .rss-sizzle-band {
        grid-template-columns: 1fr;
      }
      .rss-section-heading {
        display: block;
      }
      .rss-suite-card summary {
        grid-template-columns: auto 1fr;
      }
      .rss-chevron {
        display: none;
      }
      .rss-suite-body {
        padding-left: 1.35rem;
      }
      .rss-feature-list {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_header_lite.php';
} else {
  include __DIR__ . '/../../includes/header.php';
}
?>

<main>
  <section class="rss-store-hero">
    <div class="container rss-store-hero-grid">
      <div>
        <div class="rss-store-kicker"><i class="fa-solid fa-bolt"></i> Ready Set Shows</div>
        <h1>The musician toolkit that helps the gig pay for itself.</h1>
        <p>
          Book faster, promote every date, track the money, and turn live audiences into tips,
          paid requests, reviews, emails, and future song ideas. Less admin. More music. Better nights.
        </p>
        <div class="rss-store-actions">
          <a class="btn btn-primary" href="<?= e($readySetShowsUrl) ?>">Open Ready Set Shows</a>
          <?php if (!$user): ?>
            <a class="btn btn-outline" href="<?= e(rss_studio_root_url() . '/member/login.php') ?>">Log In / Start Trial</a>
          <?php elseif ($toolsAccess['state'] === 'free'): ?>
            <a class="btn btn-outline" href="<?= e(rss_tool_trial_url()) ?>">Start Free Trial</a>
          <?php else: ?>
            <a class="btn btn-outline" href="<?= e(rss_tool_launch_url()) ?>">Open Ready Set Shows</a>
          <?php endif; ?>
        </div>
        <div class="rss-store-proof">
          <span><i class="fa-solid fa-check"></i> Calendar</span>
          <span><i class="fa-solid fa-check"></i> SetMaxx</span>
          <span><i class="fa-solid fa-check"></i> Finance</span>
          <span><i class="fa-solid fa-check"></i> Publishing</span>
        </div>
        <p class="muted" style="margin-top:1rem;color:rgba(255,255,255,.7);">Current tools state: <?= e($toolsAccess['label']) ?></p>
      </div>

      <div class="rss-hero-image-card">
        <a class="rss-hero-shot rss-hero-shot-main" href="<?= e($readySetShowsUrl) ?>">
          <img src="<?= e($screensBase . '/setmaxx-public-requests.png') ?>" alt="Ready Set Shows live request page preview">
        </a>
        <a class="rss-hero-shot rss-hero-shot-small" href="<?= e(rss_studio_root_url() . '/finance/index.php') ?>">
          <img src="<?= e($screensBase . '/finance-ledger.png') ?>" alt="Ready Set Shows finance ledger preview">
        </a>
        <a class="rss-hero-shot rss-hero-shot-tiny" href="<?= e(rss_studio_root_url() . '/tools/index.php') ?>">
          <img src="<?= e($screensBase . '/calendar-availability.png') ?>" alt="Ready Set Shows calendar availability preview">
        </a>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="rss-section-heading">
        <div>
            <p class="eyebrow">Ready Set Shows tools</p>
            <h2>One suite for the parts of the gig that usually steal your attention.</h2>
          <p class="muted">Ready Set Shows is built for working musicians who need to book cleanly, earn more at the show, understand the money afterward, and keep promoting without staring at a blank screen.</p>
        </div>
      </div>

      <div class="rss-sizzle-band">
        <div class="rss-sizzle-card">
          <strong>Get booked faster</strong>
          <span>Clean availability answers make you look ready while the buyer is still deciding.</span>
        </div>
        <div class="rss-sizzle-card">
          <strong>Make each room worth more</strong>
          <span>Give fans a polished way to request, tip, pay, join, review, and come back.</span>
        </div>
        <div class="rss-sizzle-card">
          <strong>Stop guessing</strong>
          <span>Track income, payouts, averages, and trends so the business side stops feeling fuzzy.</span>
        </div>
      </div>

      <details class="rss-suite-card" open>
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-list-check"></i></span>
          <div>
            <h3>SetMaxx</h3>
            <p class="muted">Turn the audience into tips, requests, emails, reviews, and future show ideas.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <p class="rss-suite-copy">Build your song catalog and generate practical setlists for free. Upgrade when you want the live public request page that helps the crowd participate without hijacking the room.</p>
          <ul class="rss-feature-list">
            <li>Maintain your master song catalog</li>
            <li>Generate sets by crowd, danceability, energy, and flow</li>
            <li>Take tips, paid requests, cards, and Venmo</li>
            <li>Collect email signups, reviews, and song suggestions</li>
          </ul>
          <div class="rss-card-actions">
            <a class="btn btn-primary" href="<?= e(rss_tool_upgrade_url()) ?>">View Pricing</a>
            <a class="btn btn-outline" href="<?= e(rss_studio_root_url() . '/setmaxx/index.php') ?>">Open SetMaxx</a>
          </div>
        </div>
      </details>

      <details class="rss-suite-card">
        <summary>
          <span class="rss-icon"><i class="fa-regular fa-calendar-check"></i></span>
          <div>
            <h3>Availability &amp; Calendar Tools</h3>
            <p class="muted">Answer date requests fast, clean, and accurately so you can land the gig.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <p class="rss-suite-copy">When a buyer asks, speed matters. Check multiple calendars, format useful availability, and produce clean outputs for clients and platforms.</p>
          <ul class="rss-feature-list">
            <li>Check availability across multiple iCal calendars</li>
            <li>Create clean date lists for booking conversations</li>
            <li>Format calendar output for copy, print, and sharing</li>
            <li>Prepare Bandsintown bulk upload files faster</li>
          </ul>
          <div class="rss-card-actions">
            <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/tools/index.php') ?>">Open Calendar Tools</a>
          </div>
        </div>
      </details>

      <details class="rss-suite-card" id="publishing-tools">
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-bullhorn"></i></span>
          <div>
            <h3>Publishing Tools</h3>
            <p class="muted">Never skimp on promotion just because you are tired of writing posts.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <p class="rss-suite-copy">Turn upcoming gigs into social posts, blurbs, newsletters, and date-list copy in a few clicks, so every show gets a real push.</p>
          <ul class="rss-feature-list">
            <li>Draft newsletter-style show announcements</li>
            <li>Create social captions from calendar entries</li>
            <li>Prepare event details for Facebook and other platforms</li>
            <li>Reuse your gig data instead of retyping everything</li>
          </ul>
          <p><a class="btn btn-outline" href="<?= e(base_url('/publishing/index.php')) ?>">Open Publishing</a></p>
        </div>
      </details>

      <details class="rss-suite-card" id="business-tracking">
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-chart-line"></i></span>
          <div>
            <h3>Finance</h3>
            <p class="muted">Know what came in, what went out, and whether the work is actually working.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <p class="rss-suite-copy">Bring in calendar events, import past income with Pro, reconcile payouts between all parties, and watch the real shape of your music business emerge.</p>
          <ul class="rss-feature-list">
            <li>Track gig guarantees and tips</li>
            <li>See yearly revenue and average booking value</li>
            <li>Spot which gigs and clients are most profitable</li>
            <li>Keep payouts clean for easy reconciliation</li>
          </ul>
          <p><a class="btn btn-outline" href="<?= e(base_url('/finance/index.php')) ?>">Open Finance</a></p>
        </div>
      </details>

      <?php if (!$isReadySetShowsHost): ?>
      <article class="rss-other-products">
        <div>
          <p class="eyebrow">Also available</p>
          <h2>
            <a href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">
              <?= e($products['btb']['title'] ?? 'Backing Track Blueprint') ?>
            </a>
          </h2>
          <p class="product-description">
            A practical guide to sounding bigger, tighter, and more professional with backing tracks — built for solo musicians and small acts.
          </p>
          <?php if (Auth::isLoggedIn()): ?>
            <p class="muted" style="margin-top:.8rem;">Already have an account? Your purchases will appear in My Products.</p>
          <?php endif; ?>
          <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">View Product</a>
        </div>
        <a href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">
          <img src="<?= e(base_url('../assets/img/BackingTrackBlueprint.png')) ?>" alt="Backing Track Blueprint cover">
        </a>
      </article>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
if ($isReadySetShowsHost) {
  include __DIR__ . '/../../includes/tools_footer_lite.php';
} else {
  include __DIR__ . '/../../includes/footer.php';
}
?>
</body>
</html>

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
  <style>
    .rss-store-hero {
      padding: 4.5rem 0 3.5rem;
      background:
        radial-gradient(circle at top left, rgba(255,255,255,.12), transparent 34rem),
        linear-gradient(135deg, #171717 0%, #251a14 45%, #101010 100%);
      color: #fff;
    }
    .rss-store-hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(280px, .9fr);
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
      color: rgba(255,255,255,.82);
      font-size: .78rem;
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
    .rss-store-actions,
    .rss-card-actions {
      display: flex;
      gap: .7rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .rss-hero-image-card {
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.16);
      border-radius: 24px;
      padding: 1rem;
      box-shadow: 0 24px 80px rgba(0,0,0,.28);
    }
    .rss-hero-image-card img {
      width: 100%;
      display: block;
      border-radius: 18px;
      background: #fff;
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
        <div class="rss-store-kicker"><i class="fa-solid fa-music"></i> Ready Set Shows</div>
        <h1>Turn your setlist into a paycheck.</h1>
        <p>
          Set Maxx is the flagship Ready Set Shows app: live paid requests, QR-friendly request pages,
          smarter setlists, song catalog control, and practical tools for working musicians.
        </p>
        <div class="rss-store-actions">
          <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/setmaxx/index.php') ?>">Open Set Maxx</a>
          <?php if (!$user): ?>
            <a class="btn btn-outline" href="<?= e(rss_studio_root_url() . '/member/login.php') ?>">Log In / Start Trial</a>
          <?php elseif ($toolsAccess['state'] === 'free'): ?>
            <a class="btn btn-outline" href="<?= e(rss_tool_trial_url()) ?>">Start Free Trial</a>
          <?php else: ?>
            <a class="btn btn-outline" href="<?= e(rss_tool_launch_url()) ?>">Open My Tools</a>
          <?php endif; ?>
        </div>
        <p class="muted" style="margin-top:1rem;color:rgba(255,255,255,.7);">Current tools state: <?= e($toolsAccess['label']) ?></p>
      </div>

      <div class="rss-hero-image-card">
        <a href="<?= e(rss_studio_root_url() . '/setmaxx/index.php') ?>">
          <img src="<?= e(base_url('../assets/img/rss-tools.png')) ?>" alt="Ready Set Shows tools preview">
        </a>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="rss-section-heading">
        <div>
          <p class="eyebrow">Included with Ready Set Shows</p>
          <h2>One subscription. Multiple gig tools.</h2>
          <p class="muted">Start with Set Maxx, then use the calendar and business tools as the suite grows around it.</p>
        </div>
      </div>

      <details class="rss-suite-card" open>
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-list-check"></i></span>
          <div>
            <h3>Set Maxx</h3>
            <p class="muted">Build better sets, manage your song list, and take controlled crowd requests.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <ul class="rss-feature-list">
            <li>Maintain your master song catalog</li>
            <li>Generate sets by crowd, danceability, energy, and flow</li>
            <li>Create gig-specific request sessions</li>
            <li>Share a QR-friendly public request page</li>
          </ul>
          <div class="rss-card-actions">
            <a class="btn btn-primary" href="<?= e(rss_tool_upgrade_url()) ?>">View Pricing</a>
            <a class="btn btn-outline" href="<?= e(rss_studio_root_url() . '/setmaxx/index.php') ?>">Explore Set Maxx</a>
          </div>
        </div>
      </details>

      <details class="rss-suite-card">
        <summary>
          <span class="rss-icon"><i class="fa-regular fa-calendar-check"></i></span>
          <div>
            <h3>Availability &amp; Calendar Tools</h3>
            <p class="muted">Find open dates across multiple calendars and turn messy gig data into useful outputs.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
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

      <details class="rss-suite-card" id="business-tracking">
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-bullhorn"></i></span>
          <div>
            <h3>Publishing Tools</h3>
            <p class="muted">Future tools for turning selected shows into promo copy, newsletters, and event posts.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <ul class="rss-feature-list">
            <li>Draft newsletter-style show announcements</li>
            <li>Create social captions from calendar entries</li>
            <li>Prepare event details for Facebook and other platforms</li>
            <li>Reuse your gig data instead of retyping everything</li>
          </ul>
          <p class="muted">Planned for the Ready Set Shows roadmap.</p>
        </div>
      </details>

      <details class="rss-suite-card">
        <summary>
          <span class="rss-icon"><i class="fa-solid fa-chart-line"></i></span>
          <div>
            <h3>Business Tracking</h3>
            <p class="muted">Future tools for deposits, balances, yearly totals, and average gig value.</p>
          </div>
          <i class="fa-solid fa-chevron-down rss-chevron"></i>
        </summary>
        <div class="rss-suite-body">
          <ul class="rss-feature-list">
            <li>Track gig fees, deposits, and balances due</li>
            <li>See yearly revenue and average booking value</li>
            <li>Spot which gigs and clients are most profitable</li>
            <li>Run your music work more like a real business</li>
          </ul>
          <p class="muted">Planned for the Ready Set Shows roadmap.</p>
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

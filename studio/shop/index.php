<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$products = product_file_map();
$user = Auth::currentUser($pdo);
if ($user) {
  Auth::syncEntitlementsByEmail($pdo, (int)$user['id']);
}

$toolsAccess = $user ? rss_tools_access_badge($pdo) : ['state' => 'free', 'label' => 'Free plan'];
$blueprintProduct = ensure_product_row_for_key($pdo, 'btb', $products['btb'] ?? []);
$blueprintOwned = false;
if ($user) {
  $stmt = $pdo->prepare("
    SELECT 1
    FROM entitlements
    WHERE user_id = ?
      AND product_id = ?
      AND status = 'active'
      AND (expires_at IS NULL OR expires_at > NOW())
    LIMIT 1
  ");
  $stmt->execute([(int)$user['id'], (int)$blueprintProduct['id']]);
  $blueprintOwned = (bool)$stmt->fetchColumn();
}

$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isReadySetShowsHost = in_array($requestHost, ['readysetshows.com', 'www.readysetshows.com'], true);
$readySetShowsUrl = rss_tool_launch_url();
$screensBase = base_url('../assets/img/readysetshows');
$blueprintTitle = $products['btb']['title'] ?? 'Backing Track Blueprint';
$shopReturnUrl = rss_studio_root_url() . '/shop/';
$shopLoginUrl = rss_studio_root_url() . '/member/login.php?' . http_build_query(['next' => $shopReturnUrl]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop | Nick Sanzeri</title>
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
      --shop-bg: #080910;
      --shop-panel: #141625;
      --shop-border: rgba(255,255,255,.1);
      --shop-gold: #e7c75a;
      --shop-muted: rgba(255,255,255,.72);
    }
    .shop-compact {
      min-height: 70vh;
      padding: 3rem 0 4rem;
      background: var(--shop-bg);
      color: #fff;
    }
    .shop-heading {
      margin-bottom: 1.35rem;
    }
    .shop-heading h1 {
      margin: 0 0 .45rem;
      font-size: clamp(2rem, 4vw, 3.15rem);
      line-height: 1.05;
    }
    .shop-heading p {
      max-width: 720px;
      margin: 0;
      color: var(--shop-muted);
      line-height: 1.6;
    }
    .shop-list {
      display: grid;
      gap: 1rem;
    }
    .shop-row {
      display: grid;
      grid-template-columns: 176px minmax(0, 1fr) auto;
      gap: 1.1rem;
      align-items: center;
      padding: 1rem;
      border: 1px solid var(--shop-border);
      border-radius: 16px;
      background: linear-gradient(180deg, var(--shop-panel) 0%, #10111c 100%);
      color: #fff;
      box-shadow: 0 12px 34px rgba(0,0,0,.25);
    }
    .shop-thumb {
      aspect-ratio: 16 / 10;
      overflow: hidden;
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 10px;
      background: #0f111b;
    }
    .shop-thumb img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .shop-copy h2 {
      margin: 0 0 .35rem;
      font-size: 1.35rem;
      line-height: 1.2;
    }
    .shop-copy h2 a {
      color: #fff;
      text-decoration: none;
    }
    .shop-copy p {
      max-width: 760px;
      margin: 0;
      color: var(--shop-muted);
      line-height: 1.55;
    }
    .shop-meta {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem .75rem;
      margin-top: .65rem;
      color: rgba(255,255,255,.76);
      font-size: .88rem;
    }
    .shop-meta span {
      display: inline-flex;
      align-items: center;
      gap: .32rem;
    }
    .shop-meta i {
      color: #65d58a;
    }
    .shop-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: .55rem;
      min-width: 190px;
    }
    .shop-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: .25rem .65rem;
      border: 1px solid rgba(231,199,90,.34);
      border-radius: 999px;
      color: var(--shop-gold);
      font-size: .78rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .shop-actions .btn {
      white-space: nowrap;
    }
    .shop-note {
      color: rgba(255,255,255,.62);
      font-size: .82rem;
      text-align: right;
    }
    @media (max-width: 800px) {
      .shop-row {
        grid-template-columns: 1fr;
      }
      .shop-actions {
        align-items: flex-start;
        min-width: 0;
      }
      .shop-note {
        text-align: left;
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

<main class="shop-compact">
  <div class="container">
    <div class="shop-heading">
      <p class="eyebrow">Shop</p>
      <h1>Tools for working musicians.</h1>
      <p>Digital products and apps from Nick Sanzeri for making the music side, the gig side, and the business side easier to manage.</p>
    </div>

    <div class="shop-list">
      <article class="shop-row">
        <a class="shop-thumb" href="<?= e($readySetShowsUrl) ?>" aria-label="Open Ready Set Shows">
          <img src="<?= e($screensBase . '/setmaxx-public-requests.png') ?>" alt="Ready Set Shows request page preview">
        </a>
        <div class="shop-copy">
          <h2><a href="<?= e($readySetShowsUrl) ?>">Ready Set Shows</a></h2>
          <p>Ready Set Shows handles the busy work so you can get back to making music. Calendar, SetMaxx, Finance, Publishing, and booking requests live in one working-musician toolkit.</p>
          <div class="shop-meta">
            <span><i class="fa-solid fa-check"></i> Calendar</span>
            <span><i class="fa-solid fa-check"></i> SetMaxx</span>
            <span><i class="fa-solid fa-check"></i> Finance</span>
            <span><i class="fa-solid fa-check"></i> Publishing</span>
          </div>
        </div>
        <div class="shop-actions">
          <span class="shop-chip"><?= e($toolsAccess['label']) ?></span>
          <?php if (!$user): ?>
            <a class="btn btn-primary" href="<?= e($shopLoginUrl) ?>">Log In / Start Trial</a>
          <?php elseif ($toolsAccess['state'] === 'free'): ?>
            <a class="btn btn-primary" href="<?= e(rss_tool_trial_url()) ?>">Start Free Trial</a>
            <a class="btn btn-outline" href="<?= e($readySetShowsUrl) ?>">Open Tools</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e($readySetShowsUrl) ?>">Open Ready Set Shows</a>
          <?php endif; ?>
        </div>
      </article>

      <?php if (!$isReadySetShowsHost): ?>
      <article class="shop-row">
        <a class="shop-thumb" href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>" aria-label="View <?= e($blueprintTitle) ?>">
          <img src="<?= e(base_url('../assets/img/BackingTrackBlueprint.png')) ?>" alt="<?= e($blueprintTitle) ?> cover">
        </a>
        <div class="shop-copy">
          <h2>
            <a href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">
              <?= e($blueprintTitle) ?>
            </a>
          </h2>
          <p>A practical guide to sounding bigger, tighter, and more professional with backing tracks, built for solo musicians and small acts.</p>
          <div class="shop-meta">
            <span><i class="fa-solid fa-check"></i> Instant PDF</span>
            <span><i class="fa-solid fa-check"></i> Backing track workflow</span>
            <span><i class="fa-solid fa-check"></i> Re-download from your account</span>
          </div>
        </div>
        <div class="shop-actions">
          <?php if ($blueprintOwned): ?>
            <span class="shop-chip">Purchased</span>
            <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/member/download.php?product_id=' . (int)$blueprintProduct['id']) ?>">Download Blueprint</a>
          <?php else: ?>
            <span class="shop-chip">Digital guide</span>
            <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">View Product</a>
            <?php if (Auth::isLoggedIn()): ?>
              <span class="shop-note">Use the same checkout email to sync past purchases.</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </article>
      <?php endif; ?>
    </div>
  </div>
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

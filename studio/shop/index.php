<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/_core/tool_access.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$products = product_file_map();
$user = Auth::currentUser($pdo);
$toolsAccess = $user ? rss_tools_access_badge($pdo) : ['state' => 'free', 'label' => 'Free plan'];
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
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">Shop</p>
      <h1>Digital products &amp; downloads</h1>
      <p class="page-intro">Instant downloads built for working musicians.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="shop-grid">
        <article class="product-row">
          <div class="product-info">
            <h2 class="product-title">
              <a href="<?= e(rss_tool_upgrade_url()) ?>">Calendar Tools</a>
            </h2>

            <p class="product-description">
              Free, trial, and Pro access for the Ready Set Shows calendar workflow. Every logged-in user gets the free version in the library.
            </p>

            <p class="muted" style="margin-top:.8rem;">Current tools state: <?= e($toolsAccess['label']) ?></p>

            <div style="display:flex; gap:.7rem; flex-wrap:wrap; margin-top:1rem;">
              <a class="btn btn-outline" href="<?= e(rss_tool_upgrade_url()) ?>">View Pricing</a>
              <?php if (!$user): ?>
                <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/member/login.php') ?>">Log In to Start Trial</a>
              <?php elseif ($toolsAccess['state'] === 'free'): ?>
                <a class="btn btn-primary" href="<?= e(rss_tool_trial_url()) ?>">Start Free Trial</a>
              <?php else: ?>
                <a class="btn btn-primary" href="<?= e(rss_tool_launch_url()) ?>">Open Tools</a>
              <?php endif; ?>
            </div>
          </div>
<div class="product-art">
            <a class="product-image-link" href="<?= e(rss_studio_root_url() . '/tools/index.php') ?>">
              <img
                class="product-image"
                src="<?= e(base_url('../assets/img/rss-tools.png')) ?>"
                alt="Ready Set Shows Calendar Tools"
              >
            </a>
          </div>
        </article>

        <article class="product-row">
          <div class="product-info">
            <h2 class="product-title">
              <a href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">
                <?= e($products['btb']['title'] ?? 'Backing Track Blueprint')?>
              </a>
            </h2>

            <p class="product-description">
              How solo musicians and bands sound bigger, tighter, and more professional — without adding more gear or complexity.
            </p>

            <?php if (Auth::isLoggedIn()): ?>
              <p class="muted" style="margin-top:.8rem;">Already have an account? Your purchases will appear in your library.</p>
            <?php endif; ?>

            <a class="btn btn-primary" href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">View Product</a>
          </div>
                         <div class="product-art">
            <a class="product-image-link" href="<?= e(rss_studio_root_url() . '/shop/blueprint.php') ?>">
              <img
                class="product-image"
                src="<?= e(base_url('../assets/img/BackingTrackBlueprint.png')) ?>"
                alt="Backing Track Blueprint cover"
              >
            </a>
          </div>
          
        </article>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

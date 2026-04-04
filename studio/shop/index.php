<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';

$products = product_file_map();
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
              <a href="<?= e(base_url('/shop/blueprint.php')) ?>">
                <?= e($products['btb']['title'] ?? 'Backing Track Blueprint')?>
              </a>
            </h2>

            <p class="product-description">
              How solo musicians and bands sound bigger, tighter, and more professional — without adding more gear or complexity.
            </p>

            <?php if (Auth::isLoggedIn()): ?>
              <p class="muted" style="margin-top:.8rem;">Already have an account? Your purchases will appear in your library.</p>
            <?php endif; ?>

            <a class="btn btn-primary" href="<?= e(base_url('/shop/blueprint.php')) ?>">
              View Product
            </a>
          </div>

          <div class="product-art">
            <a class="product-image-link" href="<?= e(base_url('/shop/blueprint.php')) ?>">
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

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
      <h1>Tools and resources for working musicians</h1>
      <p class="page-intro">
        Digital downloads and practical tools built to help musicians sound better, stay organized, and book smarter.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="shop-grid">

        <!-- Product 1: Backing Track Blueprint -->
        <article class="product-row">
          <div class="product-info">
            <p class="eyebrow" style="margin-bottom:.6rem;">Instant Download</p>

            <h2 class="product-title">
              <a href="<?= e(base_url('/shop/blueprint.php')) ?>">
                <?= e($products['btb']['title'] ?? 'Backing Track Blueprint') ?>
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

        <!-- Product 2: Calendar Tools Subscription -->
        <article class="product-row">
          <div class="product-info">
            <p class="eyebrow" style="margin-bottom:.6rem;">Subscription Tool</p>

            <h2 class="product-title">
              <a href="<?= e(base_url('/shop/calendar-tools.php')) ?>">
                Ready Set Shows Calendar Tools
              </a>
            </h2>

            <p class="product-description">
              Know your real availability fast. Connect multiple calendar feeds, find true open dates across your band, print clean schedule views, and export dates for Bands In Town.
            </p>

            <ul style="margin:1rem 0 1.4rem 1.1rem; color:rgba(255,255,255,.82); line-height:1.7;">
              <li>Check shared availability across multiple calendars</li>
              <li>Give bookers fast, reliable answers</li>
              <li>Print and export useful date views</li>
              <li>Built for solo acts, bandleaders, and working musicians</li>
            </ul>

            <p class="muted" style="margin-bottom:1rem;">
              Starting at $5/month with a free trial.
            </p>

            <a class="btn btn-primary" href="<?= e(base_url('/shop/calendar-tools.php')) ?>">
              Learn More
            </a>
          </div>

          <div class="product-art">
            <a
              class="product-image-link"
              href="<?= e(base_url('/shop/calendar-tools.php')) ?>"
              style="text-decoration:none;"
            >
              <div
                class="product-image"
                aria-label="Calendar Tools preview"
                style="
                  min-height: 340px;
                  display: flex;
                  flex-direction: column;
                  justify-content: space-between;
                  padding: 1.25rem;
                  border-radius: 18px;
                  background:
                    linear-gradient(145deg, rgba(255,255,255,.08), rgba(255,255,255,.03)),
                    radial-gradient(circle at top left, rgba(212,175,55,.22), transparent 35%),
                    #111;
                  border: 1px solid rgba(255,255,255,.08);
                  box-shadow: 0 18px 40px rgba(0,0,0,.28);
                "
              >
                <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
                  <div>
                    <div style="font-size:.78rem; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.58);">
                      Calendar Tools
                    </div>
                    <div style="font-family:'Playfair Display', serif; font-size:1.3rem; color:#fff; margin-top:.25rem;">
                      Band Availability
                    </div>
                  </div>
                  <div style="font-size:1.2rem; color:#d4af37;">
                    <i class="fa-regular fa-calendar-days"></i>
                  </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:.7rem; margin:1.2rem 0;">
                  <div style="padding:.8rem; border-radius:14px; background:rgba(255,255,255,.06); color:#fff;">
                    <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.55);">Members</div>
                    <div style="font-size:1.4rem; font-weight:600; margin-top:.25rem;">5 Linked</div>
                  </div>
                  <div style="padding:.8rem; border-radius:14px; background:rgba(255,255,255,.06); color:#fff;">
                    <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.55);">Open Dates</div>
                    <div style="font-size:1.4rem; font-weight:600; margin-top:.25rem;">12 Found</div>
                  </div>
                </div>

                <div style="display:grid; gap:.6rem;">
                  <div style="padding:.7rem .85rem; border-radius:12px; background:rgba(255,255,255,.05); color:rgba(255,255,255,.88); font-size:.95rem;">
                    <strong style="color:#fff;">Fri, Jun 12</strong> — Full band available
                  </div>
                  <div style="padding:.7rem .85rem; border-radius:12px; background:rgba(255,255,255,.05); color:rgba(255,255,255,.88); font-size:.95rem;">
                    <strong style="color:#fff;">Sat, Jun 20</strong> — 1 conflict
                  </div>
                  <div style="padding:.7rem .85rem; border-radius:12px; background:rgba(255,255,255,.05); color:rgba(255,255,255,.88); font-size:.95rem;">
                    <strong style="color:#fff;">Fri, Jun 26</strong> — Full band available
                  </div>
                </div>
              </div>
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
<?php
// shop/index.php
require_once __DIR__ . '/../stripe_config.php';
require_once __DIR__ . '/../_core/bootstrap.php';
$products = product_file_map();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop | Nick Sanzeri</title>
 <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/style.css')) ?>">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a href="/index.html" class="brand">
        <span class="brand-mark">NS</span>
        <span class="brand-text">
          <span class="brand-name">Nick Sanzeri</span>
          <span class="brand-tagline">One Man · Full‑Band Experience</span>
        </span>
      </a>
      <nav class="main-nav open" id="mainNav">
        <ul>
          <li><a href="/index.html">Home</a></li>
          <li><a href="/shows.html">Shows</a></li>
          <li><a href="/media.html">Media</a></li>
          <li><a href="/about.html">About</a></li>
          <li><a href="/testimonials.html">Testimonials</a></li>
          <li><a href="/booking.html">Booking</a></li>
          <li><a href="/shop/index.php">Shop</a></li>
        </ul>
      </nav>
    </div>
  </header>

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
        <div class="testimonial-grid">
          <div class="testimonial-card">
            <p class="testimonial-text"><strong><?= htmlspecialchars($products['btb']['title'] ?? 'Backing Track Blueprint') ?></strong><br>
              <span class="muted"><?= htmlspecialchars($products['btb']['description'] ?? 'Instant download') ?></span></p>
              								
            <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/shop/blueprint.php'))?>">View</a>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>

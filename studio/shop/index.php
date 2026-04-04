<?php
// shop/index.php
// Load core bootstrap first (sessions, helpers, DB, env, composer autoload)
require_once __DIR__ . '/../_private/_core/bootstrap.php';
// Then load Stripe + product configuration
require_once __DIR__ . '/../_private/config/stripe.php';

$products = product_file_map();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop | Nick Sanzeri</title>
 <link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 
 <script>
	!function(f,b,e,v,n,t,s)
	{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};
	if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
	n.queue=[];t=b.createElement(e);t.async=!0;
	t.src='https://connect.facebook.net/en_US/fbevents.js';
	s=b.getElementsByTagName(e)[0];
	s.parentNode.insertBefore(t,s)}
	(window, document,'script');
	
	fbq('init', '512475687955029');
	fbq('track', 'PageView');
</script>
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
        <a href="<?= htmlspecialchars(base_url('/shop/blueprint.php')) ?>">
          <?= htmlspecialchars($products['btb']['title'] ?? 'Backing Track Blueprint') ?>
        </a>
      </h2>

      <p class="product-description">
        How solo musicians and bands sound bigger, tighter, and more professional — without adding more gear or complexity.
      </p>

      <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/shop/blueprint.php')) ?>">
        View Product
      </a>
    </div>

    <div class="product-art">
      <a class="product-image-link" href="<?= htmlspecialchars(base_url('/shop/blueprint.php')) ?>">
        <img
          class="product-image"
          src="<?= htmlspecialchars(base_url('../assets/img/BackingTrackBlueprint.png')) ?>"
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

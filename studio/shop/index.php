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
<link rel="stylesheet" href="../../assets/css/style.css">
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
  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>

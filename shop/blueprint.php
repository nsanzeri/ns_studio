<?php
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../_core/bootstrap.php';
$products = product_file_map();
$p = $products['btb'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($p['title']) ?> | Shop</title>
 <link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/css/style.css')) ?>">
</head>
<body>
  <main>
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Digital Download</p>
        <h1><?= htmlspecialchars($p['title']) ?></h1>
        <p class="page-intro">A practical, step‑by‑step guide to building backing tracks that make you sound huge — and bookable.</p>
      </div>
    </section>

    <section class="section">
      <div class="container grid-2 booking-layout">
        <div>
          <h2>What you get</h2>
          <ul class="feature-list">
            <li>PDF download (instant access)</li>
            <li>Real-world workflow, not theory</li>
            <li>Templates + examples you can reuse</li>
            <li>Make your show tighter, bigger, and easier to run</li>
          </ul>
        </div>

        <div class="form">
          <h3 class="form-title">Buy now</h3>
          <p class="muted">Checkout is secure via Stripe. After purchase you’ll see a <strong>Download Now</strong> button — no login required.</p>

          <button class="btn btn-primary btn-full" id="buyBtn">Buy &amp; Download</button>
          <p class="muted small" style="margin-top:10px;">Tip: you can also create a Studio login on the success page to save this in your Library.</p>

          <p id="buyErr" class="muted small" style="display:none; margin-top:10px;"></p>
        </div>
      </div>
    </section>
  </main>

  <script>
  const btn = document.getElementById('buyBtn');
  const err = document.getElementById('buyErr');

	const checkoutUrl = "<?= base_url('api/create_checkout_session.php') ?>";
	
	async function go() {
	  try {
	    const response = await fetch(checkoutUrl, {
	      method: 'POST',
	      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
	      body: new URLSearchParams({ product_key: 'btb' })
	    });
	
	    const data = await response.json();
	
	    if (data.url) {
	      window.location = data.url;
	    } else {
	      alert('Checkout error.');
	      console.error(data);
	    }
	  } catch (err) {
	    console.error(err);
	    alert('Something went wrong.');
	  }
	}


  btn.addEventListener('click', go);
  </script>
</body>
</html>

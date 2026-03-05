<?php
// /studio/pricing.php
// Gumroad product URL
define('STUDIO_MEMBER_PRICE', '$4');
define('BANDLEADER_PRICE', '$6');
define('HEADLINER_PRICE', '$47');
define('BACKSTAGE_PASS_PRICE', '$141');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Studio Membership • Nick Sanzeri</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/index.html">
      <span class="brand-mark">NS</span>
      <span class="brand-text">
        <span class="brand-name">Nick Sanzeri</span>
        <span class="brand-tagline">One Man • Full-Band Experience</span>
      </span>
    </a>

    <nav class="main-nav">
      <ul>
        <li><a href="/shows.html">Shows</a></li>
        <li><a href="/media.html">Media</a></li>
        <li><a href="/booking.html">Book</a></li>
        <li><a href="/shop/">Shop</a></li>
        <li><a href="/studio/">Studio</a></li>
      </ul>
    </nav>
  </div>
</header>

<main class="container" style="padding: 3.25rem 0 4.5rem;">
  <section style="text-align:center; padding: 1rem 0 2rem;">
    <h1 style="font-family:'Playfair Display',serif; letter-spacing:0.06em; margin:0; font-size:2.2rem;">
      Become a Studio Member
    </h1>
    <p class="muted" style="margin:0.75rem auto 0; max-width: 58ch;">
      Start free, unlock the good stuff when you’re ready — Blueprint access, exclusive drops, and behind-the-scenes content.
    </p>
    <div style="margin-top: 1.25rem;">
      <a class="text-link" href="/studio/login.php">Already have an account? Log in</a>
      <span class="muted" style="padding: 0 0.75rem;">•</span>
      <a class="text-link" href="/studio/signup.php" style="display:none;">Start free</a>
      <a class="text-link" href="#free">Start with a free account</a>
    </div>
  </section>

  <section class="pricing-grid">
    <!-- Studio Member -->
    <article class="pricing-card">
      <div class="pricing-badge">Studio Member</div>
      <div class="pricing-price"><?= htmlspecialchars(STUDIO_MEMBER_PRICE) ?> <span class="muted">/ month</span></div>
      <button class="btn btn-primary btn-full js-subscribe" data-tier="studio_member">Join now</button>

      <p class="pricing-desc">
        For fans and fellow music nerds who want behind-the-scenes access and occasional exclusives.
      </p>

      <ul class="pricing-list">
        <li>Members-only posts</li>
        <li>Occasional exclusive downloads</li>
        <li><strong>5% off</strong> Shop (Blueprint + prints)</li>
        <li>Early access to new print drops</li>
      </ul>
    </article>

    <!-- Bandleader -->
    <article class="pricing-card pricing-card-featured">
      <div class="pricing-badge">Bandleader</div>
      <div class="pricing-price"><?= htmlspecialchars(BANDLEADER_PRICE) ?> <span class="muted">/ month</span></div>
      <button class="btn btn-primary btn-full js-subscribe" data-tier="bandleader">Join now</button>

      <p class="pricing-desc">
        The working-musician tier. Monthly value + the Blueprint included while you’re active.
      </p>

      <ul class="pricing-list">
        <li>Everything in Studio Member</li>
        <li><strong>Backing Track Blueprint included</strong></li>
        <li><strong>10% off</strong> Shop</li>
        <li>Monthly “Bandleader Drop” (packs/templates)</li>
        <li>Deeper training + workflow posts</li>
      </ul>
    </article>

    <!-- Headliner -->
    <article class="pricing-card">
      <div class="pricing-badge">Headliner</div>
      <div class="pricing-price"><?= htmlspecialchars(HEADLINER_PRICE) ?> <span class="muted">/ month</span></div>
      <button class="btn btn-primary btn-full js-subscribe" data-tier="headliner">Join now</button>

      <p class="pricing-desc">
        Premium access + higher-value drops and live Q&As. For serious supporters.
      </p>

      <ul class="pricing-list">
        <li>Everything in Bandleader</li>
        <li><strong>15% off</strong> Shop</li>
        <li>Quarterly live Zoom/Q&A</li>
        <li>Priority requests</li>
        <li>Headliner-only downloads</li>
      </ul>
    </article>

    <!-- Backstage Pass -->
    <article class="pricing-card pricing-card-wide">
      <div class="pricing-badge">Backstage Pass (Coaching)</div>
      <div class="pricing-price"><?= htmlspecialchars(BACKSTAGE_PASS_PRICE) ?> <span class="muted">/ month</span></div>
      <button class="btn btn-primary btn-full js-subscribe" data-tier="backstage">Join now</button>

      <p class="pricing-desc">
        Limited spots. Monthly 1:1 to help you level up — music, gigs, workflow, or your own creator business.
      </p>

      <ul class="pricing-list">
        <li>Everything in Headliner</li>
        <li><strong>1× monthly 30–45 min call</strong></li>
        <li>Priority support</li>
        <li>Limited availability</li>
      </ul>
    </article>
  </section>

  <section id="free" class="pricing-free">
    <div class="pricing-free-inner">
      <div>
        <div class="pricing-badge">Studio Pass</div>
        <div style="font-size:1.25rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase;">Free account</div>
        <p class="muted" style="margin:0.35rem 0 0;">
          Get access to the Studio dashboard + a starter set of freebies.
        </p>
      </div>
      <a class="btn btn-outline" href="/studio/login.php">Create free account</a>
    </div>
  </section>

</main>

<script>
  // Placeholder: wire this to /api/create-checkout-session.php next.
  document.querySelectorAll('.js-subscribe').forEach(btn => {
    btn.addEventListener('click', async () => {
      const tier = btn.dataset.tier;
      // For now: just demonstrate intent
      alert("Subscribe: " + tier + " (next: Stripe Checkout)");
    });
  });
</script>

</body>
</html>
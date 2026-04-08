<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ready Set Shows Calendar Tools | Nick Sanzeri</title>
  <meta name="description" content="Check shared band availability across multiple calendars, print clean date views, and export dates for Bandsintown. Built for working musicians.">
  <link rel="stylesheet" href="<?= e(base_url('../assets/css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .tools-hero-grid{
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap:2rem;
      align-items:center;
    }

    .tools-hero-copy .eyebrow{
      margin-bottom:.75rem;
    }

    .tools-hero-copy h1{
      margin-bottom:1rem;
      max-width:12ch;
    }

    .tools-hero-copy .page-intro{
      max-width:56ch;
    }

    .tools-hero-cta{
      display:flex;
      flex-wrap:wrap;
      gap:.85rem;
      margin-top:1.5rem;
    }

    .tools-note{
      margin-top:1rem;
      color:rgba(255,255,255,.7);
      font-size:.95rem;
    }

    .tools-preview{
      min-height:420px;
      border-radius:24px;
      padding:1.25rem;
      background:
        linear-gradient(145deg, rgba(255,255,255,.08), rgba(255,255,255,.03)),
        radial-gradient(circle at top left, rgba(212,175,55,.24), transparent 35%),
        #111;
      border:1px solid rgba(255,255,255,.08);
      box-shadow:0 20px 50px rgba(0,0,0,.28);
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }

    .tools-preview-top{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:1rem;
      margin-bottom:1rem;
    }

    .tools-preview-label{
      font-size:.78rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      color:rgba(255,255,255,.58);
    }

    .tools-preview-title{
      font-family:'Playfair Display', serif;
      font-size:1.35rem;
      color:#fff;
      margin-top:.2rem;
    }

    .tools-preview-icon{
      font-size:1.3rem;
      color:#d4af37;
    }

    .tools-preview-stats{
      display:grid;
      grid-template-columns:repeat(2,1fr);
      gap:.8rem;
      margin:1rem 0 1.25rem;
    }

    .tools-stat{
      padding:.9rem;
      border-radius:16px;
      background:rgba(255,255,255,.06);
      color:#fff;
    }

    .tools-stat-label{
      font-size:.75rem;
      letter-spacing:.08em;
      text-transform:uppercase;
      color:rgba(255,255,255,.56);
    }

    .tools-stat-value{
      font-size:1.45rem;
      font-weight:600;
      margin-top:.3rem;
    }

    .tools-results{
      display:grid;
      gap:.65rem;
    }

    .tools-result{
      padding:.8rem .9rem;
      border-radius:14px;
      background:rgba(255,255,255,.05);
      color:rgba(255,255,255,.88);
      font-size:.96rem;
    }

    .tools-result strong{
      color:#fff;
    }

    .feature-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:1.25rem;
    }

    .feature-card{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:20px;
      padding:1.35rem;
      box-shadow:0 12px 28px rgba(0,0,0,.18);
    }

    .feature-card i{
      font-size:1.15rem;
      color:#d4af37;
      margin-bottom:.9rem;
    }

    .feature-card h3{
      margin-bottom:.65rem;
      color:#fff;
      font-size:1.15rem;
    }

    .feature-card p{
      margin:0;
      color:rgba(255,255,255,.8);
      line-height:1.7;
    }

    .split-section{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:2rem;
      align-items:start;
    }

    .list-card{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:22px;
      padding:1.5rem;
    }

    .list-card h3{
      margin-bottom:1rem;
    }

    .list-card ul{
      margin:0;
      padding-left:1.1rem;
      color:rgba(255,255,255,.82);
      line-height:1.8;
    }

    .pricing-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:1.5rem;
    }

    .pricing-card{
      position:relative;
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:24px;
      padding:1.6rem;
      box-shadow:0 16px 34px rgba(0,0,0,.18);
    }

    .pricing-card.featured{
      border-color:rgba(212,175,55,.45);
      box-shadow:0 20px 40px rgba(0,0,0,.22);
    }

    .pricing-badge{
      display:inline-block;
      margin-bottom:.85rem;
      padding:.35rem .65rem;
      border-radius:999px;
      background:rgba(212,175,55,.14);
      color:#f2d67c;
      font-size:.78rem;
      letter-spacing:.08em;
      text-transform:uppercase;
    }

    .pricing-card h3{
      margin-bottom:.45rem;
    }

    .pricing-subtitle{
      color:rgba(255,255,255,.75);
      margin-bottom:1rem;
    }

    .price-line{
      display:flex;
      align-items:flex-end;
      gap:.35rem;
      margin-bottom:1rem;
    }

    .price{
      font-size:2.5rem;
      font-weight:700;
      line-height:1;
      color:#fff;
    }

    .price-unit{
      color:rgba(255,255,255,.68);
      margin-bottom:.25rem;
    }

    .pricing-card ul{
      margin:0 0 1.35rem 1.1rem;
      color:rgba(255,255,255,.82);
      line-height:1.8;
    }

    .pricing-card .btn{
      width:100%;
      justify-content:center;
    }

    .faq-grid{
      display:grid;
      gap:1rem;
    }

    .faq-item{
      background:rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:18px;
      padding:1.2rem 1.25rem;
    }

    .faq-item h3{
      font-size:1.05rem;
      margin-bottom:.45rem;
    }

    .faq-item p{
      margin:0;
      color:rgba(255,255,255,.8);
      line-height:1.7;
    }

    .final-cta{
      text-align:center;
      max-width:760px;
      margin:0 auto;
    }

    .final-cta .tools-hero-cta{
      justify-content:center;
    }
.pricing-grid-single-feature{
  grid-template-columns:repeat(2, minmax(0, 1fr));
  align-items:stretch;
}

.small-pricing-note{
  margin-top:.85rem;
  color:rgba(255,255,255,.62);
  font-size:.9rem;
  line-height:1.5;
}

@media (max-width: 980px){
  .pricing-grid-single-feature{
    grid-template-columns:1fr;
  }
}
    @media (max-width: 980px){
      .tools-hero-grid,
      .split-section,
      .pricing-grid,
      .feature-grid{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main>
  <section class="page-hero">
    <div class="container">
      <div class="tools-hero-grid">
		<div class="tools-hero-copy">
		  <p class="eyebrow">Ready Set Shows</p>
		  <h1>Turn your calendar into more gigs.</h1>
		  <p class="page-intro">
		    Ready Set Shows Calendar Tools helps working musicians and bandleaders check shared availability,
		    create cleaner scheduling workflows, and stay organized without the usual back-and-forth chaos.
		  </p>
		
		  <div class="tools-hero-cta">
		    <a class="btn btn-primary" href="#pricing">
		      View Pricing
		    </a>
		    <a class="btn btn-secondary" href="<?= e(base_url('/member/login.php')) ?>">
		      Get Started Free
		    </a>
		  </div>
		
		  <p class="tools-note">
		    Built for working musicians. Founder pricing starts at just $5/month.
		  </p>
		</div>
        <div class="tools-preview" aria-label="Calendar tools preview">
          <div>
            <div class="tools-preview-top">
              <div>
                <div class="tools-preview-label">Ready Set Shows</div>
                <div class="tools-preview-title">Band Availability Dashboard</div>
              </div>
              <div class="tools-preview-icon">
                <i class="fa-regular fa-calendar-days"></i>
              </div>
            </div>

            <div class="tools-preview-stats">
              <div class="tools-stat">
                <div class="tools-stat-label">Calendars</div>
                <div class="tools-stat-value">6 Linked</div>
              </div>
              <div class="tools-stat">
                <div class="tools-stat-label">Open Dates</div>
                <div class="tools-stat-value">14 Found</div>
              </div>
            </div>
          </div>

          <div class="tools-results">
            <div class="tools-result"><strong>Fri, Jun 12</strong> — Full band available</div>
            <div class="tools-result"><strong>Sat, Jun 20</strong> — Drummer busy</div>
            <div class="tools-result"><strong>Fri, Jun 26</strong> — Full band available</div>
            <div class="tools-result"><strong>Sat, Jul 11</strong> — Full band available</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-heading" style="max-width:760px; margin-bottom:2rem;">
        <p class="eyebrow">Why it matters</p>
        <h2>Stop texting the whole band just to answer one booking question.</h2>
        <p>
          This is not just a calendar utility. It is a practical booking tool that helps you answer faster, look more organized, and avoid the embarrassment of giving the wrong date availability.
        </p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <i class="fa-solid fa-people-group"></i>
          <h3>Check shared band availability</h3>
          <p>
            Read multiple calendar feeds at once and quickly find dates that actually work across the whole group.
          </p>
        </article>

        <article class="feature-card">
          <i class="fa-solid fa-bolt"></i>
          <h3>Answer bookers faster</h3>
          <p>
            Stop saying “let me get back to you.” Get to a reliable answer quickly when a venue, client, or buyer asks about a date.
          </p>
        </article>

        <article class="feature-card">
          <i class="fa-solid fa-print"></i>
          <h3>Print clean schedule views</h3>
          <p>
            Create readable calendar views you can use internally or send along when you need something simple and professional.
          </p>
        </article>

        <article class="feature-card">
          <i class="fa-solid fa-file-csv"></i>
          <h3>Export for Bandsintown</h3>
          <p>
            Turn your calendar data into a useful CSV format so your promo workflow is less repetitive and less error-prone.
          </p>
        </article>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="split-section">
        <div class="list-card">
          <p class="eyebrow">What you can do</p>
          <h3>Core tools included</h3>
          <ul>
            <li>Link multiple iCal calendar feeds</li>
            <li>Check shared availability across selected calendars</li>
            <li>See open dates quickly in one place</li>
            <li>Generate printable date views</li>
            <li>Export dates for Bandsintown upload workflows</li>
          </ul>
        </div>

        <div class="list-card">
          <p class="eyebrow">Best for</p>
          <h3>Who this is built for</h3>
          <ul>
            <li>Bandleaders coordinating multiple musicians</li>
            <li>Solo performers managing busy calendars</li>
            <li>Working musicians who need quick, reliable answers</li>
            <li>Acts that want to look more organized and professional</li>
            <li>Anyone tired of calendar chaos and back-and-forth texting</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

	<section class="section" id="pricing">
	  <div class="container">
	    <div class="section-heading" style="max-width:760px; margin-bottom:2rem;">
	      <p class="eyebrow">Pricing</p>
	      <h2>Start free. Upgrade when it starts saving you time.</h2>
	      <p>
	        Explore the tools with a free account, then unlock the full workflow with Pro.
	        Early users can lock in founder pricing.
	      </p>
	    </div>
	
	    <div class="pricing-grid pricing-grid-single-feature">
	      <article class="pricing-card">
	        <div class="pricing-badge">Free</div>
	        <h3>Free Account</h3>
	        <p class="pricing-subtitle">A simple way to explore the tools risk-free.</p>
	
	        <div class="price-line">
	          <div class="price">$0</div>
	          <div class="price-unit">/ month</div>
	        </div>
	
	        <ul>
	          <li>1 connected calendar</li>
	          <li>Basic availability view</li>
	          <li>Limited preview access</li>
	          <li>See how the system works before upgrading</li>
	        </ul>
	
	        <a class="btn btn-secondary" href="<?= e(base_url('/member/login.php')) ?>">
	          Get Started Free
	        </a>
	      </article>
	
	      <article class="pricing-card featured">
	        <div class="pricing-badge">Founder Pricing</div>
	        <h3>Pro</h3>
	        <p class="pricing-subtitle">For working musicians who want the full workflow.</p>
	
	        <div class="price-line">
	          <div class="price">$5</div>
	          <div class="price-unit">/ month</div>
	        </div>
	
	        <ul>
	          <li>Unlimited availability checks</li>
	          <li>Multiple connected calendars</li>
	          <li>Printable calendar views</li>
	          <li>Bandsintown CSV export</li>
	          <li>Full date range access</li>
	          <li><strong>5% off all shop purchases</strong></li>
	        </ul>
	
	        <a class="btn btn-primary" href="<?= e(base_url('/member/pricing.php')) ?>">
	          Upgrade to Pro
	        </a>
	
	        <p class="small-pricing-note">
	          Lock in founder pricing now. Future users may pay more.
	        </p>
	      </article>
	    </div>
	  </div>
	</section>
	
	<section class="section">
  <div class="container">
    <div class="list-card" style="max-width:860px; margin:0 auto;">
      <p class="eyebrow">Why Pro makes sense</p>
      <h3>This pays for itself fast.</h3>
      <ul>
        <li>Book one extra gig and it is covered</li>
        <li>Save even a little time every week and it is covered</li>
        <li>Buy from the shop and your member discount helps offset the cost</li>
        <li>Look more organized when venues, clients, or bandmates need answers quickly</li>
      </ul>
    </div>
  </div>
</section>

  <section class="section">
    <div class="container">
      <div class="section-heading" style="max-width:760px; margin-bottom:2rem;">
        <p class="eyebrow">FAQ</p>
        <h2>Common questions</h2>
      </div>

      <div class="faq-grid">
        <article class="faq-item">
          <h3>Do I have to manually enter all my events?</h3>
          <p>
            No. The tool is designed to read iCal feeds you connect, so you can work from the calendars you already use.
          </p>
        </article>

        <article class="faq-item">
          <h3>Can I use this for a full band?</h3>
          <p>
            Yes. One of the strongest use cases is a bandleader linking multiple members’ calendars and checking reliable shared availability quickly.
          </p>
        </article>

<article class="faq-item">
  <h3>Do I need the subscription to try it?</h3>
  <p>
    No. You can start with a free account and explore the tools before deciding whether Pro makes sense for you.
  </p>
</article>
<article class="faq-item">
  <h3>Why is Pro only $5 per month?</h3>
  <p>
    This is founder pricing for early users. It is meant to reward the first musicians who get on board and help shape the platform.
  </p>
</article>

        <article class="faq-item">
          <h3>Will this help with Bandsintown?</h3>
          <p>
            Yes. One of the included tools is a CSV export workflow built to make Bandsintown uploads easier.
          </p>
        </article>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="final-cta">
        <p class="eyebrow">Ready to simplify your booking workflow?</p>
        <h2>Stop guessing. Check your real availability fast.</h2>
        <p class="page-intro" style="margin-left:auto; margin-right:auto;">
          Whether you are managing your own dates or coordinating a whole band, Ready Set Shows Calendar Tools helps you answer faster and stay organized.
        </p>

        <div class="tools-hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('/shop/checkout-calendar-tools.php')) ?>">
            Start Free Trial
          </a>
          <a class="btn btn-secondary" href="<?= e(base_url('/shop/index.php')) ?>">
            Back to Shop
          </a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
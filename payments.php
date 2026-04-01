<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Nick Sanzeri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
	<link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
	<link rel="manifest" href="site.webmanifest">
	<link rel="shortcut icon" href="favicons/favicon.ico">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "PerformingGroup",
  "name": "Nick Sanzeri - Midwest and Chicago area Private Event Performer",
  "alternateName": "Live bassist and vocalist provising music for private parties, weddings, corporate events, casinos, restaurants, clubs and more.",
  "url": "https://nicksanzeri.com",
  "description": "Singer and bassist with a full bandsound specializing in birthdays, parties, corporate events, reunions and weddings in the Chicago area and beyond",
  "sameAs": [
  	"https://www.facebook.com/nicksanzeri13",
	"https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ?si=O4zvZ3xUQyWhO1Jhsq-dnQ",
    "https://twitter.com/nick_sanzeri",
    "https://www.tiktok.com/@nicksanzeri?lang=en",
    "https://www.instagram.com/nick_sanzeri/",
    "https://www.youtube.com/channel/UCnTEOsjjmdnM0jBZyJY6jfg"
  }
  "areaServed": {
    "@type": "Place",
    "name": "Chicago, IL"
  },
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "Performance Services",
    "itemListElement": [
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "Wedding Entertainment and Music",
          "description": "Singer and bassist with full band sound for weddings receptions and able to provide DJ services and learn songs on request"
        }
      },
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "Restaurant, club and bar entertainment",
          "description": "Live music to keep your patrons interested and staying all night"
        }
      },
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "Casino live music",
          "description": "The perfect background live music for your guests to enjoy a lively night out."
        }
      },
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "Private parties, home events, and milestone events",
          "description": "Thriliing your family and friends with live music and singing. Engaging and inclusive song choices."
        }
      },
      {
        "@type": "Offer",
        "itemOffered": {
          "@type": "Service",
          "name": "Corporate events",
          "description": "Building team spirit and bring your team together through the crowd engaging live music and singing."
        }
      }
    ]
  },
  "location": {
    "@type": "Place",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "Carol Stream",
      "addressRegion": "IL",
      "addressCountry": "US"
    }
  }
}
</script>

</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>

    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Payments</p>
                <h1>Easy, cash‑free tipping &amp; payments.</h1>
                <p class="page-intro">
                    No cash? No problem. Use Venmo, Zelle, or card for tips and event payments.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container payments-layout">
                <div class="payments-column">
                    <h2>Venmo</h2>
                    <p>Quick and easy mobile payments.</p>
                    <a href="https://venmo.com/nsanzeri" target="_blank" rel="noopener noreferrer" class="venmo-link">
                        <img src="venmo-logo.png" alt="Venmo" width="20" height="20">
                        Venmo Me
                    </a>
                </div>
                <div class="payments-column">
                    <h2>Zelle®</h2>
                    <div class="zelle-widget">
                        <div class="zelle-header">
                            <div class="zelle-logo">Zelle®</div>
                            <h3>Fast &amp; secure</h3>
                        </div>
                        <div class="zelle-content">
                            <div class="payment-method">
                                <div class="method-option">
                                    <strong>Email:</strong>
                                    <span id="zelleEmail">nsanzeri@gmail.com</span>
                                    <button type="button" onclick="copyToClipboard('zelleEmail')" class="copy-btn">Copy</button>
                                </div>
                                <div class="method-option">
                                    <strong>Phone:</strong>
                                    <span id="zellePhone">(224) 535‑0104</span>
                                    <button type="button" onclick="copyToClipboard('zellePhone')" class="copy-btn">Copy</button>
                                </div>
                            </div>
                            <div class="zelle-features">
                                <div class="feature"><span>⚡</span><span>Instant transfers</span></div>
                                <div class="feature"><span>🔒</span><span>Bank‑level security</span></div>
                                <div class="feature"><span>💙</span><span>No fees</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="payments-column">
                    <h2>Credit / Debit</h2>
                    <p>Use your card via Stripe’s secure checkout.</p>
						<script async
						  src="https://js.stripe.com/v3/buy-button.js">
						</script>
						
						<stripe-buy-button
						  buy-button-id="buy_btn_1SPzAPI8bUVPaxrek8BvkH3S"
						  publishable-key="pk_live_51SPyZ2I8bUVPaxre0Y6kVs8A7UTEJWzIUOplW24CYQVMklvlnVrzcNTgYvygSyoFySSJmApsS6b5y1qen4XWw0RE00EcojUBal">
						</stripe-buy-button>
                </div>
            </div>
        </section>

        <div class="floating-venmo">
            <a href="https://venmo.com/nsanzeri" target="_blank" rel="noopener noreferrer" class="floating-btn">
                <span>Venmo</span>
            </a>
        </div>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>

    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(() => {
            const original = element.textContent;
            element.textContent = 'Copied!';
            element.style.color = '#d4af37';
            element.style.fontWeight = '600';
            setTimeout(() => {
                element.textContent = original;
                element.style.color = '';
                element.style.fontWeight = '';
            }, 1800);
        });
    }
    </script>
</body>
</html>

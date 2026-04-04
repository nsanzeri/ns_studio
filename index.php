<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nick Sanzeri | One Man · Full‑Band Experience</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16.png">
	<link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/favicon-180.png">
	<link rel="shortcut icon" href="assets/favicons/favicon.ico">
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
<script>
document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("heroVideo");
    if (!video) return;

    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    const src = isMobile
        ? "assets/video/hero-mobile.webm"
        : "assets/video/hero-desktop.webm";

    video.src = src;
    video.load();
});
</script>
</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>



    <main>
        <!-- Hero with video background -->
        <section class="hero">
		        <div class="hero-media">
				    <video id="heroVideo" class="hero-video" autoplay muted loop playsinline></video>
				    <div class="hero-overlay"></div>
				</div>
        
<!--             <div class="hero-media">
                <video class="hero-video" autoplay muted loop playsinline>
                    <source src="assets/video/Fireball.webm" type="video/webm">
                    Fallback image if video not available
                </video>
                <div class="hero-overlay"></div>
            </div>
 -->            <div class="container hero-content">
                <div class="hero-text">
				<p class="eyebrow">Live Music for Events That Matter</p>
				
				<h1>Big energy. Zero headaches.</h1>
				
				<p class="hero-subtitle">
				Live music that elevates the room — not overwhelms it. 
				A proven choice for private parties, corporate events, and venues that expect more.
				</p>
                    <div class="hero-actions">
                        <a href="booking.php" class="btn btn-primary">Book Nick for Your Event</a>
                        <a href="media.php" class="btn btn-outline">Watch &amp; Listen</a>
                    </div>
                    <div class="hero-meta">
                        <span>Trusted at 1,600+ events across Chicagoland and beyond.</span>
                        <span>Clubs · Weddings · Corporate · Private Events</span>
                    </div>
                </div>
                <div class="hero-card">
                    <div class="hero-card-header">
                        <h2>Up Next...</h2>
                        <p class="hero-card-caption">Catch Nick live at these upcoming shows.</p>
                    </div>
                    <div id="nextShows" class="next-shows">
                        <!-- Populated by events.js -->
                    </div>
                    <a href="shows.php" class="hero-card-link">View full show calendar</a>
                </div>
            </div>
        </section>

        <!-- Why Nick Section -->
        <section class="section section-why">
            <div class="container grid-2">
                <div>
                    <h2>Why hosts book Nick again and again.</h2>
                    <p>
						When you’re responsible for the vibe of the room, you don’t gamble on live music.
					</p>
					<p>
						Nick delivers full-band energy without the footprint, the volume chaos, or the logistical circus.
					</p>
					<p>
						He shows up early. Sets up fast. Reads the room in real time.
					</p>
					<p>
						And keeps the night exactly where it needs to be.                    
					</p>
                    <ul class="feature-list">
                        <li><span>Full‑band sound from a single performer — tighter, cleaner, and easier to work with.</span></li>
                        <li><span>State‑of‑the‑art Bose sound system tailored to your room and guest count.</span></li>
                        <li><span>Smart, surprising song choices that keep everyone — from 20‑somethings to grandparents — engaged.</span></li>
                        <li><span>Seamless background music between sets — no separate DJ required.</span></li>
                    </ul>
                </div>
                <div class="pillars">
                    <div class="pillar">
                        <h3>For Corporate Events</h3>
                        <p>Whether it’s a holiday party, networking event, or client appreciation night — the music needs to elevate the room without overwhelming it.
							Nick adapts in real time — from polished background energy during cocktails to full-room momentum when it’s time to celebrate.</p>
                    </div>
                    <div class="pillar">
                        <h3>For Private Events</h3>
                        <p>From backyard to ballroom, Nick turns any space into a night your guests talk about for months.</p>
                    </div>
                    <div class="pillar">
                        <h3>For Venues</h3>
                        <p>Your revenue depends on how long people stay.Nick keeps them there — engaged, ordering, and coming back.</p>
                    </div>
                    <div class="pillar">
                        <h3>For Weddings</h3>
                        <p>Your once‑in‑a‑lifetime day deserves more than generic background noise. Get a charismatic frontman who reads the room in real time.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Social Proof Strip -->
        <section class="section section-strip">
            <div class="container strip-inner">
                <p>Trusted by hundreds of couples, venues, and event planners — over 100 weddings and 300+ private events.</p>
            </div>
        </section>

        <!-- Highlight Media -->
        <section class="section section-highlight">
            <div class="container grid-2">
                <div>
                    <h2>Hear the difference.</h2>
                    <p>See and hear Nick in action with a curated selection of live and studio performances.</p>
                    <a href="media.php" class="btn btn-primary">Explore videos &amp; original music</a>
                </div>
                <div class="video-embed-ratio">
                    <iframe src="https://www.youtube.com/embed/xkAh-Np-aIE" 
                            title="Nick Sanzeri Live" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                            referrerpolicy="strict-origin-when-cross-origin" 
                            allowfullscreen></iframe>
                </div>
            </div>
        </section>

        <!-- Testimonial Callout -->
        <section class="section section-quote">
            <div class="container">
                <blockquote>
                    “Nick is simply outstanding — as a singer he’s superb, as an entertainer he’s electric, and as a bass player he’s dangerous. Our wedding wouldn’t have been the same without him.”
                </blockquote>
                <p class="quote-attrib">— Mark R., groom</p>
                <a href="testimonials.php" class="btn btn-outline">Read more rave reviews</a>
            </div>
        </section>

        <!-- CTA -->
        <section class="section section-cta">
            <div class="container cta-inner">
                <div>
                    <h2>Ready to make your event unforgettable?</h2>
                    <p>Share a few details about your date, vibe, and vision — Nick will follow up personally.</p>
                </div>
                <div class="cta-actions">
                    <a href="booking.php" class="btn btn-primary">Start a Booking Inquiry</a>
                    <a href="shows.php" class="text-link">Or come see a show first</a>
                </div>
            </div>
        </section>
        <section class="section section-cta">
            <div class="container cta-inner">
                <div>
	                If you had a great time and feel inspired to share it, I’d be truly grateful for a quick Google review. Your words help others feel confident booking live music they’ll love.</div>
   	            	<div>
   	            	<a href="https://g.page/r/CU3CItu-sH55EBM/review" class="btn btn-primary">Leave a Review</a>
                </div>
            </div>
        </section>
		         
    </main>

	<?php 
	include __DIR__ . '/includes/footer.php';
	?>
</body>
</html>

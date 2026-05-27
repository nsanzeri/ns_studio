<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nick Sanzeri | One Man · Full‑Band Experience</title>
    <meta name="description" content="Nick Sanzeri is a Chicagoland singing bassist and live entertainer for weddings, private parties, corporate events, restaurants, clubs, casinos, festivals, and community events.">
    <script id="mcjs">!function(c,h,i,m,p){m=c.createElement(h),p=c.getElementsByTagName(h)[0],m.async=1,m.src=i,p.parentNode.insertBefore(m,p)}(document,"script","https://chimpstatic.com/mcjs-connected/js/users/758e0b12aa6b2c9e1c489b5f1/19d51ba4bab265cb87fd8e398.js");</script>
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
    <?php
    require_once __DIR__ . '/includes/schema-global.php';
    ns_schema_output('home');
    ?>
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

        <!-- Live Requests -->
        <section class="section section-live-requests">
            <div class="container live-requests-inner">
                <div>
                    <p class="eyebrow">At the show</p>
                    <h2>Request songs, send tips, and stay in the loop.</h2>
                    <p>
                        When Nick opens requests at a live gig, this is the easy way to jump in without waving from across the room.
                        The same link also works between shows for tips, reviews, and future song ideas.
                    </p>
                    <div class="hero-actions">
                        <a href="requests.php" class="btn btn-primary">Open Live Requests</a>
                        <a href="shows.php" class="btn btn-outline">See Upcoming Dates</a>
                    </div>
                </div>
                <a class="live-requests-qr" href="requests.php" aria-label="Open live requests">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=12&data=<?= urlencode(((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'nicksanzeri.com') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/requests.php') ?>" alt="QR code for Nick Sanzeri live requests">
                    <span>Scan at the gig</span>
                </a>
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
<!--                 <div class="video-embed-ratio">
                    <iframe src="https://www.youtube.com/embed/xkAh-Np-aIE" 
                            title="Nick Sanzeri Live" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                            referrerpolicy="strict-origin-when-cross-origin" 
                            allowfullscreen></iframe>
                </div> -->
                <div class="youtube-short-embed">
				  <iframe
				    src="https://www.youtube.com/embed/dsU-GBFgwcw"
				    title="YouTube Short"
				    frameborder="0"
				    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				    allowfullscreen>
				  </iframe>
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
                    <a href="faq.php" class="text-link">FAQs</a>
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

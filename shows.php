<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shows | Nick Sanzeri</title>
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
                <p class="eyebrow">Live Shows</p>
                <h1>Upcoming Performances</h1>
                <p class="page-intro">
                    Come see the one‑man full‑band experience live. Check out where Nick is playing next and plan your night out.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container">
				<!-- <div id="nextShows" class="next-shows"> -->
                <div id="eventsList" class="events-list">
                    <!-- Populated by events.js -->
                </div>
                <div class="external-calendar">
                    <a href="https://calendar.google.com/calendar/u/0?cid=cGpqZmRnZWx2ZGp0dXZycjg5dHVuM251N2tAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ" 
                       target="_blank" 
                       class="text-link">
                        View full Google Calendar
                    </a>
                </div>
            </div>
        </section>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>
</body>
</html>

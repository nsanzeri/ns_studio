<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media | Nick Sanzeri</title>
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

</head>
<body id="top">
<?php 
include __DIR__ . '/includes/header.php';
?>


    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Media</p>
                <h1>Watch &amp; Listen</h1>
                <p class="page-intro">
                    A mix of live clips, studio performances, and original music. New content is added regularly.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <h2>Featured Videos</h2>
                <div class="video-grid">
                    <div class="video-embed-ratio">
                        <iframe src="https://www.youtube.com/embed/xkAh-Np-aIE" title="Nick Live 1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <div class="video-embed-ratio">
                        <iframe src="https://www.youtube.com/embed/4BOGCTB05Jk" title="Nick Live 2" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <div class="video-embed-ratio">
                        <iframe src="https://www.youtube.com/embed/fIHKsrJ3jnM" title="Nick Live 3" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <div class="video-embed-ratio">
                        <iframe src="https://www.youtube.com/embed/w70uupRtt6A" title="Nick Live 4" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </div>

                <div class="random-video">
                    <h2>Not sure where to start?</h2>
                    <p>Let fate pick a performance. Click below to open a random video from Nick’s playlist in a new tab.</p>
                    <button class="btn btn-outline" id="randomVideoBtn">Show Me a Random Video</button>
                    <p id="randomLoading" class="random-loading">Loading videos…</p>
                </div>
            </div>
        </section>

        <section class="section section-dark">
            <div class="container">
                <h2>Original Music</h2>
                <p>Stream Nick’s original work on your favorite platform.</p>
                <div class="platform-buttons">
                    <a href="https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ?si=Qm9EUfTOR3-jG07NvUgWGQ" target="_blank" class="btn btn-primary">
                        <i class="fab fa-spotify"></i> Listen on Spotify
                    </a>
                    <a href="https://www.youtube.com/channel/UCYPtumRqvkIY8fWJrpHz5SA" target="_blank" class="btn btn-outline">
                        <i class="fab fa-youtube"></i> Listen on YouTube
                    </a>
                    <a href="https://music.apple.com/us/artist/nick-sanzeri/1444442766" target="_blank" class="btn btn-outline">
                        <i class="fab fa-apple"></i> Listen on Apple Music
                    </a>
                </div>
                <div class="spotify-embeds">
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/playlist/3NYiRAYVBNsEj35bOAWL8n?utm_source=generator" width="100%" height="352" frameborder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/playlist/37i9dQZF1DZ06evO3RJFk1?utm_source=generator" width="100%" height="352" frameborder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/playlist/6nfOhOHQCFEV22VVLXHtcF?utm_source=generator" width="100%" height="352" frameborder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>
            </div>
        </section>
    </main>
	<?php 
	include __DIR__ . '/includes/footer.php';
	?>
</body>
</html>

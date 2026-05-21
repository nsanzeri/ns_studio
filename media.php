<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media | Nick Sanzeri</title>
    <meta name="description" content="Watch and listen to performance videos, live clips, and music from Nick Sanzeri, Chicagoland singing bassist and live entertainer.">
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
    <style>
        .random-picker-card {
            margin-top: 3rem;
            padding: 2rem;
            border-radius: 24px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 18px 45px rgba(0,0,0,0.18);
            text-align: center;
        }
        .random-picker-card .picker-copy {
            max-width: 760px;
            margin: 0 auto 1.5rem;
        }
        .random-picker-buttons {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            max-width: 760px;
            margin: 1.5rem auto;
        }
        .random-picker-option {
            padding: 1.25rem;
            border-radius: 18px;
            background: rgba(0,0,0,0.18);
        }
        .random-picker-option .btn {
            width: 100%;
            justify-content: center;
        }
        .random-picker-option .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .picker-counter {
            font-size: 0.85rem;
            opacity: 0.75;
            margin-top: 0.65rem;
        }
        .picker-status {
            min-height: 1.4rem;
            font-style: italic;
            opacity: 0.8;
            margin-top: 1rem;
        }
        .picker-disclaimer {
            font-size: 0.85rem;
            opacity: 0.65;
            margin-top: 1rem;
        }
        @media (max-width: 700px) {
            .random-picker-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
    require_once __DIR__ . '/includes/schema-global.php';
    ns_schema_output('media');
    ?>
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

                <div class="random-picker-card" id="randomVideoPicker">
                    <p class="eyebrow">Randomizer</p>
                    <h2>Not sure where to start?</h2>
                    <div class="picker-copy">
                        <p>Let fate pick the next clip. Choose a random live performance or jump into one of Nick’s original songs on YouTube.</p>
                    </div>

                    <div class="random-picker-buttons">
                        <div class="random-picker-option">
                            <button class="btn btn-primary" id="playlistBtn" type="button" disabled>
                                <i class="fab fa-youtube"></i> Random Live Video
                            </button>
                            <div class="picker-counter" id="playlistCounter">Clicks: 0</div>
                        </div>

                        <div class="random-picker-option">
                            <button class="btn btn-outline" id="channelBtn" type="button" disabled>
                                <i class="fas fa-random"></i> Random Original Song
                            </button>
                            <div class="picker-counter" id="channelCounter">Clicks: 0</div>
                        </div>
                    </div>

                    <div class="picker-status" id="status">Loading video data...</div>
                    <div class="picker-disclaimer">Not responsible for addiction or spontaneous bursts of joy and laughter.</div>
                </div>
            </div>
        </section>

        <section class="section section-dark">
            <div class="container">
                <h2>Original Music</h2>
                <p>Stream Nick’s original work on your favorite platform.</p>
                <div class="platform-buttons">
                    <a href="https://open.spotify.com/artist/6xRrH2IVMxSkMihQUXcYdJ?si=Qm9EUfTOR3-jG07NvUgWGQ" target="_blank" rel="noopener" class="btn btn-primary">
                        <i class="fab fa-spotify"></i> Listen on Spotify
                    </a>
                    <a href="https://www.youtube.com/channel/UCYPtumRqvkIY8fWJrpHz5SA" target="_blank" rel="noopener" class="btn btn-outline">
                        <i class="fab fa-youtube"></i> Listen on YouTube
                    </a>
                    <a href="https://music.apple.com/us/artist/nick-sanzeri/1444442766" target="_blank" rel="noopener" class="btn btn-outline">
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

    <script>
        // ===== RANDOM VIDEO PICKER CONFIGURATION ===== //
        const API_KEY = 'AIzaSyAAn7Lr5DEU6hd53JGEfEi9cQ2XQ_peAFY';
        const PLAYLIST_ID = 'PLEyl2_bhLQwb8k_oHIGI2w60AXC2n8ypg';
        const CHANNEL_ID = 'UCYPtumRqvkIY8fWJrpHz5SA';
        // ============================================ //

        let playlistVideos = [];
        let channelVideos = [];
        let playlistClicks = parseInt(localStorage.getItem('playlistButtonClicks') || '0', 10);
        let channelClicks = parseInt(localStorage.getItem('channelButtonClicks') || '0', 10);
        let playlistLoaded = false;
        let channelLoaded = false;

        const playlistBtn = document.getElementById('playlistBtn');
        const channelBtn = document.getElementById('channelBtn');
        const playlistCounter = document.getElementById('playlistCounter');
        const channelCounter = document.getElementById('channelCounter');
        const statusEl = document.getElementById('status');

        function setStatus(message) {
            if (statusEl) statusEl.textContent = message;
        }

        function updateCounters() {
            if (playlistCounter) playlistCounter.textContent = `Clicks: ${playlistClicks}`;
            if (channelCounter) channelCounter.textContent = `Clicks: ${channelClicks}`;
        }

        function updateStatus() {
            if (playlistLoaded && channelLoaded) {
                setStatus(`Loaded ${playlistVideos.length} live videos and ${channelVideos.length} original-song videos.`);
            } else if (playlistLoaded) {
                setStatus(`Loaded ${playlistVideos.length} live videos. Loading original-song videos...`);
            } else if (channelLoaded) {
                setStatus(`Loaded ${channelVideos.length} original-song videos. Loading live videos...`);
            }
        }

        async function fetchPlaylistVideos() {
            setStatus('Loading live videos...');
            let nextPageToken = '';
            playlistVideos = [];

            try {
                do {
                    const response = await fetch(
                        `https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=${PLAYLIST_ID}&key=${API_KEY}&pageToken=${nextPageToken}`
                    );
                    const data = await response.json();

                    if (!response.ok || data.error) {
                        throw new Error(data.error?.message || 'Playlist request failed.');
                    }

                    if (data.items) {
                        playlistVideos.push(...data.items.filter(item => item.snippet?.resourceId?.kind === 'youtube#video'));
                    }
                    nextPageToken = data.nextPageToken || '';
                } while (nextPageToken);

                playlistLoaded = true;
                if (playlistBtn && playlistVideos.length) playlistBtn.disabled = false;
                updateStatus();
            } catch (error) {
                console.error('Error fetching playlist:', error);
                setStatus('Error loading live videos. Please try again later.');
            }
        }

        async function fetchChannelVideos() {
            setStatus('Loading original-song videos...');
            let nextPageToken = '';
            channelVideos = [];

            try {
                const channelResponse = await fetch(
                    `https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=${CHANNEL_ID}&key=${API_KEY}`
                );
                const channelData = await channelResponse.json();

                if (!channelResponse.ok || channelData.error || !channelData.items?.length) {
                    throw new Error(channelData.error?.message || 'Channel request failed.');
                }

                const uploadsPlaylistId = channelData.items[0].contentDetails.relatedPlaylists.uploads;

                do {
                    const response = await fetch(
                        `https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=${uploadsPlaylistId}&key=${API_KEY}&pageToken=${nextPageToken}`
                    );
                    const data = await response.json();

                    if (!response.ok || data.error) {
                        throw new Error(data.error?.message || 'Channel uploads request failed.');
                    }

                    if (data.items) {
                        channelVideos.push(...data.items.filter(item => item.snippet?.resourceId?.kind === 'youtube#video'));
                    }
                    nextPageToken = data.nextPageToken || '';
                } while (nextPageToken);

                channelLoaded = true;
                if (channelBtn && channelVideos.length) channelBtn.disabled = false;
                updateStatus();
            } catch (error) {
                console.error('Error fetching channel:', error);
                setStatus('Error loading original-song videos. Please try again later.');
            }
        }

        function openRandomVideo(videos, type) {
            if (!videos.length) {
                setStatus(`No ${type} videos are loaded yet. Please try again in a moment.`);
                return;
            }

            const randomVideo = videos[Math.floor(Math.random() * videos.length)];
            const videoId = randomVideo.snippet?.resourceId?.videoId;

            if (!videoId) {
                setStatus('Something went wrong finding that video. Please try again.');
                return;
            }

            window.open(`https://www.youtube.com/watch?v=${videoId}`, '_blank', 'noopener');
        }

        updateCounters();

        window.addEventListener('load', () => {
            fetchPlaylistVideos();
            fetchChannelVideos();
        });

        if (playlistBtn) {
            playlistBtn.addEventListener('click', function() {
                playlistClicks++;
                localStorage.setItem('playlistButtonClicks', playlistClicks);
                updateCounters();
                openRandomVideo(playlistVideos, 'live');
            });
        }

        if (channelBtn) {
            channelBtn.addEventListener('click', function() {
                channelClicks++;
                localStorage.setItem('channelButtonClicks', channelClicks);
                updateCounters();
                openRandomVideo(channelVideos, 'original-song');
            });
        }

        setInterval(() => {
            if (playlistBtn) playlistBtn.disabled = true;
            if (channelBtn) channelBtn.disabled = true;
            playlistLoaded = false;
            channelLoaded = false;
            fetchPlaylistVideos();
            fetchChannelVideos();
        }, 3600000);
    </script>
</body>
</html>

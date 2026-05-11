<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Video Picker | Nick Sanzeri</title>
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
    <style>
        .random-picker-wrap {
            max-width: 880px;
            margin: 0 auto;
        }
        .random-picker-card {
            padding: 2rem;
            border-radius: 24px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 18px 45px rgba(0,0,0,0.18);
            text-align: center;
        }
        .random-picker-card .picker-copy {
            max-width: 720px;
            margin: 0 auto 1.5rem;
            text-align: left;
        }
        .random-picker-card .picker-copy p {
            margin-bottom: 1rem;
        }
        .random-picker-buttons {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
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
</head>
<body id="top">
<?php include __DIR__ . '/includes/header.php'; ?>

    <main>
        <section class="page-hero">
            <div class="container">
                <p class="eyebrow">Press Me</p>
                <h1>Nick Sanzeri’s Random Video Picker</h1>
                <p class="page-intro">Discover random live footage or one of my original songs on YouTube.</p>
            </div>
        </section>

        <section class="section">
            <div class="container random-picker-wrap">
                <div class="random-picker-card" id="randomVideoPicker">
                    <p class="eyebrow">Choose Your Adventure</p>
                    <h2>Two buttons. Both choices are me.</h2>

                    <div class="picker-copy">
                        <p>These buttons are like a choose-your-own-adventure book where both choices are me. Lucky you.</p>
                        <p>The first button picks a live video — if you're lucky, you won't hear someone shouting “Freebird.”</p>
                        <p>The second button plays an original song — tracks I've written, performed all the instruments and recorded myself.</p>
                    </div>

                    <div class="random-picker-buttons">
                        <div class="random-picker-option">
                            <button class="btn btn-primary" id="playlistBtn" type="button" disabled>
                                <i class="fab fa-youtube"></i> Random Live Video
                            </button>
                        </div>

                        <div class="random-picker-option">
                            <button class="btn btn-outline" id="channelBtn" type="button" disabled>
                                <i class="fas fa-random"></i> Random Original Song
                            </button>
                        </div>
                    </div>

                    <div class="picker-status" id="status">Loading video data...</div>
                    <div class="picker-disclaimer">Not responsible for addiction or spontaneous bursts of joy and laughter.</div>
                </div>
            </div>
        </section>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>

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

const YT_API_KEY = 'AIzaSyAAn7Lr5DEU6hd53JGEfEi9cQ2XQ_peAFY';
const PLAYLIST_ID = 'PLEyl2_bhLQwb8k_oHIGI2w60AXC2n8ypg';
let playlistVideos = [];

async function fetchPlaylistVideos() {
    const loadingEl = document.getElementById('randomLoading');
    const btn = document.getElementById('randomVideoBtn');
    if (!loadingEl || !btn) return;

    loadingEl.style.display = 'block';
    btn.disabled = true;

    let nextPageToken = '';

    try {
        do {
            const resp = await fetch(
                `https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=${PLAYLIST_ID}&key=${YT_API_KEY}&pageToken=${nextPageToken}`
            );
            const data = await resp.json();
            const items = (data.items || []).filter(item => item.snippet && item.snippet.resourceId.kind === 'youtube#video');
            playlistVideos.push(...items);
            nextPageToken = data.nextPageToken || '';
        } while (nextPageToken);
    } catch (err) {
        console.error('Error fetching playlist:', err);
        alert('Error loading videos. Please try again later.');
    } finally {
        loadingEl.style.display = 'none';
        btn.disabled = false;
    }
}

function openRandomVideo() {
    if (!playlistVideos.length) {
        alert('Videos are still loading. Please try again in a moment.');
        return;
    }
    const random = playlistVideos[Math.floor(Math.random() * playlistVideos.length)];
    const videoId = random.snippet.resourceId.videoId;
    const url = `https://www.youtube.com/watch?v=${videoId}`;
    window.open(url, '_blank', 'width=960,height=540');
}

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('randomVideoBtn');
    if (btn) {
        btn.addEventListener('click', openRandomVideo);
    }
    fetchPlaylistVideos();
    setInterval(fetchPlaylistVideos, 3600000);
});

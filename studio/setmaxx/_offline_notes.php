<script>
window.saveSetmaxxNotesOffline = async function(noteUrls, onProgress) {
  if (!('serviceWorker' in navigator) || !window.caches) {
    throw new Error('Offline saving requires a supported browser and a secure connection.');
  }
  const registration = await navigator.serviceWorker.register(<?= json_encode(base_url('/sw.js')) ?>);
  const worker = registration.installing || registration.waiting || registration.active;
  if (worker && worker.state !== 'activated') {
    await new Promise(function(resolve, reject) {
      const timer = setTimeout(function() { reject(new Error('Offline setup timed out. Please try again.')); }, 15000);
      worker.addEventListener('statechange', function() {
        if (worker.state === 'activated' || worker.state === 'redundant') {
          clearTimeout(timer);
          if (worker.state === 'activated') resolve();
          else reject(new Error('Offline setup failed. Please reload and try again.'));
        }
      });
    });
  }
  const entryUrl = new URL(<?= json_encode(base_url('/setmaxx/song_notes.php?mode=lyrics')) ?>, location.href);
  const urls = new Set([entryUrl.href]);
  noteUrls.forEach(function(rawUrl) {
    const url = new URL(rawUrl, location.href);
    if (url.origin !== entryUrl.origin || url.pathname !== entryUrl.pathname) return;
    url.hash = '';
    url.searchParams.delete('mode');
    url.searchParams.sort();
    urls.add(url.href);
    url.searchParams.set('mode', 'lyrics');
    url.searchParams.sort();
    urls.add(url.href);
  });
  const cache = await caches.open('setmaxx-performance-notes-v1');
  let cached = 0;
  let failed = 0;
  for (const url of urls) {
    const controller = new AbortController();
    const timer = setTimeout(function() { controller.abort(); }, 20000);
    try {
      const response = await fetch(url, { credentials: 'same-origin', cache: 'reload', signal: controller.signal });
      if (!response.ok || response.redirected || response.headers.get('X-Setmaxx-Notes-User') !== <?= json_encode((string)$userId) ?>) {
        throw new Error('Could not download notes.');
      }
      await cache.put(url, response);
      cached++;
    } catch (error) {
      failed++;
    } finally {
      clearTimeout(timer);
    }
    if (onProgress) onProgress(cached + failed, urls.size);
  }
  return { cached: cached, failed: failed };
};
</script>

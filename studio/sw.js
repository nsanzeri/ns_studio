self.addEventListener('install', function(event) {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(self.clients.claim());
});

const SETMAXX_NOTES_CACHE = 'setmaxx-performance-notes-v1';

function isSetmaxxNotesRequest(request) {
  if (request.method !== 'GET') return false;
  const url = new URL(request.url);
  return url.origin === self.location.origin && url.pathname.endsWith('/setmaxx/song_notes.php');
}

self.addEventListener('message', function(event) {
  const data = event.data || {};
  if (data.type !== 'SETMAXX_CACHE_PERFORMANCE_NOTES') return;

  const replyPort = event.ports && event.ports[0];
  const urls = Array.isArray(data.urls) ? data.urls : [];

  event.waitUntil((async function() {
    let cached = 0;
    let failed = 0;
    const cache = await caches.open(SETMAXX_NOTES_CACHE);

    for (const rawUrl of urls) {
      try {
        const url = new URL(String(rawUrl), self.location.origin);
        if (url.origin !== self.location.origin || !url.pathname.endsWith('/setmaxx/song_notes.php')) {
          failed++;
          continue;
        }

        const response = await fetch(url.toString(), {
          credentials: 'same-origin',
          cache: 'reload'
        });

        if (!response.ok) {
          failed++;
          continue;
        }

        await cache.put(url.toString(), response.clone());
        cached++;
      } catch (error) {
        failed++;
      }
    }

    if (replyPort) replyPort.postMessage({ success: true, cached, failed });
  })().catch(function(error) {
    if (replyPort) {
      replyPort.postMessage({
        success: false,
        error: error && error.message ? error.message : 'Could not cache performance notes.'
      });
    }
  }));
});

self.addEventListener('fetch', function(event) {
  if (!isSetmaxxNotesRequest(event.request)) return;

  event.respondWith((async function() {
    const cache = await caches.open(SETMAXX_NOTES_CACHE);

    try {
      const response = await fetch(event.request);
      if (response && response.ok) {
        await cache.put(event.request, response.clone());
      }
      return response;
    } catch (error) {
      const cached = await cache.match(event.request);
      if (cached) return cached;
      throw error;
    }
  })());
});

self.addEventListener('push', function(event) {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (error) {
      data = { title: 'Ready Set Shows', body: event.data.text() };
    }
  }

  const title = data.title || 'Ready Set Shows';
  const fallbackUrl = new URL('setmaxx/requests.php', self.registration.scope).toString();
  const options = {
    body: data.body || 'New activity',
    tag: data.tag || 'ready-set-shows',
    renotify: true,
    actions: [
      { action: 'open', title: 'Open requests' }
    ],
    data: { url: data.url || fallbackUrl },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const fallbackUrl = new URL('setmaxx/requests.php', self.registration.scope).toString();
  const url = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : fallbackUrl;

  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
    for (const client of clientList) {
      if ('focus' in client) {
        client.navigate(url);
        return client.focus();
      }
    }
    return clients.openWindow(url);
  }));
});

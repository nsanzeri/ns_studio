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

const CACHE_STATIQUE = 'axiobad-statique-v1';

const RESSOURCES_STATIQUES = [
  '/favicon.svg',
  '/logo.svg',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
  '/offline.html',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIQUE).then((cache) => cache.addAll(RESSOURCES_STATIQUES))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((noms) => Promise.all(
      noms.filter((nom) => nom !== CACHE_STATIQUE).map((nom) => caches.delete(nom))
    ))
  );
  self.clients.claim();
});

// Assets statiques : cache d'abord (rapides, changent rarement).
// Tout le reste (pages Symfony, formulaires, API) : réseau d'abord, jamais mis en cache — les
// données (présences, adhésions, cordage...) doivent toujours être à jour et les jetons CSRF ne
// doivent jamais être servis depuis un cache.
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  const estStatique = RESSOURCES_STATIQUES.includes(url.pathname) || url.pathname.startsWith('/icons/');

  if (estStatique) {
    event.respondWith(
      caches.match(event.request).then((reponse) => reponse || fetch(event.request))
    );
    return;
  }

  if ('navigate' === event.request.mode) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match('/offline.html'))
    );
  }
});

// Notifications push (Web Push) : le payload JSON envoyé par le serveur contient
// { title, body, url }.
self.addEventListener('push', (event) => {
  let donnees = { title: 'Axiobad', body: '', url: '/' };
  if (event.data) {
    try {
      donnees = { ...donnees, ...event.data.json() };
    } catch (e) {
      donnees.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(donnees.title, {
      body: donnees.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: donnees.url || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsList) => {
      for (const client of clientsList) {
        const clientUrl = new URL(client.url);
        if (clientUrl.pathname === url && 'focus' in client) {
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(url);
      }
    })
  );
});

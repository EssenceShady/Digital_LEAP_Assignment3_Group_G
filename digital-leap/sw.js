/* Digital LEAP service worker.
 *
 * Scope is the application root, so it sees index.php?r=… pages and the
 * public/assets/ files beneath them. Strategy:
 *   - app shell (CSS, JS, logo, icons, offline page) precached at install
 *   - navigations: network-first, falling back to the last copy seen and
 *     then to the offline page, so record pages stay trustworthy when online
 *   - static assets: cache-first with runtime versioning
 *   - anything under ?r=api/ is never cached (sync must reach the server)
 * Versioned caches; old versions are deleted on activate.
 */
'use strict';

const VERSION = 'v13';
const SHELL_CACHE = 'leap-shell-' + VERSION;
const RUNTIME_CACHE = 'leap-runtime-' + VERSION;
const CACHE_PREFIX = 'leap-';

const SCOPE = self.registration.scope;
const OFFLINE_URL = new URL('public/offline.html', SCOPE).href;

const SHELL = [
  'public/offline.html',
  'public/assets/css/offline.css',
  'public/assets/css/app.css',
  'public/assets/js/app.js',
  'public/assets/js/boot.js',
  'public/assets/js/diva-offline.js',
  'public/assets/data/diva-index.json',
  'public/assets/img/icon-192.png',
  'public/assets/img/icon-512.png',
  'public/assets/img/globe-flag-sphere.webp',
  'public/assets/img/globe-flag-sphere.png',
  'public/assets/img/leap-mark.png',
  'public/assets/img/leap-badge.png',
  'public/assets/img/leap-wordmark-ink.png',
  'public/assets/img/leap-wordmark-ivory.png',
  'public/assets/img/leap-globe.png',
  'public/assets/img/limkokwing-logo.png',
  'public/assets/img/diva-mark-sm.png',
  'public/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL_CACHE);
    // Individually so one missing file cannot abort the whole install.
    await Promise.all(SHELL.map(async (path) => {
      try {
        await cache.add(new Request(new URL(path, SCOPE).href, { cache: 'reload' }));
      } catch (error) {
        /* keep going */
      }
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys
      .filter((key) => key.startsWith(CACHE_PREFIX) && key !== SHELL_CACHE && key !== RUNTIME_CACHE)
      .map((key) => caches.delete(key)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'leap-purge') {
    // Sign-out: drop every cached page so the next person sees nothing.
    event.waitUntil(caches.delete(RUNTIME_CACHE));
  }
  if (data.type === 'leap-refresh') {
    event.waitUntil((async () => {
      const cache = await caches.open(SHELL_CACHE);
      await Promise.all(SHELL.map((path) => cache.add(new URL(path, SCOPE).href).catch(() => {})));
    })());
  }
});

function isApi(url) {
  return url.search.includes('r=api/') || url.pathname.endsWith('/api.php');
}

async function networkFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 4500);
    const response = await fetch(request, { signal: controller.signal });
    clearTimeout(timer);
    if (response && response.ok && response.type === 'basic') {
      cache.put(request, response.clone()).catch(() => {});
    }
    return response;
  } catch (error) {
    // Exact URL only: index.php?r=… pages differ solely by the query string,
    // so an ignoreSearch match would replay the wrong record.
    const cached = await cache.match(request);
    if (cached) { return cached; }
    if (request.mode === 'navigate') {
      const shell = await caches.open(SHELL_CACHE);
      const offline = await shell.match(OFFLINE_URL);
      if (offline) { return offline; }
    }
    return new Response('Offline and not cached.', {
      status: 503,
      statusText: 'Offline',
      headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
  }
}

async function cacheFirst(request) {
  const shell = await caches.open(SHELL_CACHE);
  // Asset URLs carry ?v=<filemtime>, so an exact hit means this build has
  // already been seen. A changed file gets a new URL and is fetched once; the
  // unversioned precache is only the fallback when the network is away.
  const exact = await shell.match(request);
  if (exact) { return exact; }
  const runtime = await caches.open(RUNTIME_CACHE);
  const seen = await runtime.match(request);
  if (seen) { return seen; }
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      runtime.put(request, response.clone()).catch(() => {});
    }
    return response;
  } catch (error) {
    const precached = await shell.match(request, { ignoreSearch: true });
    if (precached) { return precached; }
    return new Response('', { status: 504, statusText: 'Offline' });
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') { return; }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) { return; }
  if (!url.href.startsWith(SCOPE)) { return; }
  if (isApi(url)) { return; }

  if (request.mode === 'navigate') {
    // A sign-out ends on the sign-in page. Drop cached pages first so the
    // next person at this device never sees the previous session's records.
    if (url.search.includes('r=login')) {
      event.respondWith((async () => {
        await caches.delete(RUNTIME_CACHE);
        return networkFirst(request);
      })());
      return;
    }
    event.respondWith(networkFirst(request));
    return;
  }
  if (url.pathname.includes('/public/assets/')) {
    event.respondWith(cacheFirst(request));
    return;
  }
  // The offline page is replayed at whatever route failed, so its stylesheet
  // resolves to /assets/css/offline.css rather than /public/assets/…. Serve it
  // from the shell cache wherever it was asked for.
  if (url.pathname.endsWith('/assets/css/offline.css')) {
    event.respondWith((async () => {
      const shell = await caches.open(SHELL_CACHE);
      const cached = await shell.match(new URL('public/assets/css/offline.css', SCOPE).href);
      return cached || fetch(request);
    })());
    return;
  }
  event.respondWith(networkFirst(request));
});

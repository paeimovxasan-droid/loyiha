/*
 * Tortinmang Admin PWA worker.
 * Only install assets are cached: private/admin HTML and API responses stay network-only.
 */
const CACHE_NAME = 'tortinmang-admin-assets-v1';
const APP_ASSETS = [
  './manifest.webmanifest',
  './icons/admin-180.png',
  './icons/admin-192.png',
  './icons/admin-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key.startsWith('tortinmang-admin-') && key !== CACHE_NAME)
        .map(key => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Never cache authenticated documents or API responses.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => new Response(
        '<!doctype html><html lang="uz"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#020d07"><title>Offline</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#020d07;color:#e8fff4;font-family:system-ui;text-align:center;padding:24px"><main><div style="font-size:42px">📡</div><h1>Internet aloqasi yo‘q</h1><p>Admin ma’lumotlari xavfsizlik uchun offline saqlanmaydi. Aloqani tekshirib, qayta urinib ko‘ring.</p></main></body></html>',
        {headers: {'Content-Type': 'text/html; charset=utf-8'}}
      ))
    );
    return;
  }

  if (url.pathname.includes('/admin/icons/') || url.pathname.endsWith('/admin/manifest.webmanifest')) {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(response => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
        return response;
      }))
    );
  }
});

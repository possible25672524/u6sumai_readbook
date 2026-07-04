// AI Study Assistant — Service Worker
// Strategy: Cache-first for static assets, Network-first for API, offline fallback
// Generated for vite-plugin-pwa (workbox injectManifest mode)

import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching'
import { registerRoute, setDefaultHandler } from 'workbox-routing'
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'
import { CacheableResponsePlugin } from 'workbox-cacheable-response'

const CACHE_VERSION = 'v1'
const OFFLINE_URL = '/offline.html'

// ── Precache all assets listed by vite-plugin-pwa ──────────────────────
cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST || [])

// ── Static assets: Cache-first (JS, CSS, fonts, images) ────────────────
registerRoute(
  ({ request }) =>
    ['style', 'script', 'worker'].includes(request.destination),
  new CacheFirst({
    cacheName: `${CACHE_VERSION}-static`,
    plugins: [
      new CacheableResponsePlugin({ statuses: [0, 200] }),
      new ExpirationPlugin({ maxEntries: 60, maxAgeSeconds: 7 * 24 * 60 * 60 }), // 7 days
    ],
  }),
)

// ── Images: Stale-while-revalidate ─────────────────────────────────────
registerRoute(
  ({ request }) => request.destination === 'image',
  new StaleWhileRevalidate({
    cacheName: `${CACHE_VERSION}-images`,
    plugins: [
      new CacheableResponsePlugin({ statuses: [0, 200] }),
      new ExpirationPlugin({ maxEntries: 50, maxAgeSeconds: 30 * 24 * 60 * 60 }), // 30 days
    ],
  }),
)

// ── API calls: Network-first with cache fallback ────────────────────────
// Only cache safe GET endpoints (not auth, not uploads)
const CACHEABLE_API = [
  /\/api\/documents(\?|$)/,
  /\/api\/flashcard-sets/,
  /\/api\/quizzes(\?|$)/,
  /\/api\/analytics/,
  /\/api\/study-plan/,
]

registerRoute(
  ({ url, request }) =>
    url.pathname.startsWith('/api') &&
    request.method === 'GET' &&
    CACHEABLE_API.some((re) => re.test(url.pathname + url.search)),
  new NetworkFirst({
    cacheName: `${CACHE_VERSION}-api`,
    networkTimeoutSeconds: 10,
    plugins: [
      new CacheableResponsePlugin({ statuses: [0, 200] }),
      new ExpirationPlugin({ maxEntries: 100, maxAgeSeconds: 60 * 60 }), // 1 hour
    ],
  }),
)

// ── Offline fallback for navigation requests ────────────────────────────
setDefaultHandler(
  new NetworkFirst({
    cacheName: `${CACHE_VERSION}-pages`,
    plugins: [new CacheableResponsePlugin({ statuses: [0, 200] })],
  }),
)

// Return offline.html for failed navigation
self.addEventListener('fetch', (event) => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() =>
        caches.match(OFFLINE_URL) || caches.match('/'),
      ),
    )
  }
})

// ── Background sync for offline actions ────────────────────────────────
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim())
})

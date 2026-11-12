/**
 * Edifies School Staff Attendance Management System
 * Offline Service Worker for Multi-Device Kiosk PWA
 * Version: edifies-kiosk-shell-v1.3.0 (Stage 3.2.4 Closure)
 */

const CACHE_NAME = 'edifies-kiosk-shell-v1.4.0';

// Approved static assets for offline attendance kiosk shell
const PRECACHE_ASSETS = [
    '/attendance',
    '/css/style.css',
    '/js/face-api.js',
    '/js/kiosk-face-attendance.js',
    '/js/kiosk-pin-attendance.js',
    '/js/kiosk-clock.js',
    '/js/security-shield.js',
    '/manifest.json',
    '/favicon.ico'
];

// 1. Install Event: Pre-cache static kiosk application shell
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing kiosk application shell...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Caching static assets:', PRECACHE_ASSETS);
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// 2. Activate Event: Clean up outdated caches and purge any legacy model binaries
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating new service worker...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting obsolete cache:', name);
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => {
            // Actively purge any legacy model entries that may reside in cache
            return caches.open(CACHE_NAME).then((cache) => {
                return cache.keys().then((requests) => {
                    return Promise.all(
                        requests.map((request) => {
                            if (request.url.includes('/models/')) {
                                console.log('[Service Worker] Purging legacy model from cache:', request.url);
                                return cache.delete(request);
                            }
                        })
                    );
                });
            });
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Event: Cache-First for static assets, Network-First for dynamic /attendance,
// and strictly NEVER cache admin or API endpoints.
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // STRICT CACHING RULES: Never intercept or cache Admin pages, API calls, unapproved model binaries, or non-GET requests
    if (
        event.request.method !== 'GET' ||
        url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/api') ||
        url.pathname.startsWith('/models') || // Excluded pending commercial license and provenance resolution
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout')
    ) {
        return; // Let browser handle normally over network
    }

    // Navigation requests for /attendance: Network-first with offline cache fallback
    if (event.request.mode === 'navigate' || url.pathname === '/attendance') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    console.log('[Service Worker] Network unavailable. Serving cached /attendance shell.');
                    const cachedResponse = await caches.match('/attendance');
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    return new Response(
                        `<!DOCTYPE html><html><head><title>Offline Kiosk</title><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;text-align:center;padding:2rem;"><h2>Attendance Terminal Offline</h2><p>Application shell is cached in IndexedDB queue mode.</p></body></html>`,
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                })
        );
        return;
    }

    // Static Assets (CSS, JS, manifest, fonts): Cache-first with network fallback
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }
            return fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            });
        })
    );
});


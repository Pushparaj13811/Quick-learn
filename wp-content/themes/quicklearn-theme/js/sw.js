/**
 * Service Worker for QuickLearn Theme
 * Handles caching of static assets and API responses
 */

const CACHE_NAME = 'quicklearn-v1.0';
const STATIC_CACHE = 'quicklearn-static-v1.0';
const DYNAMIC_CACHE = 'quicklearn-dynamic-v1.0';

// Assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/wp-content/themes/quicklearn-theme/css/critical.css',
    '/wp-content/themes/quicklearn-theme/js/lazy-loading.js',
    '/wp-content/themes/quicklearn-theme/js/navigation.js',
    '/wp-content/themes/quicklearn-theme/style.css'
];

// Cache strategies
const CACHE_STRATEGIES = {
    // Cache first for static assets
    CACHE_FIRST: 'cache-first',
    // Network first for dynamic content
    NETWORK_FIRST: 'network-first',
    // Stale while revalidate for frequently updated content
    STALE_WHILE_REVALIDATE: 'stale-while-revalidate'
};

/**
 * Install event - cache static assets
 */
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static assets...');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Failed to cache static assets:', error);
            })
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

/**
 * Fetch event - handle requests with appropriate caching strategy
 */
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip admin and login pages
    if (url.pathname.includes('/wp-admin/') || url.pathname.includes('/wp-login.php')) {
        return;
    }
    
    // Determine caching strategy based on request type
    const strategy = getCachingStrategy(request);
    
    switch (strategy) {
        case CACHE_STRATEGIES.CACHE_FIRST:
            event.respondWith(cacheFirst(request));
            break;
        case CACHE_STRATEGIES.NETWORK_FIRST:
            event.respondWith(networkFirst(request));
            break;
        case CACHE_STRATEGIES.STALE_WHILE_REVALIDATE:
            event.respondWith(staleWhileRevalidate(request));
            break;
        default:
            // Let the browser handle the request normally
            return;
    }
});

/**
 * Determine caching strategy for a request
 */
function getCachingStrategy(request) {
    const url = new URL(request.url);
    const pathname = url.pathname;
    
    // Static assets - cache first
    if (pathname.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot)$/)) {
        return CACHE_STRATEGIES.CACHE_FIRST;
    }
    
    // API requests - network first
    if (pathname.includes('/wp-json/') || pathname.includes('/wp-admin/admin-ajax.php')) {
        return CACHE_STRATEGIES.NETWORK_FIRST;
    }
    
    // Course pages and archives - stale while revalidate
    if (pathname.includes('/courses/') || pathname.includes('/course-category/')) {
        return CACHE_STRATEGIES.STALE_WHILE_REVALIDATE;
    }
    
    // HTML pages - network first
    if (request.headers.get('accept').includes('text/html')) {
        return CACHE_STRATEGIES.NETWORK_FIRST;
    }
    
    return null;
}

/**
 * Cache first strategy
 */
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Cache first strategy failed:', error);
        return new Response('Offline', { status: 503 });
    }
}

/**
 * Network first strategy
 */
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Network failed, trying cache:', error);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for HTML requests
        if (request.headers.get('accept').includes('text/html')) {
            return caches.match('/offline.html') || new Response('Offline', { status: 503 });
        }
        
        return new Response('Offline', { status: 503 });
    }
}

/**
 * Stale while revalidate strategy
 */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    // Fetch from network in background
    const networkResponsePromise = fetch(request)
        .then(networkResponse => {
            if (networkResponse.ok) {
                cache.put(request, networkResponse.clone());
            }
            return networkResponse;
        })
        .catch(error => {
            console.log('Network request failed:', error);
            return null;
        });
    
    // Return cached response immediately if available
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // Otherwise wait for network response
    return networkResponsePromise || new Response('Offline', { status: 503 });
}

/**
 * Background sync for offline actions
 */
self.addEventListener('sync', event => {
    if (event.tag === 'course-enrollment') {
        event.waitUntil(syncCourseEnrollments());
    }
    
    if (event.tag === 'course-rating') {
        event.waitUntil(syncCourseRatings());
    }
});

/**
 * Sync course enrollments when back online
 */
async function syncCourseEnrollments() {
    try {
        // Get pending enrollments from IndexedDB
        const pendingEnrollments = await getPendingEnrollments();
        
        for (const enrollment of pendingEnrollments) {
            try {
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: new FormData(Object.entries(enrollment))
                });
                
                if (response.ok) {
                    await removePendingEnrollment(enrollment.id);
                    console.log('Synced enrollment:', enrollment.course_id);
                }
            } catch (error) {
                console.error('Failed to sync enrollment:', error);
            }
        }
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

/**
 * Sync course ratings when back online
 */
async function syncCourseRatings() {
    try {
        // Get pending ratings from IndexedDB
        const pendingRatings = await getPendingRatings();
        
        for (const rating of pendingRatings) {
            try {
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: new FormData(Object.entries(rating))
                });
                
                if (response.ok) {
                    await removePendingRating(rating.id);
                    console.log('Synced rating:', rating.course_id);
                }
            } catch (error) {
                console.error('Failed to sync rating:', error);
            }
        }
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

/**
 * Push notification handling
 */
self.addEventListener('push', event => {
    if (!event.data) {
        return;
    }
    
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: '/wp-content/themes/quicklearn-theme/images/icon-192x192.png',
        badge: '/wp-content/themes/quicklearn-theme/images/badge-72x72.png',
        tag: data.tag || 'quicklearn-notification',
        data: data.data || {},
        actions: data.actions || []
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

/**
 * Notification click handling
 */
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    const data = event.notification.data;
    let url = data.url || '/';
    
    // Handle action clicks
    if (event.action) {
        switch (event.action) {
            case 'view-course':
                url = data.courseUrl || '/courses/';
                break;
            case 'view-dashboard':
                url = '/dashboard/';
                break;
        }
    }
    
    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then(clientList => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

/**
 * IndexedDB helpers for offline storage
 */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('quicklearn-offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            // Create object stores
            if (!db.objectStoreNames.contains('enrollments')) {
                db.createObjectStore('enrollments', { keyPath: 'id', autoIncrement: true });
            }
            
            if (!db.objectStoreNames.contains('ratings')) {
                db.createObjectStore('ratings', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

async function getPendingEnrollments() {
    const db = await openDB();
    const transaction = db.transaction(['enrollments'], 'readonly');
    const store = transaction.objectStore('enrollments');
    
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

async function getPendingRatings() {
    const db = await openDB();
    const transaction = db.transaction(['ratings'], 'readonly');
    const store = transaction.objectStore('ratings');
    
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

async function removePendingEnrollment(id) {
    const db = await openDB();
    const transaction = db.transaction(['enrollments'], 'readwrite');
    const store = transaction.objectStore('enrollments');
    
    return new Promise((resolve, reject) => {
        const request = store.delete(id);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

async function removePendingRating(id) {
    const db = await openDB();
    const transaction = db.transaction(['ratings'], 'readwrite');
    const store = transaction.objectStore('ratings');
    
    return new Promise((resolve, reject) => {
        const request = store.delete(id);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}
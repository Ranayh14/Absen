/**
 * Service Worker untuk Sistem Presensi
 * Mengoptimalkan caching dan performa aplikasi
 */

const CACHE_NAME = 'presensi-v1.0.0';
const STATIC_CACHE = 'presensi-static-v1.0.0';
const DYNAMIC_CACHE = 'presensi-dynamic-v1.0.0';

// Files to cache
const STATIC_FILES = [
    '/',
    '/index.php',
    '/assets/css/inter.css',
    '/assets/css/uicons-solid-rounded.css',
    '/assets/css/uicons-solid-straight.css',
    '/assets/css/responsive.css',
    '/assets/js/tailwind.js',
    '/assets/js/face-api.min.js',
    '/assets/js/chart.min.js',
    '/assets/js/performance-optimizer.js',
    '/assets/js/recognition-optimizer.js',
    '/assets/photo/logo.png',
    '/manifest.json'
];

// Install event
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static files...');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('Static files cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Error caching static files:', error);
            })
    );
});

// Activate event
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

// Fetch event
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // Handle different types of requests
    if (isStaticFile(request.url)) {
        event.respondWith(handleStaticRequest(request));
    } else if (isAPIRequest(request.url)) {
        event.respondWith(handleAPIRequest(request));
    } else {
        event.respondWith(handlePageRequest(request));
    }
});

// Check if request is for static file
function isStaticFile(url) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf'];
    return staticExtensions.some(ext => url.includes(ext));
}

// Check if request is for API
function isAPIRequest(url) {
    return url.includes('index.php') && (
        url.includes('action=') || 
        url.includes('get_members') ||
        url.includes('submit_attendance')
    );
}

// Handle static file requests
async function handleStaticRequest(request) {
    try {
        const cache = await caches.open(STATIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            console.log('Serving static file from cache:', request.url);
            return cachedResponse;
        }
        
        const response = await fetch(request);
        
        if (response.ok) {
            cache.put(request, response.clone());
            console.log('Cached static file:', request.url);
        }
        
        return response;
        
    } catch (error) {
        console.error('Error handling static request:', error);
        return new Response('Static file not available', { status: 404 });
    }
}

// Handle API requests
async function handleAPIRequest(request) {
    try {
        // For API requests, always try network first
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache successful API responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            console.log('Cached API response:', request.url);
        }
        
        return response;
        
    } catch (error) {
        console.error('Network error for API request:', error);
        
        // Try to serve from cache as fallback
        const cache = await caches.open(DYNAMIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            console.log('Serving API response from cache:', request.url);
            return cachedResponse;
        }
        
        return new Response('API not available', { status: 503 });
    }
}

// Handle page requests
async function handlePageRequest(request) {
    try {
        // Try network first for pages
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache page responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            console.log('Cached page:', request.url);
        }
        
        return response;
        
    } catch (error) {
        console.error('Network error for page request:', error);
        
        // Try to serve from cache as fallback
        const cache = await caches.open(DYNAMIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            console.log('Serving page from cache:', request.url);
            return cachedResponse;
        }
        
        // Return offline page if available
        const offlineResponse = await cache.match('/offline.html');
        if (offlineResponse) {
            return offlineResponse;
        }
        
        return new Response('Page not available offline', { status: 503 });
    }
}

// Message event for communication with main thread
self.addEventListener('message', event => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'CLEAR_CACHE':
            clearAllCaches();
            break;
            
        case 'GET_CACHE_SIZE':
            getCacheSize().then(size => {
                event.ports[0].postMessage({ size });
            });
            break;
            
        default:
            console.log('Unknown message type:', type);
    }
});

// Clear all caches
async function clearAllCaches() {
    try {
        const cacheNames = await caches.keys();
        await Promise.all(
            cacheNames.map(cacheName => caches.delete(cacheName))
        );
        console.log('All caches cleared');
    } catch (error) {
        console.error('Error clearing caches:', error);
    }
}

// Get cache size
async function getCacheSize() {
    try {
        const cacheNames = await caches.keys();
        let totalSize = 0;
        
        for (const cacheName of cacheNames) {
            const cache = await caches.open(cacheName);
            const keys = await cache.keys();
            totalSize += keys.length;
        }
        
        return totalSize;
    } catch (error) {
        console.error('Error getting cache size:', error);
        return 0;
    }
}

// Background sync for offline attendance
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync-attendance') {
        event.waitUntil(handleBackgroundSync());
    }
});

// Handle background sync
async function handleBackgroundSync() {
    try {
        console.log('Handling background sync for attendance...');
        
        // Get pending attendance from IndexedDB
        const pendingAttendance = await getPendingAttendance();
        
        if (pendingAttendance.length > 0) {
            console.log(`Syncing ${pendingAttendance.length} pending attendance records...`);
            
            for (const attendance of pendingAttendance) {
                try {
                    await syncAttendance(attendance);
                    await removePendingAttendance(attendance.id);
                } catch (error) {
                    console.error('Error syncing attendance:', error);
                }
            }
        }
        
    } catch (error) {
        console.error('Error in background sync:', error);
    }
}

// Get pending attendance from IndexedDB
async function getPendingAttendance() {
    // This would integrate with IndexedDB
    // For now, return empty array
    return [];
}

// Sync attendance to server
async function syncAttendance(attendance) {
    const response = await fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=submit_attendance&${new URLSearchParams(attendance)}`
    });
    
    if (!response.ok) {
        throw new Error('Failed to sync attendance');
    }
}

// Remove pending attendance from IndexedDB
async function removePendingAttendance(id) {
    // This would integrate with IndexedDB
    // For now, just log
    console.log('Removing pending attendance:', id);
}
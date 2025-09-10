const CACHE_NAME = 'absen-app-v2';

// Install event - cache resources
self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      return self.clients.claim();
    })
  );
});

// Fetch event - minimal caching for static assets only
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // Skip all non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Skip all AJAX requests and API calls
  if (url.searchParams.has('ajax') || 
      url.pathname.includes('ajax') ||
      event.request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
    return;
  }
  
  // Skip service worker for speech synthesis and other browser APIs
  if (event.request.url.includes('speech') || 
      event.request.url.includes('tts') ||
      event.request.destination === 'audio' ||
      event.request.destination === 'media') {
    return;
  }
  
  // Only cache static assets
  if (url.pathname.startsWith('/assets/') || 
      url.pathname.endsWith('.css') ||
      url.pathname.endsWith('.js') ||
      url.pathname.endsWith('.woff2') ||
      url.pathname.endsWith('.woff') ||
      url.pathname.endsWith('.ttf') ||
      url.pathname.endsWith('.png') ||
      url.pathname.endsWith('.jpg') ||
      url.pathname.endsWith('.jpeg') ||
      url.pathname.endsWith('.gif') ||
      url.pathname.endsWith('.svg')) {
    
    event.respondWith(
      caches.match(event.request)
        .then(response => {
          if (response) {
            return response;
          }
          
          return fetch(event.request).then(response => {
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            
            return response;
          });
        })
    );
  }
});

const CACHE_NAME = 'absen-app-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/manifest.json',
  '/generate-avatar.php',
  '/create-icon.php',
  '/assets/css/tailwind.min.css',
  '/assets/css/inter.css',
  '/assets/css/uicons-solid-rounded.css',
  '/assets/css/uicons-solid-straight.css',
  '/assets/js/face-api.min.js',
  '/assets/js/chart.min.js',
  '/assets/fonts/font-files/Inter-Regular.woff2',
  '/assets/fonts/font-files/Inter-Medium.woff2',
  '/assets/fonts/font-files/Inter-SemiBold.woff2',
  '/assets/fonts/font-files/Inter-Bold.woff2',
  '/assets/fonts/font-files/InterVariable.woff2',
  '/assets/js/face-api-models/tiny_face_detector_model-shard1',
  '/assets/js/face-api-models/face_landmark_68_model-shard1',
  '/assets/js/face-api-models/face_recognition_model-shard1',
  '/assets/js/face-api-models/face_recognition_model-shard2',
  '/assets/js/face-api-models/face_expression_model-shard1',
  '/assets/js/face-api-models/tiny_face_detector_model-weights_manifest.json',
  '/assets/js/face-api-models/face_landmark_68_model-weights_manifest.json',
  '/assets/js/face-api-models/face_recognition_model-weights_manifest.json',
  '/assets/js/face-api-models/face_expression_model-weights_manifest.json'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(err => {
        console.log('Cache install failed:', err);
      })
  );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', event => {
  // Skip service worker for speech synthesis and other browser APIs
  if (event.request.url.includes('speech') || 
      event.request.url.includes('tts') ||
      event.request.destination === 'audio' ||
      event.request.destination === 'media') {
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        if (response) {
          return response;
        }
        
        // Clone the request
        const fetchRequest = event.request.clone();
        
        return fetch(fetchRequest).then(response => {
          // Check if we received a valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          
          // Clone the response
          const responseToCache = response.clone();
          
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        }).catch(() => {
          // If both cache and network fail, show offline page
          if (event.request.destination === 'document') {
            return caches.match('/index.php');
          }
        });
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
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
    })
  );
});

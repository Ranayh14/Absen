/**
 * Performance Optimizer untuk Sistem Presensi
 * Mengoptimalkan performa aplikasi secara keseluruhan
 */

class PerformanceOptimizer {
    constructor() {
        this.isInitialized = false;
        this.performanceMetrics = {
            loadTime: 0,
            recognitionTime: 0,
            attendanceTime: 0
        };
        this.optimizationSettings = {
            enablePreloading: true,
            enableCaching: true,
            enableCompression: true,
            maxCacheSize: 50 // MB
        };
    }

    /**
     * Inisialisasi performance optimizer
     */
    async init() {
        try {
            console.log('Initializing Performance Optimizer...');
            
            // Setup performance monitoring
            this.setupPerformanceMonitoring();
            
            // Setup caching
            this.setupCaching();
            
            // Setup compression
            this.setupCompression();
            
            this.isInitialized = true;
            console.log('Performance Optimizer initialized successfully');
            
        } catch (error) {
            console.error('Error initializing Performance Optimizer:', error);
        }
    }

    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        // Monitor page load time
        window.addEventListener('load', () => {
            this.performanceMetrics.loadTime = performance.now();
            console.log(`Page loaded in ${this.performanceMetrics.loadTime.toFixed(2)}ms`);
        });

        // Monitor memory usage
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.8) {
                    console.warn('High memory usage detected:', {
                        used: (memory.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
                        limit: (memory.jsHeapSizeLimit / 1024 / 1024).toFixed(2) + 'MB'
                    });
                    this.optimizeMemory();
                }
            }, 5000);
        }
    }

    /**
     * Setup caching system
     */
    setupCaching() {
        if (!this.optimizationSettings.enableCaching) return;

        // Setup service worker for caching
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }
    }

    /**
     * Setup compression
     */
    setupCompression() {
        if (!this.optimizationSettings.enableCompression) return;

        // Compress images before processing
        this.setupImageCompression();
    }

    /**
     * Setup image compression
     */
    setupImageCompression() {
        // Override canvas toDataURL to compress images
        const originalToDataURL = HTMLCanvasElement.prototype.toDataURL;
        HTMLCanvasElement.prototype.toDataURL = function(type, quality) {
            if (type === 'image/jpeg' && quality === undefined) {
                quality = 0.8; // Default compression
            }
            return originalToDataURL.call(this, type, quality);
        };
    }

    /**
     * Optimize memory usage
     */
    optimizeMemory() {
        console.log('Optimizing memory usage...');
        
        // Clear unused caches
        if ('caches' in window) {
            caches.keys().then(cacheNames => {
                cacheNames.forEach(cacheName => {
                    caches.delete(cacheName);
                });
            });
        }

        // Force garbage collection if available
        if (window.gc) {
            window.gc();
        }
    }

    /**
     * Preload critical resources
     */
    async preloadResources() {
        try {
            console.log('Preloading critical resources...');
            
            // Preload Face API models
            await this.preloadFaceAPIModels();
            
            // Preload user data
            await this.preloadUserData();
            
            console.log('Critical resources preloaded');
            
        } catch (error) {
            console.error('Error preloading resources:', error);
        }
    }

    /**
     * Preload Face API models
     */
    async preloadFaceAPIModels() {
        if (typeof faceapi === 'undefined') return;

        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('assets/js/models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('assets/js/models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('assets/js/models'),
                faceapi.nets.faceExpressionNet.loadFromUri('assets/js/models')
            ]);
            console.log('Face API models preloaded');
        } catch (error) {
            console.error('Error preloading Face API models:', error);
        }
    }

    /**
     * Preload user data
     */
    async preloadUserData() {
        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_members'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.ok && data.data) {
                    // Store in session storage for quick access
                    sessionStorage.setItem('members_cache', JSON.stringify(data.data));
                    console.log('User data preloaded');
                }
            }
        } catch (error) {
            console.error('Error preloading user data:', error);
        }
    }

    /**
     * Get cached user data
     */
    getCachedUserData() {
        try {
            const cached = sessionStorage.getItem('members_cache');
            return cached ? JSON.parse(cached) : null;
        } catch (error) {
            console.error('Error getting cached user data:', error);
            return null;
        }
    }

    /**
     * Optimize for mobile devices
     */
    optimizeForMobile() {
        console.log('Optimizing for mobile devices...');
        
        // Reduce image quality for mobile
        this.optimizationSettings.imageQuality = 0.6;
        
        // Enable aggressive caching
        this.optimizationSettings.enableCaching = true;
        
        // Reduce processing frequency
        this.optimizationSettings.processingInterval = 1000; // 1 second
    }

    /**
     * Optimize for desktop
     */
    optimizeForDesktop() {
        console.log('Optimizing for desktop...');
        
        // Higher image quality for desktop
        this.optimizationSettings.imageQuality = 0.8;
        
        // Standard processing frequency
        this.optimizationSettings.processingInterval = 500; // 0.5 seconds
    }

    /**
     * Get performance metrics
     */
    getPerformanceMetrics() {
        return {
            ...this.performanceMetrics,
            memoryUsage: this.getMemoryUsage(),
            cacheSize: this.getCacheSize()
        };
    }

    /**
     * Get memory usage
     */
    getMemoryUsage() {
        if ('memory' in performance) {
            const memory = performance.memory;
            return {
                used: (memory.usedJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
                total: (memory.totalJSHeapSize / 1024 / 1024).toFixed(2) + 'MB',
                limit: (memory.jsHeapSizeLimit / 1024 / 1024).toFixed(2) + 'MB'
            };
        }
        return null;
    }

    /**
     * Get cache size
     */
    getCacheSize() {
        if ('caches' in window) {
            return caches.keys().then(cacheNames => {
                return cacheNames.length;
            });
        }
        return 0;
    }

    /**
     * Clear all caches
     */
    async clearCaches() {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(
                cacheNames.map(cacheName => caches.delete(cacheName))
            );
            console.log('All caches cleared');
        }
    }

    /**
     * Optimize image for processing
     */
    optimizeImage(canvas, quality = null) {
        if (quality === null) {
            quality = this.optimizationSettings.imageQuality || 0.8;
        }

        // Create optimized canvas
        const optimizedCanvas = document.createElement('canvas');
        const ctx = optimizedCanvas.getContext('2d');
        
        // Calculate optimal dimensions
        const maxWidth = 800;
        const maxHeight = 600;
        
        let { width, height } = canvas;
        
        if (width > maxWidth || height > maxHeight) {
            const ratio = Math.min(maxWidth / width, maxHeight / height);
            width *= ratio;
            height *= ratio;
        }
        
        optimizedCanvas.width = width;
        optimizedCanvas.height = height;
        
        // Draw optimized image
        ctx.drawImage(canvas, 0, 0, width, height);
        
        return optimizedCanvas.toDataURL('image/jpeg', quality);
    }
}

// Initialize global performance optimizer
window.performanceOptimizer = new PerformanceOptimizer();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.performanceOptimizer.init();
    });
} else {
    window.performanceOptimizer.init();
}
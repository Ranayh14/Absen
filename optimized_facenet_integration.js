/**
 * Optimized FaceNet Integration - iPhone-like Performance
 * 
 * This script provides ultra-fast face recognition with optimized algorithms
 * and caching for iPhone-like speed and accuracy.
 */

class OptimizedFaceNet {
    constructor() {
        this.isProcessing = false;
        this.recognitionMode = 'optimized'; // 'optimized', 'high_accuracy', 'standard'
        this.threshold = 0.5; // Default threshold for optimized mode
        this.cache = new Map();
        this.maxCacheSize = 100;
        
        // Performance tracking
        this.performanceStats = {
            totalRequests: 0,
            successfulRecognitions: 0,
            averageResponseTime: 0,
            cacheHits: 0,
            cacheMisses: 0
        };
        
        this.initializeUI();
        this.initializeCamera();
    }
    
    initializeUI() {
        // Create optimized controls
        this.createOptimizedControls();
        
        // Add event listeners
        this.addEventListeners();
        
        // Initialize performance monitoring
        this.initializePerformanceMonitoring();
    }
    
    createOptimizedControls() {
        // Find the main container
        let container = document.querySelector('.main-container, .container, body');
        if (!container) {
            container = document.body;
        }
        
        // Create optimized mode selector
        const modeSelector = document.createElement('div');
        modeSelector.className = 'optimized-mode-selector';
        modeSelector.innerHTML = `
            <div class="mode-selector-container">
                <h5 class="mode-title">
                    <i class="fas fa-rocket"></i>
                    Face Recognition Mode
                </h5>
                <div class="mode-options">
                    <div class="mode-option">
                        <input type="radio" id="optimizedMode" name="recognitionMode" value="optimized" checked>
                        <label for="optimizedMode">
                            <i class="fas fa-bolt"></i>
                            <span class="mode-name">Optimized (iPhone-like)</span>
                            <span class="mode-desc">Ultra-fast with high accuracy</span>
                        </label>
                    </div>
                    <div class="mode-option">
                        <input type="radio" id="highAccuracyMode" name="recognitionMode" value="high_accuracy">
                        <label for="highAccuracyMode">
                            <i class="fas fa-shield-alt"></i>
                            <span class="mode-name">High Accuracy</span>
                            <span class="mode-desc">90% confidence threshold</span>
                        </label>
                    </div>
                    <div class="mode-option">
                        <input type="radio" id="standardMode" name="recognitionMode" value="standard">
                        <label for="standardMode">
                            <i class="fas fa-cog"></i>
                            <span class="mode-name">Standard</span>
                            <span class="mode-desc">Basic face recognition</span>
                        </label>
                    </div>
                </div>
            </div>
        `;
        
        // Create performance indicators
        const performanceIndicators = document.createElement('div');
        performanceIndicators.className = 'performance-indicators';
        performanceIndicators.innerHTML = `
            <div class="performance-metrics">
                <div class="metric">
                    <span class="metric-label">Response Time:</span>
                    <span class="metric-value" id="responseTime">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Confidence:</span>
                    <span class="metric-value" id="confidenceValue">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value" id="statusValue">Ready</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Cache Hit:</span>
                    <span class="metric-value" id="cacheHitRate">--</span>
                </div>
            </div>
        `;
        
        // Create threshold slider for optimized mode
        const thresholdSlider = document.createElement('div');
        thresholdSlider.className = 'threshold-slider';
        thresholdSlider.innerHTML = `
            <div class="slider-container">
                <label for="thresholdSlider">Recognition Threshold:</label>
                <input type="range" id="thresholdSlider" min="0.3" max="0.8" step="0.1" value="0.5">
                <span class="threshold-value" id="thresholdValue">0.5</span>
            </div>
        `;
        
        // Insert controls
        container.insertBefore(modeSelector, container.firstChild);
        container.insertBefore(performanceIndicators, container.firstChild);
        container.insertBefore(thresholdSlider, container.firstChild);
        
        // Add CSS styles
        this.addOptimizedStyles();
    }
    
    addOptimizedStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .optimized-mode-selector, .performance-indicators, .threshold-slider {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 15px;
                color: white;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            
            .mode-title {
                margin: 0 0 15px 0;
                font-size: 16px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .mode-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }
            
            .mode-option {
                position: relative;
            }
            
            .mode-option input[type="radio"] {
                position: absolute;
                opacity: 0;
                cursor: pointer;
            }
            
            .mode-option label {
                display: block;
                padding: 12px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .mode-option input[type="radio"]:checked + label {
                background: rgba(255, 255, 255, 0.2);
                border-color: rgba(255, 255, 255, 0.5);
            }
            
            .mode-name {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .mode-desc {
                display: block;
                font-size: 12px;
                opacity: 0.8;
            }
            
            .performance-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
            }
            
            .metric {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .metric-label {
                color: rgba(255, 255, 255, 0.8);
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }
            
            .metric-value {
                color: white;
                font-size: 16px;
                font-weight: 700;
                padding: 6px 10px;
                border-radius: 6px;
                background: rgba(255, 255, 255, 0.2);
                min-width: 60px;
            }
            
            .metric-value.fast {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
            }
            
            .metric-value.slow {
                background: rgba(220, 53, 69, 0.3);
                border: 1px solid rgba(220, 53, 69, 0.5);
            }
            
            .metric-value.processing {
                background: rgba(255, 193, 7, 0.3);
                border: 1px solid rgba(255, 193, 7, 0.5);
                animation: pulse 1.5s infinite;
            }
            
            .slider-container {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .slider-container label {
                font-weight: 600;
                min-width: 150px;
            }
            
            #thresholdSlider {
                flex: 1;
                height: 6px;
                border-radius: 3px;
                background: rgba(255, 255, 255, 0.3);
                outline: none;
                -webkit-appearance: none;
            }
            
            #thresholdSlider::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: white;
                cursor: pointer;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            }
            
            .threshold-value {
                font-weight: 700;
                min-width: 40px;
                text-align: center;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            @media (max-width: 768px) {
                .mode-options {
                    grid-template-columns: 1fr;
                }
                
                .performance-metrics {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .slider-container {
                    flex-direction: column;
                    align-items: stretch;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    addEventListeners() {
        // Mode selection
        const modeRadios = document.querySelectorAll('input[name="recognitionMode"]');
        modeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.recognitionMode = e.target.value;
                this.updateModeSettings();
            });
        });
        
        // Threshold slider
        const thresholdSlider = document.getElementById('thresholdSlider');
        if (thresholdSlider) {
            thresholdSlider.addEventListener('input', (e) => {
                this.threshold = parseFloat(e.target.value);
                document.getElementById('thresholdValue').textContent = e.target.value;
            });
        }
        
        // Override existing attendance button
        const attendanceButton = document.querySelector('#attendanceButton, .attendance-btn');
        if (attendanceButton) {
            attendanceButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.processAttendance();
            });
        }
        
        // Override existing embedding generation
        const embeddingButton = document.querySelector('#generateEmbedding, .embedding-btn');
        if (embeddingButton) {
            embeddingButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.generateEmbedding();
            });
        }
    }
    
    updateModeSettings() {
        const thresholdSlider = document.getElementById('thresholdSlider');
        const thresholdValue = document.getElementById('thresholdValue');
        
        switch (this.recognitionMode) {
            case 'optimized':
                this.threshold = 0.5;
                if (thresholdSlider) thresholdSlider.value = 0.5;
                if (thresholdValue) thresholdValue.textContent = '0.5';
                break;
            case 'high_accuracy':
                this.threshold = 0.6; // Higher threshold for high accuracy
                if (thresholdSlider) thresholdSlider.value = 0.6;
                if (thresholdValue) thresholdValue.textContent = '0.6';
                break;
            case 'standard':
                this.threshold = 0.4; // Lower threshold for standard mode
                if (thresholdSlider) thresholdSlider.value = 0.4;
                if (thresholdValue) thresholdValue.textContent = '0.4';
                break;
        }
    }
    
    async processAttendance() {
        if (this.isProcessing) {
            this.showMessage('Processing in progress...', 'warning');
            return;
        }
        
        this.isProcessing = true;
        const startTime = performance.now();
        
        try {
            this.updateStatus('Processing...', 'processing');
            
            // Capture image
            const imageData = await this.captureImage();
            if (!imageData) {
                throw new Error('Failed to capture image');
            }
            
            // Check cache first
            const cacheKey = this.getCacheKey(imageData);
            if (this.cache.has(cacheKey)) {
                this.performanceStats.cacheHits++;
                const cachedResult = this.cache.get(cacheKey);
                this.handleRecognitionResult(cachedResult, performance.now() - startTime);
                return;
            }
            
            this.performanceStats.cacheMisses++;
            
            // Process based on selected mode
            let result;
            switch (this.recognitionMode) {
                case 'optimized':
                    result = await this.callOptimizedAPI('process_optimized_attendance', {
                        image: imageData,
                        threshold: this.threshold
                    });
                    break;
                case 'high_accuracy':
                    result = await this.callHighAccuracyAPI('process_high_accuracy_attendance', {
                        image: imageData
                    });
                    break;
                case 'standard':
                    result = await this.callStandardAPI('process_attendance_facenet', {
                        image: imageData,
                        threshold: this.threshold
                    });
                    break;
            }
            
            const responseTime = performance.now() - startTime;
            
            if (result && result.ok) {
                // Cache successful results
                if (this.cache.size >= this.maxCacheSize) {
                    const firstKey = this.cache.keys().next().value;
                    this.cache.delete(firstKey);
                }
                this.cache.set(cacheKey, result);
                
                this.handleRecognitionResult(result, responseTime);
            } else {
                this.handleRecognitionError(result?.error || 'Recognition failed', responseTime);
            }
            
        } catch (error) {
            console.error('Attendance processing error:', error);
            this.handleRecognitionError(error.message, performance.now() - startTime);
        } finally {
            this.isProcessing = false;
        }
    }
    
    async generateEmbedding() {
        if (this.isProcessing) {
            this.showMessage('Processing in progress...', 'warning');
            return;
        }
        
        this.isProcessing = true;
        const startTime = performance.now();
        
        try {
            this.updateStatus('Generating...', 'processing');
            
            // Capture image
            const imageData = await this.captureImage();
            if (!imageData) {
                throw new Error('Failed to capture image');
            }
            
            // Generate embedding based on selected mode
            let result;
            switch (this.recognitionMode) {
                case 'optimized':
                    result = await this.callOptimizedAPI('generate_optimized_embedding', {
                        image: imageData
                    });
                    break;
                case 'high_accuracy':
                    result = await this.callHighAccuracyAPI('generate_high_accuracy_embedding', {
                        image: imageData
                    });
                    break;
                case 'standard':
                    result = await this.callStandardAPI('generate_face_embedding', {
                        image: imageData
                    });
                    break;
            }
            
            const responseTime = performance.now() - startTime;
            
            if (result && result.ok) {
                this.handleEmbeddingResult(result, responseTime);
            } else {
                this.handleEmbeddingError(result?.error || 'Embedding generation failed', responseTime);
            }
            
        } catch (error) {
            console.error('Embedding generation error:', error);
            this.handleEmbeddingError(error.message, performance.now() - startTime);
        } finally {
            this.isProcessing = false;
        }
    }
    
    async callOptimizedAPI(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    async callHighAccuracyAPI(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    async callStandardAPI(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    handleRecognitionResult(result, responseTime) {
        this.performanceStats.totalRequests++;
        this.performanceStats.successfulRecognitions++;
        this.updateAverageResponseTime(responseTime);
        
        // Update UI
        this.updateResponseTime(responseTime);
        this.updateConfidence(result.data?.confidence || 0);
        this.updateStatus('Success', 'fast');
        
        // Show success message
        this.showMessage(
            `Attendance recorded successfully!<br>
             NIM: ${result.data.nim}<br>
             Nama: ${result.data.nama}<br>
             Confidence: ${(result.data.confidence * 100).toFixed(1)}%<br>
             Response Time: ${responseTime.toFixed(0)}ms`,
            'success'
        );
        
        // Refresh attendance data if available
        if (typeof refreshAttendanceData === 'function') {
            refreshAttendanceData();
        }
    }
    
    handleRecognitionError(error, responseTime) {
        this.performanceStats.totalRequests++;
        this.updateAverageResponseTime(responseTime);
        
        // Update UI
        this.updateResponseTime(responseTime);
        this.updateStatus('Failed', 'slow');
        
        // Show error message
        this.showMessage(error, 'error');
    }
    
    handleEmbeddingResult(result, responseTime) {
        this.updateResponseTime(responseTime);
        this.updateStatus('Generated', 'fast');
        
        this.showMessage(
            `Face embedding generated successfully!<br>
             Response Time: ${responseTime.toFixed(0)}ms`,
            'success'
        );
    }
    
    handleEmbeddingError(error, responseTime) {
        this.updateResponseTime(responseTime);
        this.updateStatus('Failed', 'slow');
        
        this.showMessage(error, 'error');
    }
    
    updateResponseTime(responseTime) {
        const responseTimeElement = document.getElementById('responseTime');
        if (responseTimeElement) {
            responseTimeElement.textContent = `${responseTime.toFixed(0)}ms`;
            responseTimeElement.className = responseTime < 1000 ? 'metric-value fast' : 'metric-value slow';
        }
    }
    
    updateConfidence(confidence) {
        const confidenceElement = document.getElementById('confidenceValue');
        if (confidenceElement) {
            confidenceElement.textContent = `${(confidence * 100).toFixed(1)}%`;
            confidenceElement.className = confidence > 0.8 ? 'metric-value fast' : 'metric-value slow';
        }
    }
    
    updateStatus(status, type) {
        const statusElement = document.getElementById('statusValue');
        if (statusElement) {
            statusElement.textContent = status;
            statusElement.className = `metric-value ${type}`;
        }
    }
    
    updateAverageResponseTime(newTime) {
        const totalRequests = this.performanceStats.totalRequests;
        const currentAvg = this.performanceStats.averageResponseTime;
        
        this.performanceStats.averageResponseTime = 
            ((currentAvg * (totalRequests - 1)) + newTime) / totalRequests;
    }
    
    updateCacheHitRate() {
        const cacheHitRateElement = document.getElementById('cacheHitRate');
        if (cacheHitRateElement) {
            const totalCacheRequests = this.performanceStats.cacheHits + this.performanceStats.cacheMisses;
            if (totalCacheRequests > 0) {
                const hitRate = (this.performanceStats.cacheHits / totalCacheRequests) * 100;
                cacheHitRateElement.textContent = `${hitRate.toFixed(1)}%`;
                cacheHitRateElement.className = hitRate > 50 ? 'metric-value fast' : 'metric-value slow';
            }
        }
    }
    
    getCacheKey(imageData) {
        // Create a simple hash of the image data for caching
        let hash = 0;
        for (let i = 0; i < imageData.length; i++) {
            const char = imageData.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return hash.toString();
    }
    
    async captureImage() {
        const video = document.querySelector('#camera, video');
        if (!video) {
            throw new Error('Camera not available');
        }
        
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        // Set high resolution
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;
        
        // Draw video frame
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to base64
        return canvas.toDataURL('image/jpeg', 0.9);
    }
    
    initializeCamera() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            const constraints = {
                video: {
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                    frameRate: { ideal: 30, min: 15 },
                    facingMode: 'user'
                }
            };
            
            navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    const video = document.querySelector('#camera, video');
                    if (video) {
                        video.srcObject = stream;
                        video.play();
                    }
                })
                .catch(error => {
                    console.error('Camera access error:', error);
                });
        }
    }
    
    initializePerformanceMonitoring() {
        // Update cache hit rate every 5 seconds
        setInterval(() => {
            this.updateCacheHitRate();
        }, 5000);
    }
    
    showMessage(message, type) {
        // Create or update message element
        let messageElement = document.getElementById('optimizedMessage');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.id = 'optimizedMessage';
            messageElement.className = 'optimized-message';
            document.body.appendChild(messageElement);
        }
        
        messageElement.innerHTML = message;
        messageElement.className = `optimized-message ${type}`;
        messageElement.style.display = 'block';
        
        // Auto-hide after 4 seconds
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, 4000);
    }
    
    getPerformanceStats() {
        return {
            ...this.performanceStats,
            successRate: this.performanceStats.totalRequests > 0 ? 
                (this.performanceStats.successfulRecognitions / this.performanceStats.totalRequests) * 100 : 0,
            cacheHitRate: (this.performanceStats.cacheHits + this.performanceStats.cacheMisses) > 0 ?
                (this.performanceStats.cacheHits / (this.performanceStats.cacheHits + this.performanceStats.cacheMisses)) * 100 : 0
        };
    }
}

// Initialize optimized FaceNet when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.optimizedFaceNet = new OptimizedFaceNet();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OptimizedFaceNet;
}

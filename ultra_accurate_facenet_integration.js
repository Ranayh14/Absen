/**
 * Ultra Accurate FaceNet Integration - Maximum Accuracy with Ultra-Fast Response
 * 
 * This script provides maximum accuracy face recognition with ultra-fast response
 * times and multiple validation conditions for attendance system.
 */

class UltraAccurateFaceNet {
    constructor() {
        this.isProcessing = false;
        this.recognitionMode = 'ultra_accurate'; // 'ultra_accurate', 'optimized', 'high_accuracy', 'standard'
        this.validationLevel = 'normal'; // 'strict', 'normal', 'lenient'
        this.cache = new Map();
        this.maxCacheSize = 200;
        
        // Performance tracking
        this.performanceStats = {
            totalRequests: 0,
            successfulRecognitions: 0,
            averageResponseTime: 0,
            cacheHits: 0,
            cacheMisses: 0,
            validationPasses: 0,
            validationFails: 0
        };
        
        this.initializeUI();
        this.initializeCamera();
    }
    
    initializeUI() {
        // Create ultra accurate controls
        this.createUltraAccurateControls();
        
        // Add event listeners
        this.addEventListeners();
        
        // Initialize performance monitoring
        this.initializePerformanceMonitoring();
    }
    
    createUltraAccurateControls() {
        // Find the main container
        let container = document.querySelector('.main-container, .container, body');
        if (!container) {
            container = document.body;
        }
        
        // Create ultra accurate mode selector
        const modeSelector = document.createElement('div');
        modeSelector.className = 'ultra-accurate-mode-selector';
        modeSelector.innerHTML = `
            <div class="mode-selector-container">
                <h5 class="mode-title">
                    <i class="fas fa-bullseye"></i>
                    Ultra Accurate Face Recognition
                </h5>
                <div class="mode-options">
                    <div class="mode-option">
                        <input type="radio" id="ultraAccurateMode" name="recognitionMode" value="ultra_accurate" checked>
                        <label for="ultraAccurateMode">
                            <i class="fas fa-bullseye"></i>
                            <span class="mode-name">Ultra Accurate (Recommended)</span>
                            <span class="mode-desc">Maximum accuracy with ultra-fast response</span>
                        </label>
                    </div>
                    <div class="mode-option">
                        <input type="radio" id="optimizedMode" name="recognitionMode" value="optimized">
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
        
        // Create validation level selector
        const validationSelector = document.createElement('div');
        validationSelector.className = 'validation-level-selector';
        validationSelector.innerHTML = `
            <div class="validation-container">
                <h6 class="validation-title">
                    <i class="fas fa-check-double"></i>
                    Validation Level
                </h6>
                <div class="validation-options">
                    <div class="validation-option">
                        <input type="radio" id="strictValidation" name="validationLevel" value="strict">
                        <label for="strictValidation">
                            <span class="validation-name">Strict</span>
                            <span class="validation-desc">Maximum accuracy (99%+)</span>
                        </label>
                    </div>
                    <div class="validation-option">
                        <input type="radio" id="normalValidation" name="validationLevel" value="normal" checked>
                        <label for="normalValidation">
                            <span class="validation-name">Normal</span>
                            <span class="validation-desc">High accuracy (95%+)</span>
                        </label>
                    </div>
                    <div class="validation-option">
                        <input type="radio" id="lenientValidation" name="validationLevel" value="lenient">
                        <label for="lenientValidation">
                            <span class="validation-name">Lenient</span>
                            <span class="validation-desc">Good accuracy (90%+)</span>
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
                    <span class="metric-label">Validation:</span>
                    <span class="metric-value" id="validationValue">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value" id="statusValue">Ready</span>
                </div>
            </div>
        `;
        
        // Insert controls
        container.insertBefore(modeSelector, container.firstChild);
        container.insertBefore(validationSelector, container.firstChild);
        container.insertBefore(performanceIndicators, container.firstChild);
        
        // Add CSS styles
        this.addUltraAccurateStyles();
    }
    
    addUltraAccurateStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ultra-accurate-mode-selector, .validation-level-selector, .performance-indicators {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 15px;
                color: white;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            
            .mode-title, .validation-title {
                margin: 0 0 15px 0;
                font-size: 16px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .mode-options, .validation-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }
            
            .mode-option, .validation-option {
                position: relative;
            }
            
            .mode-option input[type="radio"], .validation-option input[type="radio"] {
                position: absolute;
                opacity: 0;
                cursor: pointer;
            }
            
            .mode-option label, .validation-option label {
                display: block;
                padding: 12px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .mode-option input[type="radio"]:checked + label, .validation-option input[type="radio"]:checked + label {
                background: rgba(255, 255, 255, 0.2);
                border-color: rgba(255, 255, 255, 0.5);
            }
            
            .mode-name, .validation-name {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .mode-desc, .validation-desc {
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
            
            .metric-value.ultra-fast {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
                animation: pulse 1s infinite;
            }
            
            .metric-value.fast {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
            }
            
            .metric-value.medium {
                background: rgba(255, 193, 7, 0.3);
                border: 1px solid rgba(255, 193, 7, 0.5);
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
            
            .metric-value.pass {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
            }
            
            .metric-value.fail {
                background: rgba(220, 53, 69, 0.3);
                border: 1px solid rgba(220, 53, 69, 0.5);
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            @media (max-width: 768px) {
                .mode-options, .validation-options {
                    grid-template-columns: 1fr;
                }
                
                .performance-metrics {
                    grid-template-columns: repeat(2, 1fr);
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
        
        // Validation level selection
        const validationRadios = document.querySelectorAll('input[name="validationLevel"]');
        validationRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.validationLevel = e.target.value;
            });
        });
        
        // Override existing attendance button
        const attendanceButton = document.querySelector('#attendanceButton, .attendance-btn');
        if (attendanceButton) {
            attendanceButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.processAttendance();
            });
        }
    }
    
    updateModeSettings() {
        // Update UI based on selected mode
        const statusElement = document.getElementById('statusValue');
        if (statusElement) {
            switch (this.recognitionMode) {
                case 'ultra_accurate':
                    statusElement.textContent = 'Ultra Accurate Mode';
                    statusElement.className = 'metric-value ultra-fast';
                    break;
                case 'optimized':
                    statusElement.textContent = 'Optimized Mode';
                    statusElement.className = 'metric-value fast';
                    break;
                case 'high_accuracy':
                    statusElement.textContent = 'High Accuracy Mode';
                    statusElement.className = 'metric-value medium';
                    break;
                case 'standard':
                    statusElement.textContent = 'Standard Mode';
                    statusElement.className = 'metric-value slow';
                    break;
            }
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
                case 'ultra_accurate':
                    result = await this.callUltraAccurateAPI('process_ultra_accurate_attendance', {
                        image: imageData,
                        validation_level: this.validationLevel
                    });
                    break;
                case 'optimized':
                    result = await this.callOptimizedAPI('process_optimized_attendance', {
                        image: imageData,
                        threshold: 0.5
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
                        threshold: 0.5
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
    
    async callUltraAccurateAPI(action, data) {
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
        this.updateValidation(result.data?.validation_result);
        this.updateStatus('Success', 'ultra-fast');
        
        // Show success message
        this.showMessage(
            `Attendance recorded successfully!<br>
             NIM: ${result.data.nim}<br>
             Nama: ${result.data.nama}<br>
             Confidence: ${(result.data.confidence * 100).toFixed(1)}%<br>
             Response Time: ${responseTime.toFixed(0)}ms<br>
             Validation: ${result.data.validation_result?.checks_passed || 0}/${result.data.validation_result?.total_checks || 0} passed`,
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
    
    updateResponseTime(responseTime) {
        const responseTimeElement = document.getElementById('responseTime');
        if (responseTimeElement) {
            responseTimeElement.textContent = `${responseTime.toFixed(0)}ms`;
            if (responseTime < 500) {
                responseTimeElement.className = 'metric-value ultra-fast';
            } else if (responseTime < 1000) {
                responseTimeElement.className = 'metric-value fast';
            } else if (responseTime < 2000) {
                responseTimeElement.className = 'metric-value medium';
            } else {
                responseTimeElement.className = 'metric-value slow';
            }
        }
    }
    
    updateConfidence(confidence) {
        const confidenceElement = document.getElementById('confidenceValue');
        if (confidenceElement) {
            confidenceElement.textContent = `${(confidence * 100).toFixed(1)}%`;
            if (confidence > 0.95) {
                confidenceElement.className = 'metric-value ultra-fast';
            } else if (confidence > 0.90) {
                confidenceElement.className = 'metric-value fast';
            } else if (confidence > 0.80) {
                confidenceElement.className = 'metric-value medium';
            } else {
                confidenceElement.className = 'metric-value slow';
            }
        }
    }
    
    updateValidation(validationResult) {
        const validationElement = document.getElementById('validationValue');
        if (validationElement && validationResult) {
            const passed = validationResult.checks_passed || 0;
            const total = validationResult.total_checks || 0;
            validationElement.textContent = `${passed}/${total}`;
            validationElement.className = passed >= 4 ? 'metric-value pass' : 'metric-value fail';
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
        // Update performance stats every 3 seconds
        setInterval(() => {
            this.updatePerformanceStats();
        }, 3000);
    }
    
    updatePerformanceStats() {
        // Update cache hit rate
        const totalCacheRequests = this.performanceStats.cacheHits + this.performanceStats.cacheMisses;
        if (totalCacheRequests > 0) {
            const hitRate = (this.performanceStats.cacheHits / totalCacheRequests) * 100;
            // You can display this in UI if needed
        }
    }
    
    showMessage(message, type) {
        // Create or update message element
        let messageElement = document.getElementById('ultraAccurateMessage');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.id = 'ultraAccurateMessage';
            messageElement.className = 'ultra-accurate-message';
            document.body.appendChild(messageElement);
        }
        
        messageElement.innerHTML = message;
        messageElement.className = `ultra-accurate-message ${type}`;
        messageElement.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, 5000);
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

// Initialize ultra accurate FaceNet when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.ultraAccurateFaceNet = new UltraAccurateFaceNet();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UltraAccurateFaceNet;
}

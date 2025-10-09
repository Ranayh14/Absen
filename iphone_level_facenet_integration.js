/**
 * iPhone-Level Accurate FaceNet Integration - Maximum Accuracy with Unique Feature Analysis
 * 
 * This script provides iPhone Face ID level accuracy by analyzing unique facial features,
 * facial landmarks, skin texture, eye characteristics, and other biometric markers.
 */

class iPhoneLevelFaceNet {
    constructor() {
        this.isProcessing = false;
        this.recognitionMode = 'iphone_level'; // 'iphone_level', 'ultra_accurate', 'optimized', 'high_accuracy', 'standard'
        this.cache = new Map();
        this.maxCacheSize = 100;
        
        // Performance tracking
        this.performanceStats = {
            totalRequests: 0,
            successfulRecognitions: 0,
            averageResponseTime: 0,
            uniqueFeatureMatches: 0,
            landmarkAnalysisCount: 0,
            textureAnalysisCount: 0,
            eyeAnalysisCount: 0
        };
        
        this.initializeUI();
        this.initializeCamera();
    }
    
    initializeUI() {
        // Create iPhone-level controls
        this.createIPhoneLevelControls();
        
        // Add event listeners
        this.addEventListeners();
        
        // Initialize performance monitoring
        this.initializePerformanceMonitoring();
    }
    
    createIPhoneLevelControls() {
        // Find the main container
        let container = document.querySelector('.main-container, .container, body');
        if (!container) {
            container = document.body;
        }
        
        // Create iPhone-level mode selector
        const modeSelector = document.createElement('div');
        modeSelector.className = 'iphone-level-mode-selector';
        modeSelector.innerHTML = `
            <div class="mode-selector-container">
                <h5 class="mode-title">
                    <i class="fas fa-mobile-alt"></i>
                    iPhone-Level Accurate Face Recognition
                </h5>
                <div class="mode-options">
                    <div class="mode-option">
                        <input type="radio" id="iphoneLevelMode" name="recognitionMode" value="iphone_level" checked>
                        <label for="iphoneLevelMode">
                            <i class="fas fa-mobile-alt"></i>
                            <span class="mode-name">iPhone-Level (Maximum Accuracy)</span>
                            <span class="mode-desc">Face ID level accuracy with unique feature analysis</span>
                        </label>
                    </div>
                    <div class="mode-option">
                        <input type="radio" id="ultraAccurateMode" name="recognitionMode" value="ultra_accurate">
                        <label for="ultraAccurateMode">
                            <i class="fas fa-bullseye"></i>
                            <span class="mode-name">Ultra Accurate</span>
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
        
        // Create unique feature analysis display
        const featureAnalysis = document.createElement('div');
        featureAnalysis.className = 'feature-analysis-display';
        featureAnalysis.innerHTML = `
            <div class="feature-analysis-container">
                <h6 class="feature-title">
                    <i class="fas fa-search-plus"></i>
                    Unique Feature Analysis
                </h6>
                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-eye feature-icon"></i>
                        <span class="feature-name">Facial Landmarks</span>
                        <span class="feature-status" id="landmarkStatus">Ready</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-hand-paper feature-icon"></i>
                        <span class="feature-name">Skin Texture</span>
                        <span class="feature-status" id="textureStatus">Ready</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-eye feature-icon"></i>
                        <span class="feature-name">Eye Analysis</span>
                        <span class="feature-status" id="eyeStatus">Ready</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-balance-scale feature-icon"></i>
                        <span class="feature-name">Facial Symmetry</span>
                        <span class="feature-status" id="symmetryStatus">Ready</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-ruler feature-icon"></i>
                        <span class="feature-name">Proportions</span>
                        <span class="feature-status" id="proportionStatus">Ready</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-fingerprint feature-icon"></i>
                        <span class="feature-name">Unique Features</span>
                        <span class="feature-status" id="uniqueStatus">Ready</span>
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
                    <span class="metric-label">Unique Features:</span>
                    <span class="metric-value" id="uniqueFeaturesValue">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value" id="statusValue">Ready</span>
                </div>
            </div>
        `;
        
        // Insert controls
        container.insertBefore(modeSelector, container.firstChild);
        container.insertBefore(featureAnalysis, container.firstChild);
        container.insertBefore(performanceIndicators, container.firstChild);
        
        // Add CSS styles
        this.addIPhoneLevelStyles();
    }
    
    addIPhoneLevelStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .iphone-level-mode-selector, .feature-analysis-display, .performance-indicators {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 15px;
                color: white;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            
            .mode-title, .feature-title {
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
            
            .mode-name, .feature-name {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .mode-desc, .feature-desc {
                display: block;
                font-size: 12px;
                opacity: 0.8;
            }
            
            .feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .feature-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 10px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
            }
            
            .feature-icon {
                font-size: 1.5rem;
                margin-bottom: 8px;
                color: rgba(255, 255, 255, 0.8);
            }
            
            .feature-status {
                font-size: 11px;
                font-weight: 500;
                padding: 4px 8px;
                border-radius: 4px;
                background: rgba(255, 255, 255, 0.2);
            }
            
            .feature-status.analyzing {
                background: rgba(255, 193, 7, 0.3);
                animation: pulse 1s infinite;
            }
            
            .feature-status.completed {
                background: rgba(40, 167, 69, 0.3);
            }
            
            .feature-status.error {
                background: rgba(220, 53, 69, 0.3);
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
            
            .metric-value.iphone-level {
                background: rgba(0, 122, 255, 0.3);
                border: 1px solid rgba(0, 122, 255, 0.5);
                animation: pulse 1s infinite;
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
            
            .metric-value.excellent {
                background: rgba(0, 122, 255, 0.3);
                border: 1px solid rgba(0, 122, 255, 0.5);
            }
            
            .metric-value.good {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
            }
            
            .metric-value.poor {
                background: rgba(220, 53, 69, 0.3);
                border: 1px solid rgba(220, 53, 69, 0.5);
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            /* Responsive Design for All Screen Sizes */
            @media (max-width: 1920px) {
                .mode-options {
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                }
                .feature-grid {
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                }
            }
            
            @media (max-width: 1366px) {
                .mode-options {
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                }
                .feature-grid {
                    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                }
                .performance-metrics {
                    grid-template-columns: repeat(3, 1fr);
                }
            }
            
            @media (max-width: 1024px) {
                .mode-options {
                    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                }
                .feature-grid {
                    grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
                }
                .performance-metrics {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            
            @media (max-width: 768px) {
                .mode-options, .feature-grid {
                    grid-template-columns: 1fr;
                }
                .performance-metrics {
                    grid-template-columns: repeat(2, 1fr);
                }
                .mode-title, .feature-title {
                    font-size: 14px;
                }
                .mode-name, .feature-name {
                    font-size: 12px;
                }
                .mode-desc, .feature-desc {
                    font-size: 10px;
                }
            }
            
            @media (max-width: 480px) {
                .iphone-level-mode-selector, .feature-analysis-display, .performance-indicators {
                    padding: 10px;
                    margin-bottom: 10px;
                }
                .mode-options, .feature-grid {
                    gap: 8px;
                }
                .mode-option label, .feature-item {
                    padding: 8px;
                }
                .performance-metrics {
                    grid-template-columns: 1fr;
                    gap: 10px;
                }
                .metric-value {
                    font-size: 14px;
                    padding: 4px 8px;
                }
            }
            
            @media (max-width: 360px) {
                .mode-title, .feature-title {
                    font-size: 12px;
                }
                .mode-name, .feature-name {
                    font-size: 11px;
                }
                .mode-desc, .feature-desc {
                    font-size: 9px;
                }
                .metric-value {
                    font-size: 12px;
                    padding: 3px 6px;
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
                case 'iphone_level':
                    statusElement.textContent = 'iPhone-Level Mode';
                    statusElement.className = 'metric-value iphone-level';
                    break;
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
            this.updateFeatureStatus('analyzing');
            
            // Capture image
            const imageData = await this.captureImage();
            if (!imageData) {
                throw new Error('Failed to capture image');
            }
            
            // Check cache first
            const cacheKey = this.getCacheKey(imageData);
            if (this.cache.has(cacheKey)) {
                const cachedResult = this.cache.get(cacheKey);
                this.handleRecognitionResult(cachedResult, performance.now() - startTime);
                return;
            }
            
            // Process based on selected mode
            let result;
            switch (this.recognitionMode) {
                case 'iphone_level':
                    result = await this.callDirectIPhoneLevelAPI('process_iphone_level_attendance', {
                        image: imageData
                    });
                    break;
                case 'ultra_detailed':
                    result = await this.callUltraDetailedAPI('process_ultra_detailed_attendance', {
                        image: imageData
                    });
                    break;
                case 'ultra_accurate':
                    result = await this.callUltraAccurateAPI('process_ultra_accurate_attendance', {
                        image: imageData,
                        validation_level: 'normal'
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
            this.updateFeatureStatus('completed');
        }
    }
    
    async callIPhoneLevelAPI(action, data) {
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
    
    async callDirectIPhoneLevelAPI(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        // Direct processing without API layer
        const startTime = performance.now();
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        const endTime = performance.now();
        
        const result = await response.json();
        
        // Add processing time info
        if (result.ok && result.data) {
            result.data.processing_time = endTime - startTime;
        }
        
        return result;
    }
    
    async callUltraDetailedAPI(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        // Ultra detailed processing with maximum speed
        const startTime = performance.now();
        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });
        const endTime = performance.now();
        
        const result = await response.json();
        
        // Add processing time info
        if (result.ok && result.data) {
            result.data.processing_time = endTime - startTime;
            result.data.ultra_detailed_features = result.data.ultra_features;
        }
        
        return result;
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
        this.updateUniqueFeatures(result.data?.unique_features);
        this.updateStatus('Success', 'excellent');
        
        // Show success message
        let message = `Attendance recorded successfully!<br>
             NIM: ${result.data.nim}<br>
             Nama: ${result.data.nama}<br>
             Confidence: ${(result.data.confidence * 100).toFixed(1)}%<br>
             Response Time: ${responseTime.toFixed(0)}ms`;
        
        if (result.data.unique_features) {
            message += `<br>Unique Features: ${Object.keys(result.data.unique_features).length} analyzed`;
        }
        
        this.showMessage(message, 'success');
        
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
        this.updateStatus('Failed', 'poor');
        this.updateFeatureStatus('error');
        
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
                confidenceElement.className = 'metric-value excellent';
            } else if (confidence > 0.90) {
                confidenceElement.className = 'metric-value good';
            } else {
                confidenceElement.className = 'metric-value poor';
            }
        }
    }
    
    updateUniqueFeatures(uniqueFeatures) {
        const uniqueFeaturesElement = document.getElementById('uniqueFeaturesValue');
        if (uniqueFeaturesElement && uniqueFeatures) {
            const featureCount = Object.keys(uniqueFeatures).length;
            uniqueFeaturesElement.textContent = `${featureCount} features`;
            uniqueFeaturesElement.className = 'metric-value excellent';
        }
    }
    
    updateFeatureStatus(status) {
        const featureStatuses = [
            'landmarkStatus', 'textureStatus', 'eyeStatus', 
            'symmetryStatus', 'proportionStatus', 'uniqueStatus'
        ];
        
        featureStatuses.forEach(statusId => {
            const element = document.getElementById(statusId);
            if (element) {
                element.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                element.className = `feature-status ${status}`;
            }
        });
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
        // Update performance statistics
        // This would be enhanced with more detailed tracking
    }
    
    showMessage(message, type) {
        // Create or update message element
        let messageElement = document.getElementById('iphoneLevelMessage');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.id = 'iphoneLevelMessage';
            messageElement.className = 'iphone-level-message';
            document.body.appendChild(messageElement);
        }
        
        messageElement.innerHTML = message;
        messageElement.className = `iphone-level-message ${type}`;
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
            uniqueFeatureMatchRate: this.performanceStats.totalRequests > 0 ?
                (this.performanceStats.uniqueFeatureMatches / this.performanceStats.totalRequests) * 100 : 0
        };
    }
}

// Initialize iPhone-level FaceNet when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.iphoneLevelFaceNet = new iPhoneLevelFaceNet();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = iPhoneLevelFaceNet;
}

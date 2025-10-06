/**
 * High Accuracy FaceNet Integration
 * 
 * This script provides high-accuracy face recognition with strict quality validation
 * and confidence thresholds to ensure only reliable recognitions are accepted.
 */

class HighAccuracyFaceNet {
    constructor() {
        this.isProcessing = false;
        this.qualityThreshold = 0.80; // 80% minimum quality score
        this.confidenceThreshold = 0.90; // 90% minimum confidence
        this.maxAttempts = 3;
        this.attemptCount = 0;
        this.lastAttemptTime = 0;
        this.cooldownPeriod = 30000; // 30 seconds
        
        // Performance tracking
        this.stats = {
            totalAttempts: 0,
            successfulRecognitions: 0,
            qualityRejections: 0,
            confidenceRejections: 0,
            rateLimitRejections: 0
        };
        
        this.initializeUI();
    }
    
    initializeUI() {
        // Create high accuracy controls
        this.createHighAccuracyControls();
        
        // Add event listeners
        this.addEventListeners();
        
        // Initialize camera with quality settings
        this.initializeHighQualityCamera();
    }
    
    createHighAccuracyControls() {
        // Create high accuracy mode toggle
        const highAccuracyToggle = document.createElement('div');
        highAccuracyToggle.className = 'high-accuracy-toggle';
        highAccuracyToggle.innerHTML = `
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="highAccuracyMode" checked>
                <label class="form-check-label" for="highAccuracyMode">
                    <i class="fas fa-shield-alt"></i> High Accuracy Mode (90% confidence)
                </label>
            </div>
        `;
        
        // Create quality indicators
        const qualityIndicators = document.createElement('div');
        qualityIndicators.className = 'quality-indicators';
        qualityIndicators.innerHTML = `
            <div class="quality-metrics">
                <div class="metric">
                    <span class="metric-label">Confidence:</span>
                    <span class="metric-value" id="confidenceValue">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Quality:</span>
                    <span class="metric-value" id="qualityValue">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value" id="statusValue">Ready</span>
                </div>
            </div>
        `;
        
        // Create attempt counter
        const attemptCounter = document.createElement('div');
        attemptCounter.className = 'attempt-counter';
        attemptCounter.innerHTML = `
            <div class="attempt-info">
                <span class="attempt-label">Attempts:</span>
                <span class="attempt-value" id="attemptValue">0/3</span>
            </div>
            <div class="cooldown-info" id="cooldownInfo" style="display: none;">
                <span class="cooldown-label">Cooldown:</span>
                <span class="cooldown-value" id="cooldownValue">0s</span>
            </div>
        `;
        
        // Insert controls into the main container
        const mainContainer = document.querySelector('.main-container') || document.body;
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'high-accuracy-controls';
        controlsContainer.appendChild(highAccuracyToggle);
        controlsContainer.appendChild(qualityIndicators);
        controlsContainer.appendChild(attemptCounter);
        
        mainContainer.insertBefore(controlsContainer, mainContainer.firstChild);
    }
    
    addEventListeners() {
        // High accuracy mode toggle
        const highAccuracyMode = document.getElementById('highAccuracyMode');
        if (highAccuracyMode) {
            highAccuracyMode.addEventListener('change', (e) => {
                this.toggleHighAccuracyMode(e.target.checked);
            });
        }
        
        // Override existing attendance button
        const attendanceButton = document.querySelector('#attendanceButton, .attendance-btn');
        if (attendanceButton) {
            attendanceButton.addEventListener('click', (e) => {
                if (this.isHighAccuracyMode()) {
                    e.preventDefault();
                    this.processHighAccuracyAttendance();
                }
            });
        }
        
        // Override existing embedding generation
        const embeddingButton = document.querySelector('#generateEmbedding, .embedding-btn');
        if (embeddingButton) {
            embeddingButton.addEventListener('click', (e) => {
                if (this.isHighAccuracyMode()) {
                    e.preventDefault();
                    this.generateHighAccuracyEmbedding();
                }
            });
        }
    }
    
    initializeHighQualityCamera() {
        // Enhanced camera settings for high quality
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
                    console.error('High quality camera initialization failed:', error);
                });
        }
    }
    
    isHighAccuracyMode() {
        const toggle = document.getElementById('highAccuracyMode');
        return toggle ? toggle.checked : true;
    }
    
    toggleHighAccuracyMode(enabled) {
        const statusValue = document.getElementById('statusValue');
        if (statusValue) {
            statusValue.textContent = enabled ? 'High Accuracy Mode' : 'Standard Mode';
            statusValue.className = enabled ? 'metric-value high-accuracy' : 'metric-value standard';
        }
        
        // Update UI elements
        const controls = document.querySelector('.high-accuracy-controls');
        if (controls) {
            controls.style.display = enabled ? 'block' : 'none';
        }
    }
    
    async processHighAccuracyAttendance() {
        if (this.isProcessing) {
            this.showMessage('Processing in progress...', 'warning');
            return;
        }
        
        // Check rate limiting
        if (!this.checkRateLimit()) {
            return;
        }
        
        this.isProcessing = true;
        this.attemptCount++;
        this.stats.totalAttempts++;
        
        try {
            this.updateStatus('Processing...', 'processing');
            this.updateAttemptCounter();
            
            // Capture image
            const imageData = await this.captureHighQualityImage();
            if (!imageData) {
                throw new Error('Failed to capture image');
            }
            
            // Process with high accuracy service
            const result = await this.callHighAccuracyAPI('process_high_accuracy_attendance', {
                image: imageData
            });
            
            if (result.ok && result.data) {
                this.handleSuccessfulRecognition(result.data);
            } else {
                this.handleRecognitionFailure(result.error || 'Recognition failed');
            }
            
        } catch (error) {
            console.error('High accuracy attendance error:', error);
            this.handleRecognitionFailure(error.message);
        } finally {
            this.isProcessing = false;
            this.lastAttemptTime = Date.now();
        }
    }
    
    async generateHighAccuracyEmbedding() {
        if (this.isProcessing) {
            this.showMessage('Processing in progress...', 'warning');
            return;
        }
        
        this.isProcessing = true;
        
        try {
            this.updateStatus('Generating embedding...', 'processing');
            
            // Capture image
            const imageData = await this.captureHighQualityImage();
            if (!imageData) {
                throw new Error('Failed to capture image');
            }
            
            // Generate embedding with high accuracy service
            const result = await this.callHighAccuracyAPI('generate_high_accuracy_embedding', {
                image: imageData
            });
            
            if (result.ok && result.data) {
                this.handleSuccessfulEmbedding(result.data);
            } else {
                this.handleEmbeddingFailure(result.error || 'Embedding generation failed');
            }
            
        } catch (error) {
            console.error('High accuracy embedding error:', error);
            this.handleEmbeddingFailure(error.message);
        } finally {
            this.isProcessing = false;
        }
    }
    
    async captureHighQualityImage() {
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
    
    handleSuccessfulRecognition(data) {
        this.stats.successfulRecognitions++;
        this.attemptCount = 0; // Reset on success
        
        // Update quality metrics
        this.updateQualityMetrics(data.confidence, data.quality_score);
        
        // Show success message
        this.showMessage(
            `Attendance recorded successfully!<br>
             NIM: ${data.nim}<br>
             Nama: ${data.nama}<br>
             Confidence: ${(data.confidence * 100).toFixed(1)}%<br>
             Quality: ${(data.quality_score * 100).toFixed(1)}%`,
            'success'
        );
        
        // Update status
        this.updateStatus('Success', 'success');
        
        // Refresh attendance data if available
        if (typeof refreshAttendanceData === 'function') {
            refreshAttendanceData();
        }
    }
    
    handleRecognitionFailure(error) {
        let errorType = 'error';
        let errorMessage = error;
        
        // Categorize error types
        if (error.includes('confidence') || error.includes('Confidence')) {
            this.stats.confidenceRejections++;
            errorType = 'confidence-error';
        } else if (error.includes('quality') || error.includes('Quality')) {
            this.stats.qualityRejections++;
            errorType = 'quality-error';
        } else if (error.includes('rate limit') || error.includes('cooldown')) {
            this.stats.rateLimitRejections++;
            errorType = 'rate-limit-error';
        }
        
        // Show error message
        this.showMessage(error, errorType);
        
        // Update status
        this.updateStatus('Failed', 'error');
        
        // Check if max attempts reached
        if (this.attemptCount >= this.maxAttempts) {
            this.startCooldown();
        }
    }
    
    handleSuccessfulEmbedding(data) {
        // Update quality metrics
        this.updateQualityMetrics(1.0, data.quality_score);
        
        // Show success message
        this.showMessage(
            `High-quality face embedding generated!<br>
             Quality Score: ${(data.quality_score * 100).toFixed(1)}%<br>
             User ID: ${data.user_id}`,
            'success'
        );
        
        // Update status
        this.updateStatus('Embedding Generated', 'success');
    }
    
    handleEmbeddingFailure(error) {
        this.showMessage(error, 'error');
        this.updateStatus('Failed', 'error');
    }
    
    updateQualityMetrics(confidence, quality) {
        const confidenceValue = document.getElementById('confidenceValue');
        const qualityValue = document.getElementById('qualityValue');
        
        if (confidenceValue) {
            confidenceValue.textContent = `${(confidence * 100).toFixed(1)}%`;
            confidenceValue.className = confidence >= this.confidenceThreshold ? 
                'metric-value good' : 'metric-value poor';
        }
        
        if (qualityValue) {
            qualityValue.textContent = `${(quality * 100).toFixed(1)}%`;
            qualityValue.className = quality >= this.qualityThreshold ? 
                'metric-value good' : 'metric-value poor';
        }
    }
    
    updateStatus(status, type) {
        const statusValue = document.getElementById('statusValue');
        if (statusValue) {
            statusValue.textContent = status;
            statusValue.className = `metric-value ${type}`;
        }
    }
    
    updateAttemptCounter() {
        const attemptValue = document.getElementById('attemptValue');
        if (attemptValue) {
            attemptValue.textContent = `${this.attemptCount}/${this.maxAttempts}`;
        }
    }
    
    checkRateLimit() {
        const now = Date.now();
        const timeSinceLastAttempt = now - this.lastAttemptTime;
        
        if (timeSinceLastAttempt < this.cooldownPeriod) {
            const remainingTime = Math.ceil((this.cooldownPeriod - timeSinceLastAttempt) / 1000);
            this.showMessage(`Please wait ${remainingTime} seconds before trying again`, 'rate-limit-error');
            return false;
        }
        
        if (this.attemptCount >= this.maxAttempts) {
            this.showMessage('Maximum attempts reached. Please wait before trying again.', 'rate-limit-error');
            return false;
        }
        
        return true;
    }
    
    startCooldown() {
        const cooldownInfo = document.getElementById('cooldownInfo');
        const cooldownValue = document.getElementById('cooldownValue');
        
        if (cooldownInfo && cooldownValue) {
            cooldownInfo.style.display = 'block';
            
            let remainingTime = this.cooldownPeriod / 1000;
            const countdown = setInterval(() => {
                cooldownValue.textContent = `${remainingTime}s`;
                remainingTime--;
                
                if (remainingTime < 0) {
                    clearInterval(countdown);
                    cooldownInfo.style.display = 'none';
                    this.attemptCount = 0;
                    this.updateAttemptCounter();
                    this.updateStatus('Ready', 'ready');
                }
            }, 1000);
        }
    }
    
    showMessage(message, type) {
        // Create or update message element
        let messageElement = document.getElementById('highAccuracyMessage');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.id = 'highAccuracyMessage';
            messageElement.className = 'high-accuracy-message';
            document.body.appendChild(messageElement);
        }
        
        messageElement.innerHTML = message;
        messageElement.className = `high-accuracy-message ${type}`;
        messageElement.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, 5000);
    }
    
    getPerformanceStats() {
        return {
            ...this.stats,
            successRate: this.stats.totalAttempts > 0 ? 
                (this.stats.successfulRecognitions / this.stats.totalAttempts) * 100 : 0,
            thresholds: {
                confidence: this.confidenceThreshold,
                quality: this.qualityThreshold,
                maxAttempts: this.maxAttempts,
                cooldownPeriod: this.cooldownPeriod
            }
        };
    }
}

// Initialize high accuracy FaceNet when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.highAccuracyFaceNet = new HighAccuracyFaceNet();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HighAccuracyFaceNet;
}

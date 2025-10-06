/**
 * Enhanced FaceNet Integration for Existing System
 * 
 * This script integrates the enhanced FaceNet system into the existing
 * attendance system, providing backward compatibility while adding
 * advanced facial feature analysis capabilities.
 */

class EnhancedFaceNetIntegration {
    constructor() {
        this.isEnhancedMode = true;
        this.fallbackEnabled = true;
        this.debugMode = false;
        this.recognitionHistory = [];
        this.featureAnalysisEnabled = true;
        
        // API endpoints
        this.endpoints = {
            processAttendance: 'process_attendance_enhanced',
            generateEmbedding: 'generate_face_embedding_enhanced',
            getStats: 'get_enhanced_stats',
            migrate: 'migrate_to_enhanced',
            toggleMode: 'toggle_enhanced_mode',
            setFallback: 'set_fallback_mode'
        };
        
        this.initializeIntegration();
    }
    
    /**
     * Initialize the integration
     */
    initializeIntegration() {
        console.log('Enhanced FaceNet Integration initialized');
        
        // Add enhanced mode indicator
        this.addEnhancedModeIndicator();
        
        // Override existing functions
        this.overrideExistingFunctions();
        
        // Add enhanced UI elements
        this.addEnhancedUIElements();
        
        // Load system stats
        this.loadSystemStats();
    }
    
    /**
     * Add enhanced mode indicator to the page
     */
    addEnhancedModeIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'enhanced-mode-indicator';
        indicator.className = 'enhanced-mode-indicator';
        indicator.innerHTML = `
            <div class="mode-status">
                <span class="mode-icon">🔍</span>
                <span class="mode-text">Enhanced Mode: ON</span>
            </div>
            <div class="mode-controls">
                <button class="btn-toggle" onclick="enhancedFaceNet.toggleEnhancedMode()">Toggle</button>
            </div>
        `;
        
        // Insert at the top of the page
        const body = document.body;
        body.insertBefore(indicator, body.firstChild);
        
        // Add CSS for the indicator
        this.addEnhancedModeStyles();
    }
    
    /**
     * Add CSS styles for enhanced mode indicator
     */
    addEnhancedModeStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .enhanced-mode-indicator {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 10px 15px;
                border-radius: 25px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 0.9em;
                font-weight: 600;
            }
            
            .mode-status {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .mode-icon {
                font-size: 1.1em;
            }
            
            .btn-toggle {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                cursor: pointer;
                font-size: 0.8em;
                transition: background 0.3s ease;
            }
            
            .btn-toggle:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            
            .enhanced-mode-indicator.standard {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            
            .enhanced-mode-indicator.standard .mode-text::after {
                content: " (Standard)";
                opacity: 0.8;
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Override existing functions to use enhanced mode
     */
    overrideExistingFunctions() {
        // Override the existing face recognition function
        if (typeof window.processFaceRecognition === 'function') {
            window.originalProcessFaceRecognition = window.processFaceRecognition;
            window.processFaceRecognition = (imageData) => this.processEnhancedFaceRecognition(imageData);
        }
        
        // Override the existing attendance processing function
        if (typeof window.processAttendance === 'function') {
            window.originalProcessAttendance = window.processAttendance;
            window.processAttendance = (imageData) => this.processEnhancedAttendance(imageData);
        }
        
        // Override the existing embedding generation function
        if (typeof window.generateFaceEmbedding === 'function') {
            window.originalGenerateFaceEmbedding = window.generateFaceEmbedding;
            window.generateFaceEmbedding = (imageData) => this.generateEnhancedEmbedding(imageData);
        }
    }
    
    /**
     * Add enhanced UI elements to the page
     */
    addEnhancedUIElements() {
        // Add enhanced controls panel
        this.addEnhancedControlsPanel();
        
        // Add feature analysis display
        this.addFeatureAnalysisDisplay();
        
        // Add recognition history
        this.addRecognitionHistory();
        
        // Add system statistics
        this.addSystemStatistics();
    }
    
    /**
     * Add enhanced controls panel
     */
    addEnhancedControlsPanel() {
        const controlsPanel = document.createElement('div');
        controlsPanel.id = 'enhanced-controls-panel';
        controlsPanel.className = 'enhanced-controls-panel';
        controlsPanel.innerHTML = `
            <div class="controls-header">
                <h4>🔍 Enhanced FaceNet Controls</h4>
                <button class="btn-collapse" onclick="enhancedFaceNet.toggleControlsPanel()">−</button>
            </div>
            <div class="controls-content">
                <div class="control-group">
                    <label for="enhanced-threshold">Recognition Threshold:</label>
                    <input type="range" id="enhanced-threshold" min="0.1" max="2.0" step="0.1" value="1.0">
                    <span id="threshold-value">1.0</span>
                </div>
                <div class="control-group">
                    <label for="show-feature-analysis">Show Feature Analysis:</label>
                    <input type="checkbox" id="show-feature-analysis" checked>
                </div>
                <div class="control-group">
                    <label for="fallback-mode">Fallback to Standard:</label>
                    <input type="checkbox" id="fallback-mode" checked>
                </div>
                <div class="control-actions">
                    <button class="btn btn-primary" onclick="enhancedFaceNet.toggleEnhancedMode()">
                        Toggle Enhanced Mode
                    </button>
                    <button class="btn btn-secondary" onclick="enhancedFaceNet.loadSystemStats()">
                        Refresh Stats
                    </button>
                </div>
            </div>
        `;
        
        // Insert after the main content
        const mainContent = document.querySelector('.main-content') || document.querySelector('main') || document.body;
        mainContent.appendChild(controlsPanel);
        
        // Add event listeners
        this.addControlEventListeners();
        
        // Add CSS for controls panel
        this.addControlsPanelStyles();
    }
    
    /**
     * Add event listeners for controls
     */
    addControlEventListeners() {
        // Threshold slider
        const thresholdSlider = document.getElementById('enhanced-threshold');
        const thresholdValue = document.getElementById('threshold-value');
        
        if (thresholdSlider && thresholdValue) {
            thresholdSlider.addEventListener('input', function() {
                const value = this.value;
                thresholdValue.textContent = value;
                enhancedFaceNet.setThreshold(parseFloat(value));
            });
        }
        
        // Feature analysis checkbox
        const featureAnalysisCheckbox = document.getElementById('show-feature-analysis');
        if (featureAnalysisCheckbox) {
            featureAnalysisCheckbox.addEventListener('change', function() {
                enhancedFaceNet.toggleFeatureAnalysis(this.checked);
            });
        }
        
        // Fallback mode checkbox
        const fallbackCheckbox = document.getElementById('fallback-mode');
        if (fallbackCheckbox) {
            fallbackCheckbox.addEventListener('change', function() {
                enhancedFaceNet.setFallbackMode(this.checked);
            });
        }
    }
    
    /**
     * Add CSS styles for controls panel
     */
    addControlsPanelStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .enhanced-controls-panel {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 15px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                overflow: hidden;
            }
            
            .controls-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.1);
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .controls-header h4 {
                margin: 0;
                font-size: 1.2em;
            }
            
            .btn-collapse {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                padding: 5px 10px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.2em;
            }
            
            .controls-content {
                padding: 20px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .control-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .control-group label {
                font-weight: 600;
                color: rgba(255, 255, 255, 0.9);
            }
            
            .control-group input[type="range"] {
                width: 100%;
            }
            
            .control-group input[type="checkbox"] {
                width: 20px;
                height: 20px;
            }
            
            .control-actions {
                grid-column: 1 / -1;
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-top: 10px;
            }
            
            .control-actions .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-primary {
                background: linear-gradient(45deg, #11998e, #38ef7d);
                color: white;
            }
            
            .btn-secondary {
                background: linear-gradient(45deg, #f093fb, #f5576c);
                color: white;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            }
            
            .enhanced-controls-panel.collapsed .controls-content {
                display: none;
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Add feature analysis display
     */
    addFeatureAnalysisDisplay() {
        const analysisDisplay = document.createElement('div');
        analysisDisplay.id = 'enhanced-feature-analysis';
        analysisDisplay.className = 'enhanced-feature-analysis';
        analysisDisplay.style.display = 'none';
        
        const mainContent = document.querySelector('.main-content') || document.querySelector('main') || document.body;
        mainContent.appendChild(analysisDisplay);
    }
    
    /**
     * Add recognition history
     */
    addRecognitionHistory() {
        const historyDisplay = document.createElement('div');
        historyDisplay.id = 'enhanced-recognition-history';
        historyDisplay.className = 'enhanced-recognition-history';
        historyDisplay.style.display = 'none';
        
        const mainContent = document.querySelector('.main-content') || document.querySelector('main') || document.body;
        mainContent.appendChild(historyDisplay);
    }
    
    /**
     * Add system statistics
     */
    addSystemStatistics() {
        const statsDisplay = document.createElement('div');
        statsDisplay.id = 'enhanced-system-stats';
        statsDisplay.className = 'enhanced-system-stats';
        statsDisplay.innerHTML = `
            <div class="stats-header">
                <h4>📊 Enhanced FaceNet Statistics</h4>
                <button class="btn-refresh" onclick="enhancedFaceNet.loadSystemStats()">🔄</button>
            </div>
            <div class="stats-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="total-users">-</div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="standard-coverage">-</div>
                        <div class="stat-label">Standard Coverage</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="enhanced-coverage">-</div>
                        <div class="stat-label">Enhanced Coverage</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="recognition-count">0</div>
                        <div class="stat-label">Recognitions</div>
                    </div>
                </div>
            </div>
        `;
        
        const mainContent = document.querySelector('.main-content') || document.querySelector('main') || document.body;
        mainContent.appendChild(statsDisplay);
        
        // Add CSS for stats
        this.addStatsStyles();
    }
    
    /**
     * Add CSS styles for statistics
     */
    addStatsStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .enhanced-system-stats {
                background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
                color: white;
                border-radius: 15px;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }
            
            .stats-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.1);
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .stats-header h4 {
                margin: 0;
                font-size: 1.2em;
            }
            
            .btn-refresh {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                padding: 8px 12px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.1em;
            }
            
            .stats-content {
                padding: 20px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
            }
            
            .stat-item {
                text-align: center;
                background: rgba(255, 255, 255, 0.1);
                padding: 15px;
                border-radius: 10px;
                backdrop-filter: blur(10px);
            }
            
            .stat-value {
                font-size: 2em;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 0.9em;
                opacity: 0.9;
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Process enhanced face recognition
     */
    async processEnhancedFaceRecognition(imageData) {
        try {
            console.log('Processing enhanced face recognition...');
            
            const response = await fetch(`index.php?ajax=${this.endpoints.processAttendance}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image=${encodeURIComponent(imageData)}&threshold=${this.threshold || 1.0}`
            });
            
            const result = await response.json();
            
            if (result.ok) {
                this.addToRecognitionHistory(result.data);
                this.showFeatureAnalysis(result.data);
                this.updateRecognitionCount();
                return result.data;
            } else {
                throw new Error(result.error || 'Enhanced face recognition failed');
            }
            
        } catch (error) {
            console.error('Enhanced face recognition error:', error);
            this.showError('Enhanced face recognition failed: ' + error.message);
            return null;
        }
    }
    
    /**
     * Process enhanced attendance
     */
    async processEnhancedAttendance(imageData) {
        const result = await this.processEnhancedFaceRecognition(imageData);
        
        if (result && result.recognized) {
            // Show attendance success
            this.showAttendanceSuccess(result);
        } else {
            // Show attendance failure
            this.showAttendanceFailure(result);
        }
        
        return result;
    }
    
    /**
     * Generate enhanced embedding
     */
    async generateEnhancedEmbedding(imageData) {
        try {
            console.log('Generating enhanced embedding...');
            
            const response = await fetch(`index.php?ajax=${this.endpoints.generateEmbedding}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image=${encodeURIComponent(imageData)}`
            });
            
            const result = await response.json();
            
            if (result.ok) {
                this.showSuccess('Enhanced face embedding generated successfully');
                return result.data;
            } else {
                throw new Error(result.error || 'Failed to generate enhanced embedding');
            }
            
        } catch (error) {
            console.error('Enhanced embedding generation error:', error);
            this.showError('Enhanced embedding generation failed: ' + error.message);
            return null;
        }
    }
    
    /**
     * Load system statistics
     */
    async loadSystemStats() {
        try {
            const response = await fetch(`index.php?ajax=${this.endpoints.getStats}`);
            const result = await response.json();
            
            if (result.ok) {
                this.updateSystemStats(result.data);
            }
            
        } catch (error) {
            console.error('Error loading system stats:', error);
        }
    }
    
    /**
     * Update system statistics display
     */
    updateSystemStats(stats) {
        const elements = {
            'total-users': stats.total_users || 0,
            'standard-coverage': (stats.standard_coverage || 0).toFixed(1) + '%',
            'enhanced-coverage': (stats.enhanced_coverage || 0).toFixed(1) + '%',
            'recognition-count': this.recognitionHistory.length
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
    
    /**
     * Add to recognition history
     */
    addToRecognitionHistory(data) {
        this.recognitionHistory.unshift({
            timestamp: new Date().toISOString(),
            data: data,
            recognized: data.recognized
        });
        
        // Keep only last 10 entries
        if (this.recognitionHistory.length > 10) {
            this.recognitionHistory = this.recognitionHistory.slice(0, 10);
        }
        
        this.updateRecognitionHistory();
    }
    
    /**
     * Update recognition history display
     */
    updateRecognitionHistory() {
        const historyDisplay = document.getElementById('enhanced-recognition-history');
        if (!historyDisplay) return;
        
        if (this.recognitionHistory.length === 0) {
            historyDisplay.style.display = 'none';
            return;
        }
        
        let html = `
            <div class="history-header">
                <h4>📊 Recognition History</h4>
            </div>
            <div class="history-list">
        `;
        
        this.recognitionHistory.forEach((entry, index) => {
            const time = new Date(entry.timestamp).toLocaleTimeString();
            const status = entry.recognized ? '✅' : '❌';
            const name = entry.recognized ? entry.data.nama : 'Unknown';
            const score = entry.recognized ? (entry.data.similarity_score * 100).toFixed(1) + '%' : 'N/A';
            
            html += `
                <div class="history-item">
                    <span class="history-time">${time}</span>
                    <span class="history-status">${status}</span>
                    <span class="history-name">${name}</span>
                    <span class="history-score">${score}</span>
                </div>
            `;
        });
        
        html += `
            </div>
        `;
        
        historyDisplay.innerHTML = html;
        historyDisplay.style.display = 'block';
    }
    
    /**
     * Show feature analysis
     */
    showFeatureAnalysis(data) {
        if (!this.featureAnalysisEnabled) return;
        
        const analysisDisplay = document.getElementById('enhanced-feature-analysis');
        if (!analysisDisplay) return;
        
        if (data && data.feature_analysis) {
            const analysis = data.feature_analysis;
            const geometry = analysis.geometry || {};
            
            let html = `
                <div class="analysis-header">
                    <h4>🔍 Enhanced Facial Feature Analysis</h4>
                </div>
                <div class="analysis-grid">
                    <div class="analysis-section">
                        <h5>📏 Face Geometry</h5>
                        <div class="feature-item">
                            <span class="feature-label">Face Width:</span>
                            <span class="feature-value">${geometry.face_width ? geometry.face_width.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Face Height:</span>
                            <span class="feature-value">${geometry.face_height ? geometry.face_height.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Forehead Width:</span>
                            <span class="feature-value">${geometry.forehead_width ? geometry.forehead_width.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Face Shape:</span>
                            <span class="feature-value feature-shape">${geometry.face_shape || 'Unknown'}</span>
                        </div>
                    </div>
                    
                    <div class="analysis-section">
                        <h5>👁️ Eye Analysis</h5>
                        <div class="feature-item">
                            <span class="feature-label">Eye Width:</span>
                            <span class="feature-value">${geometry.eye_analysis?.eye_width ? geometry.eye_analysis.eye_width.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Eye Spacing:</span>
                            <span class="feature-value">${geometry.eye_analysis?.eye_spacing ? geometry.eye_analysis.eye_spacing.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Eye Shape:</span>
                            <span class="feature-value feature-shape">${geometry.eye_analysis?.eye_shape || 'Unknown'}</span>
                        </div>
                    </div>
                    
                    <div class="analysis-section">
                        <h5>👃 Nose Analysis</h5>
                        <div class="feature-item">
                            <span class="feature-label">Nose Width:</span>
                            <span class="feature-value">${geometry.nose_analysis?.nose_width ? geometry.nose_analysis.nose_width.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Nose Height:</span>
                            <span class="feature-value">${geometry.nose_analysis?.nose_height ? geometry.nose_analysis.nose_height.toFixed(1) : 'N/A'}px</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Nose Shape:</span>
                            <span class="feature-value feature-shape">${geometry.nose_analysis?.nose_shape || 'Unknown'}</span>
                        </div>
                    </div>
                    
                    <div class="analysis-section">
                        <h5>⚖️ Quality Metrics</h5>
                        <div class="feature-item">
                            <span class="feature-label">Symmetry Score:</span>
                            <span class="feature-value">${geometry.symmetry_score ? (geometry.symmetry_score * 100).toFixed(1) + '%' : 'N/A'}</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Face Angle:</span>
                            <span class="feature-value">${geometry.face_angle ? geometry.face_angle.toFixed(1) + '°' : 'N/A'}</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-label">Landmarks:</span>
                            <span class="feature-value">${geometry.landmarks_count || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
            
            analysisDisplay.innerHTML = html;
            analysisDisplay.style.display = 'block';
        }
    }
    
    /**
     * Show attendance success
     */
    showAttendanceSuccess(data) {
        this.showSuccess(`Attendance processed successfully for ${data.nama}`);
    }
    
    /**
     * Show attendance failure
     */
    showAttendanceFailure(data) {
        this.showError(`Attendance failed: ${data.message || 'Face not recognized'}`);
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        console.log('Success:', message);
        // You can implement a toast notification here
        alert('Success: ' + message);
    }
    
    /**
     * Show error message
     */
    showError(message) {
        console.error('Error:', message);
        // You can implement a toast notification here
        alert('Error: ' + message);
    }
    
    /**
     * Update recognition count
     */
    updateRecognitionCount() {
        const countElement = document.getElementById('recognition-count');
        if (countElement) {
            countElement.textContent = this.recognitionHistory.length;
        }
    }
    
    /**
     * Toggle enhanced mode
     */
    async toggleEnhancedMode() {
        try {
            const response = await fetch(`index.php?ajax=${this.endpoints.toggleMode}`, {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.ok) {
                this.isEnhancedMode = result.data.enabled;
                this.updateModeIndicator();
                this.showSuccess(`Enhanced mode ${this.isEnhancedMode ? 'enabled' : 'disabled'}`);
            }
            
        } catch (error) {
            console.error('Error toggling enhanced mode:', error);
            this.showError('Failed to toggle enhanced mode');
        }
    }
    
    /**
     * Update mode indicator
     */
    updateModeIndicator() {
        const indicator = document.getElementById('enhanced-mode-indicator');
        if (indicator) {
            const modeText = indicator.querySelector('.mode-text');
            if (modeText) {
                modeText.textContent = `Enhanced Mode: ${this.isEnhancedMode ? 'ON' : 'OFF'}`;
            }
            
            indicator.className = `enhanced-mode-indicator ${this.isEnhancedMode ? 'enhanced' : 'standard'}`;
        }
    }
    
    /**
     * Toggle controls panel
     */
    toggleControlsPanel() {
        const panel = document.getElementById('enhanced-controls-panel');
        if (panel) {
            panel.classList.toggle('collapsed');
        }
    }
    
    /**
     * Toggle feature analysis display
     */
    toggleFeatureAnalysis(enabled) {
        this.featureAnalysisEnabled = enabled;
        
        const analysisDisplay = document.getElementById('enhanced-feature-analysis');
        if (analysisDisplay) {
            analysisDisplay.style.display = enabled ? 'block' : 'none';
        }
    }
    
    /**
     * Set threshold
     */
    setThreshold(threshold) {
        this.threshold = threshold;
        console.log('Threshold set to:', threshold);
    }
    
    /**
     * Set fallback mode
     */
    async setFallbackMode(enabled) {
        try {
            const response = await fetch(`index.php?ajax=${this.endpoints.setFallback}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `enabled=${enabled}`
            });
            
            const result = await response.json();
            
            if (result.ok) {
                this.fallbackEnabled = result.data.enabled;
                this.showSuccess(`Fallback mode ${this.fallbackEnabled ? 'enabled' : 'disabled'}`);
            }
            
        } catch (error) {
            console.error('Error setting fallback mode:', error);
            this.showError('Failed to set fallback mode');
        }
    }
    
    /**
     * Get recognition statistics
     */
    getRecognitionStats() {
        const total = this.recognitionHistory.length;
        const recognized = this.recognitionHistory.filter(entry => entry.recognized).length;
        const successRate = total > 0 ? (recognized / total * 100).toFixed(1) : 0;
        
        return {
            total: total,
            recognized: recognized,
            failed: total - recognized,
            successRate: successRate
        };
    }
}

// Initialize enhanced FaceNet integration when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.enhancedFaceNet = new EnhancedFaceNetIntegration();
    console.log('Enhanced FaceNet Integration loaded');
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedFaceNetIntegration;
}

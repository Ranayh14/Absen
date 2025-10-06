/**
 * FaceNet Enhanced Integration
 * 
 * JavaScript integration for enhanced FaceNet face recognition system
 * with detailed facial feature analysis including face width, forehead width,
 * face shape, nose shape, and other distinguishing characteristics.
 */

class FaceNetEnhanced {
    constructor() {
        this.isEnhancedMode = true;
        this.recognitionThreshold = 1.0;
        this.featureAnalysisEnabled = true;
        this.debugMode = false;
        
        // API endpoints
        this.endpoints = {
            generateEmbedding: 'generate_enhanced_face_embedding',
            recognizeFace: 'recognize_enhanced_face',
            processAttendance: 'process_enhanced_attendance'
        };
        
        // Feature analysis results
        this.lastFeatureAnalysis = null;
        this.recognitionHistory = [];
    }
    
    /**
     * Generate enhanced face embedding with detailed feature analysis
     */
    async generateEnhancedEmbedding(imageData) {
        try {
            console.log('Generating enhanced face embedding...');
            
            const response = await fetch(`index.php?ajax=${this.endpoints.generateEmbedding}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image=${encodeURIComponent(imageData)}`
            });
            
            const result = await response.json();
            
            if (result.ok) {
                console.log('Enhanced embedding generated successfully');
                this.showFeatureAnalysis(result.data);
                return result;
            } else {
                throw new Error(result.error || 'Failed to generate enhanced embedding');
            }
            
        } catch (error) {
            console.error('Error generating enhanced embedding:', error);
            this.showError('Failed to generate enhanced face embedding: ' + error.message);
            return null;
        }
    }
    
    /**
     * Recognize face using enhanced features
     */
    async recognizeEnhancedFace(imageData, threshold = null) {
        try {
            console.log('Recognizing face with enhanced features...');
            
            const requestBody = `image=${encodeURIComponent(imageData)}`;
            const thresholdParam = threshold ? `&threshold=${threshold}` : '';
            
            const response = await fetch(`index.php?ajax=${this.endpoints.recognizeFace}${thresholdParam}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: requestBody
            });
            
            const result = await response.json();
            
            if (result.ok) {
                console.log('Enhanced face recognition completed');
                this.lastFeatureAnalysis = result.data.feature_analysis;
                this.addToRecognitionHistory(result.data);
                this.showRecognitionResult(result.data);
                return result.data;
            } else {
                throw new Error(result.error || 'Enhanced face recognition failed');
            }
            
        } catch (error) {
            console.error('Error in enhanced face recognition:', error);
            this.showError('Enhanced face recognition failed: ' + error.message);
            return null;
        }
    }
    
    /**
     * Process attendance using enhanced face recognition
     */
    async processEnhancedAttendance(imageData, threshold = null) {
        try {
            console.log('Processing attendance with enhanced recognition...');
            
            const requestBody = `image=${encodeURIComponent(imageData)}`;
            const thresholdParam = threshold ? `&threshold=${threshold}` : '';
            
            const response = await fetch(`index.php?ajax=${this.endpoints.processAttendance}${thresholdParam}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: requestBody
            });
            
            const result = await response.json();
            
            if (result.ok) {
                console.log('Enhanced attendance processing completed');
                this.lastFeatureAnalysis = result.data.feature_analysis;
                this.addToRecognitionHistory(result.data);
                this.showAttendanceResult(result.data);
                return result.data;
            } else {
                throw new Error(result.error || 'Enhanced attendance processing failed');
            }
            
        } catch (error) {
            console.error('Error in enhanced attendance processing:', error);
            this.showError('Enhanced attendance processing failed: ' + error.message);
            return null;
        }
    }
    
    /**
     * Show detailed feature analysis results
     */
    showFeatureAnalysis(data) {
        if (!this.featureAnalysisEnabled) return;
        
        const analysisContainer = document.getElementById('feature-analysis-container') || this.createFeatureAnalysisContainer();
        
        if (data && data.feature_analysis) {
            const analysis = data.feature_analysis;
            const geometry = analysis.geometry || {};
            
            let html = `
                <div class="feature-analysis">
                    <h4>🔍 Enhanced Facial Feature Analysis</h4>
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
                                <span class="feature-label">Face Ratio:</span>
                                <span class="feature-value">${geometry.face_ratio ? geometry.face_ratio.toFixed(2) : 'N/A'}</span>
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
                                <span class="feature-label">Eye Height:</span>
                                <span class="feature-value">${geometry.eye_analysis?.eye_height ? geometry.eye_analysis.eye_height.toFixed(1) : 'N/A'}px</span>
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
                            <h5>👄 Mouth Analysis</h5>
                            <div class="feature-item">
                                <span class="feature-label">Mouth Width:</span>
                                <span class="feature-value">${geometry.mouth_analysis?.mouth_width ? geometry.mouth_analysis.mouth_width.toFixed(1) : 'N/A'}px</span>
                            </div>
                            <div class="feature-item">
                                <span class="feature-label">Mouth Height:</span>
                                <span class="feature-value">${geometry.mouth_analysis?.mouth_height ? geometry.mouth_analysis.mouth_height.toFixed(1) : 'N/A'}px</span>
                            </div>
                            <div class="feature-item">
                                <span class="feature-label">Mouth Shape:</span>
                                <span class="feature-value feature-shape">${geometry.mouth_analysis?.mouth_shape || 'Unknown'}</span>
                            </div>
                        </div>
                        
                        <div class="analysis-section">
                            <h5>🦴 Jaw Analysis</h5>
                            <div class="feature-item">
                                <span class="feature-label">Jaw Width:</span>
                                <span class="feature-value">${geometry.jaw_analysis?.jaw_width ? geometry.jaw_analysis.jaw_width.toFixed(1) : 'N/A'}px</span>
                            </div>
                            <div class="feature-item">
                                <span class="feature-label">Jaw Shape:</span>
                                <span class="feature-value feature-shape">${geometry.jaw_analysis?.jaw_shape || 'Unknown'}</span>
                            </div>
                        </div>
                        
                        <div class="analysis-section">
                            <h5>⚖️ Symmetry & Quality</h5>
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
                </div>
            `;
            
            analysisContainer.innerHTML = html;
            analysisContainer.style.display = 'block';
        }
    }
    
    /**
     * Show recognition result with enhanced details
     */
    showRecognitionResult(data) {
        const resultContainer = document.getElementById('recognition-result') || this.createRecognitionResultContainer();
        
        if (data.recognized) {
            const comparisonDetails = data.comparison_details || {};
            
            let html = `
                <div class="recognition-success">
                    <h4>✅ Face Recognized Successfully</h4>
                    <div class="user-info">
                        <div class="user-details">
                            <h5>👤 ${data.nama}</h5>
                            <p><strong>NIM:</strong> ${data.nim}</p>
                            <p><strong>Email:</strong> ${data.email}</p>
                        </div>
                        <div class="recognition-metrics">
                            <div class="metric-item">
                                <span class="metric-label">Similarity Score:</span>
                                <span class="metric-value high-confidence">${(data.similarity_score * 100).toFixed(1)}%</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Confidence:</span>
                                <span class="metric-value high-confidence">${(data.confidence * 100).toFixed(1)}%</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-label">Threshold:</span>
                                <span class="metric-value">${data.threshold}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="comparison-details">
                        <h5>🔍 Detailed Comparison</h5>
                        <div class="comparison-grid">
                            <div class="comparison-item">
                                <span class="comparison-label">FaceNet Similarity:</span>
                                <span class="comparison-value">${(comparisonDetails.facenet_similarity * 100).toFixed(1)}%</span>
                            </div>
                            <div class="comparison-item">
                                <span class="comparison-label">Advanced Features:</span>
                                <span class="comparison-value">${(comparisonDetails.advanced_similarity * 100).toFixed(1)}%</span>
                            </div>
                            <div class="comparison-item">
                                <span class="comparison-label">Geometric Similarity:</span>
                                <span class="comparison-value">${(comparisonDetails.geometric_similarity * 100).toFixed(1)}%</span>
                            </div>
                            <div class="comparison-item">
                                <span class="comparison-label">Combined Score:</span>
                                <span class="comparison-value high-confidence">${(comparisonDetails.combined_score * 100).toFixed(1)}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            resultContainer.innerHTML = html;
        } else {
            let html = `
                <div class="recognition-failed">
                    <h4>❌ Face Not Recognized</h4>
                    <p><strong>Reason:</strong> ${data.message}</p>
                    ${data.similarity_score ? `<p><strong>Best Match Score:</strong> ${(data.similarity_score * 100).toFixed(1)}%</p>` : ''}
                    ${data.threshold ? `<p><strong>Threshold:</strong> ${data.threshold}</p>` : ''}
                </div>
            `;
            
            resultContainer.innerHTML = html;
        }
        
        resultContainer.style.display = 'block';
    }
    
    /**
     * Show attendance result
     */
    showAttendanceResult(data) {
        this.showRecognitionResult(data);
        
        // Add attendance-specific actions
        if (data.recognized) {
            this.showAttendanceActions(data);
        }
    }
    
    /**
     * Show attendance actions
     */
    showAttendanceActions(data) {
        const actionsContainer = document.getElementById('attendance-actions') || this.createAttendanceActionsContainer();
        
        let html = `
            <div class="attendance-actions">
                <h5>📝 Attendance Actions</h5>
                <div class="action-buttons">
                    <button class="btn btn-success" onclick="processAttendance('${data.nim}', '${data.nama}')">
                        ✅ Mark Attendance
                    </button>
                    <button class="btn btn-info" onclick="viewUserProfile('${data.nim}')">
                        👤 View Profile
                    </button>
                    <button class="btn btn-warning" onclick="updateFaceData('${data.nim}')">
                        🔄 Update Face Data
                    </button>
                </div>
            </div>
        `;
        
        actionsContainer.innerHTML = html;
        actionsContainer.style.display = 'block';
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
        const historyContainer = document.getElementById('recognition-history') || this.createRecognitionHistoryContainer();
        
        if (this.recognitionHistory.length === 0) {
            historyContainer.style.display = 'none';
            return;
        }
        
        let html = `
            <div class="recognition-history">
                <h5>📊 Recognition History</h5>
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
            </div>
        `;
        
        historyContainer.innerHTML = html;
        historyContainer.style.display = 'block';
    }
    
    /**
     * Create feature analysis container
     */
    createFeatureAnalysisContainer() {
        const container = document.createElement('div');
        container.id = 'feature-analysis-container';
        container.className = 'feature-analysis-container';
        container.style.display = 'none';
        
        // Insert after the main content
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.appendChild(container);
        
        return container;
    }
    
    /**
     * Create recognition result container
     */
    createRecognitionResultContainer() {
        const container = document.createElement('div');
        container.id = 'recognition-result';
        container.className = 'recognition-result-container';
        container.style.display = 'none';
        
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.appendChild(container);
        
        return container;
    }
    
    /**
     * Create attendance actions container
     */
    createAttendanceActionsContainer() {
        const container = document.createElement('div');
        container.id = 'attendance-actions';
        container.className = 'attendance-actions-container';
        container.style.display = 'none';
        
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.appendChild(container);
        
        return container;
    }
    
    /**
     * Create recognition history container
     */
    createRecognitionHistoryContainer() {
        const container = document.createElement('div');
        container.id = 'recognition-history';
        container.className = 'recognition-history-container';
        container.style.display = 'none';
        
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.appendChild(container);
        
        return container;
    }
    
    /**
     * Show error message
     */
    showError(message) {
        console.error(message);
        
        // You can implement a toast notification or modal here
        alert('Error: ' + message);
    }
    
    /**
     * Toggle enhanced mode
     */
    toggleEnhancedMode() {
        this.isEnhancedMode = !this.isEnhancedMode;
        console.log('Enhanced mode:', this.isEnhancedMode ? 'ON' : 'OFF');
        
        // Update UI to reflect mode change
        const modeIndicator = document.getElementById('enhanced-mode-indicator');
        if (modeIndicator) {
            modeIndicator.textContent = this.isEnhancedMode ? 'Enhanced Mode: ON' : 'Enhanced Mode: OFF';
            modeIndicator.className = this.isEnhancedMode ? 'mode-indicator enhanced' : 'mode-indicator standard';
        }
    }
    
    /**
     * Set recognition threshold
     */
    setThreshold(threshold) {
        this.recognitionThreshold = threshold;
        console.log('Recognition threshold set to:', threshold);
    }
    
    /**
     * Toggle feature analysis display
     */
    toggleFeatureAnalysis() {
        this.featureAnalysisEnabled = !this.featureAnalysisEnabled;
        console.log('Feature analysis display:', this.featureAnalysisEnabled ? 'ON' : 'OFF');
        
        const analysisContainer = document.getElementById('feature-analysis-container');
        if (analysisContainer) {
            analysisContainer.style.display = this.featureAnalysisEnabled ? 'block' : 'none';
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

// Global instance
const faceNetEnhanced = new FaceNetEnhanced();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceNetEnhanced;
}

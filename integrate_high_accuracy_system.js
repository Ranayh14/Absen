/**
 * High Accuracy System Integration
 * 
 * This script integrates the high-accuracy FaceNet system into the existing
 * attendance system with 90% confidence threshold and quality validation.
 */

class HighAccuracySystemIntegration {
    constructor() {
        this.isHighAccuracyMode = false;
        this.userSettings = {};
        this.systemStatus = {};
        this.performanceStats = {};
        
        this.initializeIntegration();
    }
    
    async initializeIntegration() {
        try {
            // Check if user is logged in
            if (!this.isUserLoggedIn()) {
                console.log('User not logged in, skipping high accuracy integration');
                return;
            }
            
            // Load user settings and system status
            await this.loadUserSettings();
            await this.checkSystemStatus();
            
            // Initialize UI elements
            this.initializeUI();
            
            // Override existing functions
            this.overrideExistingFunctions();
            
            console.log('High accuracy system integration initialized');
            
        } catch (error) {
            console.error('Error initializing high accuracy integration:', error);
        }
    }
    
    isUserLoggedIn() {
        // Check if user session exists (adjust based on your session management)
        return document.cookie.includes('PHPSESSID') || 
               localStorage.getItem('user_id') || 
               window.userSession;
    }
    
    async loadUserSettings() {
        try {
            const response = await fetch('integrate_high_accuracy_system.php?action=get_high_accuracy_status');
            const result = await response.json();
            
            if (result.success) {
                this.userSettings = result.data;
                this.isHighAccuracyMode = result.data.high_accuracy_mode;
                this.performanceStats = result.data.performance_stats || {};
                this.systemStatus = result.data.system_status || {};
            }
            
        } catch (error) {
            console.error('Error loading user settings:', error);
        }
    }
    
    async checkSystemStatus() {
        try {
            const response = await fetch('integrate_high_accuracy_system.php?action=test_high_accuracy');
            const result = await response.json();
            
            if (result.success) {
                this.systemStatus = result.data;
            }
            
        } catch (error) {
            console.error('Error checking system status:', error);
        }
    }
    
    initializeUI() {
        // Create high accuracy mode toggle
        this.createHighAccuracyToggle();
        
        // Create quality indicators
        this.createQualityIndicators();
        
        // Create system status indicator
        this.createSystemStatusIndicator();
        
        // Add CSS styles
        this.addIntegrationStyles();
    }
    
    createHighAccuracyToggle() {
        // Find the main container or create one
        let container = document.querySelector('.main-container, .container, body');
        if (!container) {
            container = document.body;
        }
        
        // Create high accuracy toggle
        const toggleContainer = document.createElement('div');
        toggleContainer.className = 'high-accuracy-integration';
        toggleContainer.innerHTML = `
            <div class="high-accuracy-toggle-container">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="highAccuracyToggle" ${this.isHighAccuracyMode ? 'checked' : ''}>
                    <label class="form-check-label" for="highAccuracyToggle">
                        <i class="fas fa-shield-alt"></i>
                        High Accuracy Mode (90% confidence)
                    </label>
                </div>
                <div class="high-accuracy-info">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Only high-quality recognitions will be accepted
                    </small>
                </div>
            </div>
        `;
        
        // Insert at the top of the container
        container.insertBefore(toggleContainer, container.firstChild);
        
        // Add event listener
        const toggle = document.getElementById('highAccuracyToggle');
        if (toggle) {
            toggle.addEventListener('change', (e) => {
                this.toggleHighAccuracyMode(e.target.checked);
            });
        }
    }
    
    createQualityIndicators() {
        const container = document.querySelector('.high-accuracy-integration');
        if (!container) return;
        
        const indicatorsContainer = document.createElement('div');
        indicatorsContainer.className = 'quality-indicators-container';
        indicatorsContainer.innerHTML = `
            <div class="quality-metrics">
                <div class="metric">
                    <span class="metric-label">Confidence:</span>
                    <span class="metric-value" id="confidenceIndicator">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Quality:</span>
                    <span class="metric-value" id="qualityIndicator">--</span>
                </div>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value" id="statusIndicator">Ready</span>
                </div>
            </div>
        `;
        
        container.appendChild(indicatorsContainer);
    }
    
    createSystemStatusIndicator() {
        const container = document.querySelector('.high-accuracy-integration');
        if (!container) return;
        
        const statusContainer = document.createElement('div');
        statusContainer.className = 'system-status-container';
        statusContainer.innerHTML = `
            <div class="system-status">
                <span class="status-label">System Status:</span>
                <span class="status-indicator" id="systemStatusIndicator">
                    <i class="fas fa-circle"></i>
                    Checking...
                </span>
            </div>
        `;
        
        container.appendChild(statusContainer);
        
        // Update system status
        this.updateSystemStatus();
    }
    
    addIntegrationStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .high-accuracy-integration {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 20px;
                color: white;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            
            .high-accuracy-toggle-container {
                margin-bottom: 10px;
            }
            
            .high-accuracy-toggle-container .form-check-label {
                color: white;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .high-accuracy-toggle-container .form-check-input {
                width: 20px;
                height: 20px;
                background-color: rgba(255, 255, 255, 0.3);
                border: 2px solid rgba(255, 255, 255, 0.5);
            }
            
            .high-accuracy-toggle-container .form-check-input:checked {
                background-color: #28a745;
                border-color: #28a745;
            }
            
            .high-accuracy-info {
                margin-top: 5px;
            }
            
            .high-accuracy-info small {
                color: rgba(255, 255, 255, 0.8);
            }
            
            .quality-indicators-container {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 10px;
                margin-top: 10px;
            }
            
            .quality-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 10px;
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
                margin-bottom: 3px;
            }
            
            .metric-value {
                color: white;
                font-size: 14px;
                font-weight: 700;
                padding: 4px 8px;
                border-radius: 6px;
                min-width: 60px;
                background: rgba(255, 255, 255, 0.2);
            }
            
            .metric-value.good {
                background: rgba(40, 167, 69, 0.3);
                border: 1px solid rgba(40, 167, 69, 0.5);
            }
            
            .metric-value.poor {
                background: rgba(220, 53, 69, 0.3);
                border: 1px solid rgba(220, 53, 69, 0.5);
            }
            
            .metric-value.processing {
                background: rgba(255, 193, 7, 0.3);
                border: 1px solid rgba(255, 193, 7, 0.5);
                animation: pulse 1.5s infinite;
            }
            
            .system-status-container {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .system-status {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .status-label {
                color: rgba(255, 255, 255, 0.8);
                font-size: 12px;
                font-weight: 500;
            }
            
            .status-indicator {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .status-indicator .fa-circle {
                font-size: 8px;
            }
            
            .status-indicator.ready .fa-circle {
                color: #28a745;
            }
            
            .status-indicator.warning .fa-circle {
                color: #ffc107;
            }
            
            .status-indicator.error .fa-circle {
                color: #dc3545;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            @media (max-width: 768px) {
                .quality-metrics {
                    grid-template-columns: 1fr;
                }
                
                .high-accuracy-integration {
                    padding: 12px;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    async toggleHighAccuracyMode(enabled) {
        try {
            this.updateStatus('Updating...', 'processing');
            
            const action = enabled ? 'enable_high_accuracy' : 'disable_high_accuracy';
            const response = await fetch('integrate_high_accuracy_system.php?action=' + action, {
                method: 'POST'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.isHighAccuracyMode = enabled;
                this.updateStatus(enabled ? 'High Accuracy Mode' : 'Standard Mode', 'ready');
                
                // Show success message
                this.showMessage(
                    enabled ? 'High accuracy mode enabled' : 'High accuracy mode disabled',
                    'success'
                );
                
                // Reload settings
                await this.loadUserSettings();
                
            } else {
                this.updateStatus('Error', 'error');
                this.showMessage('Failed to update high accuracy mode', 'error');
            }
            
        } catch (error) {
            console.error('Error toggling high accuracy mode:', error);
            this.updateStatus('Error', 'error');
            this.showMessage('Error updating high accuracy mode', 'error');
        }
    }
    
    updateStatus(status, type) {
        const statusIndicator = document.getElementById('statusIndicator');
        if (statusIndicator) {
            statusIndicator.textContent = status;
            statusIndicator.className = `metric-value ${type}`;
        }
    }
    
    updateSystemStatus() {
        const systemStatusIndicator = document.getElementById('systemStatusIndicator');
        if (!systemStatusIndicator) return;
        
        if (this.systemStatus.overall_status) {
            systemStatusIndicator.innerHTML = '<i class="fas fa-circle"></i> Ready';
            systemStatusIndicator.className = 'status-indicator ready';
        } else {
            systemStatusIndicator.innerHTML = '<i class="fas fa-circle"></i> Issues Detected';
            systemStatusIndicator.className = 'status-indicator warning';
        }
    }
    
    updateQualityMetrics(confidence, quality) {
        const confidenceIndicator = document.getElementById('confidenceIndicator');
        const qualityIndicator = document.getElementById('qualityIndicator');
        
        if (confidenceIndicator) {
            confidenceIndicator.textContent = `${(confidence * 100).toFixed(1)}%`;
            confidenceIndicator.className = confidence >= 0.90 ? 'metric-value good' : 'metric-value poor';
        }
        
        if (qualityIndicator) {
            qualityIndicator.textContent = `${(quality * 100).toFixed(1)}%`;
            qualityIndicator.className = quality >= 0.80 ? 'metric-value good' : 'metric-value poor';
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
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, 3000);
    }
    
    overrideExistingFunctions() {
        // Override existing attendance function if it exists
        if (typeof window.processAttendance === 'function') {
            const originalProcessAttendance = window.processAttendance;
            window.processAttendance = async (imageData) => {
                if (this.isHighAccuracyMode) {
                    return await this.processHighAccuracyAttendance(imageData);
                } else {
                    return await originalProcessAttendance(imageData);
                }
            };
        }
        
        // Override existing embedding generation if it exists
        if (typeof window.generateEmbedding === 'function') {
            const originalGenerateEmbedding = window.generateEmbedding;
            window.generateEmbedding = async (imageData) => {
                if (this.isHighAccuracyMode) {
                    return await this.generateHighAccuracyEmbedding(imageData);
                } else {
                    return await originalGenerateEmbedding(imageData);
                }
            };
        }
    }
    
    async processHighAccuracyAttendance(imageData) {
        try {
            this.updateStatus('Processing...', 'processing');
            
            const formData = new FormData();
            formData.append('action', 'process_high_accuracy_attendance');
            formData.append('image', imageData);
            
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.ok && result.data) {
                this.updateQualityMetrics(result.data.confidence, result.data.quality_score);
                this.updateStatus('Success', 'good');
                
                this.showMessage(
                    `Attendance recorded successfully!<br>
                     NIM: ${result.data.nim}<br>
                     Confidence: ${(result.data.confidence * 100).toFixed(1)}%<br>
                     Quality: ${(result.data.quality_score * 100).toFixed(1)}%`,
                    'success'
                );
                
                return result.data;
            } else {
                this.updateStatus('Failed', 'poor');
                this.showMessage(result.error || 'High accuracy attendance failed', 'error');
                return null;
            }
            
        } catch (error) {
            console.error('High accuracy attendance error:', error);
            this.updateStatus('Error', 'poor');
            this.showMessage('Error processing high accuracy attendance', 'error');
            return null;
        }
    }
    
    async generateHighAccuracyEmbedding(imageData) {
        try {
            this.updateStatus('Generating...', 'processing');
            
            const formData = new FormData();
            formData.append('action', 'generate_high_accuracy_embedding');
            formData.append('image', imageData);
            
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.ok && result.data) {
                this.updateQualityMetrics(1.0, result.data.quality_score);
                this.updateStatus('Generated', 'good');
                
                this.showMessage(
                    `High-quality face embedding generated!<br>
                     Quality Score: ${(result.data.quality_score * 100).toFixed(1)}%`,
                    'success'
                );
                
                return result.data;
            } else {
                this.updateStatus('Failed', 'poor');
                this.showMessage(result.error || 'High accuracy embedding failed', 'error');
                return null;
            }
            
        } catch (error) {
            console.error('High accuracy embedding error:', error);
            this.updateStatus('Error', 'poor');
            this.showMessage('Error generating high accuracy embedding', 'error');
            return null;
        }
    }
    
    // Public methods for external use
    getHighAccuracyMode() {
        return this.isHighAccuracyMode;
    }
    
    getUserSettings() {
        return this.userSettings;
    }
    
    getSystemStatus() {
        return this.systemStatus;
    }
    
    getPerformanceStats() {
        return this.performanceStats;
    }
}

// Initialize integration when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.highAccuracyIntegration = new HighAccuracySystemIntegration();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HighAccuracySystemIntegration;
}

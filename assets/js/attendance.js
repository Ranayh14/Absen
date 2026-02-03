/**
 * Attendance System Logic
 * Consolidated and Optimized
 */

// Global variables
let video = document.getElementById('video');
let canvas = document.getElementById('overlay'); // Ensure ID matches HTML
let videoInterval;
let labeledFaceDescriptors = [];
let members = [];
let scanMode = null; // 'masuk' or 'pulang'
let isCameraActive = false;
let isPresensiSuccess = false;
// Global for speech synthesis
window.lastSpokenMessage = null;
let isDetectionPaused = false;
let isDetectionStopped = false;
let isProcessingRecognition = false;
let processedLabels = new Map();
let recognitionCompleted = false;
let logMasukData = [];
let logPulangData = [];
let currentRecognitionData = null;

// UI Elements (Lazy bound)
let loadingOverlay = document.getElementById('loading-overlay');
let presensiStatus = document.getElementById('presensi-status');
let scanButtonsContainer = document.getElementById('scan-buttons');
let videoContainer = document.getElementById('video-container');
let btnBackScan = document.getElementById('btn-back-scan');
let btnScanMasuk = document.getElementById('btn-scan-masuk');
let btnScanPulang = document.getElementById('btn-scan-pulang');

// Configuration
const detectionConfig = {
    faceMatcherThreshold: 0.45,
    recognitionThreshold: 0.45,
    qualityThreshold: 0.20,
    scoreThreshold: 0.5,
    inputSize: 320,
    minFaceSize: 50,
    maxFaces: 1,
    detectionThrottle: 100,
    strictMode: true,
    multiAttemptValidation: true,
    genderValidation: true
};

const performanceStats = {
    detectionCount: 0,
    totalDetectionTime: 0,
    averageDetectionTime: 0,
    lastDetectionTime: 0
};

// ---- Helpers ----
function qs(selector) { return document.querySelector(selector); }
function qsa(selector) { return document.querySelectorAll(selector); }

// Notification Wrapper
function statusMessage(msg, classes) {
    if (presensiStatus) {
        presensiStatus.textContent = msg;
        presensiStatus.className = `fixed bottom-10 left-1/2 -translate-x-1/2 bg-white text-gray-800 px-6 py-3 rounded-full font-medium shadow-xl z-70 animate-fade-in-up ${classes || ''}`;
        presensiStatus.classList.remove('hidden');
        
        // Speak if critical
        if (classes && (classes.includes('red') || classes.includes('green'))) {
             if (typeof speak === 'function') speak(msg);
        }
        
        // Auto hide after 5s
        setTimeout(() => {
            if (presensiStatus) presensiStatus.classList.add('hidden');
        }, 5000);
    } else {
        // Fallback
        if (typeof showNotif === 'function') showNotif(msg, classes.includes('green'));
    }
}

// Device Detection
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function detectDevicePerformance() {
    const cores = navigator.hardwareConcurrency || 4;
    const memory = navigator.deviceMemory || 4;
    if (cores <= 4 && memory <= 4) return 'low';
    if (cores <= 8 && memory <= 8) return 'medium';
    return 'high';
}

function getAdjustedRecognitionThreshold() {
    const perf = detectDevicePerformance();
    const isMobile = isMobileDevice();
    let threshold = detectionConfig.recognitionThreshold;
    if (isMobile) threshold += 0.05;
    if (perf === 'low') threshold += 0.02;
    return Math.min(0.6, threshold);
}

function getAdjustedQualityThreshold() {
    const perf = detectDevicePerformance();
    const isMobile = isMobileDevice();
    let threshold = detectionConfig.qualityThreshold;
    if (isMobile) threshold -= 0.05;
    if (perf === 'low') threshold -= 0.05;
    return Math.max(0.1, threshold);
}

function getAdjustedFaceMatcherThreshold() { return detectionConfig.faceMatcherThreshold; }

// ---- Face Recognition Setup ----

async function initializeFaceRecognition() {
    try {
        // Force CPU backend if WebGL is unstable or not supported
        // This fixes the "WebGL is not supported" error
        try {
            // Using CPU backend as WASM files are not present locally
            await faceapi.tf.setBackend('cpu');
            console.log('Force set backend to CPU for compatibility');
        } catch (e) {
            console.warn('Failed to set backend to CPU, falling back to default:', e);
        }

        await loadFaceApiModels();
        await loadLabeledFaceDescriptors();
        console.log('Face recognition initialized');
    } catch (error) {
        console.error('Failed to initialize face recognition:', error);
        statusMessage('Gagal memuat sistem pengenalan wajah', 'bg-red-100 text-red-700');
    }
}

async function loadFaceApiModels() {
    if (window.faceApiModelsLoaded) return;
    if (window.loadingFaceApiModels) {
        // Wait if already loading
        while (window.loadingFaceApiModels) {
            await new Promise(r => setTimeout(r, 100));
            if (window.faceApiModelsLoaded) return;
        }
    }
    
    window.loadingFaceApiModels = true;
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');
    
    const MODEL_URL = window.FACEAPI_MODEL_URL || 'assets/js/face-api-models';
    
    try {
        console.log('🚀 Initializing face recognition system...');
        
        // Ensure backend is ready
        await faceapi.tf.ready();
        
        // Try WebGL first for speed, fallback to CPU
        try {
            if (faceapi.tf.getBackend() !== 'webgl') {
                await faceapi.tf.setBackend('webgl');
            }
        } catch(e) {
            console.warn('WebGL not available, sticking with:', faceapi.tf.getBackend());
        }

        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL)
        ]);
        window.faceApiModelsLoaded = true;
    } catch (e) {
        console.error('Error loading models', e);
        throw e;
    } finally {
        window.loadingFaceApiModels = false;
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
    }
}

async function loadLabeledFaceDescriptors() {
    if (typeof api !== 'function') return;
    
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    const isLateReq = mode === 'late_req';
    
    try {
        const startTime = performance.now();
        console.log(`Starting descriptor load (Mode: ${isLateReq ? 'Limited' : 'Full'})...`);
        
        let membersToProcess = [];
        
        if (isLateReq) {
            // Optimized: Only load current user for late requests
            const res = await api('?ajax=get_current_user_descriptor', {}, { cache: true });
            if (res.ok && res.data) {
                membersToProcess = [res.data];
                members = [res.data];
            }
        } else {
            // Standard: Load all members (Kiosk mode)
            const res = await api('?ajax=get_members');
            members = res.data || [];
            membersToProcess = members;
        }

        if (membersToProcess.length === 0) return;

        // Try load from IndexedDB if available
        if (typeof idbGetDescriptors === 'function' && typeof computeMembersVersionKey === 'function') {
            const versionKey = await computeMembersVersionKey(membersToProcess);
            const cached = await idbGetDescriptors(versionKey);
            if (cached && Array.isArray(cached) && cached.length > 0) {
                labeledFaceDescriptors = cached.map(item => new faceapi.LabeledFaceDescriptors(
                    item.label,
                    item.descriptors.map(d => new Float32Array(d))
                ));
                console.log(`Loaded ${labeledFaceDescriptors.length} face descriptors from IDB cache in ${(performance.now() - startTime).toFixed(2)}ms`);
                return;
            }
        }

        labeledFaceDescriptors = [];
        const perfLevel = detectDevicePerformance();
        const batchSize = isLateReq ? 1 : (perfLevel === 'low' ? 3 : 10);
        
        for (let i = 0; i < membersToProcess.length; i += batchSize) {
            const batch = membersToProcess.slice(i, i + batchSize);
            const promises = batch.map(async m => {
                if (!m.foto_base64) return null;
                try {
                    const img = await faceapi.fetchImage(m.foto_base64);
                    const det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                    if (det) {
                        return new faceapi.LabeledFaceDescriptors(m.nim, [det.descriptor]);
                    }
                } catch (e) { console.warn('Failed to load face for', m.nama); }
                return null;
            });
            const results = await Promise.all(promises);
            labeledFaceDescriptors.push(...results.filter(r => r !== null));
            if (i + batchSize < membersToProcess.length) await new Promise(r => setTimeout(r, 50));
        }
        
        // Save to cache
        if (typeof idbSetDescriptors === 'function' && typeof computeMembersVersionKey === 'function') {
            const versionKey = await computeMembersVersionKey(membersToProcess);
            const toStore = labeledFaceDescriptors.map(ld => ({
                label: ld.label,
                descriptors: ld.descriptors.map(arr => Array.from(arr))
            }));
            await idbSetDescriptors(versionKey, toStore);
        }
        
        console.log(`Loaded ${labeledFaceDescriptors.length} face descriptors in ${(performance.now() - startTime).toFixed(2)}ms`);
    } catch (e) {
        console.error('Failed to load descriptors', e);
    }
}

// ---- Camera & Recognition Logic ----

async function startScan(mode) {
    scanMode = mode;
    isPresensiSuccess = false;
    isDetectionStopped = false;
    isDetectionPaused = false;
    isProcessingRecognition = false;
    currentRecognitionData = null;
    
    // UI Updates - GUARDED for safety
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');
    
    if (!window.faceApiModelsLoaded) {
        await loadFaceApiModels();
    }
    
    // Ensure descriptors are loaded (database of known faces)
    if (labeledFaceDescriptors.length === 0) {
        await loadLabeledFaceDescriptors();
    }

    if (loadingOverlay) loadingOverlay.classList.add('hidden');

    if (scanButtonsContainer) scanButtonsContainer.classList.add('hidden');
    if (videoContainer) videoContainer.classList.remove('hidden');
    if (btnBackScan) btnBackScan.classList.remove('hidden');
    
    const stopBtn = qs('#btn-stop-detection');
    if (stopBtn) stopBtn.classList.remove('hidden');

    // Show appropriate log table
    const logMasuk = qs('#log-masuk-container');
    const logPulang = qs('#log-pulang-container');
    
    if (mode === 'masuk') {
        if (logMasuk) logMasuk.classList.remove('hidden');
        if (logPulang) logPulang.classList.add('hidden');
        loadLogMasuk();
    } else {
        if (logPulang) logPulang.classList.remove('hidden');
        if (logMasuk) logMasuk.classList.add('hidden');
        loadLogPulang();
    }

    statusMessage('Memulai kamera...', 'bg-blue-100 text-blue-700');
    startVideo();
}

async function startVideo() {
    if (!video) return;
    try {
        const perfLevel = detectDevicePerformance();
        let constraints = {
            video: {
                facingMode: 'user'
                // Remove strict ideal constraints to prevent timeout on some devices
            }
        };

        // Tuning for low end
        if (perfLevel === 'low') {
            constraints.video.width = { ideal: 320 };
            constraints.video.height = { ideal: 240 };
        }

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = stream;
        isCameraActive = true;
        video.onloadedmetadata = () => {
            video.play();
            startVideoInterval();
        };
    } catch (err) {
        console.error('Camera error:', err);
        statusMessage('Gagal mengakses kamera: ' + err.message, 'bg-red-100 text-red-700');
    }
}

function stopVideo() {
    if (video && video.srcObject) {
        video.srcObject.getTracks().forEach(t => t.stop());
        video.srcObject = null;
    }
    isCameraActive = false;
    if (videoInterval) clearInterval(videoInterval);
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function resetPresensiPage() {
    stopVideo();
    isPresensiSuccess = false;
    if (scanButtonsContainer) scanButtonsContainer.classList.remove('hidden');
    if (videoContainer) videoContainer.classList.add('hidden');
    if (btnBackScan) btnBackScan.classList.add('hidden');
    const stopBtn = qs('#btn-stop-detection');
    if (stopBtn) stopBtn.classList.add('hidden');
    
    // Hide next scan button
    const nextBtn = qs('#next-scan-container');
    if (nextBtn) nextBtn.classList.add('hidden');
    
    // Hide confirmation modal
    const confirmModal = qs('#confirm-presensi-modal');
    if (confirmModal) confirmModal.classList.add('hidden');
    
    // Go back logic
    if (window.history.length > 1) {
       // window.history.back(); // Optional: depend on UX
    }
}

function startVideoInterval() {
    if (!isCameraActive || videoInterval || !video) return;
    
    statusMessage('Kamera aktif. Mencari wajah...', 'bg-blue-100 text-blue-700');

    videoInterval = setInterval(async () => {
        // debug heartbeat
        // console.log('Detection loop running...');
        
        if (isDetectionStopped || !isCameraActive || isPresensiSuccess || isProcessingRecognition || isDetectionPaused) return;
        
        try {
            const displaySize = { width: video.clientWidth, height: video.clientHeight };
            faceapi.matchDimensions(canvas, displaySize);

            // CPU Optimized: Detect ONE face, no expressions/landmarks unless needed
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: parseInt(detectionConfig.inputSize),
                scoreThreshold: parseFloat(detectionConfig.scoreThreshold)
            })).withFaceLandmarks().withFaceDescriptor();

            if (detection) {
                // Fix: Access score from nested detection object
                const score = detection.detection ? detection.detection.score : (detection.score || 0);
                console.log('Face detected! Score:', score.toFixed(2));
                
                const displaySize = { width: video.clientWidth, height: video.clientHeight };
                const resizedDetections = faceapi.resizeResults(detection, displaySize);
                
                // Draw box
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // MIRROR LOGIC: Since video is CSS mirrored (scaleX(-1)), 
                // but canvas is NOT, we must flip the X coordinate of the box 
                // to make it align with the face on screen.
                const box = resizedDetections.detection.box;
                const mirroredX = displaySize.width - box.x - box.width;
                
                // Draw detection with label if found
                let labelText = 'Mencari...';
                if (labeledFaceDescriptors.length > 0) {
                    const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, getAdjustedFaceMatcherThreshold());
                    const bestMatch = faceMatcher.findBestMatch(detection.descriptor);
                    
                    if (bestMatch.label === 'unknown') {
                        labelText = 'Wajah tidak dikenal';
                    } else {
                        // Find member name from NIM
                        const member = members.find(m => m.nim === bestMatch.label);
                        labelText = member ? `${member.nama} (${bestMatch.distance.toFixed(2)})` : bestMatch.toString();
                        
                        console.log('Match found:', bestMatch.label);
                        handleRecognition(bestMatch.label, 'neutral');
                    }
                }
                
                // Draw the box manually to handle mirrored X and keep text upright
                ctx.strokeStyle = '#22c55e'; // Green-500
                ctx.lineWidth = 3;
                ctx.strokeRect(mirroredX, box.y, box.width, box.height);
                
                // Label background
                ctx.fillStyle = '#22c55e';
                ctx.font = 'bold 14px Inter, sans-serif';
                const textWidth = ctx.measureText(labelText).width;
                ctx.fillRect(mirroredX, box.y - 25, textWidth + 10, 25);
                
                // Label text
                ctx.fillStyle = 'white';
                ctx.fillText(labelText, mirroredX + 5, box.y - 7);
            } else {
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            }
        } catch (e) {
            console.error('Detection error:', e);
        }
    }, 1000); // Slower interval for CPU (1s)
}

function shouldAcceptDetection(match, faceData) {
    if (match.distance > getAdjustedRecognitionThreshold()) return false;
    if (assessFaceQuality(faceData) < getAdjustedQualityThreshold()) return false;
    return true;
}

function assessFaceQuality(face) {
    if (!face || !face.detection) return 0;
    const box = face.detection.box;
    const area = box.width * box.height;
    let quality = 1.0;
    if (area < 15000) quality *= 0.5;
    const centerX = box.x + box.width / 2;
    const canvasCenterX = (canvas ? canvas.width : 640) / 2;
    const dist = Math.abs(centerX - canvasCenterX);
    if (dist > 150) quality *= 0.6;
    return quality;
}

function getTopExpression(expressions) {
    if (!expressions) return 'neutral';
    return Object.keys(expressions).reduce((a, b) => expressions[a] > expressions[b] ? a : b);
}

// ---- Attendance Submission ----

async function handleRecognition(nim, expression) {
    if (isProcessingRecognition || isDetectionPaused) return;
    
    // Pause detection to show confirmation
    isDetectionPaused = true;
    
    if (typeof speak === 'function') speak('Wajah dikenali. Mohon konfirmasi data Anda.');
    
    try {
        const screenshot = await takeScreenshot();
        const pos = await getPosition();
        
        let lokasi = 'Mencari lokasi...';
        let lat = null, lng = null;
        
        if (pos) {
            lat = pos.coords.latitude;
            lng = pos.coords.longitude;
            lokasi = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
            
            try {
                const streetName = await getStreetNameFromCoordinates(lat, lng);
                if (streetName) lokasi = streetName;
            } catch(e) {}
        }
        
        const member = members.find(m => m.nim === nim);
        currentRecognitionData = {
            nim,
            nama: member ? member.nama : 'Unknown',
            mode: scanMode,
            ekspresi: expression,
            screenshot,
            lat,
            lng,
            lokasi
        };
        
        showConfirmationModal(currentRecognitionData);
    } catch (e) {
        console.error('Recognition handling error:', e);
        isDetectionPaused = false;
    }
}

function showConfirmationModal(data) {
    const modal = qs('#confirm-presensi-modal');
    if (!modal) return;
    
    qs('#confirm-nama').textContent = data.nama;
    qs('#confirm-nim').textContent = data.nim;
    qs('#confirm-lokasi').textContent = data.lokasi;
    
    modal.classList.remove('hidden');
    
    // Bind buttons
    qs('#btn-confirm-yes').onclick = async () => {
        modal.classList.add('hidden');
        await submitFinalAttendance(data);
    };
    
    qs('#btn-confirm-no').onclick = () => {
        modal.classList.add('hidden');
        resumeDetection();
    };
}

async function submitFinalAttendance(data) {
    isProcessingRecognition = true;
    statusMessage('Menyimpan data presensi...', 'bg-blue-100 text-blue-700');
    
    try {
        // Special Mode: Late Request (from Admin Help)
        const urlParams = new URLSearchParams(window.location.search);
        const mode = urlParams.get('mode');
        
        if (mode === 'late_req') {
            statusMessage('Wajah terverifikasi! Mengalihkan...', 'bg-green-100 text-green-700');
            
            // Store face verification result in sessionStorage
            sessionStorage.setItem('late_req_face_verified', JSON.stringify({
                screenshot: data.screenshot,
                lokasi: data.lokasi,
                timestamp: new Date().toISOString()
            }));
            
            // Redirect back to pegawai page where the modal will auto-open
            setTimeout(() => {
                window.location.href = '?page=pegawai';
            }, 1500);
            return;
        }

        const res = await api('?ajax=save_attendance', data, { suppressModal: true });
        
        if (res.ok) {
            statusMessage(`Berhasil: ${res.message}`, 'bg-green-100 text-green-700');
            isPresensiSuccess = true;
            if (typeof speak === 'function') speak('Presensi berhasil disimpan. Terima kasih.');
            
            // Show "Next Scan" button
            qs('#next-scan-container').classList.remove('hidden');
            
            // Log entry
            updateLogAfterAttendance(data.nim, data.mode);
        } else {
            handleAttendanceError(res, data);
        }
    } catch (e) {
        console.error('Submit error:', e);
        statusMessage('Gagal menyimpan: ' + e.message, 'bg-red-100 text-red-700');
        isDetectionPaused = false;
    } finally {
        isProcessingRecognition = false;
    }
}

function resumeDetection() {
    isDetectionPaused = false;
    isPresensiSuccess = false;
    currentRecognitionData = null;
    qs('#next-scan-container').classList.add('hidden');
    statusMessage('Mencari wajah...', 'bg-blue-100 text-blue-700');
}

function handleAttendanceError(res, pendingData) {
    window.pendingAttendanceData = pendingData;
    
    if (res.need_reason) { // WFA
        showWFAModal(res.message);
    } else if (res.need_overtime_reason) {
        showOvertimeModal(res.message);
    } else if (res.need_early_leave_reason) {
        showEarlyLeaveModal(res.message);
    } else {
        statusMessage(res.message, 'bg-red-100 text-red-700');
        if (typeof speak === 'function') speak('Gagal. ' + res.message);
        isProcessingRecognition = false;
    }
}

async function takeScreenshot() {
    if (!video) return null;
    const captureCanvas = document.createElement('canvas');
    captureCanvas.width = video.videoWidth;
    captureCanvas.height = video.videoHeight;
    const ctx = captureCanvas.getContext('2d');
    
    // Mirror the screenshot to match the preview
    ctx.translate(captureCanvas.width, 0);
    ctx.scale(-1, 1);
    
    ctx.drawImage(video, 0, 0, captureCanvas.width, captureCanvas.height);
    return captureCanvas.toDataURL('image/jpeg', 0.8);
}

function getPosition() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { timeout: 5000, enableHighAccuracy: true });
    });
}

// ---- Additional Helpers (from footer) ----

async function getStreetNameFromCoordinates(lat, lng) {
    try {
        const result = await api('?ajax=reverse_geocode', { action: 'reverse_geocode', lat: lat, lng: lng }, { suppressModal: true });
        if (result.ok && result.data && result.data.address) {
            // Simplified for brevity, assume result logic is similar to footer
             return result.data.display_name || `Lat: ${lat}, Lng: ${lng}`;
        }
    } catch (e) {}
    return null;
}

// ---- Modals ----

function showEarlyLeaveModal(message) {
    let modal = document.getElementById('early-leave-reason-modal');
    if (!modal) {
        // Create dynamically if missing
        modal = document.createElement('div');
        modal.id = 'early-leave-reason-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50'; // Remove hidden
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-2xl">
                <h3 class="text-lg font-semibold mb-4">Alasan Pulang Awal</h3>
                <p class="text-gray-600 mb-4">${message}</p>
                <div class="mb-4">
                    <textarea id="earlyLeaveReason" class="w-full p-3 border rounded-lg" rows="4" placeholder="Alasan..."></textarea>
                </div>
                <div class="flex gap-2">
                    <button id="earlyLeaveCancel" class="flex-1 bg-gray-300 py-2 rounded-lg">Batal</button>
                    <button id="earlyLeaveSubmit" class="flex-1 bg-orange-600 text-white py-2 rounded-lg">Kirim</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        qs('#earlyLeaveSubmit').onclick = () => {
            const reason = qs('#earlyLeaveReason').value.trim();
            if (reason) {
                modal.classList.add('hidden');
                submitAttendanceWithReason({ ...window.pendingAttendanceData, early_leave_reason: reason });
            }
        };
        qs('#earlyLeaveCancel').onclick = () => {
            modal.classList.add('hidden');
            isProcessingRecognition = false;
        };
    } else {
        modal.classList.remove('hidden');
        // Re-bind events if needed (simplification)
    }
}

function showWFAModal(message) {
    alert('WFA Reason: ' + message); // Stub for validation, expand like EarlyLeave
    const reason = prompt("Masukkan alasan WFA:");
    if (reason) submitAttendanceWithReason({ ...window.pendingAttendanceData, alasan_wfa: reason });
    else isProcessingRecognition = false;
}

function showOvertimeModal(message) {
    const reason = prompt("Masukkan alasan Overtime:");
    if (reason) submitAttendanceWithReason({ ...window.pendingAttendanceData, overtime_reason: reason });
    else isProcessingRecognition = false;
}

function submitAttendanceWithReason(data) {
    api('?ajax=save_attendance', data, { suppressModal: true }).then(res => {
        if (res.ok) {
            statusMessage('Berhasil saved with reason.', 'bg-green-100 text-green-700');
            isPresensiSuccess = true;
            stopVideo();
            setTimeout(() => resetPresensiPage(), 2000);
        } else {
            statusMessage(res.message, 'bg-red-100 text-red-700');
            isProcessingRecognition = false;
        }
    });
}


// ---- Log Management (from footer for presensi.php) ----

async function loadLogMasuk() {
    try {
        const result = await api('?ajax=get_today_attendance', { type: 'masuk' }, { suppressModal: true, cache: false });
        if (result.ok) {
            logMasukData = result.data || [];
            renderLogMasuk();
        }
    } catch (error) { console.error('Error loading log masuk:', error); }
}

async function loadLogPulang() {
    try {
        const result = await api('?ajax=get_today_attendance', { type: 'pulang' }, { suppressModal: true, cache: false });
        if (result.ok) {
            logPulangData = result.data || [];
            renderLogPulang();
        }
    } catch (error) { console.error('Error loading log pulang:', error); }
}

function renderLogMasuk() {
    const body = qs('#log-masuk-body');
    if (!body) return;
    body.innerHTML = '';
    if (logMasukData.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Belum ada presensi masuk hari ini</td></tr>';
        return;
    }
    logMasukData.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        const screenshot = item.screenshot_masuk ? `<img src="${item.screenshot_masuk}" class="w-16 h-12 object-cover rounded cursor-pointer hover:scale-150 transition-transform mx-auto" onclick="showScreenshotModal('${item.screenshot_masuk}', 'Screenshot Masuk')">` : '<span class="text-gray-400">-</span>';
        const jamMasuk = item.jam_masuk ? item.jam_masuk.substring(0, 5) : '-';
        const tanggal = item.jam_masuk_iso ? new Date(item.jam_masuk_iso).toLocaleDateString('id-ID') : '-';
        tr.innerHTML = `<td class="py-2 px-4 text-center">${index + 1}</td><td class="py-2 px-4">${item.nama || '-'}</td><td class="py-2 px-4 text-center">${jamMasuk}</td><td class="py-2 px-4 text-sm">${item.lokasi_masuk || '-'}</td><td class="py-2 px-4 text-center">${screenshot}</td>`;
        body.appendChild(tr);
    });
}

function renderLogPulang() {
    const body = qs('#log-pulang-body');
    if (!body) return;
    body.innerHTML = '';
    if (logPulangData.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Belum ada presensi pulang hari ini</td></tr>';
        return;
    }
    logPulangData.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        const screenshot = item.screenshot_pulang ? `<img src="${item.screenshot_pulang}" class="w-16 h-12 object-cover rounded cursor-pointer hover:scale-150 transition-transform mx-auto" onclick="showScreenshotModal('${item.screenshot_pulang}', 'Screenshot Pulang')">` : '<span class="text-gray-400">-</span>';
        const jamPulang = item.jam_pulang ? item.jam_pulang.substring(0, 5) : '-';
        const tanggal = item.jam_pulang_iso ? new Date(item.jam_pulang_iso).toLocaleDateString('id-ID') : '-';
        tr.innerHTML = `<td class="py-2 px-4 text-center">${index + 1}</td><td class="py-2 px-4">${item.nama || '-'}</td><td class="py-2 px-4 text-center">${jamPulang}</td><td class="py-2 px-4 text-sm">${item.lokasi_pulang || '-'}</td><td class="py-2 px-4 text-center">${screenshot}</td>`;
        body.appendChild(tr);
    });
}

function updateLogAfterAttendance(nim, mode) {
    // Clear API Cache to force fresh logs
    if (typeof apiCache !== 'undefined' && apiCache.clear) {
        apiCache.clear();
    }
    
    if (mode === 'masuk') loadLogMasuk();
    else loadLogPulang();
}

function checkAndResetLogDaily() {
    const today = new Date().toDateString();
    const lastReset = localStorage.getItem('lastLogReset');
    if (lastReset !== today) {
        logMasukData = [];
        logPulangData = [];
        localStorage.setItem('lastLogReset', today);
    }
}

function resetRecognitionSystem() {
    detectionHistory = []; // Ensure globals exist
    recognitionCompleted = false;
    isProcessingRecognition = false;
    lastSuccessfulDetection = null;
}

function stopDetection() {
    isDetectionStopped = true;
    if(videoInterval) { clearInterval(videoInterval); videoInterval = null; }
    resetRecognitionSystem();
}

// Ensure detectionHistory is declared
let detectionHistory = [];
let lastSuccessfulDetection = null;

// ---- Initialization ----

document.addEventListener('DOMContentLoaded', () => {
    // Re-bind variables
    video = document.getElementById('video');
    canvas = document.getElementById('overlay') || document.getElementById('canvas');
    loadingOverlay = document.getElementById('loading-overlay');
    presensiStatus = document.getElementById('presensi-status');
    scanButtonsContainer = document.getElementById('scan-buttons');
    videoContainer = document.getElementById('video-container');
    btnBackScan = document.getElementById('btn-back-scan');
    btnScanMasuk = document.getElementById('btn-scan-masuk');
    btnScanPulang = document.getElementById('btn-scan-pulang');
    
    // Auto hook buttons
    if (btnScanMasuk) btnScanMasuk.addEventListener('click', () => startScan('masuk'));
    if (btnScanPulang) btnScanPulang.addEventListener('click', () => startScan('pulang'));
    if (btnBackScan) btnBackScan.addEventListener('click', resetPresensiPage);
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('page') === 'presensi-masuk') {
        // Special case: late_req mode from admin help
        if (urlParams.get('mode') === 'late_req') {
            setTimeout(() => {
                startScan('masuk');
                statusMessage('Mode Request Terlambat: Silakan verifikasi wajah Anda', 'bg-indigo-100 text-indigo-700');
            }, 500);
        } else {
            setTimeout(() => startScan('masuk'), 500);
        }
    }
    if (urlParams.get('page') === 'presensi-pulang') {
        setTimeout(() => startScan('pulang'), 500);
    }
});

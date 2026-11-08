/**
 * Edifies School Attendance System
 * Real-Time Client-Side Face Recognition Scanner Engine
 * (Zero PINs, Zero Manual Clicks, Touchless Biometric Attendance)
 */

(function () {
    'use strict';

    // State Variables
    let isInitialized = false;
    let isDetecting = false;
    let isProcessingPunch = false;
    let videoElement = null;
    let canvasOverlay = null;
    let statusPill = null;
    let successCard = null;
    let sleepOverlay = null;
    let unrecognizedPrompt = null;
    let faceMatcher = null;
    let enrolledStaffMap = new Map(); // employee_id -> { name, dept, code }
    let recentPunches = new Map(); // employee_id -> timestamp (cooldown)
    let lastDetectedDescriptor = null;
    
    // Inactivity Sleep Timing
    let lastFaceSeenTime = Date.now();
    let isSleeping = false;
    const SLEEP_INACTIVITY_MS = 25000; // 25 seconds
    const DUPLICATE_COOLDOWN_MS = 60000; // 60 seconds per employee

    document.addEventListener('DOMContentLoaded', initScanner);

    async function initScanner() {
        videoElement = document.getElementById('scanner-video');
        canvasOverlay = document.getElementById('scanner-canvas');
        statusPill = document.getElementById('scanner-status-pill');
        successCard = document.getElementById('punch-success-card');
        sleepOverlay = document.getElementById('scanner-sleep-overlay');

        if (!videoElement) return;

        updateStatus('loading', 'Loading Neural Network Models...');

        try {
            // 1. Load TinyFaceDetector, Landmarks, and FaceRecognition nets from /models
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('/models')
            ]);

            updateStatus('loading', 'Loading Enrolled Staff Face Templates...');

            // 2. Fetch enrolled face templates from backend
            await loadEnrolledTemplates();

            // 3. Start Camera Video Feed
            updateStatus('loading', 'Starting Camera Viewport...');
            await startCamera();

            isInitialized = true;
            updateStatus('ready', 'Touchless Scanner Active • Stand in front of camera');

            // 4. Start Recognition & Sleep Monitoring Loop
            startRecognitionLoop();

            // Screen Touch Wakes Up Sleep Mode
            document.body.addEventListener('click', wakeUp);
            document.body.addEventListener('touchstart', wakeUp);

        } catch (err) {
            console.error('Scanner Initialization Error:', err);
            updateStatus('error', 'Camera or Model Initialization Failed: ' + (err.message || err));
        }
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera hardware access is not supported by this browser.');
        }

        const constraints = {
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            },
            audio: false
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        videoElement.srcObject = stream;

        return new Promise((resolve) => {
            videoElement.onloadedmetadata = () => {
                videoElement.play();
                resolve();
            };
        });
    }

    async function loadEnrolledTemplates() {
        try {
            const response = await fetch('/api/kiosk/face-templates', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch templates (HTTP ' + response.status + ')');
            }

            const data = await response.json();
            const templates = data.templates || [];

            if (templates.length === 0) {
                updateStatus('waiting', 'No staff faces enrolled yet. Enroll staff in Admin Portal.');
                faceMatcher = null;
                return;
            }

            const labeledDescriptors = [];
            enrolledStaffMap.clear();

            for (const t of templates) {
                enrolledStaffMap.set(t.employee_id, {
                    name: t.employee_name,
                    dept: t.department,
                    code: t.employee_code
                });

                if (Array.isArray(t.embedding) && t.embedding.length === 128) {
                    const descriptor = new Float32Array(t.embedding);
                    labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(t.employee_id, [descriptor]));
                }
            }

            if (labeledDescriptors.length > 0) {
                // Distance threshold 0.58 (standard for 128-d ResNet vectors)
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.58);
            }

        } catch (err) {
            console.warn('Template fetch notice:', err.message);
        }
    }

    function startRecognitionLoop() {
        let lastFrameTime = 0;
        const TARGET_FRAME_INTERVAL_ACTIVE = 150; // ~6.6 FPS when active
        const TARGET_FRAME_INTERVAL_SLEEP = 1200; // ~0.8 FPS when sleeping to conserve battery

        async function step(now) {
            const interval = isSleeping ? TARGET_FRAME_INTERVAL_SLEEP : TARGET_FRAME_INTERVAL_ACTIVE;

            if (now - lastFrameTime >= interval) {
                lastFrameTime = now;
                await processVideoFrame();
            }

            // Check Inactivity Sleep
            checkInactivitySleep(now);

            requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    async function processVideoFrame() {
        if (!isInitialized || isProcessingPunch || videoElement.paused || videoElement.ended) {
            return;
        }

        try {
            // Keep canvas resolution synced to video feed
            if (canvasOverlay && videoElement.videoWidth > 0) {
                if (canvasOverlay.width !== videoElement.videoWidth || canvasOverlay.height !== videoElement.videoHeight) {
                    canvasOverlay.width = videoElement.videoWidth;
                    canvasOverlay.height = videoElement.videoHeight;
                }
            }

            // Detect single face with landmarks and 128-d descriptor
            const detection = await faceapi
                .detectSingleFace(videoElement, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                // Clear any residual tracking box when face leaves frame
                if (canvasOverlay) {
                    const ctx = canvasOverlay.getContext('2d');
                    ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);
                }

                if (unrecognizedPrompt) {
                    unrecognizedPrompt.style.display = 'none';
                }

                if (!isSleeping && !isProcessingPunch) {
                    if (!faceMatcher) {
                        updateStatus('waiting', 'No staff faces enrolled yet • Enroll staff in Admin > Staff Directory');
                    } else {
                        updateStatus('ready', 'Touchless Scanner Active • Stand in front of camera');
                    }
                }
                return;
            }

            // Face detected! Wake up if currently sleeping
            lastFaceSeenTime = Date.now();
            if (isSleeping) {
                wakeUp();
            }

            lastDetectedDescriptor = detection.descriptor;

            // Draw live tracking reticle on face in mirror canvas
            const box = detection.detection.box;
            const ctx = canvasOverlay ? canvasOverlay.getContext('2d') : null;
            if (ctx) {
                ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);
                ctx.lineWidth = 3;
                ctx.strokeStyle = '#38bdf8'; // Sky blue while analyzing
                ctx.shadowColor = 'rgba(56, 189, 248, 0.6)';
                ctx.shadowBlur = 10;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
            }

            if (!faceMatcher) {
                updateStatus('waiting', 'No staff faces enrolled in system • Enroll staff in Admin > Staff Directory');
                return;
            }

            // Compare face against enrolled templates
            const bestMatch = faceMatcher.findBestMatch(detection.descriptor);

            if (bestMatch.label !== 'unknown') {
                const empId = bestMatch.label;
                const empInfo = enrolledStaffMap.get(empId) || { name: 'Staff Member' };
                const confidencePercent = Math.round((1 - bestMatch.distance) * 100);

                // Highlight tracking box in emerald green upon successful verification
                if (ctx) {
                    ctx.strokeStyle = '#22c55e';
                    ctx.shadowColor = 'rgba(34, 197, 94, 0.8)';
                    ctx.shadowBlur = 14;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                }

                // Check Cooldown: prevent rapid duplicate punches within 60 seconds
                const lastPunch = recentPunches.get(empId) || 0;
                if (Date.now() - lastPunch < DUPLICATE_COOLDOWN_MS) {
                    const remainingSec = Math.ceil((DUPLICATE_COOLDOWN_MS - (Date.now() - lastPunch)) / 1000);
                    updateStatus('info', `${empInfo.name} already recorded. Cooldown: ${remainingSec}s`);
                    return;
                }

                // Legitimate match! Execute automated touchless punch
                await recordFaceAttendance(empId, empInfo, confidencePercent);
            } else {
                if (ctx) {
                    ctx.strokeStyle = '#f59e0b'; // Amber for unregistered face
                    ctx.shadowColor = 'rgba(245, 158, 11, 0.8)';
                    ctx.shadowBlur = 12;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                }
                updateStatus('searching', 'Face Detected • Staff Member Not Enrolled in System');
            }

        } catch (err) {
            console.error('Frame processing error:', err);
        }
    }

    async function recordFaceAttendance(employeeId, employeeInfo, confidence) {
        if (isProcessingPunch) return;
        isProcessingPunch = true;

        updateStatus('processing', `Verified: ${employeeInfo.name} (${confidence}%) • Recording...`);

        // Capture snapshot frame for the attendance photo audit ledger
        const photoData = captureCurrentFrame();

        try {
            const response = await fetch('/api/kiosk/face-punch', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    confidence: confidence / 100,
                    photo_payload: photoData,
                    client_uuid: 'punch_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9)
                })
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                // Record in cooldown map
                recentPunches.set(employeeId, Date.now());

                // Show celebratory success card
                showSuccessCard({
                    name: result.employee_name || employeeInfo.name,
                    action: result.action_label || (result.action === 'CHECK_OUT' ? 'Check-Out (Exit)' : 'Check-In (Entry)'),
                    time: result.recorded_at || new Date().toLocaleTimeString(),
                    date: result.work_date || new Date().toLocaleDateString()
                });

                updateStatus('success', `✅ ${result.employee_name}: ${result.action_label} at ${result.recorded_at}`);

                // Hold success screen for 3.5 seconds, then resume touchless scanning
                setTimeout(() => {
                    hideSuccessCard();
                    isProcessingPunch = false;
                    updateStatus('ready', 'Touchless Scanner Active • Stand in front of camera');
                }, 3500);

            } else {
                const errMsg = result.message || 'Attendance recording failed.';
                updateStatus('error', '⚠️ ' + errMsg);
                setTimeout(() => {
                    isProcessingPunch = false;
                }, 3000);
            }

        } catch (err) {
            updateStatus('error', 'Network error while recording punch.');
            setTimeout(() => {
                isProcessingPunch = false;
            }, 3000);
        }
    }

    function captureCurrentFrame() {
        if (!videoElement || videoElement.videoWidth === 0) return null;

        const canvas = document.createElement('canvas');
        canvas.width = Math.min(videoElement.videoWidth, 640);
        canvas.height = Math.min(videoElement.videoHeight, 480);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.85);
    }

    function checkInactivitySleep(now) {
        if (!isSleeping && (now - lastFaceSeenTime > SLEEP_INACTIVITY_MS)) {
            enterSleepMode();
        }
    }

    function enterSleepMode() {
        isSleeping = true;
        if (sleepOverlay) {
            sleepOverlay.classList.add('active');
        }
    }

    function wakeUp() {
        if (isSleeping) {
            isSleeping = false;
            lastFaceSeenTime = Date.now();
            if (sleepOverlay) {
                sleepOverlay.classList.remove('active');
            }
        }
    }

    function showSuccessCard(data) {
        if (!successCard) return;

        if (canvasOverlay) {
            const ctx = canvasOverlay.getContext('2d');
            ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);
        }

        const nameEl = document.getElementById('success-emp-name');
        const actionEl = document.getElementById('success-action-text');
        const timeEl = document.getElementById('success-time-text');

        if (nameEl) nameEl.textContent = data.name;
        if (actionEl) actionEl.textContent = data.action;
        if (timeEl) timeEl.textContent = `${data.date} • ${data.time}`;

        successCard.style.display = 'flex';
        playSuccessChime();
    }

    function hideSuccessCard() {
        if (successCard) {
            successCard.style.display = 'none';
        }
    }

    function playSuccessChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;

            // Two-tone enterprise chime: 587.33 Hz (D5) -> 880 Hz (A5)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, now);
            osc.frequency.setValueAtTime(880.00, now + 0.12);

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.2, now + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.46);
        } catch (e) {
            // Audio context not allowed before user gesture, silent fail
        }
    }

    function updateStatus(type, message) {
        if (!statusPill) return;

        statusPill.className = 'scanner-status-badge badge-' + type;
        const textEl = statusPill.querySelector('.status-text');
        if (textEl) {
            textEl.textContent = message;
        } else {
            statusPill.textContent = message;
        }
    }

})();


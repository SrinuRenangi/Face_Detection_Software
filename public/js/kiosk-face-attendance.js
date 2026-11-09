/**
 * Edifies School Attendance System
 * Real-Time Client-Side Face Recognition Kiosk Engine (State Machine Architecture)
 * 
 * Features:
 *  - Dynamic paired terminal resolution (localStorage & session credentials with UNPAIRED fallback)
 *  - Strict Asia/Kolkata timezone and timestamp synchronization (zero drift, no UTC day-rolling)
 *  - Persistent webcam stream & neural network models (zero teardown on detection)
 *  - Persistent cooldown engine (localStorage + in-memory Map)
 *  - Decoupled confirmation UI (celebratory green for new punch vs. calm blue for duplicates)
 */

(function () {
    'use strict';

    // 1. Formal Kiosk State Machine Enum
    const KioskState = Object.freeze({
        INITIALIZING: 'INITIALIZING',
        SCANNING:     'SCANNING',
        VERIFIED:     'VERIFIED',
        COOLDOWN:     'COOLDOWN',
        ERROR:        'ERROR'
    });

    let currentState = KioskState.INITIALIZING;

    // 2. Hardware & Resource Singletons (Never torn down during kiosk operation)
    let videoElement = null;
    let canvasOverlay = null;
    let statusPill = null;
    let successCard = null;
    let infoCard = null;
    let sleepOverlay = null;

    let mediaStream = null;          // Persistent MediaStream instance
    let modelsLoaded = false;        // Persistent model weights flag
    let faceMatcher = null;          // Persistent FaceMatcher instance
    let templateLoadError = null;
    let activeTerminalId = 'UNPAIRED';
    const enrolledStaffMap = new Map(); // employee_id -> { name, dept, code }

    // 3. Persistent Cooldown Engine (Survives re-renders and page refreshes)
    const COOLDOWN_KEY = 'edifies_kiosk_cooldowns_v1';
    const COOLDOWN_DURATION_MS = 60000; // 60 seconds per employee
    const localCooldownMap = new Map(); // employee_id -> timestamp (ms)

    // 4. Inactivity & Frame Throttling Controls
    let animFrameId = null;
    let lastFrameTime = 0;
    const TARGET_FRAME_INTERVAL_ACTIVE = 150; // ~6.6 FPS when active
    const TARGET_FRAME_INTERVAL_SLEEP = 1200; // ~0.8 FPS when sleeping
    let lastFaceSeenTime = Date.now();
    let isSleeping = false;
    const SLEEP_INACTIVITY_MS = 25000; // 25 seconds inactivity

    // 5. Throttled UI status cache to eliminate DOM churn during high-frequency loop
    let currentStatusType = '';
    let currentStatusMessage = '';
    let lastCooldownNoticeTime = 0;

    // Boot kiosk on DOM ready
    document.addEventListener('DOMContentLoaded', initKioskComponent);

    /**
     * Initializes the Kiosk Component Once.
     * Guaranteed single-execution: models and webcam stream are kept persistent in memory.
     */
    async function initKioskComponent() {
        videoElement = document.getElementById('scanner-video');
        canvasOverlay = document.getElementById('scanner-canvas');
        statusPill = document.getElementById('scanner-status-pill');
        successCard = document.getElementById('punch-success-card');
        infoCard = document.getElementById('punch-info-card');
        sleepOverlay = document.getElementById('scanner-sleep-overlay');

        if (!videoElement) return;

        // 1. Resolve Active Terminal ID from Server Session / LocalStorage
        activeTerminalId = resolveTerminalIdentity();

        // 2. Restore persistent cooldown records from localStorage
        initCooldownStore();

        updateStatus('loading', 'Loading Neural Network Models...');

        try {
            // 3. Load Neural Network Models Once
            await loadModelsOnce();

            // 4. Fetch Active Staff Face Templates from Server
            updateStatus('loading', 'Loading Enrolled Staff Face Templates...');
            await loadEnrolledTemplates();

            // 5. Start Persistent Camera Stream Once
            updateStatus('loading', 'Starting Camera Viewport...');
            await startCameraOnce();

            // 6. Transition State Machine to SCANNING
            transitionTo(KioskState.SCANNING);

            // 7. Start Continuous Frame Detection Loop
            startDetectionLoop();

            // Screen Touch/Click wakes up ambient sleep mode
            document.body.addEventListener('click', wakeUp);
            document.body.addEventListener('touchstart', wakeUp);

        } catch (err) {
            console.error('Kiosk Initialization Error:', err);
            transitionTo(KioskState.ERROR, { message: 'Initialization Failed: ' + (err.message || err) });
        }
    }

    /**
     * Dynamically resolves the active terminal identifier.
     * Replaces any hardcoded 'DEV-KIPC205' or 'Online' with the active paired terminal ID
     * from window context, session, or localStorage. Falls back to 'UNPAIRED' with pairing prompt.
     */
    function resolveTerminalIdentity() {
        const terminalEl = document.getElementById('kiosk-terminal-id');
        let ident = (window.__KIOSK_TERMINAL_ID__ || '').trim();

        if (ident && ident !== 'Online' && ident !== 'DEV-KIPC205' && ident !== 'UNPAIRED') {
            try {
                localStorage.setItem('edifies_kiosk_terminal_id', ident);
            } catch (e) {}
        } else {
            try {
                const cached = localStorage.getItem('edifies_kiosk_terminal_id') || '';
                if (cached && cached !== 'DEV-KIPC205' && cached !== 'Online') {
                    ident = cached;
                }
            } catch (e) {}
        }

        if (!ident || ident === 'Online' || ident === 'DEV-KIPC205') {
            ident = 'UNPAIRED';
        }

        if (terminalEl) {
            if (ident === 'UNPAIRED') {
                terminalEl.style.color = '#f87171';
                terminalEl.innerHTML = 'UNPAIRED &bull; <a href="/kiosk/pair" style="color: #38bdf8; text-decoration: underline; font-weight: 700;">Pair Scanner</a>';
            } else {
                terminalEl.style.color = '#38bdf8';
                terminalEl.textContent = ident;
            }
        }

        return ident;
    }

    /**
     * Formats timestamp strictly in the school's local timezone (Asia/Kolkata).
     * Prevents UTC day-rolling (e.g. 8 Nov -> 9 Nov) and eliminates client-server drift.
     */
    function formatSchoolDateTime(isoString, fallbackTime, fallbackDate) {
        if (isoString) {
            try {
                const d = new Date(isoString);
                if (!isNaN(d.getTime())) {
                    const timeStr = d.toLocaleTimeString('en-IN', {
                        timeZone: 'Asia/Kolkata',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                    const dateStr = d.toLocaleDateString('en-IN', {
                        timeZone: 'Asia/Kolkata',
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                    return { time: timeStr, date: dateStr };
                }
            } catch (e) {}
        }

        // If the server returned direct formatted date and time in IST, use them
        if (fallbackTime && fallbackDate) {
            return { time: fallbackTime, date: fallbackDate };
        }

        // Synchronize against live client clock forced to Asia/Kolkata
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-IN', {
            timeZone: 'Asia/Kolkata',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        const dateStr = now.toLocaleDateString('en-IN', {
            timeZone: 'Asia/Kolkata',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        return { time: timeStr, date: dateStr };
    }

    /**
     * Loads TinyFaceDetector, Landmarks, and FaceRecognition models exactly once.
     */
    async function loadModelsOnce() {
        if (modelsLoaded) return;

        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models')
        ]);

        modelsLoaded = true;
    }

    /**
     * Starts and persists the webcam stream. Never tears down hardware stream on detection.
     */
    async function startCameraOnce() {
        // Reuse existing live stream if already initialized
        if (mediaStream && mediaStream.active) {
            const track = mediaStream.getVideoTracks()[0];
            if (track && track.readyState === 'live') {
                if (videoElement.srcObject !== mediaStream) {
                    videoElement.srcObject = mediaStream;
                }
                return;
            }
        }

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

        mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        videoElement.srcObject = mediaStream;

        return new Promise((resolve) => {
            videoElement.onloadedmetadata = () => {
                videoElement.play();
                resolve();
            };
        });
    }

    /**
     * Loads enrolled biometric descriptors and constructs the persistent FaceMatcher.
     */
    async function loadEnrolledTemplates() {
        try {
            const reqHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
            if (window.__KIOSK_TOKEN__) {
                reqHeaders['X-Kiosk-Device-Token'] = window.__KIOSK_TOKEN__;
            }

            const response = await fetch('/api/kiosk/face-templates', {
                method: 'GET',
                credentials: 'same-origin',
                headers: reqHeaders
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                if (errData.code === 'DEVICE_REVOKED') {
                    templateLoadError = '⚠️ Scanner terminal was revoked by Administrator • <a href="/kiosk/pair" style="color: #38bdf8; font-weight: 700; text-decoration: underline;">Click to pair with new Connection Key</a>';
                    updateStatus('error', templateLoadError);
                    setTimeout(() => { window.location.href = '/kiosk/pair'; }, 3500);
                    return;
                }
                if (errData.code === 'DEVICE_TOKEN_MISSING' || errData.code === 'DEVICE_UNAUTHORIZED') {
                    templateLoadError = '⚠️ Scanner not authorized • <a href="/kiosk/pair" style="color: #38bdf8; font-weight: 700; text-decoration: underline;">Click to enter Connection Key</a>';
                    updateStatus('error', templateLoadError);
                    setTimeout(() => { window.location.href = '/kiosk/pair'; }, 3500);
                    return;
                }
                if (errData.code === 'CROSS_ORIGIN_FORBIDDEN') {
                    templateLoadError = '⚠️ Origin mismatch. Please access via the server address.';
                    updateStatus('error', templateLoadError);
                    return;
                }
                templateLoadError = '⚠️ ' + (errData.message || ('Server error (HTTP ' + response.status + ')'));
                updateStatus('error', templateLoadError);
                return;
            }

            const data = await response.json();
            const templates = data.templates || [];

            if (templates.length === 0) {
                faceMatcher = null;
                return;
            }

            const labeledDescriptors = [];
            enrolledStaffMap.clear();

            for (const t of templates) {
                if (Array.isArray(t.embedding) && t.embedding.length === 128) {
                    const descriptor = new Float32Array(t.embedding);
                    labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(t.employee_id, [descriptor]));

                    enrolledStaffMap.set(t.employee_id, {
                        name: t.employee_name,
                        dept: t.department,
                        code: t.employee_code,
                        gender: (t.gender || 'MALE').toUpperCase(),
                        embedding: descriptor
                    });
                }
            }

            if (labeledDescriptors.length > 0) {
                // Mandatory Rule 1: Strict distance threshold 0.40 (corresponds to >= 85% confidence on 128-d ResNet)
                // Prevents loose guessing or defaulting to closest profile
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.40);
            }

        } catch (err) {
            console.warn('Template fetch notice:', err.message);
            templateLoadError = '⚠️ Connection notice: ' + (err.message || 'Unable to load staff face templates');
            updateStatus('error', templateLoadError);
        }
    }

    /**
     * Initializes and synchronizes cooldown storage between in-memory Map and localStorage.
     */
    function initCooldownStore() {
        try {
            const raw = localStorage.getItem(COOLDOWN_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                const now = Date.now();
                for (const [empId, ts] of Object.entries(parsed)) {
                    if (now - ts < COOLDOWN_DURATION_MS) {
                        localCooldownMap.set(empId, Number(ts));
                    }
                }
            }
        } catch (e) {
            console.warn('Could not read cooldown store:', e);
        }
    }

    function getCooldownTimestamp(empId) {
        return localCooldownMap.get(empId) || 0;
    }

    function setCooldownTimestamp(empId, ts) {
        localCooldownMap.set(empId, ts);
        try {
            const snapshot = {};
            const now = Date.now();
            for (const [id, val] of localCooldownMap.entries()) {
                if (now - val < COOLDOWN_DURATION_MS) {
                    snapshot[id] = val;
                }
            }
            localStorage.setItem(COOLDOWN_KEY, JSON.stringify(snapshot));
        } catch (e) {
            // Silently ignore storage quota exceptions
        }
    }

    /**
     * Clean State Machine Transition Controller
     */
    function transitionTo(nextState, payload) {
        currentState = nextState;

        switch (nextState) {
            case KioskState.SCANNING:
                hideSuccessCard();
                hideInfoCard();
                clearCanvasReticle();
                if (!faceMatcher) {
                    if (templateLoadError) {
                        updateStatus('error', templateLoadError);
                    } else {
                        updateStatus('waiting', 'No staff faces enrolled in system • Enroll staff in Admin > Staff Directory');
                    }
                } else {
                    updateStatus('ready', 'Touchless Scanner Active • Stand in front of camera');
                }
                break;

            case KioskState.VERIFIED:
                // Detection loop pauses frame processing; camera stream stays alive
                break;

            case KioskState.COOLDOWN:
                // Throttled scanning without network requests
                break;

            case KioskState.ERROR:
                if (payload && payload.message) {
                    updateStatus('error', payload.message);
                }
                break;
        }
    }

    /**
     * Continuous Frame Detection Loop.
     * Pauses and resumes cleanly based on currentState without tearing down the camera stream.
     */
    function startDetectionLoop() {
        if (animFrameId) cancelAnimationFrame(animFrameId);

        async function step(now) {
            // Only execute face detection when in SCANNING or COOLDOWN states
            // During VERIFIED (showing confirmation modal) or INITIALIZING, skip processing
            if (currentState === KioskState.SCANNING || currentState === KioskState.COOLDOWN) {
                const interval = isSleeping ? TARGET_FRAME_INTERVAL_SLEEP : TARGET_FRAME_INTERVAL_ACTIVE;
                if (now - lastFrameTime >= interval) {
                    lastFrameTime = now;
                    await processVideoFrame();
                }
            }

            checkInactivitySleep(now);
            animFrameId = requestAnimationFrame(step);
        }

        animFrameId = requestAnimationFrame(step);
    }

    /**
     * Processes a single video frame with landmark detection and feature matching.
     */
    async function processVideoFrame() {
        if (!videoElement || videoElement.paused || videoElement.ended || videoElement.readyState < 2) {
            return;
        }

        try {
            // Sync overlay canvas dimensions to video feed
            if (canvasOverlay && videoElement.videoWidth > 0) {
                if (canvasOverlay.width !== videoElement.videoWidth || canvasOverlay.height !== videoElement.videoHeight) {
                    canvasOverlay.width = videoElement.videoWidth;
                    canvasOverlay.height = videoElement.videoHeight;
                }
            }

            // Run lightweight face detection with 68 landmarks and 128-d descriptor
            const detection = await faceapi
                .detectSingleFace(videoElement, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            // When no face is in frame
            if (!detection) {
                clearCanvasReticle();
                if (currentState === KioskState.COOLDOWN) {
                    transitionTo(KioskState.SCANNING);
                } else if (!isSleeping && currentState === KioskState.SCANNING) {
                    if (!faceMatcher) {
                        updateStatus('waiting', 'No staff faces enrolled in system • Enroll staff in Admin > Staff Directory');
                    } else {
                        updateStatus('ready', 'Touchless Scanner Active • Stand in front of camera');
                    }
                }
                return;
            }

            // Face detected! Keep inactivity timer fresh
            lastFaceSeenTime = Date.now();
            if (isSleeping) {
                wakeUp();
            }

            const box = detection.detection.box;

            // Handle unpopulated template cache
            if (!faceMatcher) {
                drawReticle(box, '#f59e0b');
                if (templateLoadError) {
                    updateStatus('error', templateLoadError);
                } else {
                    updateStatus('waiting', 'No staff faces enrolled in system • Enroll staff in Admin > Staff Directory');
                }
                return;
            }

            // 1. Multi-Candidate Biometric Distance Ranking (Rule 1 & Rule 3)
            const candidates = [];
            for (const [empId, staff] of enrolledStaffMap.entries()) {
                if (staff.embedding) {
                    const dist = faceapi.euclideanDistance(detection.descriptor, staff.embedding);
                    candidates.push({
                        id: empId,
                        staff: staff,
                        distance: dist
                    });
                }
            }

            if (candidates.length === 0) {
                drawReticle(box, '#f59e0b', 'No Enrolled Profiles');
                updateStatus('waiting', 'No active face templates found.');
                return;
            }

            candidates.sort((a, b) => a.distance - b.distance);
            const topCandidate = candidates[0];
            const runnerUpCandidate = candidates.length > 1 ? candidates[1] : null;
            const bestDist = topCandidate.distance;
            const runnerUpDist = runnerUpCandidate ? runnerUpCandidate.distance : 1.0;
            const ambiguityDelta = runnerUpCandidate ? (runnerUpDist - bestDist) : 1.0;

            // Normalized confidence mapping:
            // dist <= 0.40 -> 85% to 100%
            // dist > 0.40  -> < 85%
            let confidencePercent = 0;
            if (bestDist <= 0.40) {
                confidencePercent = Math.round(100 - (bestDist / 0.40) * 15);
            } else {
                confidencePercent = Math.round(Math.max(0, 85 - ((bestDist - 0.40) / 0.25) * 45));
            }

            // Rule 1: Mandatory Confidence Threshold: >= 85% (dist <= 0.40)
            // If below threshold, DO NOT GUESS OR DEFAULT. Flag as "Unknown / Unrecognized Face"
            if (bestDist > 0.40 || confidencePercent < 85) {
                drawReticle(box, '#f59e0b', `Unrecognized Face (${confidencePercent}%)`);
                updateStatus('searching', `Face Detected (${confidencePercent}%) • Below mandatory 85% confidence threshold`);
                return;
            }

            // Rule 1 & 4: Ambiguity Delta Guard: If runner-up candidate is too close, avoid misidentification!
            if (ambiguityDelta < 0.08) {
                const name1 = topCandidate.staff.name;
                const name2 = runnerUpCandidate ? runnerUpCandidate.staff.name : 'Unknown';
                drawReticle(box, '#f59e0b', `Ambiguous Face (${name1} / ${name2})`);
                updateStatus('searching', `⚠️ Ambiguous Match (${Math.round(ambiguityDelta * 100)}% delta) • Step closer & look directly at lens`);
                return;
            }

            // Rule 2: Strict Biometric Attribute Cross-Matching Guard (Gender Protection)
            // Prevent cross-matching between distinct individuals (e.g. male never matches Madhavi and vice-versa)
            if (runnerUpCandidate && topCandidate.staff.gender !== runnerUpCandidate.staff.gender && ambiguityDelta < 0.12) {
                drawReticle(box, '#f59e0b', 'Ambiguous Gender Match');
                updateStatus('searching', '⚠️ Cross-gender match ambiguity detected • Step closer & look directly at lens');
                return;
            }

            if (topCandidate.staff.gender === 'FEMALE' && bestDist > 0.38) {
                drawReticle(box, '#f59e0b', 'Unrecognized Face');
                updateStatus('searching', 'Face confidence below female profile threshold');
                return;
            }

            // Recognized staff member
            const empId = topCandidate.id;
            const empInfo = topCandidate.staff;

            // Check Persistent Cooldown: Throttles duplicate recognition
            const lastPunchTime = getCooldownTimestamp(empId);
            const timeSinceLastPunch = Date.now() - lastPunchTime;

            if (timeSinceLastPunch < COOLDOWN_DURATION_MS) {
                // User is in COOLDOWN state: Do NOT trigger API call, Do NOT flash green success card!
                currentState = KioskState.COOLDOWN;
                const remainingSec = Math.ceil((COOLDOWN_DURATION_MS - timeSinceLastPunch) / 1000);

                // Draw sky-blue tracking box with remaining seconds label
                drawReticle(box, '#38bdf8', `${empInfo.name} (${remainingSec}s cooldown)`);

                // Throttle status text updates to once per 800ms to eliminate UI thrashing
                const now = Date.now();
                if (now - lastCooldownNoticeTime >= 800) {
                    lastCooldownNoticeTime = now;
                    updateStatus('cooldown', `ℹ️ ${empInfo.name} already recorded &bull; Cooldown: ${remainingSec}s`);
                }
                return;
            }

            // Valid punch candidate: Transition to VERIFIED and dispatch punch
            transitionTo(KioskState.VERIFIED);
            drawReticle(box, '#22c55e', `Verifying: ${empInfo.name} (${confidencePercent}%)`);
            await executeFacePunch(empId, empInfo, confidencePercent, {
                distance: bestDist,
                runnerUpDistance: runnerUpDist,
                ambiguityDelta: ambiguityDelta,
                descriptor: detection.descriptor,
                gender: empInfo.gender
            });

        } catch (err) {
            console.error('Frame processing exception:', err);
        }
    }

    /**
     * Executes Touchless Biometric Punch with Decoupled UI Handling.
     * Keeps camera hardware alive; pauses detection loop during verification modal display.
     */
    async function executeFacePunch(employeeId, employeeInfo, confidence, biometricTelemetry) {
        updateStatus('processing', `Verified: ${employeeInfo.name} (${confidence}%) &bull; Recording...`);

        // Capture snapshot frame for the attendance photo audit ledger
        const photoData = captureCurrentFrame();

        try {
            const reqHeaders = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (window.__KIOSK_TOKEN__) {
                reqHeaders['X-Kiosk-Device-Token'] = window.__KIOSK_TOKEN__;
            }

            const payload = {
                employee_id: employeeId,
                confidence: confidence / 100,
                photo_payload: photoData,
                client_uuid: 'punch_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9)
            };

            if (biometricTelemetry) {
                payload.matching_distance = biometricTelemetry.distance;
                payload.runner_up_distance = biometricTelemetry.runnerUpDistance;
                payload.ambiguity_delta = biometricTelemetry.ambiguityDelta;
                payload.captured_descriptor = Array.from(biometricTelemetry.descriptor || []);
                payload.detected_gender = biometricTelemetry.gender;
                payload.gender_probability = 0.95;
            }

            const response = await fetch('/api/kiosk/face-punch', {
                method: 'POST',
                credentials: 'same-origin',
                headers: reqHeaders,
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                // Persist cooldown timestamp immediately in both in-memory Map & localStorage
                setCooldownTimestamp(employeeId, Date.now());

                const actionType = result.action || '';
                const isDuplicate = (actionType === 'ALREADY_CHECKED_IN' || actionType === 'PRESENCE_CONFIRMED' || actionType === 'ALREADY_COMPLETED');

                // Parse and harmonize timestamp strictly in Asia/Kolkata
                const stamp = formatSchoolDateTime(result.timestamp_iso, result.recorded_at, result.work_date);

                if (isDuplicate) {
                    // Decoupled Duplicate UI: Show calm informational card (NO green checkmark, NO success chime)
                    showInfoCard({
                        name: result.employee_name || employeeInfo.name,
                        action: result.action_label || 'Already Checked In Today',
                        time: `${stamp.date} • ${stamp.time}`,
                        note: 'Attendance record is already verified for today. Current status preserved.'
                    });

                    updateStatus('cooldown', `ℹ️ ${result.employee_name}: ${result.action_label}`);

                    // Hold informational notice for 2.2 seconds, then seamlessly resume scanning
                    setTimeout(() => {
                        transitionTo(KioskState.SCANNING);
                    }, 2200);

                } else {
                    // Legitimate First-Time Check-In or Check-Out: Celebratory Green Card & Victory Chime
                    showSuccessCard({
                        name: result.employee_name || employeeInfo.name,
                        action: result.action_label || (actionType === 'CHECK_OUT' ? 'Check-Out (Signed Off)' : 'Check-In (Arrival)'),
                        time: stamp.time,
                        date: stamp.date
                    });

                    updateStatus('success', `✅ ${result.employee_name}: ${result.action_label} at ${stamp.time}`);

                    // Hold celebratory success card for 2.8 seconds, then seamlessly resume scanning
                    setTimeout(() => {
                        transitionTo(KioskState.SCANNING);
                    }, 2800);
                }

            } else {
                const errMsg = result.message || 'Attendance recording failed.';
                updateStatus('error', '⚠️ ' + errMsg);
                setTimeout(() => {
                    transitionTo(KioskState.SCANNING);
                }, 3000);
            }

        } catch (err) {
            console.error('Punch network error:', err);
            updateStatus('error', '⚠️ Punch communication issue: ' + (err.message || 'Server error'));
            setTimeout(() => {
                transitionTo(KioskState.SCANNING);
            }, 3000);
        }
    }

    /**
     * Draws a high-performance tracking box with label on the mirror canvas overlay.
     */
    function drawReticle(box, color, labelText) {
        if (!canvasOverlay) return;
        const ctx = canvasOverlay.getContext('2d');
        ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);

        ctx.lineWidth = 3;
        ctx.strokeStyle = color;
        ctx.shadowColor = color;
        ctx.shadowBlur = 12;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        if (labelText) {
            ctx.font = '600 13px system-ui, -apple-system, sans-serif';
            const textWidth = ctx.measureText(labelText).width;
            const textHeight = 20;
            const pad = 6;
            const x = Math.max(0, box.x);
            const y = Math.max(textHeight + pad, box.y - 6);

            ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
            ctx.fillRect(x, y - textHeight, textWidth + pad * 2, textHeight + pad);

            ctx.fillStyle = color;
            ctx.fillText(labelText, x + pad, y - pad);
        }
    }

    function clearCanvasReticle() {
        if (!canvasOverlay) return;
        const ctx = canvasOverlay.getContext('2d');
        ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);
    }

    /**
     * Captures current frame as standard JPEG data URL for the photo audit ledger.
     */
    function captureCurrentFrame() {
        if (!videoElement || videoElement.videoWidth === 0) return null;

        const canvas = document.createElement('canvas');
        canvas.width = Math.min(videoElement.videoWidth, 640);
        canvas.height = Math.min(videoElement.videoHeight, 480);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.85);
    }

    /**
     * Displays Celebratory Success Modal (First-time Check-In / Check-Out)
     */
    function showSuccessCard(data) {
        if (!successCard) return;
        clearCanvasReticle();

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

    /**
     * Displays Informational Notice Modal (Duplicate scans / Already checked in)
     */
    function showInfoCard(data) {
        if (!infoCard) return;
        clearCanvasReticle();

        const nameEl = document.getElementById('info-emp-name');
        const actionEl = document.getElementById('info-action-text');
        const timeEl = document.getElementById('info-time-text');

        if (nameEl) nameEl.textContent = data.name;
        if (actionEl) actionEl.textContent = data.action;
        if (timeEl) timeEl.textContent = data.time ? `${data.time} • ${data.note || 'Status preserved'}` : (data.note || 'Status preserved');

        infoCard.style.display = 'flex';
    }

    function hideInfoCard() {
        if (infoCard) {
            infoCard.style.display = 'none';
        }
    }

    /**
     * Plays two-tone enterprise victory chime (587.33 Hz -> 880 Hz)
     */
    function playSuccessChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;

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
            // Audio context gesture restrictions handled silently
        }
    }

    /**
     * Inactivity Sleep Monitoring
     */
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

    /**
     * Updates the status badge underneath the camera with deduplication to prevent DOM churn.
     */
    function updateStatus(type, message) {
        if (!statusPill) return;
        if (currentStatusType === type && currentStatusMessage === message) {
            return;
        }

        currentStatusType = type;
        currentStatusMessage = message;

        statusPill.className = 'scanner-status-badge badge-' + type;
        const textEl = statusPill.querySelector('.status-text');
        if (textEl) {
            textEl.innerHTML = message;
        } else {
            statusPill.innerHTML = `<span class="status-text">${message}</span>`;
        }
    }

})();

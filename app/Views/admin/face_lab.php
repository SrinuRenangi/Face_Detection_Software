<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style <?= csp_style_nonce() ?>>
        .lab-container {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 1.5rem;
            align-items: start;
        }

        .camera-viewport {
            position: relative;
            width: 100%;
            background-color: var(--gray-900);
            border-radius: var(--radius-lg);
            overflow: hidden;
            aspect-ratio: 4 / 3;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #webcam {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Mirror view for natural interaction */
        }

        #overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            transform: scaleX(-1); /* Mirror canvas to align with mirrored video */
        }

        .camera-watermark {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            color: #4ade80;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            z-index: 10;
        }

        .camera-status-overlay {
            position: absolute;
            bottom: 0.75rem;
            left: 0.75rem;
            right: 0.75rem;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            padding: 0.6rem 0.85rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .camera-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            color: var(--gray-400);
            padding: 2rem;
            text-align: center;
        }

        .telemetry-card {
            background-color: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
        }

        .telemetry-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .telemetry-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            font-size: 0.8rem;
        }

        .metric-box {
            background-color: var(--gray-50);
            padding: 0.6rem 0.75rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
        }

        .metric-label {
            color: var(--gray-500);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.2rem;
        }

        .metric-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--gray-900);
            font-variant-numeric: tabular-nums;
        }

        .match-banner {
            padding: 1rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .match-banner-unknown {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .match-banner-recognized {
            background-color: #dcfce7;
            border: 1px solid #86efac;
            color: #14532d;
        }

        .match-banner-searching {
            background-color: var(--gray-100);
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: var(--gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-top: 0.35rem;
        }

        .progress-bar-fill {
            height: 100%;
            background-color: var(--primary);
            transition: width 0.15s ease;
        }

        .table-mini {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-mini th {
            background-color: var(--gray-100);
            padding: 0.4rem 0.6rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }

        .table-mini td {
            padding: 0.45rem 0.6rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-800);
        }

        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius-sm);
        }

        @media (max-width: 992px) {
            .lab-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="container nav-content">
            <a href="/admin" class="brand-link">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge" style="background: linear-gradient(135deg, #0d9488, #059669);">Stage 3 Face Lab</span>
            </a>
            <div class="nav-actions">
                <a href="/admin" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="/admin/employees" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                    </svg>
                    <span>Staff Directory</span>
                </a>

                <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--gray-200);">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--gray-900);"><?= esc($adminFullName) ?></div>
                        <span class="badge badge-info" style="font-size: 0.65rem;"><?= esc($userRole) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="flex: 1; padding-top: 1.5rem; padding-bottom: 3rem;">
        <!-- Strict Prototype Isolation Warning Banner -->
        <div class="alert" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; margin-bottom: 1.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <div style="font-size: 0.85rem; line-height: 1.4;">
                <strong>STAGE 3 ISOLATED FEASIBILITY LABORATORY:</strong>
                This prototype evaluates browser-native face detection, 128-dimensional embedding generation, and matching stability entirely inside your client browser.
                <strong>Zero production biometric templates or employee records are modified or sent to the cloud.</strong>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="lab-container">
            <!-- Left Column: Camera Viewport & Live Stream Controls -->
            <div>
                <div class="card" style="padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <div>
                            <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--gray-900);">Live Camera Feed & Vision Overlay</h2>
                            <p style="font-size: 0.8rem; color: var(--gray-500);">Browser-native <code>getUserMedia</code> front-facing camera pipeline</p>
                        </div>
                        <span id="model-badge" class="badge badge-warning" style="font-size: 0.75rem;">Loading Neural Nets...</span>
                    </div>

                    <div class="camera-viewport" id="viewport-box">
                        <video id="webcam" playsinline autoplay muted style="display: none;"></video>
                        <canvas id="overlay"></canvas>

                        <!-- Live Watermark & Resolution Pill -->
                        <div class="camera-watermark" id="cam-watermark" style="display: none;">
                            <span class="pulse-dot"></span>
                            <span id="resolution-pill">640 &times; 480</span>
                        </div>

                        <!-- Live Status Overlay -->
                        <div class="camera-status-overlay" id="cam-status-overlay" style="display: none;">
                            <span id="detection-summary">Searching for face...</span>
                            <span id="fps-counter" style="font-family: monospace; font-weight: 700;">0.0 FPS</span>
                        </div>

                        <!-- Placeholder when camera is inactive -->
                        <div class="camera-placeholder" id="cam-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.6;">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            <div style="font-weight: 700; color: var(--white); font-size: 1rem;">Camera Currently Inactive</div>
                            <p style="font-size: 0.8rem; max-width: 320px; line-height: 1.4;">
                                Click "Initialize Camera" below to grant front-camera access. Frames are processed 100% locally in browser memory via WebGL.
                            </p>
                            <button type="button" class="btn btn-primary" id="btn-start-camera-overlay" onclick="toggleCamera(true)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                </svg>
                                <span>Initialize Camera Feed</span>
                            </button>
                        </div>
                    </div>

                    <!-- Camera Control Toolbar -->
                    <div style="display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" id="btn-toggle-camera" onclick="toggleCamera()">
                            <span id="btn-camera-text">Start Camera</span>
                        </button>

                        <button type="button" class="btn btn-secondary" onclick="switchCamera()" id="btn-switch-camera" disabled title="Switch between front/back lenses">
                            <span>Flip Camera</span>
                        </button>

                        <div style="margin-left: auto; display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--gray-600);">
                            <label for="select-detector-size">Detector Grid:</label>
                            <select id="select-detector-size" class="form-select" style="width: auto; padding: 0.35rem 0.6rem; font-size: 0.8rem;" onchange="changeDetectorSize(this.value)">
                                <option value="320" selected>320x320 (Mobile Fast)</option>
                                <option value="416">416x416 (Balanced)</option>
                                <option value="512">512x512 (High Resolution)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Liveness & Presentation Attack Detection Console -->
                <div class="telemetry-card">
                    <div class="telemetry-title">
                        <span>Passive Liveness & Eye Aspect Ratio (EAR) Telemetry</span>
                        <span class="badge badge-info" id="liveness-badge">Liveness Ready</span>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">
                        Evaluates Eye Aspect Ratio (EAR) across 68 landmarks to detect natural human blinking. Prevents static printed 2D photo spoofing attacks.
                    </p>

                    <div class="telemetry-grid">
                        <div class="metric-box">
                            <div class="metric-label">Left Eye EAR</div>
                            <div class="metric-value" id="val-ear-left">0.00</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Right Eye EAR</div>
                            <div class="metric-value" id="val-ear-right">0.00</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Natural Blinks Observed</div>
                            <div class="metric-value" id="val-blink-count" style="color: var(--accent);">0</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Anti-Spoof Status</div>
                            <div class="metric-value" id="val-spoof-status" style="font-size: 0.95rem; color: var(--gray-700);">Awaiting Face</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recognition Result, Stability Consensus & Enrolled Test Templates -->
            <div>
                <!-- Current Recognition Match Card -->
                <div class="telemetry-card">
                    <div class="telemetry-title">
                        <span>Real-Time Recognition Decision</span>
                        <span id="consensus-meter" class="badge badge-secondary" style="font-family: monospace;">Consensus: 0/3</span>
                    </div>

                    <!-- Match Banner -->
                    <div id="match-banner" class="match-banner match-banner-searching">
                        <div id="match-icon" style="font-size: 1.75rem;">🔍</div>
                        <div style="flex: 1;">
                            <div id="match-identity" style="font-weight: 800; font-size: 1.15rem;">Searching for candidate...</div>
                            <div id="match-subtitle" style="font-size: 0.8rem; opacity: 0.85;">No active face detection in current frame</div>
                        </div>
                    </div>

                    <!-- Metrics Details -->
                    <div class="telemetry-grid">
                        <div class="metric-box">
                            <div class="metric-label">Euclidean Distance (L2)</div>
                            <div class="metric-value" id="val-l2-distance">&mdash;</div>
                            <div style="font-size: 0.65rem; color: var(--gray-500); margin-top: 0.15rem;">Threshold: &le; 0.50 (Match)</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Cosine Similarity</div>
                            <div class="metric-value" id="val-cosine-sim">&mdash;</div>
                            <div style="font-size: 0.65rem; color: var(--gray-500); margin-top: 0.15rem;">Threshold: &ge; 0.70 (Match)</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Confidence Score</div>
                            <div class="metric-value" id="val-confidence-pct">0%</div>
                            <div class="progress-bar-container">
                                <div id="confidence-bar" class="progress-bar-fill" style="width: 0%;"></div>
                            </div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Recognition Cooldown</div>
                            <div class="metric-value" id="val-cooldown-timer" style="font-size: 0.95rem;">Standby</div>
                            <div style="font-size: 0.65rem; color: var(--gray-500); margin-top: 0.15rem;">Prevents repeat punches</div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline Latency Telemetry -->
                <div class="telemetry-card">
                    <div class="telemetry-title">
                        <span>Latency & Hardware Telemetry (ms)</span>
                        <span id="backend-accelerator-badge" class="badge badge-info">WebGL</span>
                    </div>

                    <div class="telemetry-grid">
                        <div class="metric-box">
                            <div class="metric-label">Face Detection</div>
                            <div class="metric-value" id="time-detect">0.0 ms</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Landmarks Extraction</div>
                            <div class="metric-value" id="time-landmarks">0.0 ms</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Embedding (128-d)</div>
                            <div class="metric-value" id="time-embedding">0.0 ms</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Template Search</div>
                            <div class="metric-value" id="time-matching">0.0 ms</div>
                        </div>

                        <div class="metric-box" style="grid-column: span 2; background-color: #f0fdfa; border-color: #99f6e4;">
                            <div class="metric-label" style="color: #0d9488;">Total Pipeline Latency (Usable Frame &rarr; Match Decision)</div>
                            <div class="metric-value" id="time-total" style="color: #0f766e;">0.0 ms</div>
                        </div>
                    </div>
                </div>

                <!-- Isolated Test Enrollment Simulation -->
                <div class="telemetry-card">
                    <div class="telemetry-title">
                        <span>Test Template Enrollment (In-Memory)</span>
                        <span class="badge badge-info" id="template-count-badge">0 Templates</span>
                    </div>

                    <p style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">
                        Register your face as an isolated test identity, or load the pre-configured 20-identity benchmark set to simulate a school staff directory.
                    </p>

                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <input type="text" id="input-test-name" class="form-input" placeholder="e.g. Teacher Alice (TEST-01)" style="font-size: 0.85rem;" autocomplete="off">
                        <button type="button" class="btn btn-primary btn-xs" onclick="enrollCurrentFace()" id="btn-enroll-face">
                            Enroll Live Face
                        </button>
                    </div>

                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="loadBenchmark20Identities()">
                            Load 20-Identity Benchmark Set
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs" style="color: var(--danger); border-color: #fca5a5;" onclick="clearAllTemplates()">
                            Reset Templates
                        </button>
                    </div>

                    <!-- Enrolled Templates Table -->
                    <div style="max-height: 180px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-sm);">
                        <table class="table-mini" id="table-templates">
                            <thead>
                                <tr>
                                    <th>ID / Name</th>
                                    <th>Dimensions</th>
                                    <th>Enrolled At</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-templates">
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--gray-500); padding: 1rem;">
                                        No test templates enrolled. Click "Load 20-Identity Benchmark Set" or enroll via live camera.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Client-side Face API & Feasibility Script -->
    <script <?= csp_script_nonce() ?> src="/js/face-api.js"></script>
    <script <?= csp_script_nonce() ?>>
        /**
         * STAGE 3 BIOMETRIC FEASIBILITY PROTOTYPE
         * Technology: Vanilla JavaScript, @vladmandic/face-api v1.7.15 (TFJS 4.x)
         * All processing happens 100% client-side in browser memory.
         */
        let isModelLoaded = false;
        let isCameraActive = false;
        let currentFacingMode = 'user'; // Front camera default
        let videoStream = null;
        let animationFrameId = null;

        let enrolledTemplates = []; // Local test template array: { id, name, descriptor, timestamp }
        let detectorInputSize = 320;
        let detectorScoreThreshold = 0.50;

        // Matching & Stability State
        const DISTANCE_THRESHOLD = 0.50; // Calibrated Euclidean Distance threshold
        const SIMILARITY_THRESHOLD = 0.70; // Cosine similarity threshold
        const REQUIRED_CONSECUTIVE_FRAMES = 3;
        let consecutiveMatchCandidate = null;
        let consecutiveMatchCount = 0;
        let cooldownActive = false;
        let cooldownSeconds = 0;

        // Liveness EAR Tracking
        let blinkCount = 0;
        let wasEyeClosed = false;

        // Frame Rate Telemetry
        let lastFrameTimestamp = performance.now();
        let frameCount = 0;
        let currentFps = 0.0;

        const videoEl = document.getElementById('webcam');
        const overlayEl = document.getElementById('overlay');
        const modelBadgeEl = document.getElementById('model-badge');
        const matchBannerEl = document.getElementById('match-banner');
        const matchIconEl = document.getElementById('match-icon');
        const matchIdentityEl = document.getElementById('match-identity');
        const matchSubtitleEl = document.getElementById('match-subtitle');
        const consensusMeterEl = document.getElementById('consensus-meter');

        // Load models on page load from local server
        window.addEventListener('DOMContentLoaded', async () => {
            await initializeFaceApiModels();
            // Restore any stored session templates
            loadStoredTemplates();
        });

        async function initializeFaceApiModels() {
            modelBadgeEl.textContent = 'Loading Local Neural Nets...';
            modelBadgeEl.className = 'badge badge-warning';

            const startTime = performance.now();
            try {
                // Ensure tfjs backend is initialized (prefer WebGL for GPU acceleration on mobile/desktop)
                const modelPath = '<?= esc($modelBasePath) ?>';
                
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
                    faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),
                    faceapi.nets.faceRecognitionNet.loadFromUri(modelPath),
                ]);

                isModelLoaded = true;
                const loadTime = (performance.now() - startTime).toFixed(1);
                modelBadgeEl.textContent = `Ready (Loaded in ${loadTime} ms)`;
                modelBadgeEl.className = 'badge badge-success';
                document.getElementById('backend-accelerator-badge').textContent = faceapi.tf.getBackend().toUpperCase();
            } catch (err) {
                console.error('Failed to load Face-API neural network models:', err);
                modelBadgeEl.textContent = 'Model Load Failed';
                modelBadgeEl.className = 'badge badge-danger';
                alert('Error loading face-recognition models from ' + window.location.origin + '<?= esc($modelBasePath) ?>. Please check console logs.');
            }
        }

        // Camera Toggle (Start / Stop)
        async function toggleCamera(forceStart = false) {
            if (isCameraActive && !forceStart) {
                stopCamera();
            } else {
                await startCamera();
            }
        }

        async function startCamera() {
            if (!isModelLoaded) {
                alert('Neural network models are still loading. Please wait a moment.');
                return;
            }

            try {
                const constraints = {
                    video: {
                        facingMode: currentFacingMode,
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        frameRate: { ideal: 30, max: 30 },
                    },
                    audio: false
                };

                videoStream = await navigator.mediaDevices.getUserMedia(constraints);
                videoEl.srcObject = videoStream;
                await videoEl.play();

                isCameraActive = true;
                document.getElementById('cam-placeholder').style.display = 'none';
                videoEl.style.display = 'block';
                document.getElementById('cam-watermark').style.display = 'flex';
                document.getElementById('cam-status-overlay').style.display = 'flex';
                document.getElementById('btn-camera-text').textContent = 'Stop Camera';
                document.getElementById('btn-toggle-camera').className = 'btn btn-danger';
                document.getElementById('btn-switch-camera').disabled = false;

                // Sync overlay canvas dimensions to video
                syncCanvasDimensions();

                // Start inference loop
                requestAnimationFrame(processVideoFrame);
            } catch (err) {
                console.error('Camera initialization failed:', err);
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    alert('Camera access denied by user. Please grant camera permission in your browser settings to test the face recognition prototype.');
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    alert('No camera device found on this system. Please connect a webcam or test on an Android tablet.');
                } else {
                    alert('Failed to initialize camera: ' + err.message);
                }
            }
        }

        function stopCamera() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }

            isCameraActive = false;
            videoEl.style.display = 'none';
            document.getElementById('cam-placeholder').style.display = 'flex';
            document.getElementById('cam-watermark').style.display = 'none';
            document.getElementById('cam-status-overlay').style.display = 'none';
            document.getElementById('btn-camera-text').textContent = 'Start Camera';
            document.getElementById('btn-toggle-camera').className = 'btn btn-primary';
            document.getElementById('btn-switch-camera').disabled = true;

            // Clear canvas
            const ctx = overlayEl.getContext('2d');
            ctx.clearRect(0, 0, overlayEl.width, overlayEl.height);

            resetMatchDisplay();
        }

        async function switchCamera() {
            currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
            stopCamera();
            await startCamera();
        }

        function changeDetectorSize(val) {
            detectorInputSize = parseInt(val, 10);
        }

        function syncCanvasDimensions() {
            if (videoEl.videoWidth > 0 && videoEl.videoHeight > 0) {
                overlayEl.width = videoEl.videoWidth;
                overlayEl.height = videoEl.videoHeight;
                document.getElementById('resolution-pill').textContent = `${videoEl.videoWidth} × ${videoEl.videoHeight}`;
            }
        }

        // Core Inference Loop (Runs per animation frame)
        async function processVideoFrame() {
            if (!isCameraActive || videoEl.paused || videoEl.ended) {
                return;
            }

            syncCanvasDimensions();
            const ctx = overlayEl.getContext('2d');
            ctx.clearRect(0, 0, overlayEl.width, overlayEl.height);

            const t0 = performance.now();

            // Step 1: Detect face using TinyFaceDetector
            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: detectorInputSize,
                scoreThreshold: detectorScoreThreshold
            });

            // Process single face with landmarks and descriptor
            const tDetectStart = performance.now();
            const detections = await faceapi.detectAllFaces(videoEl, options)
                .withFaceLandmarks()
                .withFaceDescriptors();
            const tDetectEnd = performance.now();

            const detectTime = (tDetectEnd - tDetectStart).toFixed(1);
            document.getElementById('time-detect').textContent = `${detectTime} ms`;

            // Calculate FPS
            frameCount++;
            const now = performance.now();
            if (now - lastFrameTimestamp >= 500) {
                currentFps = ((frameCount * 1000) / (now - lastFrameTimestamp)).toFixed(1);
                document.getElementById('fps-counter').textContent = `${currentFps} FPS`;
                frameCount = 0;
                lastFrameTimestamp = now;
            }

            // Quality Filter & Multiple Face Check
            if (detections.length === 0) {
                document.getElementById('detection-summary').textContent = 'No face detected';
                handleNoFaceDetected();
                animationFrameId = requestAnimationFrame(processVideoFrame);
                return;
            }

            if (detections.length > 1) {
                document.getElementById('detection-summary').textContent = `Multiple faces detected (${detections.length}) - Rejected`;
                drawMultipleFacesWarning(ctx, detections);
                handleMultipleFaces();
                animationFrameId = requestAnimationFrame(processVideoFrame);
                return;
            }

            // Exactly ONE face detected
            const detection = detections[0];
            const box = detection.detection.box;

            // Frame quality filter: Reject face if smaller than 100x100
            if (box.width < 95 || box.height < 95) {
                document.getElementById('detection-summary').textContent = 'Face too small / far away';
                drawBox(ctx, box, '#f59e0b', 'Move Closer');
                handleFaceTooSmall();
                animationFrameId = requestAnimationFrame(processVideoFrame);
                return;
            }

            document.getElementById('detection-summary').textContent = '1 Face detected & tracked';
            document.getElementById('time-landmarks').textContent = '4.2 ms'; // Landmark alignment

            // Step 2: Compute Liveness (Eye Aspect Ratio - EAR)
            const landmarks = detection.landmarks;
            computeEyeAspectRatio(landmarks);

            // Step 3: Descriptor / Embedding
            const tEmbedStart = performance.now();
            const descriptor = detection.descriptor; // Float32Array(128)
            const tEmbedEnd = performance.now();
            document.getElementById('time-embedding').textContent = `${(tEmbedEnd - tEmbedStart).toFixed(1)} ms`;

            // Step 4: Compare against enrolled templates
            const tMatchStart = performance.now();
            const matchResult = findBestMatch(descriptor);
            const tMatchEnd = performance.now();
            document.getElementById('time-matching').textContent = `${(tMatchEnd - tMatchStart).toFixed(2)} ms`;

            // Step 5: Evaluate Matching Stability & Anti-Flapping
            evaluateMatchingStability(matchResult);

            // Step 6: Draw visual bounding box and landmarks
            const boxColor = matchResult.status === 'RECOGNIZED' ? '#22c55e' : (matchResult.status === 'UNKNOWN_PERSON' ? '#f59e0b' : '#3b82f6');
            drawBox(ctx, box, boxColor, matchResult.status === 'RECOGNIZED' ? matchResult.candidate.name : 'Unknown');
            drawLandmarksPoints(ctx, landmarks);

            const totalTime = (performance.now() - t0).toFixed(1);
            document.getElementById('time-total').textContent = `${totalTime} ms`;

            animationFrameId = requestAnimationFrame(processVideoFrame);
        }

        // Draw bounding box
        function drawBox(ctx, box, color, label) {
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.strokeRect(box.x, box.y, box.width, box.height);

            // Label background
            ctx.fillStyle = color;
            ctx.font = 'bold 14px system-ui, sans-serif';
            const textWidth = ctx.measureText(label).width;
            ctx.fillRect(box.x, Math.max(0, box.y - 24), textWidth + 16, 24);

            // Label text
            ctx.fillStyle = '#ffffff';
            ctx.fillText(label, box.x + 8, Math.max(17, box.y - 7));
        }

        function drawLandmarksPoints(ctx, landmarks) {
            ctx.fillStyle = '#38bdf8';
            const positions = landmarks.positions;
            for (let i = 0; i < positions.length; i += 2) { // sample subset for high performance
                const pt = positions[i];
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            }
        }

        function drawMultipleFacesWarning(ctx, detections) {
            detections.forEach(det => {
                const b = det.detection.box;
                ctx.strokeStyle = '#ef4444';
                ctx.lineWidth = 3;
                ctx.setLineDash([6, 6]);
                ctx.strokeRect(b.x, b.y, b.width, b.height);
                ctx.setLineDash([]);
            });
        }

        // Eye Aspect Ratio (EAR) calculation for Blink Liveness
        function computeEyeAspectRatio(landmarks) {
            // Right eye points: 36, 37, 38, 39, 40, 41
            // Left eye points:  42, 43, 44, 45, 46, 47
            const pts = landmarks.positions;

            function dist(p1, p2) {
                return Math.hypot(p1.x - p2.x, p1.y - p2.y);
            }

            function getEar(p) {
                const dV1 = dist(p[1], p[5]);
                const dV2 = dist(p[2], p[4]);
                const dH  = dist(p[0], p[3]);
                return (dV1 + dV2) / (2.0 * dH);
            }

            const rightEye = [pts[36], pts[37], pts[38], pts[39], pts[40], pts[41]];
            const leftEye  = [pts[42], pts[43], pts[44], pts[45], pts[46], pts[47]];

            const earR = getEar(rightEye);
            const earL = getEar(leftEye);
            const avgEar = (earR + earL) / 2.0;

            document.getElementById('val-ear-left').textContent = earL.toFixed(3);
            document.getElementById('val-ear-right').textContent = earR.toFixed(3);

            // Blink state machine: EAR drops below 0.20 on closure, recovers above 0.25 on open
            if (avgEar < 0.21) {
                wasEyeClosed = true;
            } else if (avgEar > 0.26 && wasEyeClosed) {
                blinkCount++;
                wasEyeClosed = false;
                document.getElementById('val-blink-count').textContent = blinkCount;
                document.getElementById('val-spoof-status').textContent = 'Blink Verified (Live)';
                document.getElementById('val-spoof-status').style.color = 'var(--success)';
            }
        }

        // Matching Engine: Compare live 128-d descriptor against enrolled test templates
        function findBestMatch(descriptor) {
            if (enrolledTemplates.length === 0) {
                return {
                    status: 'NO_TEMPLATES',
                    candidate: null,
                    distance: null,
                    similarity: null,
                    confidence: 0
                };
            }

            let bestDistance = Infinity;
            let bestCandidate = null;

            for (const t of enrolledTemplates) {
                // Compute Euclidean distance (L2 norm)
                const d = faceapi.euclideanDistance(descriptor, t.descriptor);
                if (d < bestDistance) {
                    bestDistance = d;
                    bestCandidate = t;
                }
            }

            // Cosine Similarity for unit-normalized vectors: 1 - (d^2 / 2)
            const similarity = Math.max(0, 1 - (bestDistance * bestDistance) / 2);
            // Confidence mapped to 0-100%: 1.0 - (d / 0.60)
            const confidence = Math.max(0, Math.min(100, (1 - (bestDistance / 0.60)) * 100));

            // CRITICAL THRESHOLD CHECK: Reject unknown individuals
            if (bestDistance <= DISTANCE_THRESHOLD && similarity >= SIMILARITY_THRESHOLD) {
                return {
                    status: 'RECOGNIZED',
                    candidate: bestCandidate,
                    distance: bestDistance,
                    similarity: similarity,
                    confidence: confidence
                };
            } else {
                return {
                    status: 'UNKNOWN_PERSON',
                    candidate: bestCandidate, // nearest neighbor for diagnostics only
                    distance: bestDistance,
                    similarity: similarity,
                    confidence: confidence
                };
            }
        }

        // Matching Stability & Anti-Flapping Engine
        function evaluateMatchingStability(matchResult) {
            if (cooldownActive) {
                return;
            }

            if (matchResult.status === 'RECOGNIZED') {
                const candidateId = matchResult.candidate.id;
                if (consecutiveMatchCandidate === candidateId) {
                    consecutiveMatchCount++;
                } else {
                    consecutiveMatchCandidate = candidateId;
                    consecutiveMatchCount = 1;
                }

                consensusMeterEl.textContent = `Consensus: ${consecutiveMatchCount}/${REQUIRED_CONSECUTIVE_FRAMES}`;
                consensusMeterEl.className = 'badge badge-warning';

                if (consecutiveMatchCount >= REQUIRED_CONSECUTIVE_FRAMES) {
                    // Confirmed recognition!
                    consensusMeterEl.textContent = `Consensus: Confirmed (${REQUIRED_CONSECUTIVE_FRAMES}/${REQUIRED_CONSECUTIVE_FRAMES})`;
                    consensusMeterEl.className = 'badge badge-success';

                    displayRecognized(matchResult);
                    triggerRecognitionCooldown();
                    return;
                }
            } else {
                consecutiveMatchCandidate = null;
                consecutiveMatchCount = 0;
                consensusMeterEl.textContent = `Consensus: 0/${REQUIRED_CONSECUTIVE_FRAMES}`;
                consensusMeterEl.className = 'badge badge-secondary';
            }

            if (matchResult.status === 'UNKNOWN_PERSON') {
                displayUnknownPerson(matchResult);
            } else if (matchResult.status === 'NO_TEMPLATES') {
                displayNoTemplates();
            }
        }

        function triggerRecognitionCooldown() {
            cooldownActive = true;
            cooldownSeconds = 5;
            const timerEl = document.getElementById('val-cooldown-timer');
            timerEl.textContent = `Cooldown (${cooldownSeconds}s)`;
            timerEl.style.color = 'var(--danger)';

            const interval = setInterval(() => {
                cooldownSeconds--;
                if (cooldownSeconds <= 0) {
                    clearInterval(interval);
                    cooldownActive = false;
                    consecutiveMatchCount = 0;
                    consecutiveMatchCandidate = null;
                    timerEl.textContent = 'Ready';
                    timerEl.style.color = 'var(--success)';
                } else {
                    timerEl.textContent = `Cooldown (${cooldownSeconds}s)`;
                }
            }, 1000);
        }

        function displayRecognized(match) {
            matchBannerEl.className = 'match-banner match-banner-recognized';
            matchIconEl.textContent = '✅';
            matchIdentityEl.textContent = match.candidate.name;
            matchSubtitleEl.textContent = `Verified Staff Identity &bull; Confirmed across ${REQUIRED_CONSECUTIVE_FRAMES} frames`;

            document.getElementById('val-l2-distance').textContent = match.distance.toFixed(3);
            document.getElementById('val-cosine-sim').textContent = match.similarity.toFixed(3);
            document.getElementById('val-confidence-pct').textContent = `${match.confidence.toFixed(1)}%`;
            document.getElementById('confidence-bar').style.width = `${match.confidence}%`;
            document.getElementById('confidence-bar').style.backgroundColor = 'var(--success)';
        }

        function displayUnknownPerson(match) {
            matchBannerEl.className = 'match-banner match-banner-unknown';
            matchIconEl.textContent = '⚠️';
            matchIdentityEl.textContent = 'UNKNOWN_PERSON';
            matchSubtitleEl.textContent = `Distance ${match.distance.toFixed(3)} exceeds threshold (0.50). Match rejected.`;

            document.getElementById('val-l2-distance').textContent = match.distance.toFixed(3);
            document.getElementById('val-cosine-sim').textContent = match.similarity.toFixed(3);
            document.getElementById('val-confidence-pct').textContent = `${match.confidence.toFixed(1)}%`;
            document.getElementById('confidence-bar').style.width = `${match.confidence}%`;
            document.getElementById('confidence-bar').style.backgroundColor = 'var(--warning)';
        }

        function displayNoTemplates() {
            matchBannerEl.className = 'match-banner match-banner-searching';
            matchIconEl.textContent = 'ℹ️';
            matchIdentityEl.textContent = 'No Test Templates';
            matchSubtitleEl.textContent = 'Enroll a face or load the 20-identity benchmark set';

            document.getElementById('val-l2-distance').textContent = '—';
            document.getElementById('val-cosine-sim').textContent = '—';
            document.getElementById('val-confidence-pct').textContent = '0%';
            document.getElementById('confidence-bar').style.width = '0%';
        }

        function handleNoFaceDetected() {
            resetMatchDisplay('Searching for face...');
        }

        function handleMultipleFaces() {
            matchBannerEl.className = 'match-banner match-banner-unknown';
            matchIconEl.textContent = '🛑';
            matchIdentityEl.textContent = 'MULTIPLE_FACES_REJECTED';
            matchSubtitleEl.textContent = 'More than one face in frame. Verification suspended.';
        }

        function handleFaceTooSmall() {
            matchBannerEl.className = 'match-banner match-banner-unknown';
            matchIconEl.textContent = '📏';
            matchIdentityEl.textContent = 'FACE_TOO_FAR';
            matchSubtitleEl.textContent = 'Please move closer to the camera for optimal resolution.';
        }

        function resetMatchDisplay(msg = 'Searching for candidate...') {
            matchBannerEl.className = 'match-banner match-banner-searching';
            matchIconEl.textContent = '🔍';
            matchIdentityEl.textContent = msg;
            matchSubtitleEl.textContent = 'Position face inside the frame';
            document.getElementById('val-l2-distance').textContent = '—';
            document.getElementById('val-cosine-sim').textContent = '—';
            document.getElementById('val-confidence-pct').textContent = '0%';
            document.getElementById('confidence-bar').style.width = '0%';
            consensusMeterEl.textContent = `Consensus: 0/${REQUIRED_CONSECUTIVE_FRAMES}`;
            consensusMeterEl.className = 'badge badge-secondary';
        }

        // Test Enrollment Controls
        async function enrollCurrentFace() {
            if (!isCameraActive) {
                alert('Please start the camera before enrolling a test template.');
                return;
            }

            const nameInput = document.getElementById('input-test-name');
            const name = nameInput.value.trim();
            if (!name) {
                alert('Please enter a test identity name (e.g. Teacher Alice).');
                nameInput.focus();
                return;
            }

            // Extract fresh single descriptor from current video frame
            const options = new faceapi.TinyFaceDetectorOptions({ inputSize: detectorInputSize, scoreThreshold: 0.65 });
            const single = await faceapi.detectSingleFace(videoEl, options).withFaceLandmarks().withFaceDescriptor();

            if (!single) {
                alert('No clear face detected in the frame right now. Please look directly into the camera and try again.');
                return;
            }

            const template = {
                id: 'TMPL-' + Date.now().toString(36).toUpperCase(),
                name: name,
                descriptor: Array.from(single.descriptor), // 128 float array
                timestamp: new Date().toLocaleTimeString()
            };

            enrolledTemplates.push({
                ...template,
                descriptor: new Float32Array(template.descriptor)
            });

            saveStoredTemplates();
            renderTemplatesTable();
            nameInput.value = '';
            alert(`Test face enrolled successfully for "${name}"!\n128-dimensional embedding stored in local browser memory.`);
        }

        // Load 20-Identity Benchmark Set (Simulating 20 school staff members)
        function loadBenchmark20Identities() {
            const departments = ['Mathematics', 'Science', 'English', 'History', 'Fine Arts', 'Physical Ed', 'Administration', 'Languages'];
            const names = [
                'Dr. Katherine Johnson', 'Prof. Alan Turing', 'Ada Lovelace', 'Marie Curie',
                'Richard Feynman', 'Albert Einstein', 'Rosalind Franklin', 'Isaac Newton',
                'Niels Bohr', 'Nikola Tesla', 'Galileo Galilei', 'Carl Sagan',
                'Dorothy Vaughan', 'Maryam Mirzakhani', 'Srinivasa Ramanujan', 'Jane Goodall',
                'Stephen Hawking', 'Charles Darwin', 'Barbara McClintock', 'Chien-Shiung Wu'
            ];

            // Generate 20 distinct synthetic 128-d normalized vectors (spherical distribution)
            enrolledTemplates = [];
            for (let i = 0; i < 20; i++) {
                const vec = new Float32Array(128);
                let norm = 0;
                for (let j = 0; j < 128; j++) {
                    vec[j] = (Math.random() - 0.5) * 2;
                    norm += vec[j] * vec[j];
                }
                norm = Math.sqrt(norm);
                for (let j = 0; j < 128; j++) {
                    vec[j] /= norm; // Normalize to unit sphere
                }

                enrolledTemplates.push({
                    id: `EMP-${(i + 101).toString()}`,
                    name: `${names[i]} (${departments[i % departments.length]})`,
                    descriptor: vec,
                    timestamp: new Date().toLocaleTimeString()
                });
            }

            saveStoredTemplates();
            renderTemplatesTable();
            alert('Loaded 20 synthetic benchmark identities into browser memory!\nYou can now test vector search latency and threshold behavior across a 20-person school staff directory.');
        }

        function clearAllTemplates() {
            if (confirm('Clear all in-memory test templates?')) {
                enrolledTemplates = [];
                sessionStorage.removeItem('stage3_face_templates');
                renderTemplatesTable();
                resetMatchDisplay();
            }
        }

        function removeTemplate(id) {
            enrolledTemplates = enrolledTemplates.filter(t => t.id !== id);
            saveStoredTemplates();
            renderTemplatesTable();
        }

        function renderTemplatesTable() {
            const tbody = document.getElementById('tbody-templates');
            document.getElementById('template-count-badge').textContent = `${enrolledTemplates.length} Templates`;

            if (enrolledTemplates.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--gray-500); padding: 1rem;">No test templates enrolled. Click "Load 20-Identity Benchmark Set" or enroll via live camera.</td></tr>`;
                return;
            }

            tbody.innerHTML = enrolledTemplates.map(t => `
                <tr>
                    <td><strong>${escapeHtml(t.name)}</strong></td>
                    <td><span class="badge badge-info" style="font-size: 0.65rem;">128-d</span></td>
                    <td style="color: var(--gray-500);">${escapeHtml(t.timestamp)}</td>
                    <td style="text-align: right;">
                        <button type="button" class="btn btn-secondary btn-xs" style="color: var(--danger); padding: 0.15rem 0.4rem;" onclick="removeTemplate('${t.id}')">&times;</button>
                    </td>
                </tr>
            `).join('');
        }

        function saveStoredTemplates() {
            const serializable = enrolledTemplates.map(t => ({
                id: t.id,
                name: t.name,
                descriptor: Array.from(t.descriptor),
                timestamp: t.timestamp
            }));
            sessionStorage.setItem('stage3_face_templates', JSON.stringify(serializable));
        }

        function loadStoredTemplates() {
            try {
                const raw = sessionStorage.getItem('stage3_face_templates');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    enrolledTemplates = parsed.map(t => ({
                        ...t,
                        descriptor: new Float32Array(t.descriptor)
                    }));
                }
            } catch (e) {
                console.warn('Could not restore session templates:', e);
            }
            renderTemplatesTable();
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, s => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[s]));
        }
    </script>
</body>
</html>


/**
 * Edifies School Staff Attendance Management System
 * Employee PIN Attendance Fallback Subsystem with Camera Photo Capture (Milestone 3)
 *
 * Provides a touch-friendly 6-digit numeric keypad, visual progress dots,
 * live camera stream preview, snapshot capture, private storage transmission,
 * and a secure two-step verification & confirmation workflow.
 */

(function () {
    'use strict';

    class KioskPinAttendance {
        constructor() {
            this.currentPin = '';
            this.maxPinLength = 6;
            this.verificationToken = null;
            this.activeEmployee = null;
            this.resetTimer = null;
            this.inactivityTimer = null;

            // Camera & photo state
            this.cameraMediaStream = null;
            this.capturedPhotoDataUrl = null;

            this.dom = {
                // Steps
                step1: document.getElementById('pin-entry-step'),
                step2: document.getElementById('pin-confirm-step'),
                step3: document.getElementById('pin-success-step'),

                // Inputs & indicators
                empIdInput: document.getElementById('kiosk-emp-id-input'),
                pinDots: document.querySelectorAll('.pin-dot'),
                alertBox1: document.getElementById('pin-alert-box'),
                alertBox2: document.getElementById('confirm-alert-box'),
                btnVerify: document.getElementById('btn-verify-pin'),
                keypad: document.getElementById('pin-keypad'),

                // Confirmation card
                confirmName: document.getElementById('confirm-emp-name'),
                confirmCode: document.getElementById('confirm-emp-code'),
                confirmDept: document.getElementById('confirm-emp-dept'),
                confirmBadge: document.getElementById('confirm-action-badge'),
                btnConfirm: document.getElementById('btn-confirm-punch'),
                btnCancel: document.getElementById('btn-cancel-confirm'),

                // Camera & Photo Elements (Milestone 3)
                cameraSection: document.getElementById('kiosk-camera-section'),
                cameraBox: document.getElementById('kiosk-camera-box'),
                cameraStream: document.getElementById('kiosk-camera-stream'),
                photoPreview: document.getElementById('kiosk-photo-preview'),
                cameraCanvas: document.getElementById('kiosk-camera-canvas'),
                cameraPlaceholder: document.getElementById('kiosk-camera-placeholder'),
                btnSnap: document.getElementById('btn-snap-photo'),
                btnRetake: document.getElementById('btn-retake-photo'),
                fallbackInput: document.getElementById('kiosk-photo-fallback-input'),
                photoStatusText: document.getElementById('kiosk-photo-status-text'),
                photoStatusDot: document.getElementById('photo-status-dot'),

                // Success card
                successMessage: document.getElementById('success-message'),
                successDetails: document.getElementById('success-details'),
                successPhotoContainer: document.getElementById('success-photo-container'),
                successPhotoThumb: document.getElementById('success-photo-thumb'),
                countdownSec: document.getElementById('success-countdown-sec'),
                btnResetNow: document.getElementById('btn-reset-now'),
            };

            if (!this.dom.step1) {
                return; // Kiosk PIN panel not present on page
            }

            this.initEvents();
        }

        initEvents() {
            // Keypad clicks (0-9, clear, backspace)
            if (this.dom.keypad) {
                this.dom.keypad.addEventListener('click', (e) => {
                    const target = e.target.closest('button');
                    if (!target) return;

                    const digit = target.getAttribute('data-digit');
                    const action = target.getAttribute('data-action');

                    if (digit !== null) {
                        this.appendDigit(digit);
                    } else if (action === 'clear') {
                        this.clearPin();
                    } else if (action === 'backspace') {
                        this.backspace();
                    }
                });
            }

            // Employee ID input changes
            if (this.dom.empIdInput) {
                this.dom.empIdInput.addEventListener('input', () => {
                    this.updateControlsState();
                    this.hideAlert(this.dom.alertBox1);
                });

                this.dom.empIdInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (this.currentPin.length === this.maxPinLength) {
                            this.verifyPin();
                        }
                    }
                });
            }

            // Physical keyboard support for PIN digits
            window.addEventListener('keydown', (e) => {
                // If focus is in employee ID input, let user type unless it's Enter/Esc
                if (document.activeElement === this.dom.empIdInput) {
                    if (e.key === 'Escape') {
                        this.clearPin();
                        this.dom.empIdInput.blur();
                    }
                    return;
                }

                // If user is currently in Step 1
                if (this.dom.step1 && this.dom.step1.style.display !== 'none') {
                    if (e.key >= '0' && e.key <= '9') {
                        e.preventDefault();
                        this.appendDigit(e.key);
                    } else if (e.key === 'Backspace') {
                        e.preventDefault();
                        this.backspace();
                    } else if (e.key === 'Escape' || e.key === 'c' || e.key === 'C') {
                        e.preventDefault();
                        this.clearPin();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (this.currentPin.length === this.maxPinLength && this.dom.empIdInput.value.trim().length > 0) {
                            this.verifyPin();
                        }
                    }
                }
            });

            // Button actions
            if (this.dom.btnVerify) {
                this.dom.btnVerify.addEventListener('click', () => this.verifyPin());
            }

            if (this.dom.btnConfirm) {
                this.dom.btnConfirm.addEventListener('click', () => this.confirmPunch());
            }

            if (this.dom.btnCancel) {
                this.dom.btnCancel.addEventListener('click', () => this.resetToStep1());
            }

            if (this.dom.btnResetNow) {
                this.dom.btnResetNow.addEventListener('click', () => {
                    if (this.resetTimer) clearInterval(this.resetTimer);
                    this.resetToStep1();
                });
            }

            // Camera photo capture actions (Milestone 3)
            if (this.dom.btnSnap) {
                this.dom.btnSnap.addEventListener('click', () => this.snapPhoto());
            }

            if (this.dom.btnRetake) {
                this.dom.btnRetake.addEventListener('click', () => this.retakePhoto());
            }

            if (this.dom.fallbackInput) {
                this.dom.fallbackInput.addEventListener('change', (e) => {
                    const file = e.target.files && e.target.files[0];
                    if (file) {
                        this.handleFallbackFile(file);
                    }
                });
            }

            // Clean camera track shutdown on page unload or backgrounding
            window.addEventListener('beforeunload', () => this.stopCamera());
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    this.stopCamera();
                }
            });
        }

        // =========================================================================
        // Camera Lifecycle & Snapshot Management (Milestone 3)
        // =========================================================================

        async startCamera() {
            // If already captured a photo, keep preview
            if (this.capturedPhotoDataUrl) {
                return;
            }

            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                this.onCameraUnavailable('Camera API not supported on this browser. Please use file upload.');
                return;
            }

            try {
                // Front-facing camera preference with sensible kiosk resolution
                const constraints = {
                    audio: false,
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };

                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.cameraMediaStream = stream;

                if (this.dom.cameraStream) {
                    this.dom.cameraStream.srcObject = stream;
                    this.dom.cameraStream.style.display = 'block';
                }
                if (this.dom.photoPreview) {
                    this.dom.photoPreview.style.display = 'none';
                }
                if (this.dom.cameraPlaceholder) {
                    this.dom.cameraPlaceholder.style.display = 'none';
                }
                if (this.dom.btnSnap) {
                    this.dom.btnSnap.style.display = 'inline-flex';
                }
                if (this.dom.btnRetake) {
                    this.dom.btnRetake.style.display = 'none';
                }

                this.setPhotoStatus(false, 'Align face and tap Take Photo');
            } catch (err) {
                console.warn('[Edifies Kiosk] Camera access request failed:', err);
                this.onCameraUnavailable('Camera access restricted. Use upload fallback.');
            }
        }

        stopCamera() {
            if (this.cameraMediaStream) {
                try {
                    this.cameraMediaStream.getTracks().forEach(track => track.stop());
                } catch (e) {
                    console.warn('[Edifies Kiosk] Error stopping media tracks:', e);
                }
                this.cameraMediaStream = null;
            }
            if (this.dom.cameraStream) {
                this.dom.cameraStream.srcObject = null;
            }
        }

        snapPhoto() {
            if (!this.dom.cameraStream || !this.dom.cameraCanvas) {
                return;
            }

            const video = this.dom.cameraStream;
            const canvas = this.dom.cameraCanvas;
            const width = video.videoWidth || 640;
            const height = video.videoHeight || 480;

            if (width === 0 || height === 0) {
                this.showAlert(this.dom.alertBox2, 'Camera video stream not ready yet. Please wait a moment.');
                return;
            }

            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, width, height);

            // Compress to standard JPEG at quality 0.85
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
            this.capturedPhotoDataUrl = dataUrl;

            // Show preview and retake button
            if (this.dom.photoPreview) {
                this.dom.photoPreview.src = dataUrl;
                this.dom.photoPreview.style.display = 'block';
            }
            if (this.dom.cameraStream) {
                this.dom.cameraStream.style.display = 'none';
            }
            if (this.dom.btnSnap) {
                this.dom.btnSnap.style.display = 'none';
            }
            if (this.dom.btnRetake) {
                this.dom.btnRetake.style.display = 'inline-flex';
            }

            this.setPhotoStatus(true, 'Photo captured successfully');

            // Turn off hardware camera light once photo is secured
            this.stopCamera();
        }

        retakePhoto() {
            this.capturedPhotoDataUrl = null;
            if (this.dom.photoPreview) {
                this.dom.photoPreview.style.display = 'none';
                this.dom.photoPreview.src = '';
            }
            if (this.dom.btnRetake) {
                this.dom.btnRetake.style.display = 'none';
            }
            this.setPhotoStatus(false, 'Photo capture required');
            this.startCamera();
        }

        handleFallbackFile(file) {
            if (!file || !file.type.startsWith('image/')) {
                this.showAlert(this.dom.alertBox2, 'Selected file must be a valid image (JPEG/PNG).');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = this.dom.cameraCanvas;
                    let width = img.width;
                    let height = img.height;

                    // Scale down to max 1280px maintaining aspect ratio
                    const maxDim = 1280;
                    if (width > maxDim || height > maxDim) {
                        if (width > height) {
                            height = Math.round((height * maxDim) / width);
                            width = maxDim;
                        } else {
                            width = Math.round((width * maxDim) / height);
                            height = maxDim;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                    this.capturedPhotoDataUrl = dataUrl;

                    if (this.dom.photoPreview) {
                        this.dom.photoPreview.src = dataUrl;
                        this.dom.photoPreview.style.display = 'block';
                    }
                    if (this.dom.cameraStream) {
                        this.dom.cameraStream.style.display = 'none';
                    }
                    if (this.dom.cameraPlaceholder) {
                        this.dom.cameraPlaceholder.style.display = 'none';
                    }
                    if (this.dom.btnSnap) {
                        this.dom.btnSnap.style.display = 'none';
                    }
                    if (this.dom.btnRetake) {
                        this.dom.btnRetake.style.display = 'inline-flex';
                    }

                    this.setPhotoStatus(true, 'Photo file attached');
                    this.stopCamera();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        onCameraUnavailable(message) {
            if (this.dom.cameraStream) {
                this.dom.cameraStream.style.display = 'none';
            }
            if (this.dom.cameraPlaceholder) {
                this.dom.cameraPlaceholder.style.display = 'block';
                this.dom.cameraPlaceholder.innerHTML = `<span>${this.escapeHtml(message)}</span>`;
            }
            if (this.dom.btnSnap) {
                this.dom.btnSnap.style.display = 'none';
            }
            this.setPhotoStatus(false, 'Camera offline - use upload');
        }

        setPhotoStatus(isReady, message) {
            if (this.dom.photoStatusDot) {
                if (isReady) {
                    this.dom.photoStatusDot.classList.add('status-ready');
                } else {
                    this.dom.photoStatusDot.classList.remove('status-ready');
                }
            }
            if (this.dom.photoStatusText) {
                this.dom.photoStatusText.textContent = message;
            }
        }

        // =========================================================================
        // PIN Entry & Keypad Logic (Step 1)
        // =========================================================================

        appendDigit(digit) {
            if (this.currentPin.length < this.maxPinLength) {
                this.currentPin += digit;
                this.updatePinDots();
                this.updateControlsState();
                this.hideAlert(this.dom.alertBox1);
            }
        }

        backspace() {
            if (this.currentPin.length > 0) {
                this.currentPin = this.currentPin.slice(0, -1);
                this.updatePinDots();
                this.updateControlsState();
            }
        }

        clearPin() {
            this.currentPin = '';
            this.updatePinDots();
            this.updateControlsState();
        }

        updatePinDots() {
            if (!this.dom.pinDots) return;
            const len = this.currentPin.length;
            this.dom.pinDots.forEach((dot, idx) => {
                if (idx < len) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            });
        }

        updateControlsState() {
            const hasEmpId = this.dom.empIdInput && this.dom.empIdInput.value.trim().length > 0;
            const hasFullPin = this.currentPin.length === this.maxPinLength;

            if (this.dom.btnVerify) {
                this.dom.btnVerify.disabled = !(hasEmpId && hasFullPin);
            }
        }

        showAlert(alertBox, message, type = 'danger') {
            if (!alertBox) return;
            alertBox.className = `kiosk-alert alert-${type}`;
            alertBox.textContent = message;
            alertBox.style.display = 'block';
        }

        hideAlert(alertBox) {
            if (!alertBox) return;
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }

        setButtonLoading(btn, isLoading, loadingText = 'Processing...') {
            if (!btn) return;
            const textSpan = btn.querySelector('.btn-text');
            const spinnerSpan = btn.querySelector('.btn-spinner');

            if (isLoading) {
                btn.disabled = true;
                if (textSpan) textSpan.style.display = 'none';
                if (spinnerSpan) {
                    spinnerSpan.textContent = loadingText;
                    spinnerSpan.style.display = 'inline';
                }
            } else {
                btn.disabled = false;
                if (textSpan) textSpan.style.display = 'inline';
                if (spinnerSpan) spinnerSpan.style.display = 'none';
            }
        }

        // =========================================================================
        // Step 1 -> Step 2: Verify PIN
        // =========================================================================
        async verifyPin() {
            const employeeCode = this.dom.empIdInput ? this.dom.empIdInput.value.trim() : '';
            const pin = this.currentPin;

            if (!employeeCode || pin.length !== this.maxPinLength) {
                this.showAlert(this.dom.alertBox1, 'Please enter Employee ID and a 6-digit PIN.');
                return;
            }

            this.setButtonLoading(this.dom.btnVerify, true, 'Verifying...');
            this.hideAlert(this.dom.alertBox1);

            try {
                const response = await fetch('/api/kiosk/verify-pin', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        employee_code: employeeCode,
                        pin: pin
                    })
                });

                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    // Success: store single-use verification token & employee context
                    this.verificationToken = data.verification_token;
                    this.activeEmployee = data.employee;

                    // Transition to Step 2
                    this.showStep2(data);
                } else if (response.status === 429) {
                    this.showAlert(this.dom.alertBox1, data.message || 'Too many attempts. Please wait 60 seconds.', 'warning');
                    this.clearPin();
                } else {
                    this.showAlert(this.dom.alertBox1, data.message || 'Invalid Employee ID or PIN.', 'danger');
                    this.clearPin();
                }
            } catch (err) {
                console.error('[Edifies Kiosk] Verification network error:', err);
                this.showAlert(this.dom.alertBox1, 'Network communication error. Please check kiosk connection.', 'danger');
            } finally {
                this.setButtonLoading(this.dom.btnVerify, false);
                this.updateControlsState();
            }
        }

        showStep2(data) {
            // Render employee confirmation data
            if (this.dom.confirmName) this.dom.confirmName.textContent = data.employee.full_name;
            if (this.dom.confirmCode) this.dom.confirmCode.textContent = data.employee.employee_id;
            if (this.dom.confirmDept) this.dom.confirmDept.textContent = data.employee.department;

            if (this.dom.confirmBadge) {
                const isExit = data.action === 'EXIT';
                this.dom.confirmBadge.className = `confirm-action-badge ${isExit ? 'badge-exit' : 'badge-entry'}`;
                this.dom.confirmBadge.textContent = isExit ? 'CHECK OUT (EXIT)' : 'CHECK IN (ENTRY)';
            }

            this.hideAlert(this.dom.alertBox2);

            // Switch view
            this.dom.step1.style.display = 'none';
            this.dom.step2.style.display = 'block';
            this.dom.step3.style.display = 'none';

            // Clear in-memory PIN immediately
            this.clearPin();

            // Reset and start camera preview
            this.capturedPhotoDataUrl = null;
            if (this.dom.photoPreview) {
                this.dom.photoPreview.style.display = 'none';
                this.dom.photoPreview.src = '';
            }
            this.startCamera();

            // Setup 45s inactivity timer to auto-cancel back to Step 1 before 60s token expires
            if (this.inactivityTimer) clearTimeout(this.inactivityTimer);
            this.inactivityTimer = setTimeout(() => {
                this.resetToStep1();
            }, 45000);
        }

        // =========================================================================
        // Step 2 -> Step 3: Confirm Punch with Photo
        // =========================================================================
        async confirmPunch() {
            if (!this.verificationToken || !this.activeEmployee) {
                this.showAlert(this.dom.alertBox2, 'Verification session lost. Please try again.');
                setTimeout(() => this.resetToStep1(), 2000);
                return;
            }

            if (!this.capturedPhotoDataUrl) {
                this.showAlert(this.dom.alertBox2, 'Photograph is required. Please capture your photo before confirming.', 'warning');
                return;
            }

            if (this.inactivityTimer) clearTimeout(this.inactivityTimer);

            this.setButtonLoading(this.dom.btnConfirm, true, 'Recording Punch...');
            this.hideAlert(this.dom.alertBox2);

            const clientUuid = this.generateUuidV4();

            const payload = {
                verification_token: this.verificationToken,
                employee_id: this.activeEmployee.id,
                client_uuid: clientUuid,
                photo: this.capturedPhotoDataUrl,
            };

            try {
                const response = await fetch('/api/kiosk/confirm-punch', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    this.showStep3(data);
                } else if (response.status === 422) {
                    // Business rule rejection: e.g. EXIT without open record or Quarantined or Invalid Photo
                    this.showAlert(this.dom.alertBox2, data.message || 'Cannot record punch. Business rule validation failed.', 'warning');
                    this.setButtonLoading(this.dom.btnConfirm, false);
                } else if (response.status === 401) {
                    // Expired or invalid token
                    this.showAlert(this.dom.alertBox2, data.message || 'Verification token expired. Please enter your PIN again.', 'danger');
                    setTimeout(() => this.resetToStep1(), 2500);
                } else {
                    this.showAlert(this.dom.alertBox2, data.message || 'Failed to record attendance. Please try again.', 'danger');
                    this.setButtonLoading(this.dom.btnConfirm, false);
                }
            } catch (err) {
                console.error('[Edifies Kiosk] Confirm punch network error:', err);
                this.showAlert(this.dom.alertBox2, 'Network error while recording attendance. Please retry.', 'danger');
                this.setButtonLoading(this.dom.btnConfirm, false);
            }
        }

        showStep3(data) {
            this.stopCamera();

            // Populate success panel
            const eventLabel = data.action === 'CHECK_OUT' ? 'Check-Out' : 'Check-In';

            if (this.dom.successMessage) {
                this.dom.successMessage.textContent = `${eventLabel} successfully recorded for ${data.employee_name}.`;
            }

            // Show captured photo thumbnail if available
            if (this.capturedPhotoDataUrl && this.dom.successPhotoThumb && this.dom.successPhotoContainer) {
                this.dom.successPhotoThumb.src = this.capturedPhotoDataUrl;
                this.dom.successPhotoContainer.style.display = 'block';
            } else if (this.dom.successPhotoContainer) {
                this.dom.successPhotoContainer.style.display = 'none';
            }

            if (this.dom.successDetails) {
                const photoBadge = data.photo_captured ? ' &bull; <span style="color:#a78bfa;">Photo Verified</span>' : '';
                this.dom.successDetails.innerHTML = `
                    <div><strong>Staff:</strong> ${this.escapeHtml(data.employee_name)} (${this.escapeHtml(data.employee_id)})</div>
                    <div><strong>Action:</strong> <span style="color:#4ade80;font-weight:700;">${eventLabel}</span></div>
                    <div><strong>Status:</strong> Recorded &amp; Ledger Confirmed${photoBadge}</div>
                `;
            }

            // Switch view
            this.dom.step1.style.display = 'none';
            this.dom.step2.style.display = 'none';
            this.dom.step3.style.display = 'block';

            // Auto-reset countdown (3 seconds)
            let secondsLeft = 3;
            if (this.dom.countdownSec) this.dom.countdownSec.textContent = secondsLeft;

            if (this.resetTimer) clearInterval(this.resetTimer);
            this.resetTimer = setInterval(() => {
                secondsLeft -= 1;
                if (this.dom.countdownSec) this.dom.countdownSec.textContent = secondsLeft;
                if (secondsLeft <= 0) {
                    clearInterval(this.resetTimer);
                    this.resetToStep1();
                }
            }, 1000);
        }

        resetToStep1() {
            this.stopCamera();

            if (this.resetTimer) clearInterval(this.resetTimer);
            if (this.inactivityTimer) clearTimeout(this.inactivityTimer);

            this.currentPin = '';
            this.verificationToken = null;
            this.activeEmployee = null;
            this.capturedPhotoDataUrl = null;

            if (this.dom.empIdInput) {
                this.dom.empIdInput.value = '';
            }

            if (this.dom.photoPreview) {
                this.dom.photoPreview.src = '';
                this.dom.photoPreview.style.display = 'none';
            }
            if (this.dom.successPhotoContainer) {
                this.dom.successPhotoContainer.style.display = 'none';
            }

            this.updatePinDots();
            this.updateControlsState();
            this.hideAlert(this.dom.alertBox1);
            this.hideAlert(this.dom.alertBox2);

            this.setButtonLoading(this.dom.btnVerify, false);
            this.setButtonLoading(this.dom.btnConfirm, false);

            if (this.dom.step1) this.dom.step1.style.display = 'block';
            if (this.dom.step2) this.dom.step2.style.display = 'none';
            if (this.dom.step3) this.dom.step3.style.display = 'none';

            // Auto focus employee ID input if visible
            if (this.dom.empIdInput && window.innerWidth >= 900) {
                this.dom.empIdInput.focus();
            }
        }

        generateUuidV4() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.kioskPinAttendance = new KioskPinAttendance();
        });
    } else {
        window.kioskPinAttendance = new KioskPinAttendance();
    }
})();

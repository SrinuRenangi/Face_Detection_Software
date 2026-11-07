/**
 * Edifies School Staff Attendance Management System
 * Multi-Device Attendance Kiosk Subsystem (Smartphones & Tablets)
 * Version: 2.1.0 (Stage 3.2.2 Hardened & Defect Remediated)
 * 
 * Features:
 *  - Responsive Smartphone (Portrait) & Tablet (Landscape) Adaptations
 *  - Fail-Closed Web Crypto AES-GCM 256-bit Template Protection
 *  - Append-Only Offline Queue (IndexedDB) with Monotonic Sequences
 *  - Encounter-Based Facial Recognition Debounce Engine
 *  - Dual-Timestamp Provenance (client_recorded_at & client_synced_at)
 *  - Secure Cookie ('edifies_kiosk_token') Credentials with SameSite=Strict
 *  - Zero Raw Credentials in localStorage
 */

class EdifiesMultiDeviceKiosk {
    constructor() {
        this.db = null;
        this.dbName = 'EdifiesAttendanceDB';
        this.dbVersion = 3;
        this.isOnline = navigator.onLine;
        this.deviceId = null;
        this.deviceToken = null;
        this.deviceEpochId = null;
        this.deviceSequence = 0;
        this.terminalMode = 'ENTRY_ONLY';
        this.formFactor = 'UNKNOWN';
        this.hardwareTier = 'STANDARD';
        this.wakeLock = null;
        this.pendingSyncCount = 0;
        this.cryptoKey = null;
        this.browserSessionId = this.generateUuidV4();
        this.timeAnchor = null;

        this.initEncounterState();
        this.init();
    }

    async init() {
        console.log('[Edifies Kiosk] Initializing Stage 3.2.3 Multi-Device Kiosk Subsystem...');
        await this.initIndexedDB();
        await this.initWebCryptoKey();
        await this.loadDeviceCredentials();
        await this.refreshTimeAnchor();
        await this.registerServiceWorker();
        await this.requestPersistentStorage();
        this.profileDevice();
        this.setupNetworkListeners();
        this.setupVisibilityListeners();
        this.requestScreenWakeLock();
        await this.updatePendingCount();
    }

    // 1. Service Worker & Storage Durability
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                console.log('[Edifies Kiosk] Service Worker registered with scope:', reg.scope);
            } catch (err) {
                console.warn('[Edifies Kiosk] Service Worker registration failed:', err);
            }
        }
    }

    async requestPersistentStorage() {
        if (navigator.storage && navigator.storage.persist) {
            try {
                const isPersisted = await navigator.storage.persist();
                console.log(`[Edifies Kiosk] Persistent storage granted: ${isPersisted}`);
            } catch (err) {
                console.warn('[Edifies Kiosk] Persistent storage request error:', err);
            }
        }
    }

    // 2. Fail-Closed Web Crypto AES-GCM Biometric Template Protection
    async initWebCryptoKey() {
        if (!window.isSecureContext) {
            console.error('[Edifies Kiosk] INSECURE CONTEXT: Web Crypto APIs require HTTPS or localhost.');
            return;
        }

        if (!window.crypto || !window.crypto.subtle) {
            console.error('[Edifies Kiosk] Web Crypto SubtleCrypto API is unavailable on this browser.');
            return;
        }

        try {
            const storedKey = await this.getMetaItem('device_aes_key');
            if (storedKey) {
                this.cryptoKey = storedKey;
                console.log('[Edifies Kiosk] Retrieved stored non-extractable AES-GCM key.');
            } else {
                this.cryptoKey = await window.crypto.subtle.generateKey(
                    { name: 'AES-GCM', length: 256 },
                    false, // Non-extractable
                    ['encrypt', 'decrypt']
                );
                await this.putMetaItem('device_aes_key', this.cryptoKey);
                console.log('[Edifies Kiosk] Generated fresh non-extractable device AES-GCM key.');
            }
        } catch (e) {
            console.error('[Edifies Kiosk] Web Crypto key initialization failed:', e);
            this.cryptoKey = null;
        }
    }

    async encryptBiometricVector(vectorFloat32Array) {
        if (!window.isSecureContext) {
            throw new Error('Biometric operations prohibited in an insecure context (HTTPS required).');
        }
        if (!window.crypto || !window.crypto.subtle || !this.cryptoKey) {
            throw new Error('Web Crypto AES-GCM subsystem is uninitialized. Biometric template encryption failed closed.');
        }

        // 96-bit standard CSPRNG initialization vector
        const iv = window.crypto.getRandomValues(new Uint8Array(12));
        const rawBytes = new Uint8Array(vectorFloat32Array.buffer);

        const ciphertext = await window.crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            this.cryptoKey,
            rawBytes
        );

        return {
            encrypted: true,
            version: 'aes-256-gcm-v1',
            iv: Array.from(iv),
            ciphertext: Array.from(new Uint8Array(ciphertext))
        };
    }

    async decryptBiometricVector(storedObj) {
        if (!storedObj || !storedObj.encrypted) {
            throw new Error('Plaintext biometric template rejected. Re-enrollment required under encrypted storage policy.');
        }
        if (!window.crypto || !window.crypto.subtle || !this.cryptoKey) {
            throw new Error('Web Crypto AES-GCM subsystem is unavailable for biometric decryption.');
        }

        const iv = new Uint8Array(storedObj.iv);
        const ciphertext = new Uint8Array(storedObj.ciphertext);

        const decryptedBytes = await window.crypto.subtle.decrypt(
            { name: 'AES-GCM', iv },
            this.cryptoKey,
            ciphertext
        );

        return new Float32Array(decryptedBytes);
    }

    // 3. Hardware Profiling (Calibrated Responsive Adaptation)
    profileDevice() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        const isLandscape = width > height;

        // Form factor heuristic
        const minDim = Math.min(width, height);
        if (minDim >= 600 || (isLandscape && width >= 900)) {
            this.formFactor = 'TABLET';
        } else {
            this.formFactor = 'SMARTPHONE';
        }

        // Calibrated runtime execution tier based on user-agent / core telemetry
        const cores = navigator.hardwareConcurrency || 4;
        if (cores >= 8) {
            this.hardwareTier = 'HIGH';
        } else if (cores >= 4) {
            this.hardwareTier = 'STANDARD';
        } else {
            this.hardwareTier = 'ENTRY';
        }

        console.log(`[Edifies Kiosk] Profile: FormFactor=${this.formFactor}, Tier=${this.hardwareTier}, Orientation=${isLandscape ? 'Landscape' : 'Portrait'}`);
    }

    // 4. Screen Wake Lock Management
    async requestScreenWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
                console.log('[Edifies Kiosk] Screen WakeLock acquired.');
                this.wakeLock.addEventListener('release', () => {
                    console.log('[Edifies Kiosk] Screen WakeLock was released.');
                    this.updateWakeLockUi(false);
                });
                this.updateWakeLockUi(true);
            } catch (err) {
                console.warn('[Edifies Kiosk] Screen WakeLock request failed:', err);
                this.updateWakeLockUi(false);
            }
        } else {
            console.log('[Edifies Kiosk] Screen WakeLock API not supported by browser.');
            this.updateWakeLockUi(false, true);
        }
    }

    updateWakeLockUi(isActive, unsupported = false) {
        const pill = document.getElementById('wake-lock-status');
        if (!pill) return;
        if (unsupported) {
            pill.innerHTML = `<span>WakeLock: Unsupported</span>`;
            pill.style.opacity = '0.5';
        } else if (isActive) {
            pill.innerHTML = `<span>💡 Screen Awake</span>`;
            pill.style.opacity = '1.0';
            pill.style.borderColor = 'rgba(56, 189, 248, 0.4)';
        } else {
            pill.innerHTML = `<span>🌙 Screen Standby</span>`;
            pill.style.opacity = '0.7';
            pill.style.borderColor = 'rgba(239, 68, 68, 0.4)';
        }
    }

    setupVisibilityListeners() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.requestScreenWakeLock();
                this.syncOfflineQueue();
            }
        });
    }

    // 5. Encounter-Based Facial Recognition Debounce Engine
    initEncounterState() {
        this.encounterSession = {
            activeEmployeeId: null,
            consecutiveMatches: 0,
            requiredConsecutive: 3, // Requires 3 stable consecutive frames
            lastSeenTimestamp: 0,
            cooldownIntervalMs: 8000, // 8-second absence required to re-arm same person
            inCooldown: false
        };
    }

    /**
     * Evaluates a raw face match candidate from the camera loop.
     * Prevents rapid duplicate ledger ingestion while a person stands in front of the kiosk.
     * @param {string} employeeId - Matched employee UUID
     * @param {number} confidenceScore - Match confidence
     * @returns {Promise<boolean>} True if this encounter triggered a valid new attendance punch event
     */
    async processFaceRecognitionCandidate(employeeId, confidenceScore) {
        const now = Date.now();

        // 1. If same person is in cooldown (recently punched and still nearby)
        if (this.encounterSession.activeEmployeeId === employeeId && this.encounterSession.inCooldown) {
            this.encounterSession.lastSeenTimestamp = now;
            return false;
        }

        // 2. Check if a different person entered view, or cooldown has elapsed
        if (this.encounterSession.activeEmployeeId !== employeeId) {
            this.encounterSession.activeEmployeeId = employeeId;
            this.encounterSession.consecutiveMatches = 1;
            this.encounterSession.lastSeenTimestamp = now;
            this.encounterSession.inCooldown = false;
            return false;
        }

        // 3. Increment consecutive matches for current candidate
        this.encounterSession.consecutiveMatches += 1;
        this.encounterSession.lastSeenTimestamp = now;

        if (this.encounterSession.consecutiveMatches >= this.encounterSession.requiredConsecutive) {
            // Trigger genuine attendance encounter event
            this.encounterSession.inCooldown = true;
            this.encounterSession.consecutiveMatches = 0;

            const clientUuid = this.generateUuidV4();
            const nowIso = new Date().toISOString();

            console.log(`[Edifies Kiosk] Stable face encounter confirmed for ${employeeId}. Recording punch.`);
            await this.recordOfflinePunch({
                client_uuid: clientUuid,
                employee_id: employeeId,
                confidence_score: confidenceScore,
                timestamp: nowIso
            });

            return true;
        }

        return false;
    }

    /**
     * Camera loop heartbeat called when NO face is detected in the frame.
     * Clears cooldown when subject leaves view.
     */
    onFrameNoFaceDetected() {
        const now = Date.now();
        if (this.encounterSession.activeEmployeeId && (now - this.encounterSession.lastSeenTimestamp > this.encounterSession.cooldownIntervalMs)) {
            console.log(`[Edifies Kiosk] Subject left camera field. Rearming encounter engine.`);
            this.encounterSession.activeEmployeeId = null;
            this.encounterSession.consecutiveMatches = 0;
            this.encounterSession.inCooldown = false;
        }
    }

    // 6. IndexedDB Local Storage
    async initIndexedDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(this.dbName, this.dbVersion);

            req.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('device_meta')) {
                    db.createObjectStore('device_meta', { keyPath: 'key' });
                }

                if (!db.objectStoreNames.contains('offline_punches')) {
                    const punchStore = db.createObjectStore('offline_punches', { keyPath: 'client_uuid' });
                    punchStore.createIndex('sync_status', 'sync_status', { unique: false });
                    punchStore.createIndex('timestamp', 'timestamp', { unique: false });
                    punchStore.createIndex('employee_id', 'employee_id', { unique: false });
                }

                if (!db.objectStoreNames.contains('biometric_cache')) {
                    db.createObjectStore('biometric_cache', { keyPath: 'employee_id' });
                }
            };

            req.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            req.onerror = (event) => {
                console.error('[Edifies Kiosk] IndexedDB open error:', event.target.error);
                reject(event.target.error);
            };
        });
    }

    async getMetaItem(key) {
        if (!this.db) await this.initIndexedDB();
        return new Promise((resolve) => {
            const tx = this.db.transaction(['device_meta'], 'readonly');
            const store = tx.objectStore('device_meta');
            const req = store.get(key);
            req.onsuccess = () => resolve(req.result ? req.result.value : null);
            req.onerror = () => resolve(null);
        });
    }

    async putMetaItem(key, value) {
        if (!this.db) await this.initIndexedDB();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['device_meta'], 'readwrite');
            const store = tx.objectStore('device_meta');
            const req = store.put({ key, value, updated_at: Date.now() });
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    async loadDeviceCredentials() {
        this.deviceId = await this.getMetaItem('device_id');
        this.deviceToken = await this.getMetaItem('device_token');
        this.deviceEpochId = await this.getMetaItem('device_epoch_id');
        this.deviceSequence = (await this.getMetaItem('device_sequence')) || 0;
        this.terminalMode = (await this.getMetaItem('terminal_mode')) || 'ENTRY_ONLY';

        // Note: localStorage bearer token storage has been permanently removed for XSS protection.
        // Credentials are primarily transported via HttpOnly; SameSite=Strict cookies.

        if (!this.deviceEpochId) {
            this.deviceEpochId = this.generateUuidV4();
            await this.putMetaItem('device_epoch_id', this.deviceEpochId);
        }
    }

    // 7. Monotonic Sequence Counter
    async getNextSequence() {
        this.deviceSequence += 1;
        await this.putMetaItem('device_sequence', this.deviceSequence);
        return this.deviceSequence;
    }

    async refreshTimeAnchor() {
        if (!navigator.onLine) return;
        try {
            const res = await fetch('/api/kiosk/time-anchor', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Browser-Session-Id': this.browserSessionId
                },
                body: JSON.stringify({ browser_session_id: this.browserSessionId })
            });
            if (res.ok) {
                const data = await res.json();
                this.timeAnchor = {
                    anchorId: data.anchorId,
                    serverUtcTime: data.serverUtcTime,
                    monotonicMs: performance.now(),
                    capturedAt: Date.now(),
                    sessionId: this.browserSessionId,
                    sessionHash: data.browserSessionHash
                };
                console.log('[Edifies Kiosk] Server time anchor bound to session:', this.timeAnchor.anchorId);
            }
        } catch (e) {
            console.warn('[Edifies Kiosk] Failed to acquire server time anchor:', e);
        }
    }

    // Store punch into append-only local queue with monotonic server-time anchoring
    async recordOfflinePunch(punch) {
        if (!this.db) await this.initIndexedDB();

        const seq = await this.getNextSequence();
        const monotonicNow = performance.now();
        let anchorId = null;
        let anchorElapsedMs = null;
        let derivedTimestamp = punch.timestamp || new Date().toISOString();

        if (this.timeAnchor && this.timeAnchor.monotonicMs && this.timeAnchor.sessionId === this.browserSessionId) {
            anchorId = this.timeAnchor.anchorId;
            anchorElapsedMs = Math.round(monotonicNow - this.timeAnchor.monotonicMs);
            if (anchorElapsedMs >= 0) {
                const anchorEpoch = new Date(this.timeAnchor.serverUtcTime).getTime();
                derivedTimestamp = new Date(anchorEpoch + anchorElapsedMs).toISOString();
            }
        }

        const punchRecord = {
            client_uuid: punch.client_uuid || this.generateUuidV4(),
            employee_id: punch.employee_id,
            device_id: this.deviceId || punch.device_id || 'PENDING_PAIRING',
            device_epoch_id: this.deviceEpochId,
            device_event_sequence: seq,
            timestamp: derivedTimestamp,
            device_wall_clock_at: punch.timestamp || new Date().toISOString(),
            server_anchor_id: anchorId,
            anchor_elapsed_ms: anchorElapsedMs,
            browser_session_id: this.browserSessionId,
            client_monotonic_ms: Math.round(monotonicNow),
            client_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Kolkata',
            terminal_mode: this.terminalMode,
            confidence_score: punch.confidence_score || 0.85,
            sync_status: 'PENDING',
            sync_attempts: 0,
            created_at: Date.now()
        };

        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['offline_punches'], 'readwrite');
            const store = tx.objectStore('offline_punches');
            const req = store.put(punchRecord);

            req.onsuccess = () => {
                this.updatePendingCount();
                resolve(punchRecord);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async getPendingPunches() {
        if (!this.db) await this.initIndexedDB();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['offline_punches'], 'readonly');
            const store = tx.objectStore('offline_punches');
            const index = store.index('sync_status');
            const req = index.getAll('PENDING');
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async markPunchSynced(clientUuid) {
        if (!this.db) await this.initIndexedDB();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(['offline_punches'], 'readwrite');
            const store = tx.objectStore('offline_punches');
            const req = store.get(clientUuid);

            req.onsuccess = () => {
                const record = req.result;
                if (record) {
                    record.sync_status = 'SYNCED';
                    record.synced_at = Date.now();
                    const updateReq = store.put(record);
                    updateReq.onsuccess = () => {
                        this.updatePendingCount();
                        resolve();
                    };
                    updateReq.onerror = () => reject(updateReq.error);
                } else {
                    resolve();
                }
            };
            req.onerror = () => reject(req.error);
        });
    }

    async updatePendingCount() {
        try {
            const pending = await this.getPendingPunches();
            this.pendingSyncCount = pending.length;
            this.updateUiPill();
        } catch (e) {
            console.warn('Could not update pending count:', e);
        }
    }

    // 8. Network Auto-Sync with Dual-Timestamp Telemetry
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('[Edifies Kiosk] Network connection restored. Syncing offline queue...');
            this.updateUiPill();
            this.syncOfflineQueue();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('[Edifies Kiosk] Network connection lost. Operating in offline queue mode.');
            this.updateUiPill();
        });
    }

    async syncOfflineQueue() {
        if (!navigator.onLine) return;

        const pending = await this.getPendingPunches();
        if (pending.length === 0) return;

        console.log(`[Edifies Kiosk] Syncing ${pending.length} pending offline punches to server...`);

        // Sort by device_event_sequence ascending for strict chronological delivery
        pending.sort((a, b) => (a.device_event_sequence || 0) - (b.device_event_sequence || 0));

        for (const punch of pending) {
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };
                // Note: Header-based X-Kiosk-Device-Token has been completely removed.
                // Device authentication is strictly transported via HttpOnly; SameSite=Strict cookies.

                const res = await fetch('/api/kiosk/attendance/sync', {
                    method: 'POST',
                    credentials: 'same-origin', // Transmits HttpOnly edifies_kiosk_token cookie
                    headers: headers,
                    body: JSON.stringify({
                        client_uuid: punch.client_uuid,
                        employee_id: punch.employee_id,
                        device_id: punch.device_id,
                        device_epoch_id: punch.device_epoch_id,
                        device_event_sequence: punch.device_event_sequence,
                        timestamp: punch.timestamp,
                        client_synced_at: new Date().toISOString(),
                        confidence_score: punch.confidence_score,
                        sync_source: 'OFFLINE_SYNC',
                        client_timezone: punch.client_timezone,
                        client_monotonic_ms: punch.client_monotonic_ms,
                        server_anchor_id: punch.server_anchor_id || null,
                        browser_session_id: punch.browser_session_id || null,
                        device_wall_clock_at: punch.device_wall_clock_at || null,
                        anchor_elapsed_ms: punch.anchor_elapsed_ms || null,
                        browser_restarted: punch.browser_restarted || false,
                        terminal_mode: punch.terminal_mode
                    })
                });

                if (res.ok) {
                    await this.markPunchSynced(punch.client_uuid);
                    console.log(`[Edifies Kiosk] Punch ${punch.client_uuid} successfully synced.`);
                } else if (res.status === 401 || res.status === 403) {
                    console.error('[Edifies Kiosk] Device authorization rejected or revoked. Halting sync.');
                    this.deviceToken = null;
                    await this.putMetaItem('device_token', null);
                    this.updateUiPill();
                    break;
                } else if (res.status === 422 || res.status === 409) {
                    // Quarantined, sequence collision, or review required: mark synced to prevent endless loop
                    console.warn(`[Edifies Kiosk] Punch ${punch.client_uuid} flagged/quarantined by server (HTTP ${res.status}).`);
                    await this.markPunchSynced(punch.client_uuid);
                }
            } catch (err) {
                console.error(`[Edifies Kiosk] Sync network failure:`, err);
                break;
            }
        }
    }

    updateUiPill() {
        const badge = document.getElementById('kiosk-device-badge');
        if (!badge) return;

        if (this.isOnline) {
            if (this.pendingSyncCount > 0) {
                badge.className = 'kiosk-status-pill badge-warning';
                badge.innerHTML = `<div class="pulse-dot" style="background:#f59e0b;"></div><span>Online (${this.pendingSyncCount} syncing...)</span>`;
            } else {
                badge.className = 'kiosk-status-pill';
                badge.innerHTML = `<div class="pulse-dot"></div><span>Online (${this.formFactor} - ${this.terminalMode})</span>`;
            }
        } else {
            badge.className = 'kiosk-status-pill';
            badge.style.borderColor = '#f59e0b';
            badge.innerHTML = `<div class="pulse-dot" style="background:#f59e0b;"></div><span>Offline (${this.pendingSyncCount} queued)</span>`;
        }
    }

    generateUuidV4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
}

// Global kiosk singleton
window.edifiesKiosk = new EdifiesMultiDeviceKiosk();

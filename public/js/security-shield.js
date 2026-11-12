/**
 * Edifies School - Security & Anti-Inspection Shield
 * Enforces client-side anti-tampering, inspect prevention, and console lockdown.
 */
(function() {
    'use strict';

    // 1. Disable Right-Click Context Menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }, { capture: true });

    // 2. Intercept and Block Inspection Key Shortcuts
    document.addEventListener('keydown', function(e) {
        // F12 (Developer Tools)
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        const isCtrl = e.ctrlKey || e.metaKey;

        // Ctrl + Shift + I (DevTools), Ctrl + Shift + J (Console), Ctrl + Shift + C (Element Inspector)
        if (isCtrl && e.shiftKey) {
            const k = (e.key || '').toUpperCase();
            if (k === 'I' || k === 'J' || k === 'C') {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }

        // Ctrl + U (View Source)
        if (isCtrl && (e.key === 'u' || e.key === 'U')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Ctrl + S (Save Page)
        if (isCtrl && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, { capture: true });

    // 3. Disable Drag-and-Drop Tampering
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    }, { capture: true });

    // 4. Console Lockdown & Anti-Reversing Warning
    (function lockdownConsole() {
        const titleStyle = 'font-size: 24px; font-weight: 800; color: #ef4444; background: #0f172a; padding: 6px 12px; border-radius: 4px;';
        const bodyStyle  = 'font-size: 14px; font-weight: 600; color: #f87171; line-height: 1.5;';
        const infoStyle  = 'font-size: 12px; color: #94a3b8;';

        function showWarning() {
            try {
                console.clear();
                console.log('%c⚠️ SECURITY SHIELD ACTIVE', titleStyle);
                console.log('%cUnauthorized inspection, script injection, or client tampering is prohibited.', bodyStyle);
                console.log('%cAll client anomalies and authentication violations are recorded.', infoStyle);
            } catch (_) {}
        }

        showWarning();

        // Neutralize output leakage across all standard console methods
        const suppress = function() {};
        try {
            console.log = suppress;
            console.info = suppress;
            console.debug = suppress;
            console.dir = suppress;
            console.table = suppress;
        } catch (_) {}
    })();

    // 5. DevTools Detection & Tamper Alert Overlay
    (function devToolsDetector() {
        const threshold = 160;
        let isDevToolsOpen = false;

        function checkDevTools() {
            const widthDiff = window.outerWidth - window.innerWidth;
            const heightDiff = window.outerHeight - window.innerHeight;

            if (widthDiff > threshold || heightDiff > threshold) {
                if (!isDevToolsOpen) {
                    isDevToolsOpen = true;
                    showTamperOverlay();
                }
            } else {
                if (isDevToolsOpen) {
                    isDevToolsOpen = false;
                    hideTamperOverlay();
                }
            }
        }

        function showTamperOverlay() {
            if (document.getElementById('security-tamper-overlay')) return;

            const overlay = document.createElement('div');
            overlay.id = 'security-tamper-overlay';
            overlay.style.cssText = [
                'position: fixed',
                'top: 0',
                'left: 0',
                'width: 100vw',
                'height: 100vh',
                'background: rgba(15, 23, 42, 0.97)',
                'z-index: 9999999',
                'display: flex',
                'flex-direction: column',
                'align-items: center',
                'justify-content: center',
                'color: #ffffff',
                'font-family: system-ui, -apple-system, sans-serif',
                'text-align: center',
                'padding: 2rem',
                'backdrop-filter: blur(12px)',
                'user-select: none'
            ].join(';');

            overlay.innerHTML = `
                <div style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.15); border: 2px solid #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #f8fafc;">Developer Inspection Restricted</h2>
                <p style="font-size: 0.95rem; color: #94a3b8; max-width: 460px; line-height: 1.5; margin-bottom: 1.25rem;">
                    Client-side inspection and developer tool tampering have been blocked for operational security.
                    Please close developer tools and reload to proceed.
                </p>
                <div style="font-size: 0.8rem; color: #64748b; background: rgba(30, 41, 59, 0.8); padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.05);">
                    Security Protocol: ACT-SEC-80P &bull; Threat Neutralization Active
                </div>
            `;

            document.body.appendChild(overlay);
        }

        function hideTamperOverlay() {
            const overlay = document.getElementById('security-tamper-overlay');
            if (overlay) {
                overlay.remove();
            }
        }

        window.addEventListener('resize', checkDevTools);
        setInterval(checkDevTools, 1200);
    })();
})();


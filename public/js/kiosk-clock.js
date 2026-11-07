/**
 * Edifies School Attendance Kiosk - Clock & UI Telemetry Engine (Stage 3.2.2)
 * Pure external script to eliminate inline nonces from cached PWA shell.
 */
(function () {
    'use strict';

    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const dateStr = now.toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const clockEl = document.getElementById('kiosk-live-clock');
        const dateEl = document.getElementById('kiosk-live-date');

        if (clockEl) clockEl.textContent = timeStr;
        if (dateEl) dateEl.textContent = dateStr;
    }

    setInterval(updateClock, 1000);
    updateClock();

    window.addEventListener('load', () => {
        setTimeout(() => {
            if (window.edifiesKiosk) {
                const kiosk = window.edifiesKiosk;
                const tierEl = document.getElementById('telemetry-tier');
                const headerTier = document.getElementById('kiosk-header-form-factor');

                if (tierEl) {
                    tierEl.textContent = `Tier: ${kiosk.hardwareTier || 'Auto-Detected'}`;
                }
                if (headerTier && kiosk.formFactor) {
                    headerTier.textContent = `Attendance Kiosk (${kiosk.formFactor})`;
                }
            }
        }, 500);
    });
})();


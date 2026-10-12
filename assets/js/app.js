/**
 * =====================================================
 * ShortLink Pro — Client-Side Interactions
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Handles:
 *   1. Dark mode toggle (with localStorage persistence)
 *   2. Copy-to-clipboard (any [data-copy] element)
 *   3. Toast notifications (window.showToast)
 *   4. Delete confirmations (any [data-confirm] element)
 *   5. Auto-focus inputs
 *   6. Auto-dismiss alerts
 * =====================================================
 */

'use strict';

// =====================================================
// 1. DARK MODE TOGGLE
// =====================================================
(function initTheme() {
    const STORAGE_KEY = 'shortlink-theme';
    const root = document.documentElement;
    const toggle = document.getElementById('theme-toggle');

    // Priority: saved choice → OS preference → light
    const saved = localStorage.getItem(STORAGE_KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const initial = saved || (prefersDark ? 'dark' : 'light');
    root.setAttribute('data-theme', initial);

    if (!toggle) return;

    toggle.addEventListener('click', () => {
        const current = root.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        localStorage.setItem(STORAGE_KEY, next);
    });
})();

// =====================================================
// 2. TOAST NOTIFICATIONS
// =====================================================
(function initToasts() {
    // Create the container once, on demand
    function getContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Show a toast notification.
     * @param {string} message - Text to display.
     * @param {'info'|'success'|'error'} type - Visual style.
     * @param {number} duration - Milliseconds before auto-dismiss.
     */
    window.showToast = function (message, type = 'info', duration = 2500) {
        const container = getContainer();

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);

        // Auto-dismiss
        setTimeout(() => {
            toast.classList.add('hide');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        }, duration);
    };
})();

// =====================================================
// 3. COPY-TO-CLIPBOARD
// =====================================================
(function initCopy() {
    /**
     * Copy text to clipboard with a fallback for older browsers.
     */
    async function copyText(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            // Fallback: hidden textarea + execCommand
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            ta.remove();
            return ok;
        } catch (err) {
            console.error('Copy failed:', err);
            return false;
        }
    }

    /**
     * Attach copy behaviour to any element with [data-copy].
     * Clicking copies the attribute value (or reads from a target input).
     */
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-copy]');
        if (!trigger) return;

        event.preventDefault();

        // Source of the text:
        //   1. data-copy value, if not empty
        //   2. an input/textarea referenced by data-copy-target
        let text = trigger.dataset.copy;
        const targetSel = trigger.dataset.copyTarget;

        if ((!text || text === 'true') && targetSel) {
            const target = document.querySelector(targetSel);
            if (target) text = target.value || target.textContent;
        }

        if (!text) return;

        const ok = await copyText(text);

        if (ok) {
            window.showToast('Copied to clipboard', 'success');

            // Optional: swap icon briefly
            if (trigger.dataset.copyLabel) {
                const original = trigger.textContent;
                trigger.textContent = trigger.dataset.copyLabel;
                setTimeout(() => { trigger.textContent = original; }, 1200);
            }
        } else {
            window.showToast('Copy failed', 'error');
        }
    });
})();

// =====================================================
// 4. DELETE CONFIRMATIONS
// =====================================================
(function initConfirm() {
    /**
     * Any element with [data-confirm] asks for confirmation before
     * following its href (for links) or submitting its form (for buttons).
     */
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-confirm]');
        if (!el) return;

        const message = el.dataset.confirm || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
})();

// =====================================================
// 5. AUTO-FOCUS
// =====================================================
(function initAutoFocus() {
    const el = document.querySelector('[data-autofocus]');
    if (el) {
        // Delay slightly so animations don't jump
        setTimeout(() => el.focus(), 100);
    }
})();

// =====================================================
// 6. AUTO-DISMISS ALERTS
// =====================================================
(function initAutoDismiss() {
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach((alert) => {
        const delay = parseInt(alert.dataset.autoDismiss, 10) || 5000;
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(() => alert.remove(), 350);
        }, delay);
    });
})();
// =====================================================
// 7. DASHBOARD: LIVE LINK FILTER
// =====================================================
(function initLinksFilter() {
    const input = document.getElementById('links-search');
    const table = document.getElementById('links-table');
    if (!input || !table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();

        rows.forEach((row) => {
            const haystack = row.dataset.search || '';
            const match = query === '' || haystack.includes(query);
            row.style.display = match ? '' : 'none';
        });
    });
})();
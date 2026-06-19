const byId = (id) => document.getElementById(id);
const API_BASE = '/api';
const escHtml = (str) => String(str).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const APP_NOTIFICATION_MODAL_ID = 'app-notification-modal';
const APP_BUSY_OVERLAY_ID = 'app-busy-overlay';
let appPendingWriteRequests = 0;

const formatAssignedTo = (assignedRoleName, assignedToName, assignedToEmail) => {
    if (assignedRoleName) {
        return `Role: ${assignedRoleName}`;
    }

    if (!assignedToName) {
        return 'Unassigned';
    }

    return assignedToEmail ? `${assignedToName} (${assignedToEmail})` : assignedToName;
};

const getFormString = (formData, fieldName) => {
    const value = formData.get(fieldName);
    return typeof value === 'string' ? value.trim() : '';
};

const decodeJwtPayload = (token) => {
    const parts = String(token || '').split('.');
    if (parts.length !== 3) {
        return null;
    }

    const base64Url = parts[1].replaceAll('-', '+').replaceAll('_', '/');
    const padding = base64Url.length % 4;
    const padded = padding === 0 ? base64Url : `${base64Url}${'='.repeat(4 - padding)}`;

    try {
        return JSON.parse(atob(padded));
    } catch {
        return null;
    }
};

const TokenManager = {
    getToken: () => {
        const token = localStorage.getItem('hr_token');
        if (!token) {
            return null;
        }

        const payload = decodeJwtPayload(token);
        if (!payload || typeof payload !== 'object') {
            // If client-side decode fails, defer validation to the API instead of forcing logout.
            return token;
        }

        const exp = Number(payload?.exp || 0);
        const now = Math.floor(Date.now() / 1000);
        const leewaySeconds = 60;
        if (Number.isFinite(exp) && exp > 0 && exp <= (now - leewaySeconds)) {
            localStorage.removeItem('hr_token');
            return null;
        }

        return token;
    },
    setToken: (token) => localStorage.setItem('hr_token', token),
    clearToken: () => localStorage.removeItem('hr_token'),
    hasToken: () => !!TokenManager.getToken()
};

const buildHrLoginRedirectUrl = () => {
    const current = `${globalThis.location.pathname || '/'}${globalThis.location.search || ''}${globalThis.location.hash || ''}`;
    return `/hr/login?return_to=${encodeURIComponent(current)}`;
};

const getSafeReturnToPath = () => {
    const value = new URLSearchParams(globalThis.location.search).get('return_to') || '';
    if (!value.startsWith('/') || value.startsWith('//')) {
        return '';
    }
    if (value.startsWith('/api/')) {
        return '';
    }
    return value;
};

const handleApiAuthFailure = (url, responseStatus) => {
    const isAuthFailure = responseStatus === 401 || responseStatus === 403 || responseStatus === 419;
    const isHrLoginRequest = /\/api\/hr\/login(?:$|\?)/.test(url);

    if (!isAuthFailure || isHrLoginRequest) {
        return;
    }

    TokenManager.clearToken();
    globalThis._navAuthUpdate?.(false);
    if ((globalThis.location.pathname || '').startsWith('/hr')) {
        globalThis.location.href = buildHrLoginRedirectUrl();
    }
};

const parseApiResponseBody = async (url, response) => {
    const rawBody = await response.text();
    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    const isJson = contentType.includes('application/json');
    let data = {};

    if (rawBody && isJson) {
        try {
            data = JSON.parse(rawBody);
        } catch (error) {
            const preview = rawBody.slice(0, 180).replace(/\s+/g, ' ').trim();
            const reason = error instanceof Error ? error.message : 'parse error';
            throw new Error(`Invalid JSON from ${url} (HTTP ${response.status}): ${preview} (${reason})`);
        }
    }

    return { rawBody, isJson, data };
};

const ensureApiSuccess = (url, response, payload) => {
    const { rawBody, isJson, data } = payload;

    if (!response.ok) {
        handleApiAuthFailure(url, response.status);

        if (isJson) {
            throw new Error(data.error || `Request failed (HTTP ${response.status})`);
        }

        const preview = rawBody.slice(0, 180).replace(/\s+/g, ' ').trim();
        throw new Error(`Request failed (HTTP ${response.status}) from ${url}: ${preview || 'No response body'}`);
    }

    if (!isJson && rawBody) {
        const preview = rawBody.slice(0, 180).replace(/\s+/g, ' ').trim();
        throw new Error(`Unexpected non-JSON response from ${url}: ${preview}`);
    }

    return data;
};

async function api(url, options = {}) {
    const token = TokenManager.getToken();
    const method = (options.method || 'GET').toString().toUpperCase();
    const isWriteRequest = method !== 'GET' && method !== 'HEAD';

    if (isWriteRequest) {
        setBusyOverlayVisible(true);
    }

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...options.headers,
                ...(token && {
                    'X-HR-Token': token,
                })
            }
        });
        const payload = await parseApiResponseBody(url, response);
        return ensureApiSuccess(url, response, payload);
    } finally {
        if (isWriteRequest) {
            setBusyOverlayVisible(false);
        }
    }
}

function createBusyOverlay() {
    let overlay = byId(APP_BUSY_OVERLAY_ID);
    if (overlay) {
        return overlay;
    }

    overlay = document.createElement('div');
    overlay.id = APP_BUSY_OVERLAY_ID;
    overlay.className = 'app-busy-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="app-busy-overlay__content" role="status" aria-live="polite">
            <div class="app-busy-overlay__spinner" aria-hidden="true"></div>
            <p class="app-busy-overlay__text mb-0">Processing request. Please wait...</p>
        </div>
    `;

    document.body.appendChild(overlay);
    return overlay;
}

function setBusyOverlayVisible(visible) {
    if (visible) {
        appPendingWriteRequests += 1;
    } else {
        appPendingWriteRequests = Math.max(0, appPendingWriteRequests - 1);
    }

    const shouldShow = appPendingWriteRequests > 0;
    const overlay = createBusyOverlay();
    overlay.classList.toggle('is-open', shouldShow);
    overlay.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
    document.body.classList.toggle('app-busy', shouldShow);
}

function createNotificationModal() {
    let modal = byId(APP_NOTIFICATION_MODAL_ID);
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = APP_NOTIFICATION_MODAL_ID;
    modal.className = 'app-notification-modal';
    modal.innerHTML = `
        <div class="app-notification-modal__backdrop" data-role="backdrop">
            <section class="app-notification-modal__card" role="dialog" aria-modal="true" aria-live="assertive" aria-labelledby="app-notification-title">
                <header class="app-notification-modal__header">
                    <div class="app-notification-modal__brand">Voice Without Fear</div>
                    <button type="button" class="app-notification-modal__close" aria-label="Close" data-role="close">&times;</button>
                </header>
                <div class="app-notification-modal__body">
                    <div class="app-notification-modal__icon" data-role="icon"></div>
                    <h3 id="app-notification-title" class="app-notification-modal__title"></h3>
                    <p class="app-notification-modal__message" data-role="message"></p>
                    <p class="app-notification-modal__details" data-role="details"></p>
                </div>
                <footer class="app-notification-modal__footer">
                    <button type="button" class="btn btn-primary app-notification-modal__action" data-role="action">Close</button>
                </footer>
            </section>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

function showNotification(message, type = 'success', options = {}) {
    const modal = createNotificationModal();
    const titleEl = modal.querySelector('.app-notification-modal__title');
    const messageEl = modal.querySelector('[data-role="message"]');
    const detailsEl = modal.querySelector('[data-role="details"]');
    const iconEl = modal.querySelector('[data-role="icon"]');
    const closeBtn = modal.querySelector('[data-role="close"]');
    const actionBtn = modal.querySelector('[data-role="action"]');
    const backdrop = modal.querySelector('[data-role="backdrop"]');

    const defaultTitles = {
        success: 'Success',
        danger: 'Something Went Wrong',
        warning: 'Please Check',
        info: 'Information'
    };

    const iconByType = {
        success: 'fa-check-circle',
        danger: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    const variant = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info';
    const title = (typeof options.title === 'string' && options.title.trim()) ? options.title.trim() : defaultTitles[variant];
    const details = typeof options.details === 'string' ? options.details.trim() : '';
    const confirmText = typeof options.confirmText === 'string' && options.confirmText.trim() ? options.confirmText.trim() : 'Close';
    const blocking = Boolean(options.blocking);

    modal.dataset.variant = variant;
    modal.classList.add('is-open');
    document.body.classList.add('app-notification-open');

    titleEl.textContent = title;
    messageEl.textContent = String(message || '');
    detailsEl.textContent = details;
    detailsEl.classList.toggle('is-hidden', details === '');
    actionBtn.textContent = confirmText;
    iconEl.innerHTML = `<i class="fas ${iconByType[variant]}" aria-hidden="true"></i>`;

    const closeModal = () => {
        modal.classList.remove('is-open');
        document.body.classList.remove('app-notification-open');
        if (modal._onKeyDown) {
            document.removeEventListener('keydown', modal._onKeyDown);
            modal._onKeyDown = null;
        }
    };

    closeBtn.classList.toggle('is-hidden', blocking);

    closeBtn.onclick = () => closeModal();
    actionBtn.onclick = () => closeModal();
    backdrop.onclick = (event) => {
        if (!blocking && event.target === backdrop) {
            closeModal();
        }
    };

    if (modal._onKeyDown) {
        document.removeEventListener('keydown', modal._onKeyDown);
    }
    modal._onKeyDown = (event) => {
        if (event.key === 'Escape' && !blocking && modal.classList.contains('is-open')) {
            closeModal();
        }
    };
    document.addEventListener('keydown', modal._onKeyDown);

    actionBtn.focus();
}



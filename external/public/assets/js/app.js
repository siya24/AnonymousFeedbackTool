const byId = (id) => document.getElementById(id);
const API_BASE = '/api';
const escHtml = (str) => String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const APP_NOTIFICATION_MODAL_ID = 'app-notification-modal';
const APP_BUSY_OVERLAY_ID = 'app-busy-overlay';
let appPendingWriteRequests = 0;

function formatSqlDateTime(value) {
    const raw = (value || '').toString().trim();
    if (!raw) {
        return '';
    }

    const normalized = raw
        .replace(' ', 'T')
        .replace(/\.(\d{3})\d+$/, '.$1');
    const parsed = new Date(normalized);

    if (Number.isNaN(parsed.getTime())) {
        return raw;
    }

    return parsed.toLocaleString();
}

async function api(url, options = {}) {
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
            }
        });

        const raw = await response.text();
        const trimmed = raw.trim();
        let data = {};

        if (trimmed !== '') {
            try {
                data = JSON.parse(trimmed);
            } catch (_e) {
                const jsonStart = trimmed.indexOf('{');
                const jsonEnd = trimmed.lastIndexOf('}');
                if (jsonStart !== -1 && jsonEnd > jsonStart) {
                    try {
                        data = JSON.parse(trimmed.slice(jsonStart, jsonEnd + 1));
                    } catch (_ignored) {
                        data = response.ok
                            ? { message: 'Request completed successfully.' }
                            : { error: trimmed || 'Unexpected response from server' };
                    }
                } else {
                    data = response.ok
                        ? { message: 'Request completed successfully.' }
                        : { error: trimmed || 'Unexpected response from server' };
                }
            }
        }

        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
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

function initPublicForms() {
    const out = byId('global-output');
    const description = byId('description');
    const descriptionCounter = byId('description-counter');

    const categoryNew = byId('category-new');
    const categoryOtherWrapper = byId('category-other-wrapper');
    const categoryOtherText = byId('category-other-text');
    const followupText = byId('update-text');
    const followupCounter = byId('followup-counter');

    api(`${API_BASE}/categories`).then(data => {
        const opts = (data.data || []).map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
        if (categoryNew) categoryNew.innerHTML = '<option value="">-- Select category --</option>' + opts;
    }).catch(() => {
        if (categoryNew) categoryNew.innerHTML = '<option value="">-- Select category --</option>';
    });

    if (categoryNew && categoryOtherWrapper && categoryOtherText) {
        categoryNew.addEventListener('change', () => {
            const isOther = categoryNew.value === 'Other';
            categoryOtherWrapper.classList.toggle('d-none', !isOther);
            categoryOtherText.required = isOther;
            categoryOtherText.setCustomValidity('');
        });
        categoryOtherText.addEventListener('input', () => {
            categoryOtherText.setCustomValidity('');
        });
    }

    if (description && descriptionCounter) {
        const updateDescriptionCounter = () => {
            const currentLength = description.value.length;
            const limit = 5000;
            descriptionCounter.textContent = `${currentLength} / ${limit} characters`;
            descriptionCounter.classList.toggle('text-danger', currentLength > limit);
            descriptionCounter.classList.toggle('fw-semibold', currentLength > limit);
        };

        description.addEventListener('input', updateDescriptionCounter);
        updateDescriptionCounter();
    }

    if (followupText && followupCounter) {
        const updateFollowupCounter = () => {
            const currentLength = followupText.value.length;
            const limit = 5000;
            followupCounter.textContent = `${currentLength} / ${limit} characters`;
            followupCounter.classList.toggle('text-danger', currentLength > limit);
            followupCounter.classList.toggle('fw-semibold', currentLength > limit);
        };

        followupText.addEventListener('input', updateFollowupCounter);
        updateFollowupCounter();
    }

    const newForm = byId('new-feedback-form');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const descriptionText = (description?.value || '').toString();
                if (descriptionText.length > 5000) {
                    showNotification('Description cannot exceed 5000 characters. Please shorten it before submitting.', 'warning');
                    description?.focus();
                    return;
                }

                const formData = new FormData(newForm);
                if (categoryNew && categoryNew.value === 'Other' && categoryOtherText) {
                    const customCategory = categoryOtherText.value.trim();
                    if (!customCategory) {
                        categoryOtherText.setCustomValidity('Please specify the category.');
                        categoryOtherText.reportValidity();
                        return;
                    }
                    formData.set('category_other', customCategory);
                }
                const data = await api(`${API_BASE}/feedback`, {
                    method: 'POST',
                    body: formData,
                });

                const reference = (data.reference_no || '').toString().trim();
                showNotification(
                    'Feedback submitted successfully. Save your case reference before continuing.',
                    'success',
                    {
                        title: 'Submission Received',
                        details: `Reference Number: ${reference || 'Unavailable'}`,
                        confirmText: 'I have saved this reference',
                        blocking: true,
                    }
                );

                out.classList.add('d-none');
                newForm.reset();
                if (categoryOtherWrapper) categoryOtherWrapper.classList.add('d-none');
                if (categoryOtherText) {
                    categoryOtherText.value = '';
                    categoryOtherText.required = false;
                }
            } catch (err) {
                showNotification(err.message, 'danger');
                out.classList.add('d-none');
            }
        });
    }

    const followupForm = byId('followup-form');
    if (followupForm) {
        followupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await api(`${API_BASE}/feedback/update`, {
                    method: 'POST',
                    body: new FormData(followupForm),
                });
                showNotification('Follow-up submitted successfully!', 'success');
                out.classList.add('d-none');
                followupForm.reset();
                if (followupText && followupCounter) {
                    followupCounter.textContent = '0 / 5000 characters';
                    followupCounter.classList.remove('text-danger', 'fw-semibold');
                }
            } catch (err) {
                showNotification(err.message, 'danger');
                out.classList.add('d-none');
            }
        });
    }

    const lookupForm = byId('lookup-form');
    const lookupOut = byId('lookup-output');
    if (lookupForm) {
        lookupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const reference = lookupForm.reference_no.value.trim();
            if (!reference) return;

            const lookupSubmitButton = lookupForm.querySelector('button[type="submit"]');
            const originalLookupButtonHtml = lookupSubmitButton ? lookupSubmitButton.innerHTML : '';
            if (lookupSubmitButton) {
                lookupSubmitButton.disabled = true;
                lookupSubmitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Retrieving...';
            }

            try {
                const data = await api(`${API_BASE}/feedback/${encodeURIComponent(reference)}`);
                lookupOut.classList.remove('d-none');

                const statusBadgeClass = data.status === 'Resolved'
                    ? 'bg-success'
                    : data.status === 'Investigation pending'
                        ? 'bg-warning text-dark'
                        : 'bg-secondary';

                const updates = (data.updates || []).map(u =>
                    `<li class="list-group-item"><small class="text-muted">${escHtml(formatSqlDateTime(u.created_at || ''))}</small><br>${escHtml(u.update_text || '')}</li>`
                ).join('');

                const reporterFeedbackUpdatesSource = Array.isArray(data.reporter_feedback_updates)
                    ? data.reporter_feedback_updates
                    : [];

                const reporterFeedbackUpdates = reporterFeedbackUpdatesSource.map(item => {
                    const timestamp = formatSqlDateTime(item.created_at || '');
                    return `<li class="list-group-item">
                        <div>${escHtml(item.summary || '')}</div>
                        ${timestamp ? `<small class="text-muted">${escHtml(timestamp)}</small>` : ''}
                    </li>`;
                }).join('');

                const reporterFeedback = (data.reporter_feedback || '').toString().trim();

                let reporterFeedbackContent = '<span class="text-muted">No feedback from HR yet.</span>';
                if (reporterFeedbackUpdates) {
                    reporterFeedbackContent = `<ul class="list-group list-group-flush">${reporterFeedbackUpdates}</ul>`;
                } else if (reporterFeedback) {
                    reporterFeedbackContent = escHtml(reporterFeedback);
                }

                const attachments = (data.attachments || []).map(a =>
                    `<li class="list-group-item"><a href="/api/attachments/${encodeURIComponent(a.id)}?reference_no=${encodeURIComponent(data.reference_no || '')}" download="${escHtml(a.original_name)}"><i class="fas fa-paperclip me-1"></i>${escHtml(a.original_name)}</a></li>`
                ).join('');

                lookupOut.innerHTML = `
                  <div class="card border-0 shadow-sm">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-3">
                        <h6 class="card-title mb-0"><i class="fas fa-folder-open me-2"></i>${escHtml(data.reference_no || '')}</h6>
                        <span class="badge ${statusBadgeClass}">${escHtml(data.status || '')}</span>
                      </div>
                      <dl class="row mb-0">
                        <dt class="col-sm-4">Category</dt><dd class="col-sm-8">${escHtml(data.category || '')}</dd>
                                                <dt class="col-sm-4">Submitted</dt><dd class="col-sm-8">${escHtml(formatSqlDateTime(data.created_at || ''))}</dd>
                        <dt class="col-sm-4">Description</dt><dd class="col-sm-8">${escHtml(data.description || '')}</dd>
                        <dt class="col-sm-4">Feedback to Reporter</dt><dd class="col-sm-8">${reporterFeedbackContent}</dd>
                      </dl>
                      ${attachments ? `<hr><p class="fw-semibold mb-1">Attachments</p><ul class="list-group list-group-flush">${attachments}</ul>` : ''}
                      ${updates ? `<hr><p class="fw-semibold mb-1">Updates</p><ul class="list-group list-group-flush">${updates}</ul>` : ''}
                    </div>
                  </div>`;
            } catch (err) {
                lookupOut.classList.remove('d-none');
                lookupOut.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${escHtml(err.message || 'Failed to retrieve case details.')}</div>`;
            } finally {
                if (lookupSubmitButton) {
                    lookupSubmitButton.disabled = false;
                    lookupSubmitButton.innerHTML = originalLookupButtonHtml;
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initPublicForms();
});
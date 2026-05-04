const byId = (id) => document.getElementById(id);
const API_BASE = '/api';
const escHtml = (str) => String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...options.headers,
        }
    });

    let data = {};
    try {
        data = await response.json();
    } catch (_e) {
        data = { error: 'Unexpected response from server' };
    }

    if (!response.ok) {
        throw new Error(data.error || 'Request failed');
    }

    return data;
}

function showNotification(message, type = 'success') {
    const alertClass = `alert alert-${type}`;
    const alertHTML = `<div class="${alertClass} alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    const container = document.createElement('div');
    container.innerHTML = alertHTML;
    document.body.insertBefore(container.firstElementChild, document.body.firstChild);
    setTimeout(() => {
        document.querySelector('.alert')?.remove();
    }, 5000);
}

function initPublicForms() {
    const out = byId('global-output');
    const newFeedbackConfirmation = byId('new-feedback-confirmation');
    const newFeedbackReference = byId('new-feedback-reference');

    const categoryNew = byId('category-new');
    const categoryOtherWrapper = byId('category-other-wrapper');
    const categoryOtherText = byId('category-other-text');

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

    const newForm = byId('new-feedback-form');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
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
                if (newFeedbackConfirmation && newFeedbackReference) {
                    newFeedbackReference.textContent = reference || '';
                    newFeedbackConfirmation.classList.remove('d-none');
                }

                out.classList.add('d-none');
                newForm.reset();
                if (categoryOtherWrapper) categoryOtherWrapper.classList.add('d-none');
                if (categoryOtherText) {
                    categoryOtherText.value = '';
                    categoryOtherText.required = false;
                }
            } catch (err) {
                showNotification(err.message, 'danger');
                out.classList.remove('d-none');
                out.textContent = err.message;
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
            } catch (err) {
                showNotification(err.message, 'danger');
                out.classList.remove('d-none');
                out.textContent = err.message;
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

            try {
                const data = await api(`${API_BASE}/feedback/${encodeURIComponent(reference)}`);
                lookupOut.classList.remove('d-none');

                const statusBadgeClass = data.status === 'Resolved'
                    ? 'bg-success'
                    : data.status === 'Investigation pending'
                        ? 'bg-warning text-dark'
                        : 'bg-secondary';

                const updates = (data.updates || []).map(u =>
                    `<li class="list-group-item"><small class="text-muted">${escHtml(u.created_at || '')}</small><br>${escHtml(u.update_text || '')}</li>`
                ).join('');

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
                        <dt class="col-sm-4">Submitted</dt><dd class="col-sm-8">${escHtml(data.created_at || '')}</dd>
                        <dt class="col-sm-4">Description</dt><dd class="col-sm-8">${escHtml(data.description || '')}</dd>
                        ${data.anonymized_summary ? `<dt class="col-sm-4">Anonymized Summary</dt><dd class="col-sm-8">${escHtml(data.anonymized_summary)}</dd>` : ''}
                      </dl>
                      ${attachments ? `<hr><p class="fw-semibold mb-1">Attachments</p><ul class="list-group list-group-flush">${attachments}</ul>` : ''}
                      ${updates ? `<hr><p class="fw-semibold mb-1">Updates</p><ul class="list-group list-group-flush">${updates}</ul>` : ''}
                    </div>
                  </div>`;
                showNotification('Case retrieved successfully!', 'success');
            } catch (err) {
                lookupOut.classList.remove('d-none');
                lookupOut.innerHTML = `<div class="alert alert-danger mb-0">${escHtml(err.message)}</div>`;
                showNotification(err.message, 'danger');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initPublicForms();
});
const byId = (id) => document.getElementById(id);

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
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

function renderTable(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>';
    }
    const head = '<tr><th><i class="fas fa-calendar me-1"></i>Date</th><th><i class="fas fa-id-card me-1"></i>Reference</th><th><i class="fas fa-tag me-1"></i>Category</th><th><i class="fas fa-info-circle me-1"></i>Status</th><th><i class="fas fa-file-text me-1"></i>Summary</th><th><i class="fas fa-check me-1"></i>Outcome</th></tr>';
    const body = rows.map((r) => {
        const statusBadge = `<span class="badge" style="background-color: ${r.status === 'Investigation completed' ? '#9d2722' : '#008AC4'}">${r.status || ''}</span>`;
        return `<tr>
            <td>${r.created_at ? new Date(r.created_at).toLocaleDateString() : ''}</td>
            <td><strong>${r.reference_no || ''}</strong></td>
            <td>${r.category || ''}</td>
            <td>${statusBadge}</td>
            <td>${(r.anonymized_summary || '').substring(0, 50)}...</td>
            <td>${(r.outcome_comments || '').substring(0, 50)}...</td>
        </tr>`;
    }).join('');
    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function initPublicForms() {
    const out = byId('global-output');

    const newForm = byId('new-feedback-form');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const data = await api('/api/feedback', {
                    method: 'POST',
                    body: new FormData(newForm),
                });
                showNotification('Feedback submitted successfully!', 'success');
                out.classList.add('d-none');
                newForm.reset();
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
                const data = await api('/api/feedback/update', {
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
            if (!reference) {
                return;
            }
            try {
                const data = await api('/api/feedback/' + encodeURIComponent(reference));
                lookupOut.classList.remove('d-none');
                lookupOut.textContent = JSON.stringify(data, null, 2);
                showNotification('Case retrieved successfully!', 'success');
            } catch (err) {
                lookupOut.classList.remove('d-none');
                lookupOut.textContent = err.message;
                showNotification(err.message, 'danger');
            }
        });
    }

    const reportFilter = byId('public-report-filter');
    const reportTable = byId('public-report-table');
    if (reportFilter) {
        const load = async () => {
            const params = new URLSearchParams(new FormData(reportFilter));
            const data = await api('/api/reports?' + params.toString());
            reportTable.innerHTML = renderTable(data.data || []);
        };

        reportFilter.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await load();
            } catch (err) {
                reportTable.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
            }
        });

        load().catch(() => {
            reportTable.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>Could not load reports.</div>';
        });
    }
}

function initHrPage() {
    const hrOutput = byId('hr-output');
    const loginForm = byId('hr-login-form');
    const logoutBtn = byId('hr-logout');
    const filterForm = byId('hr-filter-form');
    const casesTable = byId('hr-cases-table');
    const updateForm = byId('hr-update-form');

    if (!loginForm) {
        return;
    }

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = { password: loginForm.password.value };
            const data = await api('/api/hr/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            showNotification('Logged in successfully!', 'success');
            logoutBtn.style.display = 'block';
            loginForm.password.value = '';
            hrOutput.classList.add('d-none');
        } catch (err) {
            showNotification('Login failed: ' + err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });

    logoutBtn?.addEventListener('click', async () => {
        try {
            const data = await api('/api/hr/logout', { method: 'POST' });
            showNotification('Logged out successfully!', 'success');
            logoutBtn.style.display = 'none';
            casesTable.innerHTML = '';
            updateForm.reset();
            hrOutput.classList.add('d-none');
        } catch (err) {
            showNotification(err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });

    const loadCases = async () => {
        const params = new URLSearchParams(new FormData(filterForm));
        const data = await api('/api/hr/cases?' + params.toString());
        casesTable.innerHTML = renderTable(data.data || []);
    };

    filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await loadCases();
        } catch (err) {
            casesTable.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
        }
    });

    updateForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(updateForm);
        const ref = (formData.get('reference_no') || '').toString().trim();
        if (!ref) {
            showNotification('Reference is required.', 'warning');
            return;
        }

        const payload = {
            priority: formData.get('priority'),
            stage: formData.get('stage'),
            status: formData.get('status'),
            anonymized_summary: formData.get('anonymized_summary'),
            action_taken: formData.get('action_taken'),
            outcome_comments: formData.get('outcome_comments'),
            internal_notes: formData.get('internal_notes'),
            acknowledge: formData.get('acknowledge') ? 1 : 0,
        };

        try {
            const data = await api('/api/hr/cases/' + encodeURIComponent(ref), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            showNotification('Case updated successfully!', 'success');
            hrOutput.classList.add('d-none');
            await loadCases();
        } catch (err) {
            showNotification(err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initPublicForms();
    initHrPage();
});

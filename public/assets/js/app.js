const byId = (id) => document.getElementById(id);
const API_BASE = '/api';

// JWT Token management
const TokenManager = {
    getToken: () => localStorage.getItem('hr_token'),
    setToken: (token) => localStorage.setItem('hr_token', token),
    clearToken: () => localStorage.removeItem('hr_token'),
    hasToken: () => !!localStorage.getItem('hr_token')
};

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...options.headers,
            ...(TokenManager.hasToken() && {
                'Authorization': `Bearer ${TokenManager.getToken()}`
            })
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

function renderHrCasesTable(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No feedback cases found.</div>';
    }

    const head = '<tr><th>Date</th><th>Reference</th><th>Category</th><th>Status</th><th>Priority</th><th>Action</th></tr>';
    const body = rows.map((r) => {
        const reference = r.reference_no || '';
        const href = `/hr/cases/${encodeURIComponent(reference)}`;
        return `<tr>
            <td>${r.created_at ? new Date(r.created_at).toLocaleString() : ''}</td>
            <td><strong>${reference}</strong></td>
            <td>${r.category || ''}</td>
            <td>${r.status || ''}</td>
            <td>${r.priority || ''}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="${href}"><i class="fas fa-arrow-right me-1"></i>Open</a></td>
        </tr>`;
    }).join('');

    return `<table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table>`;
}

function initPublicForms() {
    const out = byId('global-output');

    const newForm = byId('new-feedback-form');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const data = await api(`${API_BASE}/feedback`, {
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
                const data = await api(`${API_BASE}/feedback/update`, {
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
                const data = await api(`${API_BASE}/feedback/${encodeURIComponent(reference)}`);
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
            const data = await api(`${API_BASE}/reports?${params.toString()}`);
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
    const hrCasesSection = byId('hr-cases-section');
    const loginNote = byId('hr-login-note');
    const filterForm = byId('hr-filter-form');
    const casesTable = byId('hr-cases-table');

    if (!loginForm) {
        return;
    }

    const setLoggedInUi = (isLoggedIn) => {
        const loginInputs = loginForm.querySelectorAll('input');
        const loginSubmit = loginForm.querySelector('[type="submit"]');

        loginInputs.forEach((input) => {
            input.disabled = isLoggedIn;
            input.closest('.col-md-4')?.classList.toggle('d-none', isLoggedIn);
        });

        if (loginSubmit) {
            loginSubmit.disabled = isLoggedIn;
            loginSubmit.classList.toggle('d-none', isLoggedIn);
        }

        if (logoutBtn) {
            logoutBtn.style.display = isLoggedIn ? 'block' : 'none';
        }

        if (loginNote) {
            loginNote.style.display = isLoggedIn ? 'none' : 'block';
        }

        if (hrCasesSection) {
            hrCasesSection.style.display = isLoggedIn ? 'block' : 'none';
        }
    };

    // Check if already logged in
    if (TokenManager.hasToken()) {
        setLoggedInUi(true);
    }

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const payload = {
                email: loginForm.email?.value || loginForm.querySelector('[type="email"]')?.value,
                password: loginForm.password.value
            };
            const data = await api(`${API_BASE}/hr/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            
            TokenManager.setToken(data.token);
            showNotification(`Logged in as ${data.user.name}!`, 'success');
            setLoggedInUi(true);
            loginForm.password.value = '';
            hrOutput.classList.add('d-none');

            await loadCases();
        } catch (err) {
            showNotification('Login failed: ' + err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });

    logoutBtn?.addEventListener('click', async () => {
        try {
            await api(`${API_BASE}/hr/logout`, { method: 'POST' });
            TokenManager.clearToken();
            showNotification('Logged out successfully!', 'success');
            setLoggedInUi(false);
            casesTable.innerHTML = '';
            hrOutput.classList.add('d-none');
        } catch (err) {
            showNotification(err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });

    const loadCases = async () => {
        const params = new URLSearchParams(new FormData(filterForm));
        const data = await api(`${API_BASE}/hr/cases?${params.toString()}`);
        casesTable.innerHTML = renderHrCasesTable(data.data || []);
    };

    filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await loadCases();
        } catch (err) {
            casesTable.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
        }
    });

    if (TokenManager.hasToken()) {
        loadCases().catch((err) => {
            casesTable.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>${err.message}</div>`;
        });
    }
}

function initHrCasePage() {
    const casePage = byId('hr-case-page');
    if (!casePage) {
        return;
    }

    const hrOutput = byId('hr-output');
    const summary = byId('hr-case-summary');
    const updateForm = byId('hr-update-form');
    const logoutBtn = byId('hr-case-logout');
    const reference = (casePage.dataset.reference || '').trim();

    if (!TokenManager.hasToken()) {
        window.location.href = '/hr';
        return;
    }

    const populateFormFromCase = (report) => {
        if (!report || !updateForm) {
            return;
        }

        updateForm.priority.value = report.priority || 'Normal';
        updateForm.stage.value = report.stage || 'Logged';
        updateForm.status.value = report.status || 'Investigation pending';
        updateForm.anonymized_summary.value = report.anonymized_summary || '';
        updateForm.action_taken.value = report.action_taken || '';
        updateForm.outcome_comments.value = report.outcome_comments || '';
        updateForm.internal_notes.value = report.internal_notes || '';
        const checked = !!report.acknowledged_at;
        updateForm.acknowledge.checked = checked;
    };

    const renderCaseSummary = (report) => {
        const created = report.created_at ? new Date(report.created_at).toLocaleString() : '';
        const acknowledged = report.acknowledged_at ? new Date(report.acknowledged_at).toLocaleString() : 'Not acknowledged';
        summary.innerHTML = `<div class="row g-3">
            <div class="col-md-6"><strong>Reference:</strong> ${report.reference_no || ''}</div>
            <div class="col-md-6"><strong>Category:</strong> ${report.category || ''}</div>
            <div class="col-md-6"><strong>Status:</strong> ${report.status || ''}</div>
            <div class="col-md-6"><strong>Priority:</strong> ${report.priority || ''}</div>
            <div class="col-md-6"><strong>Created:</strong> ${created}</div>
            <div class="col-md-6"><strong>Acknowledged:</strong> ${acknowledged}</div>
            <div class="col-12"><strong>Description:</strong><div class="mt-1">${report.description || ''}</div></div>
        </div>`;
    };

    const loadCase = async () => {
        const data = await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}`);
        const detail = data.data?.report || {};
        renderCaseSummary(detail);
        populateFormFromCase(detail);
    };

    logoutBtn?.addEventListener('click', async () => {
        try {
            await api(`${API_BASE}/hr/logout`, { method: 'POST' });
        } catch (_err) {
            // Ignore API logout failure and clear local token anyway.
        }

        TokenManager.clearToken();
        window.location.href = '/hr';
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
            const data = await api(`${API_BASE}/hr/cases/${encodeURIComponent(ref)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            showNotification('Case updated successfully!', 'success');
            hrOutput.classList.add('d-none');
            await loadCase();
        } catch (err) {
            showNotification(err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });

    loadCase().catch((err) => {
        summary.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initPublicForms();
    initHrPage();
    initHrCasePage();
});


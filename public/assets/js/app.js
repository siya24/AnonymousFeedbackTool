const byId = (id) => document.getElementById(id);
const API_BASE = '/api';
const escHtml = (str) => String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

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

function renderDashboardStatusTotals(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info mb-0">No status data available.</div>';
    }

    const items = rows.map((row) => `<li class="list-group-item d-flex justify-content-between align-items-center">
        <span>${row.status || ''}</span>
        <span class="badge bg-primary rounded-pill">${row.total || 0}</span>
    </li>`).join('');

    return `<ul class="list-group">${items}</ul>`;
}

function renderDashboardQuarterlyTrends(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info mb-0">No quarterly trend data available.</div>';
    }

    const head = '<tr><th>Year</th><th>Quarter</th><th>Category</th><th>Total Cases</th></tr>';
    const body = rows.map((row) => `<tr>
        <td>${row.year_no || ''}</td>
        <td>Q${row.quarter_no || ''}</td>
        <td>${row.category || ''}</td>
        <td>${row.total_cases || 0}</td>
    </tr>`).join('');

    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function renderDashboardCategoryFrequency(rows) {
    if (!rows || !rows.length) {
        return '<div class="alert alert-info mb-0">No category data available.</div>';
    }

    const maxOpen = Math.max(...rows.map(r => Number(r.open_cases || 0)), 1);

    const head = '<tr><th>Category</th><th>Open Cases</th><th>Total Cases</th><th>Frequency</th><th>Suggested Priority</th></tr>';
    const body = rows.map((row) => {
        const open = Number(row.open_cases || 0);
        const total = Number(row.total_cases || 0);
        const pct = Math.round((open / maxOpen) * 100);
        let badge = '';
        if (open === 0) {
            badge = '<span class="badge bg-success">Low</span>';
        } else if (pct >= 75) {
            badge = '<span class="badge bg-danger">High</span>';
        } else if (pct >= 40) {
            badge = '<span class="badge bg-warning text-dark">Medium</span>';
        } else {
            badge = '<span class="badge bg-secondary">Low</span>';
        }
        return `<tr>
            <td>${escHtml(row.category || '')}</td>
            <td>${open}</td>
            <td>${total}</td>
            <td>
                <div class="progress" style="height:16px;min-width:80px">
                    <div class="progress-bar bg-danger" style="width:${pct}%" title="${pct}%">${pct}%</div>
                </div>
            </td>
            <td>${badge}</td>
        </tr>`;
    }).join('');

    return `<p class="text-muted small mb-2">Suggested priority is derived from the proportion of still-open cases per category relative to the highest-volume category.</p>
        <div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function initPublicForms() {
    const out = byId('global-output');
    const newFeedbackConfirmation = byId('new-feedback-confirmation');
    const newFeedbackReference = byId('new-feedback-reference');

    // Populate category selects from the API
    const categoryNew = byId('category-new');
    const categoryReportFilter = byId('report-filter-category');
    api(`${API_BASE}/categories`).then(data => {
        const opts = (data.data || []).map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
        if (categoryNew) categoryNew.innerHTML = '<option value="">-- Select category --</option>' + opts;
        if (categoryReportFilter) categoryReportFilter.innerHTML = '<option value="">Any category</option>' + opts;
    }).catch(() => {
        if (categoryNew) categoryNew.innerHTML = '<option value="">-- Select category --</option>';
    });

    const statusReportFilter = byId('report-filter-status');
    api(`${API_BASE}/statuses`).then(data => {
        const opts = (data.data || []).map(s => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
        if (statusReportFilter) statusReportFilter.innerHTML = '<option value="">Any status</option>' + opts;
    }).catch(() => {
        if (statusReportFilter) statusReportFilter.innerHTML = '<option value="">Any status</option>';
    });

    const newForm = byId('new-feedback-form');
    if (newForm) {
        newForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const data = await api(`${API_BASE}/feedback`, {
                    method: 'POST',
                    body: new FormData(newForm),
                });

                const reference = (data.reference_no || '').toString().trim();
                if (newFeedbackConfirmation && newFeedbackReference) {
                    newFeedbackReference.textContent = reference || '';
                    newFeedbackConfirmation.classList.remove('d-none');
                }

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
                const statusBadgeClass = data.status === 'Resolved' ? 'bg-success' : data.status === 'Investigation pending' ? 'bg-warning text-dark' : 'bg-secondary';
                const updates = (data.updates || []).map(u => `<li class="list-group-item"><small class="text-muted">${escHtml(u.created_at || '')}</small><br>${escHtml(u.update_text || '')}</li>`).join('');
                const attachments = (data.attachments || []).map(a => `<li class="list-group-item"><a href="/api/attachments/${encodeURIComponent(a.id)}" download="${escHtml(a.original_name)}"><i class="fas fa-paperclip me-1"></i>${escHtml(a.original_name)}</a></li>`).join('');
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

    const reportFilter = byId('public-report-filter');
    const reportTable = byId('public-report-table');
    const reportingTab = byId('tab-reporting');
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

        // Auto-refresh employee reporting so updates are visible without manual reload.
        setInterval(() => {
            const isVisible = reportingTab?.classList.contains('active') || reportingTab?.classList.contains('show');
            if (!isVisible) {
                return;
            }

            load().catch(() => {
                reportTable.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>Could not refresh reports.</div>';
            });
        }, 30000);
    }
}

function initHrPage() {
    const hrOutput = byId('hr-output');
    const loginForm = byId('hr-login-form');
    const hrCasesSection = byId('hr-cases-section');
    const loginNote = byId('hr-login-note');
    const filterForm = byId('hr-filter-form');
    const casesTable = byId('hr-cases-table');
    const paginationEl = byId('hr-cases-pagination');

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

        window._navAuthUpdate?.(isLoggedIn);

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

            await loadFilterOptions();
            await loadCases();
        } catch (err) {
            showNotification('Login failed: ' + err.message, 'danger');
            hrOutput.classList.remove('d-none');
            hrOutput.textContent = err.message;
        }
    });



    let currentPage = 1;
    const perPage = 10;

    const renderPagination = (meta) => {
        if (!paginationEl) {
            return;
        }

        const page = Number(meta?.page || 1);
        const totalPages = Number(meta?.total_pages || 1);
        const total = Number(meta?.total || 0);

        if (totalPages <= 1) {
            paginationEl.innerHTML = `<small class="text-muted">Showing ${total} record(s)</small>`;
            return;
        }

        paginationEl.innerHTML = `
            <small class="text-muted">Page ${page} of ${totalPages} (${total} records)</small>
            <div class="btn-group" role="group" aria-label="Pagination controls">
                <button type="button" class="btn btn-outline-primary btn-sm" id="hr-page-prev" ${page <= 1 ? 'disabled' : ''}>Prev</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="hr-page-next" ${page >= totalPages ? 'disabled' : ''}>Next</button>
            </div>
        `;

        byId('hr-page-prev')?.addEventListener('click', () => {
            if (page > 1) {
                currentPage = page - 1;
                loadCases().catch(() => {});
            }
        });

        byId('hr-page-next')?.addEventListener('click', () => {
            if (page < totalPages) {
                currentPage = page + 1;
                loadCases().catch(() => {});
            }
        });
    };

    const loadFilterOptions = async () => {
        const [categories, statuses] = await Promise.all([
            api(`${API_BASE}/categories`),
            api(`${API_BASE}/statuses`),
        ]);

        const filterCategory = byId('filter-category');
        if (filterCategory) {
            const opts = (categories.data || []).map((c) => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
            filterCategory.innerHTML = '<option value="">Any category</option>' + opts;
        }

        const filterStatus = byId('filter-status');
        if (filterStatus) {
            const opts = (statuses.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
            filterStatus.innerHTML = '<option value="">Any status</option>' + opts;
        }
    };

    const loadCases = async () => {
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        const data = await api(`${API_BASE}/hr/cases?${params.toString()}`);
        casesTable.innerHTML = renderHrCasesTable(data.data || []);
        renderPagination(data.pagination || {});
    };

    filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        currentPage = 1;
        try {
            await loadCases();
        } catch (err) {
            casesTable.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
        }
    });

    if (TokenManager.hasToken()) {
        Promise.all([loadFilterOptions(), loadCases()]).catch((err) => {
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
    const statusSelect = byId('status');
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

    const renderCaseSummary = (report, attachments) => {
        const created = report.created_at ? new Date(report.created_at).toLocaleString() : '';
        const acknowledged = report.acknowledged_at ? new Date(report.acknowledged_at).toLocaleString() : 'Not acknowledged';
        const attachmentLinks = (attachments || []).map(a =>
            `<a href="/api/attachments/${encodeURIComponent(a.id)}" download="${escHtml(a.original_name)}" class="me-2"><i class="fas fa-paperclip me-1"></i>${escHtml(a.original_name)}</a>`
        ).join('');
        summary.innerHTML = `<div class="row g-3">
            <div class="col-md-6"><strong>Reference:</strong> ${report.reference_no || ''}</div>
            <div class="col-md-6"><strong>Category:</strong> ${report.category || ''}</div>
            <div class="col-md-6"><strong>Status:</strong> ${report.status || ''}</div>
            <div class="col-md-6"><strong>Priority:</strong> ${report.priority || ''}</div>
            <div class="col-md-6"><strong>Created:</strong> ${created}</div>
            <div class="col-md-6"><strong>Acknowledged:</strong> ${acknowledged}</div>
            <div class="col-12"><strong>Description:</strong><div class="mt-1">${report.description || ''}</div></div>
            ${attachmentLinks ? `<div class="col-12"><strong>Attachments:</strong><div class="mt-1">${attachmentLinks}</div></div>` : ''}
        </div>`;
    };

    const loadCase = async () => {
        const data = await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}`);
        const detail = data.data?.report || {};
        const attachments = data.data?.attachments || [];
        renderCaseSummary(detail, attachments);
        populateFormFromCase(detail);
    };

    const loadStatuses = async () => {
        const data = await api(`${API_BASE}/statuses`);
        const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
        if (statusSelect) {
            statusSelect.innerHTML = opts;
        }
    };



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

    (async () => {
        try {
            await loadStatuses();
            await loadCase();
        } catch (err) {
            summary.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
        }
    })();
}

function initHrDashboardPage() {
    const dashboardPage = byId('hr-dashboard-page');
    if (!dashboardPage) {
        return;
    }

    const output = byId('hr-dashboard-output');
    const statusTotals = byId('hr-dashboard-status-totals');
    const quarterlyTrends = byId('hr-dashboard-quarterly-trends');
    const categoryFrequency = byId('hr-dashboard-category-frequency');
    const refreshBtn = byId('hr-dashboard-refresh');

    if (!TokenManager.hasToken()) {
        window.location.href = '/hr';
        return;
    }

    const loadDashboard = async () => {
        const result = await api(`${API_BASE}/hr/dashboard/trends`);
        const data = result.data || {};
        statusTotals.innerHTML = renderDashboardStatusTotals(data.status_totals || []);
        quarterlyTrends.innerHTML = renderDashboardQuarterlyTrends(data.quarterly_by_category || []);
        if (categoryFrequency) {
            categoryFrequency.innerHTML = renderDashboardCategoryFrequency(data.category_frequency || []);
        }
        output.classList.add('d-none');
    };

    refreshBtn?.addEventListener('click', async () => {
        try {
            await loadDashboard();
            showNotification('Dashboard refreshed.', 'success');
        } catch (err) {
            output.classList.remove('d-none');
            output.textContent = err.message;
        }
    });

    loadDashboard().catch((err) => {
        output.classList.remove('d-none');
        output.textContent = err.message;
    });
}

function initHrCategories() {
    const section = byId('hr-categories-section');
    const catTable = byId('cat-table');
    const addBtn = byId('cat-add-btn');
    const addForm = byId('cat-add-form');
    const saveBtn = byId('cat-save-btn');
    const cancelBtn = byId('cat-cancel-btn');
    const newName = byId('cat-new-name');
    const newOrder = byId('cat-new-order');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        window.location.href = '/hr';
        return;
    }

    let editingId = null;

    const renderCategoryTable = (categories) => {
        if (!categories.length) {
            catTable.innerHTML = '<p class="text-muted">No categories yet.</p>';
            return;
        }
        const rows = categories.map(c => `
          <tr>
            <td>${escHtml(c.name)}</td>
            <td>${c.sort_order}</td>
            <td><span class="badge ${c.is_active ? 'bg-success' : 'bg-secondary'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1 cat-edit-btn" data-id="${c.id}" data-name="${escHtml(c.name)}" data-order="${c.sort_order}" data-active="${c.is_active}">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger cat-delete-btn" data-id="${c.id}" data-name="${escHtml(c.name)}">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>`).join('');
        catTable.innerHTML = `<table class="table table-sm table-hover">
          <thead><tr><th>Name</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>${rows}</tbody></table>`;

        catTable.querySelectorAll('.cat-edit-btn').forEach(btn => btn.addEventListener('click', () => {
            editingId = parseInt(btn.dataset.id);
            newName.value = btn.dataset.name;
            newOrder.value = btn.dataset.order;
            addForm.dataset.active = btn.dataset.active;
            addForm.classList.remove('d-none');
            saveBtn.textContent = 'Update';
            newName.focus();
        }));

        catTable.querySelectorAll('.cat-delete-btn').forEach(btn => btn.addEventListener('click', async () => {
            if (!confirm(`Delete category "${btn.dataset.name}"? This cannot be undone.`)) return;
            try {
                await api(`${API_BASE}/hr/categories/${encodeURIComponent(btn.dataset.id)}`, { method: 'DELETE' });
                showNotification('Category deleted.', 'success');
                await loadCategories();
            } catch (err) {
                showNotification(err.message, 'danger');
            }
        }));
    };

    const loadCategories = async () => {
        const data = await api(`${API_BASE}/hr/categories`);
        renderCategoryTable(data.data || []);

        // Also refresh the filter select in the HR cases section
        const filterSelect = byId('filter-category');
        if (filterSelect) {
            const opts = (data.data || []).map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
            filterSelect.innerHTML = '<option value="">Any category</option>' + opts;
        }
    };

    window._reloadHrCategories = loadCategories;

    addBtn?.addEventListener('click', () => {
        editingId = null;
        newName.value = '';
        newOrder.value = '0';
        delete addForm.dataset.active;
        addForm.classList.remove('d-none');
        saveBtn.textContent = 'Save';
        newName.focus();
    });

    cancelBtn?.addEventListener('click', () => {
        addForm.classList.add('d-none');
        editingId = null;
    });

    saveBtn?.addEventListener('click', async () => {
        const name = newName.value.trim();
        const order = parseInt(newOrder.value) || 0;
        if (!name) { showNotification('Category name is required.', 'warning'); return; }

        try {
            if (editingId) {
                const isActive = addForm.dataset.active !== '0';
                await api(`${API_BASE}/hr/categories/${encodeURIComponent(editingId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, is_active: isActive, sort_order: order }),
                });
                showNotification('Category updated.', 'success');
            } else {
                await api(`${API_BASE}/hr/categories`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, sort_order: order }),
                });
                showNotification('Category created.', 'success');
            }
            addForm.classList.add('d-none');
            editingId = null;
            await loadCategories();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    loadCategories().catch(() => {});
}

function initHrStatuses() {
    const section = byId('hr-statuses-section');
    const statusTable = byId('status-table');
    const addBtn = byId('status-add-btn');
    const addForm = byId('status-add-form');
    const saveBtn = byId('status-save-btn');
    const cancelBtn = byId('status-cancel-btn');
    const newName = byId('status-new-name');
    const newOrder = byId('status-new-order');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        window.location.href = '/hr';
        return;
    }

    let editingId = null;

    const renderStatusTable = (statuses) => {
        if (!statuses.length) {
            statusTable.innerHTML = '<p class="text-muted">No statuses yet.</p>';
            return;
        }

        const rows = statuses.map((s) => `
          <tr>
            <td>${escHtml(s.name)}</td>
            <td>${s.sort_order}</td>
            <td><span class="badge ${s.is_active ? 'bg-success' : 'bg-secondary'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1 status-edit-btn" data-id="${s.id}" data-name="${escHtml(s.name)}" data-order="${s.sort_order}" data-active="${s.is_active}">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger status-delete-btn" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>`).join('');

        statusTable.innerHTML = `<table class="table table-sm table-hover">
          <thead><tr><th>Name</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>${rows}</tbody></table>`;

        statusTable.querySelectorAll('.status-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
            editingId = parseInt(btn.dataset.id, 10);
            newName.value = btn.dataset.name;
            newOrder.value = btn.dataset.order;
            addForm.dataset.active = btn.dataset.active;
            addForm.classList.remove('d-none');
            saveBtn.textContent = 'Update';
            newName.focus();
        }));

        statusTable.querySelectorAll('.status-delete-btn').forEach((btn) => btn.addEventListener('click', async () => {
            if (!confirm(`Delete status "${btn.dataset.name}"? This cannot be undone.`)) return;
            try {
                await api(`${API_BASE}/hr/statuses/${encodeURIComponent(btn.dataset.id)}`, { method: 'DELETE' });
                showNotification('Status deleted.', 'success');
                await loadStatuses();
            } catch (err) {
                showNotification(err.message, 'danger');
            }
        }));
    };

    const loadStatuses = async () => {
        const data = await api(`${API_BASE}/hr/statuses`);
        renderStatusTable(data.data || []);

        const filterSelect = byId('filter-status');
        if (filterSelect) {
            const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
            filterSelect.innerHTML = '<option value="">Any status</option>' + opts;
        }
    };

    window._reloadHrStatuses = loadStatuses;

    addBtn?.addEventListener('click', () => {
        editingId = null;
        newName.value = '';
        newOrder.value = '0';
        delete addForm.dataset.active;
        addForm.classList.remove('d-none');
        saveBtn.textContent = 'Save';
        newName.focus();
    });

    cancelBtn?.addEventListener('click', () => {
        addForm.classList.add('d-none');
        editingId = null;
    });

    saveBtn?.addEventListener('click', async () => {
        const name = newName.value.trim();
        const order = parseInt(newOrder.value, 10) || 0;
        if (!name) {
            showNotification('Status name is required.', 'warning');
            return;
        }

        try {
            if (editingId) {
                const isActive = addForm.dataset.active !== '0';
                await api(`${API_BASE}/hr/statuses/${encodeURIComponent(editingId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, is_active: isActive, sort_order: order }),
                });
                showNotification('Status updated.', 'success');
            } else {
                await api(`${API_BASE}/hr/statuses`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, sort_order: order }),
                });
                showNotification('Status created.', 'success');
            }

            addForm.classList.add('d-none');
            editingId = null;
            await loadStatuses();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    loadStatuses().catch(() => {});
}

function initNavAuth() {
    const loginItem = byId('nav-hr-login-item');
    const logoutItem = byId('nav-hr-logout-item');
    const logoutBtn = byId('nav-hr-logout');

    const update = (isLoggedIn) => {
        if (loginItem) loginItem.style.display = isLoggedIn ? 'none' : '';
        if (logoutItem) logoutItem.style.display = isLoggedIn ? '' : 'none';
    };

    update(TokenManager.hasToken());

    logoutBtn?.addEventListener('click', async () => {
        try {
            await api(`${API_BASE}/hr/logout`, { method: 'POST' });
        } catch (_err) {}
        TokenManager.clearToken();
        update(false);
        showNotification('Logged out successfully!', 'success');
        if (window.location.pathname !== '/') {
            window.location.href = '/hr';
        }
    });

    window._navAuthUpdate = update;
}

document.addEventListener('DOMContentLoaded', () => {
    initNavAuth();
    initPublicForms();
    initHrPage();
    initHrCasePage();
    initHrDashboardPage();
    initHrCategories();
    initHrStatuses();
});


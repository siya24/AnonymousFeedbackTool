const byId = (id) => document.getElementById(id);
const API_BASE = '/api';
const escHtml = (str) => String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

const TokenManager = {
    getToken: () => localStorage.getItem('hr_token'),
    setToken: (token) => localStorage.setItem('hr_token', token),
    clearToken: () => localStorage.removeItem('hr_token'),
    hasToken: () => !!localStorage.getItem('hr_token')
};

const buildHrLoginRedirectUrl = () => {
    const current = `${window.location.pathname || '/'}${window.location.search || ''}${window.location.hash || ''}`;
    return `/hr?return_to=${encodeURIComponent(current)}`;
};

const getSafeReturnToPath = () => {
    const value = new URLSearchParams(window.location.search).get('return_to') || '';
    if (!value.startsWith('/') || value.startsWith('//')) {
        return '';
    }
    if (value.startsWith('/api/')) {
        return '';
    }
    return value;
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
            <td>${escHtml(r.anonymized_summary || '')}</td>
            <td>${escHtml(r.outcome_comments || '')}</td>
        </tr>`;
    }).join('');
    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function renderHrCasesTable(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No feedback cases found.</div>';
    }

    const head = '<tr><th>Date</th><th>Reference</th><th>Category</th><th>Status</th><th>Priority</th><th>Assigned To</th><th>Action</th></tr>';
    const body = rows.map((r) => {
        const reference = r.reference_no || '';
        const href = `/hr/cases/${encodeURIComponent(reference)}`;
        const assignedTo = r.assigned_to_name ? `${r.assigned_to_name}${r.assigned_to_email ? ` (${r.assigned_to_email})` : ''}` : 'Unassigned';
        return `<tr>
            <td>${r.created_at ? new Date(r.created_at).toLocaleString() : ''}</td>
            <td><strong>${reference}</strong></td>
            <td>${r.category || ''}</td>
            <td>${r.status || ''}</td>
            <td>${r.priority || ''}</td>
            <td>${escHtml(assignedTo)}</td>
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

function initHrPage() {
    const hrOutput = byId('hr-output');
    const loginForm = byId('hr-login-form');
    const hrCasesSection = byId('hr-cases-section');
    const loginNote = byId('hr-login-note');
    const hrAuthLinks = byId('hr-auth-links');
    const filterForm = byId('hr-filter-form');
    const casesTable = byId('hr-cases-table');
    const paginationEl = byId('hr-cases-pagination');

    if (!loginForm) {
        return;
    }

    const returnTo = getSafeReturnToPath();

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

        if (hrAuthLinks) {
            hrAuthLinks.classList.toggle('d-none', !isLoggedIn);
        }
    };

    if (TokenManager.hasToken()) {
        if (returnTo && returnTo !== '/hr') {
            window.location.href = returnTo;
            return;
        }
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

            if (returnTo && returnTo !== '/hr') {
                window.location.href = returnTo;
                return;
            }

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

    // Handle Clear Filters button
    const clearBtn = filterForm?.querySelector('[type="reset"]');
    clearBtn?.addEventListener('click', async () => {
        filterForm.reset();
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
    const stageSelect = byId('stage');
    const assigneeSelect = byId('assigned-to-user-id');
    const reference = (casePage.dataset.reference || '').trim();

    if (!TokenManager.hasToken()) {
        window.location.href = buildHrLoginRedirectUrl();
        return;
    }

    const populateFormFromCase = (report) => {
        if (!report || !updateForm) {
            return;
        }

        updateForm.priority.value = report.priority || 'Normal';
        if (stageSelect) {
            stageSelect.value = report.stage || 'Logged';
        }
        updateForm.status.value = report.status || 'Investigation pending';
        if (assigneeSelect) {
            assigneeSelect.value = report.assigned_to_user_id || '';
        }
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
        const assignedAt = report.assigned_at ? new Date(report.assigned_at).toLocaleString() : 'Not assigned';
        const assignedTo = report.assigned_to_name
            ? `${report.assigned_to_name}${report.assigned_to_email ? ` (${report.assigned_to_email})` : ''}`
            : 'Unassigned';
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
            <div class="col-md-6"><strong>Assigned To:</strong> ${escHtml(assignedTo)}</div>
            <div class="col-md-6"><strong>Assigned At:</strong> ${assignedAt}</div>
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

    const loadStages = async () => {
        const data = await api(`${API_BASE}/stages`);
        const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
        if (stageSelect) {
            stageSelect.innerHTML = opts;
        }
    };

    const loadAssignablePersonnel = async () => {
        if (!assigneeSelect) {
            return;
        }
        const data = await api(`${API_BASE}/hr/personnel`);
        const opts = (data.data || []).map((u) => {
            const label = `${u.name || u.email}${u.email ? ` (${u.email})` : ''}`;
            return `<option value="${escHtml(u.id || '')}">${escHtml(label)}</option>`;
        }).join('');
        assigneeSelect.innerHTML = `<option value="">Unassigned</option>${opts}`;
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
            assigned_to_user_id: formData.get('assigned_to_user_id') || null,
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
            await Promise.all([loadStatuses(), loadStages(), loadAssignablePersonnel()]);
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
        window.location.href = buildHrLoginRedirectUrl();
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

function initHrReportsPage() {
    const reportsPage = byId('hr-reports-page');
    if (!reportsPage) {
        return;
    }

    const reportFilter = byId('hr-report-filter');
    const reportTable = byId('hr-report-table');
    const reportOutput = byId('hr-report-output');
    const categoryFilter = byId('report-category');
    const statusFilter = byId('report-status');

    const loadFilters = async () => {
        try {
            const [catData, statusData] = await Promise.all([
                api(`${API_BASE}/categories`),
                api(`${API_BASE}/statuses`)
            ]);
            const catOpts = (catData.data || []).map(c => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
            const statusOpts = (statusData.data || []).map(s => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
            if (categoryFilter) categoryFilter.innerHTML = '<option value="">Any category</option>' + catOpts;
            if (statusFilter) statusFilter.innerHTML = '<option value="">Any status</option>' + statusOpts;
        } catch (err) {
            console.error('Failed to load filters:', err);
        }
    };

    const loadReports = async () => {
        try {
            const params = new URLSearchParams(new FormData(reportFilter));
            const data = await api(`${API_BASE}/reports?${params.toString()}`);
            reportTable.innerHTML = renderTable(data.data || []);
            reportOutput.classList.add('d-none');
        } catch (err) {
            reportTable.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
            reportOutput.classList.remove('d-none');
            reportOutput.textContent = err.message;
        }
    };

    reportFilter?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await loadReports();
    });

    // Handle Clear Filters button
    const clearHrReportsBtn = reportFilter?.querySelector('[type="reset"]');
    clearHrReportsBtn?.addEventListener('click', async () => {
        reportFilter.reset();
        await loadReports();
    });

    Promise.all([loadFilters(), loadReports()]).catch((err) => {
        reportTable.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-circle me-2"></i>Failed to load reports: ${err.message}</div>`;
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
        window.location.href = buildHrLoginRedirectUrl();
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
        window.location.href = buildHrLoginRedirectUrl();
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

function initHrStages() {
    const section = byId('hr-stages-section');
    const stageTable = byId('stage-table');
    const addBtn = byId('stage-add-btn');
    const addForm = byId('stage-add-form');
    const saveBtn = byId('stage-save-btn');
    const cancelBtn = byId('stage-cancel-btn');
    const newName = byId('stage-new-name');
    const newOrder = byId('stage-new-order');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        window.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;

    const renderStageTable = (stages) => {
        if (!stages.length) {
            stageTable.innerHTML = '<p class="text-muted">No stages yet.</p>';
            return;
        }

        const rows = stages.map((s) => `
          <tr>
            <td>${escHtml(s.name)}</td>
            <td>${s.sort_order}</td>
            <td><span class="badge ${s.is_active ? 'bg-success' : 'bg-secondary'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1 stage-edit-btn" data-id="${s.id}" data-name="${escHtml(s.name)}" data-order="${s.sort_order}" data-active="${s.is_active}">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger stage-delete-btn" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>`).join('');

        stageTable.innerHTML = `<table class="table table-sm table-hover">
          <thead><tr><th>Name</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>${rows}</tbody></table>`;

        stageTable.querySelectorAll('.stage-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
            editingId = parseInt(btn.dataset.id, 10);
            newName.value = btn.dataset.name;
            newOrder.value = btn.dataset.order;
            addForm.dataset.active = btn.dataset.active;
            addForm.classList.remove('d-none');
            saveBtn.textContent = 'Update';
            newName.focus();
        }));

        stageTable.querySelectorAll('.stage-delete-btn').forEach((btn) => btn.addEventListener('click', async () => {
            if (!confirm(`Delete stage "${btn.dataset.name}"? This cannot be undone.`)) return;
            try {
                await api(`${API_BASE}/hr/stages/${encodeURIComponent(btn.dataset.id)}`, { method: 'DELETE' });
                showNotification('Stage deleted.', 'success');
                await loadStages();
            } catch (err) {
                showNotification(err.message, 'danger');
            }
        }));
    };

    const loadStages = async () => {
        const data = await api(`${API_BASE}/hr/stages`);
        renderStageTable(data.data || []);
    };

    window._reloadHrStages = loadStages;

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
            showNotification('Stage name is required.', 'warning');
            return;
        }

        try {
            if (editingId) {
                const isActive = addForm.dataset.active !== '0';
                await api(`${API_BASE}/hr/stages/${encodeURIComponent(editingId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, is_active: isActive, sort_order: order }),
                });
                showNotification('Stage updated.', 'success');
            } else {
                await api(`${API_BASE}/hr/stages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, sort_order: order }),
                });
                showNotification('Stage created.', 'success');
            }

            addForm.classList.add('d-none');
            editingId = null;
            await loadStages();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    loadStages().catch(() => {});
}

function initNavAuth() {
    const hrConsoleItem = byId('nav-hr-console-item');
    const loginItem = byId('nav-hr-login-item');
    const logoutItem = byId('nav-hr-logout-item');
    const logoutBtn = byId('nav-hr-logout');

    const update = (isLoggedIn) => {
        if (hrConsoleItem) hrConsoleItem.style.display = isLoggedIn ? '' : 'none';
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
    initHrPage();
    initHrCasePage();
    initHrReportsPage();
    initHrDashboardPage();
    initHrCategories();
    initHrStatuses();
    initHrStages();
});



function renderHrCasesTable(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No feedback cases found.</div>';
    }

    const head = '<tr><th>Date</th><th>Reference</th><th>Category</th><th>Status</th><th>Priority</th><th>Assigned To</th><th>Action</th></tr>';
    const body = rows.map((r) => {
        const reference = r.reference_no || '';
        const href = `/hr/cases/${encodeURIComponent(reference)}`;
        const assignedTo = formatAssignedTo(r.assigned_role_name, r.assigned_to_name, r.assigned_to_email);
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

function initHrConsolePage() {
    const hrOutput = byId('hr-output');
    const loginForm = byId('hr-login-form');
    const hrCasesSection = byId('hr-cases-section');
    const loginNote = byId('hr-login-note');
    const filterForm = byId('hr-filter-form');
    const casesTable = byId('hr-cases-table');
    const paginationEl = byId('hr-cases-pagination');
    const isLoginPage = Boolean(loginForm) && !hrCasesSection;
    const isFeedbackPage = Boolean(hrCasesSection);

    if (!loginForm && !hrCasesSection) {
        return;
    }

    const returnTo = getSafeReturnToPath();

    const setLoggedInUi = (isLoggedIn) => {
        const loginInputs = loginForm ? loginForm.querySelectorAll('input') : [];
        const loginSubmit = loginForm?.querySelector('[type="submit"]');
        const loginCard = loginForm?.closest('.card');

        loginInputs.forEach((input) => {
            input.disabled = isLoggedIn;
            input.closest('.col-md-4')?.classList.toggle('d-none', isLoggedIn);
        });

        if (loginSubmit) {
            loginSubmit.disabled = isLoggedIn;
            loginSubmit.classList.toggle('d-none', isLoggedIn);
        }

        globalThis._navAuthUpdate?.(isLoggedIn);

        if (loginNote) {
            loginNote.style.display = isLoggedIn ? 'none' : 'block';
        }

        if (hrCasesSection) {
            hrCasesSection.style.display = isLoggedIn ? 'block' : 'none';
        }

        if (loginCard) {
            loginCard.classList.toggle('d-none', isLoggedIn);
        }
    };

    setLoggedInUi(false);

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
        if (!filterForm || !casesTable) {
            return;
        }

        const params = new URLSearchParams(new FormData(filterForm));
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        const data = await api(`${API_BASE}/hr/cases?${params.toString()}`);
        casesTable.innerHTML = renderHrCasesTable(data.data || []);
        renderPagination(data.pagination || {});
    };

    const redirectAfterLogin = () => {
        if (returnTo && returnTo !== '/hr/login') {
            globalThis.location.href = returnTo;
            return;
        }

        globalThis.location.href = isFeedbackPage ? '/hr' : '/';
    };

    loginForm?.addEventListener('submit', async (e) => {
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

            if (!data?.user || typeof data.user !== 'object') {
                const payloadPreview = typeof data === 'string'
                    ? data.slice(0, 240)
                    : JSON.stringify(data ?? null).slice(0, 240);
                throw new Error(`Login response missing user payload. Server payload: ${payloadPreview}`);
            }

            if (typeof data?.csrf_token === 'string' && data.csrf_token.trim() !== '') {
                TokenManager.setCsrfToken(data.csrf_token);
            }

            let userName = '';
            userName = String(data.user.name || data.user.email || '').trim();

            if (!userName) {
                try {
                    const me = await api(`${API_BASE}/hr/me`);
                    userName = String(me?.user?.name || me?.user?.email || '').trim();
                } catch (err) {
                    console.warn('Failed to load current HR user profile.', err);
                    userName = '';
                }
            }

            showNotification(`Logged in as ${userName || 'User'}!`, 'success');
            setLoggedInUi(true);
            loginForm.password.value = '';
            hrOutput?.classList.add('d-none');

            if (isLoginPage) {
                redirectAfterLogin();
                return;
            }

            await loadFilterOptions();
            await loadCases();
        } catch (err) {
            showNotification('Login failed: ' + err.message, 'danger');
            hrOutput?.classList.add('d-none');
        }
    });

    filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        currentPage = 1;
        try {
            await loadCases();
        } catch (err) {
            showNotification(err.message, 'danger');
            casesTable.innerHTML = renderHrCasesTable([]);
        }
    });

    const clearBtn = filterForm?.querySelector('[type="reset"]');
    clearBtn?.addEventListener('click', async () => {
        filterForm.reset();
        currentPage = 1;
        try {
            await loadCases();
        } catch (err) {
            showNotification(err.message, 'danger');
            casesTable.innerHTML = renderHrCasesTable([]);
        }
    });

    (async () => {
        if (!TokenManager.hasToken()) {
            if (isFeedbackPage) {
                globalThis.location.href = buildHrLoginRedirectUrl();
            }
            return;
        }

        setLoggedInUi(true);

        if (isLoginPage) {
            redirectAfterLogin();
            return;
        }

        try {
            if (isFeedbackPage) {
                await Promise.all([loadFilterOptions(), loadCases()]);
            }
        } catch {
            TokenManager.clearToken();
            setLoggedInUi(false);
            if (casesTable) {
                casesTable.innerHTML = renderHrCasesTable([]);
            }
            showNotification('Session expired. Please log in again.', 'warning');
        }
    })();
}

document.addEventListener('DOMContentLoaded', () => {
    initHrConsolePage();
});

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

    const head = '<tr><th>Financial Year</th><th>Quarter</th><th>Category</th><th>Total Cases</th></tr>';
    const body = rows.map((row) => `<tr>
        <td>FY ${(row.fiscal_year_start || '')}/${String((Number(row.fiscal_year_start || 0) + 1)).slice(-2)}</td>
        <td>Q${row.quarter_no || ''}</td>
        <td>${row.category || ''}</td>
        <td>${row.total_cases || 0}</td>
    </tr>`).join('');

    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function renderDashboardProvinceTotals(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info mb-0">No province grouping data available.</div>';
    }

    const head = '<tr><th>Province</th><th>Total Cases</th></tr>';
    const body = rows.map((row) => `<tr>
        <td>${escHtml(row.province || 'Not specified')}</td>
        <td>${row.total || 0}</td>
    </tr>`).join('');

    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function renderDashboardCategoryFrequency(rows) {
    if (!rows?.length) {
        return '<div class="alert alert-info mb-0">No category data available.</div>';
    }

    const maxOpen = Math.max(...rows.map((r) => Number(r.open_cases || 0)), 1);

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

function initHrDashboardPage() {
    const dashboardPage = byId('hr-dashboard-page');
    if (!dashboardPage) {
        return;
    }

    const output = byId('hr-dashboard-output');
    const statusTotals = byId('hr-dashboard-status-totals');
    const provinceTotals = byId('hr-dashboard-province-totals');
    const quarterlyTrends = byId('hr-dashboard-quarterly-trends');
    const categoryFrequency = byId('hr-dashboard-category-frequency');
    const refreshBtn = byId('hr-dashboard-refresh');

    if (!TokenManager.hasToken()) {
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    const refreshButtonState = {
        defaultHtml: refreshBtn ? refreshBtn.innerHTML : ''
    };

    const setDashboardLoadingState = (loading) => {
        if (refreshBtn) {
            refreshBtn.disabled = loading;
            refreshBtn.innerHTML = loading
                ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Refreshing...'
                : refreshButtonState.defaultHtml;
        }
    };

    const showDashboardError = (message) => {
        if (!output) {
            return;
        }

        output.textContent = message || 'Failed to load dashboard.';
        output.classList.remove('d-none');
    };

    const loadDashboard = async () => {
        setDashboardLoadingState(true);
        try {
            const result = await api(`${API_BASE}/hr/dashboard/trends`);
            const data = result.data || {};
            statusTotals.innerHTML = renderDashboardStatusTotals(data.status_totals || []);
            if (provinceTotals) {
                provinceTotals.innerHTML = renderDashboardProvinceTotals(data.province_totals || []);
            }
            quarterlyTrends.innerHTML = renderDashboardQuarterlyTrends(data.quarterly_by_category || []);
            if (categoryFrequency) {
                categoryFrequency.innerHTML = renderDashboardCategoryFrequency(data.category_frequency || []);
            }
            output.classList.add('d-none');
        } finally {
            setDashboardLoadingState(false);
        }
    };

    refreshBtn?.addEventListener('click', async () => {
        try {
            await loadDashboard();
        } catch (err) {
            showDashboardError(err.message);
        }
    });

    loadDashboard().catch((err) => {
        showDashboardError(err.message);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHrDashboardPage();
});

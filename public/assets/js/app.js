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

function initTabs() {
    const buttons = document.querySelectorAll('.tabs button');
    if (!buttons.length) {
        return;
    }

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            buttons.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.tab-content').forEach((tab) => tab.classList.remove('active'));
            byId('tab-' + btn.dataset.tab).classList.add('active');
        });
    });
}

function renderTable(rows) {
    if (!rows.length) {
        return '<p>No records found.</p>';
    }
    const head = '<tr><th>Date</th><th>Reference</th><th>Category</th><th>Status</th><th>Summary</th><th>Outcome</th></tr>';
    const body = rows.map((r) => `<tr><td>${r.created_at || ''}</td><td>${r.reference_no || ''}</td><td>${r.category || ''}</td><td>${r.status || ''}</td><td>${r.anonymized_summary || ''}</td><td>${r.outcome_comments || ''}</td></tr>`).join('');
    return `<table><thead>${head}</thead><tbody>${body}</tbody></table>`;
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
                out.textContent = JSON.stringify(data, null, 2);
                newForm.reset();
            } catch (err) {
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
                out.textContent = JSON.stringify(data, null, 2);
                followupForm.reset();
            } catch (err) {
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
                lookupOut.textContent = JSON.stringify(data, null, 2);
            } catch (err) {
                lookupOut.textContent = err.message;
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
                reportTable.textContent = err.message;
            }
        });

        load().catch(() => {
            reportTable.textContent = 'Could not load reports.';
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
            hrOutput.textContent = JSON.stringify(data, null, 2);
        } catch (err) {
            hrOutput.textContent = err.message;
        }
    });

    logoutBtn?.addEventListener('click', async () => {
        try {
            const data = await api('/api/hr/logout', { method: 'POST' });
            hrOutput.textContent = JSON.stringify(data, null, 2);
        } catch (err) {
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
            casesTable.textContent = err.message;
        }
    });

    updateForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(updateForm);
        const ref = (formData.get('reference_no') || '').toString().trim();
        if (!ref) {
            hrOutput.textContent = 'Reference is required.';
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
            hrOutput.textContent = JSON.stringify(data, null, 2);
            await loadCases();
        } catch (err) {
            hrOutput.textContent = err.message;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initPublicForms();
    initHrPage();
});

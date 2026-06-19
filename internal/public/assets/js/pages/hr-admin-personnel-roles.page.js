function initHrPersonnelRolesPage() {
    const section = byId('hr-personnel-roles-section');
    const tableContainer = byId('personnel-roles-table');
    const syncBtn = byId('sync-ad-personnel-btn');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let allPersonnelRows = [];
    let personnelCurrentPage = 1;
    const personnelPerPage = 10;

    const renderPersonnelPagination = () => {
        const total = allPersonnelRows.length;
        const totalPages = Math.max(1, Math.ceil(total / personnelPerPage));
        if (personnelCurrentPage > totalPages) {
            personnelCurrentPage = totalPages;
        }

        if (total <= personnelPerPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${personnelCurrentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Personnel roles pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="personnel-page-prev" ${personnelCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="personnel-page-next" ${personnelCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

    const renderRows = (rows) => {
        if (!allPersonnelRows.length) {
            tableContainer.innerHTML = '<p class="text-muted">No personnel found. Use Sync from AD first.</p>';
            return;
        }

        const start = (personnelCurrentPage - 1) * personnelPerPage;
        const pagedRows = rows.slice(start, start + personnelPerPage);

        const body = pagedRows.map((person) => {
            return `<tr>
                <td>
                    <div class="fw-semibold">${escHtml(person.name || '')}</div>
                    <div class="small text-muted">${escHtml(person.email || '')}</div>
                    <div class="small text-muted">Emp #: ${escHtml(person.employee_number || '-')}</div>
                </td>
                <td>${escHtml(person.department_name || '-')}</td>
                <td>${escHtml(person.position_title || '-')}</td>
                <td>${escHtml(person.office_location || '-')}</td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input personnel-can-assign" data-id="${escHtml(person.id || '')}" ${person.can_assign_cases ? 'checked' : ''}>
                </td>
                <td>
                    <button type="button" class="btn btn-sm personnel-save" style="background-color:#008AC4; border-color:#008AC4; color:#fff;" data-id="${escHtml(person.id || '')}">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </td>
            </tr>`;
        }).join('');

        tableContainer.innerHTML = `<table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Location</th>
                    <th>Can Assign Cases</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>${body}</tbody>
        </table>${renderPersonnelPagination()}`;

        tableContainer.querySelectorAll('.personnel-save').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const canAssign = tableContainer.querySelector(`.personnel-can-assign[data-id="${id}"]`);
                const canAssignCases = canAssign?.checked ? 1 : 0;

                try {
                    await api(`${API_BASE}/hr/personnel-roles/${encodeURIComponent(id)}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            can_assign_cases: canAssignCases,
                        }),
                    });
                    showNotification('Personnel roles updated.', 'success');
                } catch (err) {
                    showNotification(err.message, 'danger');
                }
            });
        });

        byId('personnel-page-prev')?.addEventListener('click', () => {
            if (personnelCurrentPage > 1) {
                personnelCurrentPage -= 1;
                renderRows(allPersonnelRows);
            }
        });

        byId('personnel-page-next')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allPersonnelRows.length / personnelPerPage));
            if (personnelCurrentPage < totalPages) {
                personnelCurrentPage += 1;
                renderRows(allPersonnelRows);
            }
        });
    };

    const loadPersonnelRoles = async () => {
        const data = await api(`${API_BASE}/hr/personnel-roles`);
        allPersonnelRows = data.data || [];
        personnelCurrentPage = 1;
        renderRows(allPersonnelRows);
    };

    syncBtn?.addEventListener('click', async () => {
        syncBtn.disabled = true;
        tableContainer.innerHTML = '<p class="text-muted">Re-syncing from AD...</p>';
        try {
            const data = await api(`${API_BASE}/hr/personnel/sync-ad`, { method: 'POST' });
            const processed = Number(data.processed || 0);
            const reset = Number(data.reset || 0);
            const hasFetched = Object.hasOwn(data, 'fetched');
            const hasSkipped = Object.hasOwn(data, 'skipped_no_email');

            if (hasFetched && hasSkipped) {
                const fetched = Number(data.fetched || 0);
                const skippedNoEmail = Number(data.skipped_no_email || 0);
                showNotification(`AD re-sync completed. fetched=${fetched}, processed=${processed}, skipped(no email)=${skippedNoEmail}, reset=${reset}.`, 'success');
            } else {
                showNotification(`AD re-sync completed. processed=${processed}, reset=${reset}. Backend diagnostics missing - restart/update the PHP service.`, 'warning');
            }
            await loadPersonnelRoles();
        } catch (err) {
            showNotification(err.message, 'danger');
            await loadPersonnelRoles();
        } finally {
            syncBtn.disabled = false;
        }
    });

    loadPersonnelRoles().catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    initHrPersonnelRolesPage();
});

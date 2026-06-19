function initHrStatusesPage() {
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
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;
    let allStatuses = [];
    let statusCurrentPage = 1;
    const statusPerPage = 10;

    const renderStatusPagination = () => {
        const total = allStatuses.length;
        const totalPages = Math.max(1, Math.ceil(total / statusPerPage));
        if (statusCurrentPage > totalPages) {
            statusCurrentPage = totalPages;
        }

        if (total <= statusPerPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${statusCurrentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Statuses pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="status-page-prev" ${statusCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="status-page-next" ${statusCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

    const renderStatusTable = (statuses) => {
        if (!allStatuses.length) {
            statusTable.innerHTML = '<p class="text-muted">No statuses yet.</p>';
            return;
        }
        const start = (statusCurrentPage - 1) * statusPerPage;
        const pagedStatuses = statuses.slice(start, start + statusPerPage);

        const rows = pagedStatuses.map((s) => `
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
          <tbody>${rows}</tbody></table>${renderStatusPagination()}`;

        statusTable.querySelectorAll('.status-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
            editingId = (btn.dataset.id || '').trim();
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

        byId('status-page-prev')?.addEventListener('click', () => {
            if (statusCurrentPage > 1) {
                statusCurrentPage -= 1;
                renderStatusTable(allStatuses);
            }
        });

        byId('status-page-next')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allStatuses.length / statusPerPage));
            if (statusCurrentPage < totalPages) {
                statusCurrentPage += 1;
                renderStatusTable(allStatuses);
            }
        });
    };

    const loadStatuses = async () => {
        const data = await api(`${API_BASE}/hr/statuses`);
        allStatuses = data.data || [];
        statusCurrentPage = 1;
        renderStatusTable(allStatuses);

        const filterSelect = byId('filter-status');
        if (filterSelect) {
            const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
            filterSelect.innerHTML = '<option value="">Any status</option>' + opts;
        }
    };

    globalThis._reloadHrStatuses = loadStatuses;

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
        const order = Number.parseInt(newOrder.value, 10) || 0;
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

document.addEventListener('DOMContentLoaded', () => {
    initHrStatusesPage();
});

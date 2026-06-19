function initHrStagesPage() {
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
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;
    let allStages = [];
    let stageCurrentPage = 1;
    const stagePerPage = 10;

    const renderStagePagination = () => {
        const total = allStages.length;
        const totalPages = Math.max(1, Math.ceil(total / stagePerPage));
        if (stageCurrentPage > totalPages) {
            stageCurrentPage = totalPages;
        }

        if (total <= stagePerPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${stageCurrentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Stages pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="stage-page-prev" ${stageCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="stage-page-next" ${stageCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

    const renderStageTable = (stages) => {
        if (!allStages.length) {
            stageTable.innerHTML = '<p class="text-muted">No stages yet.</p>';
            return;
        }
        const start = (stageCurrentPage - 1) * stagePerPage;
        const pagedStages = stages.slice(start, start + stagePerPage);

        const rows = pagedStages.map((s) => `
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
          <tbody>${rows}</tbody></table>${renderStagePagination()}`;

        stageTable.querySelectorAll('.stage-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
            editingId = (btn.dataset.id || '').trim();
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

        byId('stage-page-prev')?.addEventListener('click', () => {
            if (stageCurrentPage > 1) {
                stageCurrentPage -= 1;
                renderStageTable(allStages);
            }
        });

        byId('stage-page-next')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allStages.length / stagePerPage));
            if (stageCurrentPage < totalPages) {
                stageCurrentPage += 1;
                renderStageTable(allStages);
            }
        });
    };

    const loadStages = async () => {
        const data = await api(`${API_BASE}/hr/stages`);
        allStages = data.data || [];
        stageCurrentPage = 1;
        renderStageTable(allStages);
    };

    globalThis._reloadHrStages = loadStages;

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

document.addEventListener('DOMContentLoaded', () => {
    initHrStagesPage();
});

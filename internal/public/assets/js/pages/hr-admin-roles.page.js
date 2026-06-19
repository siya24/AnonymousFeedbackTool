function initHrRolesPage() {
    const section = byId('hr-roles-section');
    const roleTable = byId('role-table');
    const addBtn = byId('role-add-btn');
    const addForm = byId('role-add-form');
    const saveBtn = byId('role-save-btn');
    const cancelBtn = byId('role-cancel-btn');
    const newName = byId('role-new-name');
    const newOrder = byId('role-new-order');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;
    let allRoles = [];
    let roleCurrentPage = 1;
    const rolePerPage = 10;

    const renderRolePagination = () => {
        const total = allRoles.length;
        const totalPages = Math.max(1, Math.ceil(total / rolePerPage));
        if (roleCurrentPage > totalPages) {
            roleCurrentPage = totalPages;
        }

        if (total <= rolePerPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${roleCurrentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Roles pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="role-page-prev" ${roleCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="role-page-next" ${roleCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

        const renderRoleTable = (roles) => {
                if (!allRoles.length) {
                        roleTable.innerHTML = '<p class="text-muted">No roles yet.</p>';
            return;
        }
        const start = (roleCurrentPage - 1) * rolePerPage;
                const pagedRoles = roles.slice(start, start + rolePerPage);

        const rows = pagedRoles.map((r) => `
          <tr>
                        <td>${escHtml(r.name)}</td>
                        <td>${r.sort_order}</td>
                        <td><span class="badge ${r.is_active ? 'bg-success' : 'bg-secondary'}">${r.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                            <button class="btn btn-sm btn-outline-primary me-1 role-edit-btn" data-id="${r.id}" data-name="${escHtml(r.name)}" data-order="${r.sort_order}" data-active="${r.is_active}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger role-delete-btn" data-id="${r.id}" data-name="${escHtml(r.name)}">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>`).join('');

        roleTable.innerHTML = `<table class="table table-sm table-hover">
                    <thead><tr><th>Name</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>${rows}</tbody></table>${renderRolePagination()}`;

                roleTable.querySelectorAll('.role-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
                        editingId = String(btn.dataset.id || '');
                        newName.value = btn.dataset.name;
                        newOrder.value = btn.dataset.order;
                        addForm.dataset.active = btn.dataset.active;
                        addForm.classList.remove('d-none');
                        saveBtn.textContent = 'Update';
                        newName.focus();
                }));

        roleTable.querySelectorAll('.role-delete-btn').forEach((btn) => btn.addEventListener('click', async () => {
                        if (!confirm(`Delete role "${btn.dataset.name}"? This cannot be undone.`)) return;
            try {
                                await api(`${API_BASE}/hr/roles/${encodeURIComponent(btn.dataset.id)}`, { method: 'DELETE' });
                                showNotification('Role deleted.', 'success');
                                await loadRoles();
            } catch (err) {
                showNotification(err.message, 'danger');
            }
        }));

        byId('role-page-prev')?.addEventListener('click', () => {
            if (roleCurrentPage > 1) {
                roleCurrentPage -= 1;
                renderRoleTable(allRoles);
            }
        });

        byId('role-page-next')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allRoles.length / rolePerPage));
            if (roleCurrentPage < totalPages) {
                roleCurrentPage += 1;
                renderRoleTable(allRoles);
            }
        });
    };

    const loadRoles = async () => {
        const data = await api(`${API_BASE}/hr/roles`);
        allRoles = data.data || [];
        roleCurrentPage = 1;
        renderRoleTable(allRoles);
    };

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
            showNotification('Role name is required.', 'warning');
            return;
        }

        try {
            if (editingId) {
                const isActive = addForm.dataset.active !== '0';
                await api(`${API_BASE}/hr/roles/${encodeURIComponent(editingId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, is_active: isActive, sort_order: order }),
                });
                showNotification('Role updated.', 'success');
            } else {
                await api(`${API_BASE}/hr/roles`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, sort_order: order }),
                });
                showNotification('Role created.', 'success');
            }

            addForm.classList.add('d-none');
            editingId = null;
            await loadRoles();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    loadRoles().catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    initHrRolesPage();
});

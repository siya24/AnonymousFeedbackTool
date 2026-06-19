function initHrUsersPage() {
    const section = byId('hr-users-section');
    const tableContainer = byId('user-table');
    const addBtn = byId('user-add-btn');
    const formPanel = byId('user-form-panel');
    const formTitle = byId('user-form-title');
    const form = byId('user-form');
    const cancelBtn = byId('user-cancel-btn');
    const nameInput = byId('user-name');
    const emailInput = byId('user-email');
    const assignmentRoleInput = byId('user-assignment-role');
    const canAssignInput = byId('user-can-assign');
    const isActiveInput = byId('user-is-active');

    if (!section) return;
    if (!TokenManager.hasToken()) {
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;
    let allUsers = [];
    let allRoles = [];
    let currentPage = 1;
    const perPage = 10;

    const getSelectedRoleIds = () => {
        if (!assignmentRoleInput) {
            return [];
        }
        return Array.from(assignmentRoleInput.selectedOptions || [])
            .map((option) => String(option.value || '').trim())
            .filter((value) => value !== '');
    };

    const setSelectedRoleIds = (roleIds) => {
        if (!assignmentRoleInput) {
            return;
        }
        const selected = new Set((roleIds || []).map((id) => String(id || '').trim()).filter((id) => id !== ''));
        Array.from(assignmentRoleInput.options || []).forEach((option) => {
            option.selected = selected.has(String(option.value || '').trim());
        });
    };

    const resetForm = () => {
        editingId = null;
        form.reset();
        setSelectedRoleIds([]);
        isActiveInput.checked = true;
        canAssignInput.checked = false;
        formTitle.textContent = 'User Details';
    };

    const openForm = (user = null) => {
        formPanel.classList.remove('d-none');
        if (!user) {
            resetForm();
            nameInput.focus();
            return;
        }

        editingId = String(user.id || '');
        formTitle.textContent = 'Edit User';
        nameInput.value = String(user.name || '');
        emailInput.value = String(user.email || '');
        setSelectedRoleIds(user.assigned_role_ids || (user.assigned_role_id ? [user.assigned_role_id] : []));
        canAssignInput.checked = Number(user.can_assign_cases || 0) === 1;
        isActiveInput.checked = Number(user.is_active || 0) === 1;
        nameInput.focus();
    };

    const closeForm = () => {
        formPanel.classList.add('d-none');
        resetForm();
    };

    const renderPagination = () => {
        const total = allUsers.length;
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        if (total <= perPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${currentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Users pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="users-page-prev" ${currentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="users-page-next" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

    const renderRows = () => {
        if (!allUsers.length) {
            tableContainer.innerHTML = '<p class="text-muted">No users configured yet.</p>';
            return;
        }

        const start = (currentPage - 1) * perPage;
        const rows = allUsers.slice(start, start + perPage).map((user) => {
            const canAssign = Number(user.can_assign_cases || 0) === 1;
            const isActive = Number(user.is_active || 0) === 1;
            const roleBadges = Array.isArray(user.assigned_role_names) && user.assigned_role_names.length
                ? user.assigned_role_names.map((name) => `<span class="badge bg-info text-dark me-1">${escHtml(name)}</span>`).join('')
                : '';
            const roleMarkup = roleBadges || (user.assigned_role_name
                ? `<span class="badge bg-info text-dark">${escHtml(user.assigned_role_name)}</span>`
                : '<span class="badge bg-secondary">Unassigned</span>');
            const secondary = [];
            if (user.ad_username) {
                secondary.push(`AD: ${escHtml(user.ad_username)}`);
            }
            return `<tr>
                <td>
                    <div class="fw-semibold">${escHtml(user.name || '')}</div>
                    ${secondary.length ? `<div class="small text-muted">${secondary.join(' | ')}</div>` : ''}
                </td>
                <td>${escHtml(user.email || '')}</td>
                <td>${roleMarkup}</td>
                <td>${canAssign ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                <td>${isActive ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary user-edit-btn" data-id="${escHtml(user.id || '')}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-1 user-delete-btn" data-id="${escHtml(user.id || '')}" data-name="${escHtml(user.name || '')}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

        tableContainer.innerHTML = `<table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role Assignment</th>
                    <th>Can Assign</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>${renderPagination()}`;
    };

    const loadUsers = async () => {
        const data = await api(`${API_BASE}/hr/users`);
        allUsers = data.data || [];
        currentPage = 1;
        renderRows();
    };

    const loadAssignableRoles = async () => {
        const data = await api(`${API_BASE}/hr/assignable-roles`);
        allRoles = data.data || [];
        if (assignmentRoleInput) {
            const options = allRoles
                .map((role) => `<option value="${escHtml(role.id || '')}">${escHtml(role.name || '')}</option>`)
                .join('');
            assignmentRoleInput.innerHTML = options;
        }
    };

    addBtn?.addEventListener('click', () => {
        openForm();
    });

    cancelBtn?.addEventListener('click', () => {
        closeForm();
    });

    tableContainer?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const editButton = target.closest('.user-edit-btn');
        if (editButton instanceof HTMLButtonElement) {
            const user = allUsers.find((entry) => String(entry.id || '') === String(editButton.dataset.id || ''));
            if (user) {
                openForm(user);
            }
            return;
        }

        const deleteButton = target.closest('.user-delete-btn');
        if (deleteButton instanceof HTMLButtonElement) {
            const userId = String(deleteButton.dataset.id || '');
            const userName = String(deleteButton.dataset.name || 'this user');
            if (!userId) {
                return;
            }

            if (!confirm(`Delete ${userName}? This action cannot be undone.`)) {
                return;
            }

            api(`${API_BASE}/hr/users/${encodeURIComponent(userId)}`, { method: 'DELETE' })
                .then(() => {
                    showNotification('User deleted.', 'success');
                    if (editingId === userId) {
                        closeForm();
                    }
                    return loadUsers();
                })
                .catch((err) => {
                    showNotification(err.message, 'danger');
                });
            return;
        }

        const prevButton = target.closest('#users-page-prev');
        if (prevButton && currentPage > 1) {
            currentPage -= 1;
            renderRows();
            return;
        }

        const nextButton = target.closest('#users-page-next');
        if (!nextButton) {
            return;
        }

        const totalPages = Math.max(1, Math.ceil(allUsers.length / perPage));
        if (currentPage < totalPages) {
            currentPage += 1;
            renderRows();
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const payload = {
            name,
            email,
            assigned_role_ids: getSelectedRoleIds(),
            can_assign_cases: canAssignInput.checked ? 1 : 0,
            is_active: isActiveInput.checked ? 1 : 0,
        };

        if (!name) {
            showNotification('User name is required.', 'warning');
            return;
        }
        if (!email) {
            showNotification('Email is required.', 'warning');
            return;
        }

        try {
            if (editingId) {
                await api(`${API_BASE}/hr/users/${encodeURIComponent(editingId)}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotification('User updated.', 'success');
            } else {
                await api(`${API_BASE}/hr/users`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                showNotification('User created.', 'success');
            }

            closeForm();
            await loadUsers();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    Promise.all([loadAssignableRoles(), loadUsers()]).catch((err) => {
        showNotification(err.message, 'danger');
        tableContainer.innerHTML = '<p class="text-muted">Unable to load users.</p>';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHrUsersPage();
});

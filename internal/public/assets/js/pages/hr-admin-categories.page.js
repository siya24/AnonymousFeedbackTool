function initHrCategoriesPage() {
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
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    let editingId = null;
    let allCategories = [];
    let categoryCurrentPage = 1;
    const categoryPerPage = 10;

    const renderCategoryPagination = () => {
        const total = allCategories.length;
        const totalPages = Math.max(1, Math.ceil(total / categoryPerPage));
        if (categoryCurrentPage > totalPages) {
            categoryCurrentPage = totalPages;
        }

        if (total <= categoryPerPage) {
            return `<small class="text-muted">Showing ${total} record(s)</small>`;
        }

        return `
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Page ${categoryCurrentPage} of ${totalPages} (${total} records)</small>
                <div class="btn-group" role="group" aria-label="Categories pagination controls">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="cat-page-prev" ${categoryCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="cat-page-next" ${categoryCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            </div>
        `;
    };

    const renderCategoryTable = (categories) => {
        if (!allCategories.length) {
            catTable.innerHTML = '<p class="text-muted">No categories yet.</p>';
            return;
        }
        const start = (categoryCurrentPage - 1) * categoryPerPage;
        const pagedCategories = categories.slice(start, start + categoryPerPage);
        const rows = pagedCategories.map((c) => `
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
          <tbody>${rows}</tbody></table>${renderCategoryPagination()}`;

        catTable.querySelectorAll('.cat-edit-btn').forEach((btn) => btn.addEventListener('click', () => {
            editingId = (btn.dataset.id || '').trim();
            newName.value = btn.dataset.name;
            newOrder.value = btn.dataset.order;
            addForm.dataset.active = btn.dataset.active;
            addForm.classList.remove('d-none');
            saveBtn.textContent = 'Update';
            newName.focus();
        }));

        catTable.querySelectorAll('.cat-delete-btn').forEach((btn) => btn.addEventListener('click', async () => {
            if (!confirm(`Delete category "${btn.dataset.name}"? This cannot be undone.`)) return;
            try {
                await api(`${API_BASE}/hr/categories/${encodeURIComponent(btn.dataset.id)}`, { method: 'DELETE' });
                showNotification('Category deleted.', 'success');
                await loadCategories();
            } catch (err) {
                showNotification(err.message, 'danger');
            }
        }));

        byId('cat-page-prev')?.addEventListener('click', () => {
            if (categoryCurrentPage > 1) {
                categoryCurrentPage -= 1;
                renderCategoryTable(allCategories);
            }
        });

        byId('cat-page-next')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allCategories.length / categoryPerPage));
            if (categoryCurrentPage < totalPages) {
                categoryCurrentPage += 1;
                renderCategoryTable(allCategories);
            }
        });
    };

    const loadCategories = async () => {
        const data = await api(`${API_BASE}/hr/categories`);
        allCategories = data.data || [];
        categoryCurrentPage = 1;
        renderCategoryTable(allCategories);

        const filterSelect = byId('filter-category');
        if (filterSelect) {
            const opts = (data.data || []).map((c) => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
            filterSelect.innerHTML = '<option value="">Any category</option>' + opts;
        }
    };

    globalThis._reloadHrCategories = loadCategories;

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

document.addEventListener('DOMContentLoaded', () => {
    initHrCategoriesPage();
});

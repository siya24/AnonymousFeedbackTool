const CASE_REPORT_MODAL_ID = 'case-report-details-modal';

function renderReportsTable(rows) {
    if (!rows.length) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>';
    }

    const renderSummaryPreview = (row) => {
        const rawSummary = (row.anonymized_summary || '').toString();
        const normalizedSummary = rawSummary.trim();
        const preview = normalizedSummary.length > 110
            ? normalizedSummary.slice(0, 110).trimEnd() + '...'
            : normalizedSummary;

        const previewHtml = preview ? escHtml(preview) : '<span class="text-muted">No summary</span>';
        return `<div>${previewHtml}</div>`;
    };

    const head = '<tr><th><i class="fas fa-calendar me-1"></i>Date</th><th><i class="fas fa-id-card me-1"></i>Reference</th><th><i class="fas fa-tag me-1"></i>Category</th><th><i class="fas fa-info-circle me-1"></i>Status</th><th><i class="fas fa-file-text me-1"></i>Summary</th><th><i class="fas fa-check me-1"></i>Outcome</th><th><i class="fas fa-eye me-1"></i>View</th></tr>';
    const body = rows.map((r, index) => {
        const statusBadge = `<span class="badge" style="background-color: ${r.status === 'Investigation completed' ? '#9d2722' : '#008AC4'}">${r.status || ''}</span>`;
        return `<tr>
            <td>${r.created_at ? new Date(r.created_at).toLocaleDateString() : ''}</td>
            <td><strong>${r.reference_no || ''}</strong></td>
            <td>${r.category || ''}</td>
            <td>${statusBadge}</td>
            <td>${renderSummaryPreview(r)}</td>
            <td>${escHtml(r.outcome_comments || '')}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary hr-report-view-btn" data-row-index="${index}">
                    <i class="fas fa-eye me-1"></i>View
                </button>
            </td>
        </tr>`;
    }).join('');
    return `<div class="table-responsive"><table class="table table-striped table-hover"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
}

function createCaseReportModal() {
    let modal = byId(CASE_REPORT_MODAL_ID);
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = CASE_REPORT_MODAL_ID;
    modal.className = 'case-report-modal';
    modal.innerHTML = `
        <div class="case-report-modal__backdrop" data-role="backdrop">
            <section class="case-report-modal__card" role="dialog" aria-modal="true" aria-labelledby="case-report-modal-title">
                <header class="case-report-modal__header">
                    <h3 id="case-report-modal-title" class="case-report-modal__title">Case Report Details</h3>
                    <button type="button" class="case-report-modal__close" aria-label="Close" data-role="close">&times;</button>
                </header>
                <div class="case-report-modal__body" data-role="body"></div>
                <footer class="case-report-modal__footer">
                    <button type="button" class="btn btn-primary" data-role="action">Close</button>
                </footer>
            </section>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

function showCaseReportDetails(row) {
    const modal = createCaseReportModal();
    const body = modal.querySelector('[data-role="body"]');
    const closeBtn = modal.querySelector('[data-role="close"]');
    const actionBtn = modal.querySelector('[data-role="action"]');
    const backdrop = modal.querySelector('[data-role="backdrop"]');

    const fullSummary = escHtml((row?.anonymized_summary || '').toString()) || '<span class="text-muted">No summary provided.</span>';
    const fullOutcome = escHtml((row?.outcome_comments || '').toString()) || '<span class="text-muted">No outcome provided.</span>';

    const createdAt = row?.created_at ? new Date(row.created_at).toLocaleString() : '';
    const status = escHtml((row?.status || '').toString());
    const statusBadge = `<span class="badge" style="background-color: ${(row?.status || '') === 'Investigation completed' ? '#9d2722' : '#008AC4'}">${status}</span>`;

    body.innerHTML = `
        <div class="case-report-modal__grid">
            <div><strong>Date</strong><div>${escHtml(createdAt)}</div></div>
            <div><strong>Reference</strong><div>${escHtml((row?.reference_no || '').toString())}</div></div>
            <div><strong>Category</strong><div>${escHtml((row?.category || '').toString())}</div></div>
            <div><strong>Status</strong><div>${statusBadge}</div></div>
            <div class="case-report-modal__full"><strong>Anonymized Summary</strong><div class="mt-2">${fullSummary}</div></div>
            <div class="case-report-modal__full"><strong>Outcome</strong><div class="mt-2">${fullOutcome}</div></div>
        </div>
    `;

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.querySelector('[data-role="body"]').innerHTML = '';
        document.body.classList.remove('app-notification-open');
        if (modal._onKeyDown) {
            document.removeEventListener('keydown', modal._onKeyDown);
            modal._onKeyDown = null;
        }
    };

    closeBtn.onclick = closeModal;
    actionBtn.onclick = closeModal;
    backdrop.onclick = (event) => {
        if (event.target === backdrop) {
            closeModal();
        }
    };

    if (modal._onKeyDown) {
        document.removeEventListener('keydown', modal._onKeyDown);
    }
    modal._onKeyDown = (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    };
    document.addEventListener('keydown', modal._onKeyDown);

    modal.classList.add('is-open');
    document.body.classList.add('app-notification-open');
    actionBtn.focus();
}

function initHrReportsPage() {
    const reportsPage = byId('hr-reports-page');
    if (!reportsPage) {
        return;
    }

    const reportFilter = byId('hr-report-filter');
    const reportTable = byId('hr-report-table');
    const reportOutput = byId('hr-report-output');
    const reportPagination = byId('hr-report-pagination');
    const categoryFilter = byId('report-category');
    const statusFilter = byId('report-status');
    let allReportRows = [];
    let reportCurrentPage = 1;
    const reportPerPage = 10;

    const renderReportPagination = () => {
        if (!reportPagination) {
            return;
        }

        const total = allReportRows.length;
        const totalPages = Math.max(1, Math.ceil(total / reportPerPage));
        if (reportCurrentPage > totalPages) {
            reportCurrentPage = totalPages;
        }

        if (total <= reportPerPage) {
            reportPagination.innerHTML = `<small class="text-muted">Showing ${total} record(s)</small>`;
            return;
        }

        reportPagination.innerHTML = `
            <small class="text-muted">Page ${reportCurrentPage} of ${totalPages} (${total} records)</small>
            <div class="btn-group" role="group" aria-label="Report pagination controls">
                <button type="button" class="btn btn-outline-primary btn-sm" id="hr-report-page-prev" ${reportCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="hr-report-page-next" ${reportCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>
            </div>
        `;

        byId('hr-report-page-prev')?.addEventListener('click', () => {
            if (reportCurrentPage > 1) {
                reportCurrentPage -= 1;
                renderReportRows();
            }
        });

        byId('hr-report-page-next')?.addEventListener('click', () => {
            if (reportCurrentPage < totalPages) {
                reportCurrentPage += 1;
                renderReportRows();
            }
        });
    };

    const renderReportRows = () => {
        const start = (reportCurrentPage - 1) * reportPerPage;
        const rows = allReportRows.slice(start, start + reportPerPage);
        reportTable.innerHTML = renderReportsTable(rows);
        reportTable.querySelectorAll('.hr-report-view-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const rowIndexRaw = btn.dataset.rowIndex || '';
                const rowIndex = Number.parseInt(rowIndexRaw, 10);
                if (Number.isNaN(rowIndex) || !rows[rowIndex]) {
                    return;
                }
                showCaseReportDetails(rows[rowIndex]);
            });
        });
        renderReportPagination();
    };

    const loadFilters = async () => {
        try {
            const [catData, statusData] = await Promise.all([
                api(`${API_BASE}/categories`),
                api(`${API_BASE}/statuses`)
            ]);
            const catOpts = (catData.data || []).map((c) => `<option value="${escHtml(c.name)}">${escHtml(c.name)}</option>`).join('');
            const statusOpts = (statusData.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
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
            allReportRows = data.data || [];
            reportCurrentPage = 1;
            renderReportRows();
            reportOutput.classList.add('d-none');
        } catch (err) {
            showNotification(err.message, 'danger');
            reportTable.innerHTML = renderReportsTable([]);
            if (reportPagination) {
                reportPagination.innerHTML = '<small class="text-muted">Showing 0 record(s)</small>';
            }
            reportOutput.classList.add('d-none');
        }
    };

    reportFilter?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await loadReports();
    });

    const clearHrReportsBtn = reportFilter?.querySelector('[type="reset"]');
    clearHrReportsBtn?.addEventListener('click', async () => {
        reportFilter.reset();
        await loadReports();
    });

    Promise.all([loadFilters(), loadReports()]).catch((err) => {
        showNotification(`Failed to load reports: ${err.message}`, 'warning');
        reportTable.innerHTML = renderReportsTable([]);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHrReportsPage();
});

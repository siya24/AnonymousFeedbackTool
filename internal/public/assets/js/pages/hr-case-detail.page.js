function initHrCaseDetailPage() {
    const casePage = byId('hr-case-page');
    if (!casePage) {
        return;
    }

    const hrOutput = byId('hr-output');
    const summary = byId('hr-case-summary');
    const updateForm = byId('hr-update-form');
    const statusSelect = byId('status');
    const stageSelect = byId('stage');
    const provinceSelect = byId('province');
    const assignedRoleSelect = byId('assigned-role-id');
    const coInvestigatorsSelect = byId('co-investigator-select');
    const addCoInvestigatorBtn = byId('add-co-investigator-btn');
    const coInvestigatorsList = byId('co-investigators-list');
    const reference = (casePage.dataset.reference || '').trim();
    const acknowledgeModalId = 'hr-acknowledge-modal';
    let currentUserRole = '';
    let currentUserId = '';
    let canAssignCases = true;
    let canEditAnonymizedSummary = false;
    let isCaseReadOnly = false;
    let currentAssignedInvestigatorId = '';
    let currentCoInvestigatorIds = [];
    let personnelList = [];
    let hasShownAcknowledgePrompt = false;

    const isCompletedStatus = (status) => String(status || '').toLowerCase().includes('completed');
    const isClosedStage = (stage) => String(stage || '').toLowerCase().includes('closed');
    const getCompletedFieldError = (status, stage, values) => {
        if (!isCompletedStatus(status)) {
            return '';
        }

        const checks = [
            ['Feedback to Reporter', values.reporter_feedback],
            ['Action Taken', values.action_taken],
        ];

        if (isClosedStage(stage)) {
            checks.push(['Outcome Comments', values.outcome_comments]);
        }

        for (const [label, value] of checks) {
            if (!String(value || '').trim()) {
                return `${label} is required when case status is completed.`;
            }
        }

        return '';
    };

    const getAcknowledgeValidationError = (values, report) => {
        if (!values.acknowledged) {
            return 'Please tick Acknowledge Case to continue.';
        }

        if (!values.selectedPriority || !values.selectedStage) {
            return 'Priority and Stage are required.';
        }

        if (canEditAnonymizedSummary && !values.anonymizedSummary) {
            return 'Anonymized Summary is required.';
        }

        if (!canEditAnonymizedSummary && !String(report.anonymized_summary || '').trim()) {
            return 'Only Case Manager users can provide Anonymized Summary for acknowledgement.';
        }

        return getCompletedFieldError(values.selectedStatus, values.selectedStage, {
            anonymized_summary: values.anonymizedSummary,
            reporter_feedback: values.reporterFeedback,
            action_taken: values.actionTaken,
            outcome_comments: values.outcomeComments,
        });
    };

    const createAcknowledgeModal = () => {
        let modal = byId(acknowledgeModalId);
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = acknowledgeModalId;
        modal.className = 'case-report-modal';
        modal.innerHTML = `
            <div class="case-report-modal__backdrop" data-role="backdrop">
                <section class="case-report-modal__card" role="dialog" aria-modal="true" aria-labelledby="hr-ack-modal-title">
                    <header class="case-report-modal__header">
                        <h3 id="hr-ack-modal-title" class="case-report-modal__title">Acknowledge Case</h3>
                        <button type="button" class="case-report-modal__close" aria-label="Close" data-role="close">&times;</button>
                    </header>
                    <div class="case-report-modal__body" data-role="body"></div>
                    <footer class="case-report-modal__footer">
                        <button type="button" class="btn btn-primary" data-role="save">Save Acknowledge</button>
                    </footer>
                </section>
            </div>
        `;

        document.body.appendChild(modal);
        return modal;
    };

    const openAcknowledgeModal = (report) => {
        const modal = createAcknowledgeModal();
        const body = modal.querySelector('[data-role="body"]');
        const closeBtn = modal.querySelector('[data-role="close"]');
        const saveBtn = modal.querySelector('[data-role="save"]');
        const backdrop = modal.querySelector('[data-role="backdrop"]');

        const currentPriority = (updateForm?.priority?.value || report.priority || 'Normal').toString();
        const currentStage = (stageSelect?.value || report.stage || '').toString();

        body.innerHTML = `
            <p class="mb-3">This case is not acknowledged yet. Capture acknowledgement details below.</p>
            <div class="mb-3">
                <label for="ack-popup-description" class="form-label">Case Description</label>
                <textarea id="ack-popup-description" class="form-control" rows="4" readonly></textarea>
                <div class="form-text">Read-only case description.</div>
            </div>
            <div class="mb-3">
                <label for="ack-popup-summary" class="form-label">Anonymized Summary <span class="text-danger">*</span></label>
                <textarea id="ack-popup-summary" class="form-control" rows="3" maxlength="5000" required></textarea>
                <div class="form-text">Required to save acknowledgement.</div>
            </div>
            <div class="mb-3">
                <label for="ack-popup-priority" class="form-label">Priority</label>
                <select id="ack-popup-priority" class="form-select">
                    <option value="Low">Low</option>
                    <option value="Normal">Normal</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="ack-popup-stage" class="form-label">Stage</label>
                <select id="ack-popup-stage" class="form-select">${stageSelect?.innerHTML || ''}</select>
            </div>
            <div class="form-check mb-1">
                <input id="ack-popup-check" type="checkbox" class="form-check-input" checked>
                <label for="ack-popup-check" class="form-check-label">Acknowledge Case</label>
            </div>
            <div class="text-danger small d-none" data-role="error"></div>
        `;

        const priorityField = byId('ack-popup-priority');
        const stageField = byId('ack-popup-stage');
        const acknowledgeField = byId('ack-popup-check');
        const descriptionField = byId('ack-popup-description');
        const summaryField = byId('ack-popup-summary');
        const errorField = body.querySelector('[data-role="error"]');

        if (priorityField) {
            priorityField.value = currentPriority;
        }
        if (stageField && currentStage) {
            stageField.value = currentStage;
        }
        if (descriptionField) {
            descriptionField.value = (report.description || '').toString();
        }
        if (summaryField) {
            summaryField.value = (updateForm?.anonymized_summary?.value || report.anonymized_summary || '').toString();
            summaryField.readOnly = !canEditAnonymizedSummary;
        }

        const closeModal = () => {
            modal.classList.remove('is-open');
            document.body.classList.remove('app-notification-open');
            body.innerHTML = '';
            if (modal._onKeyDown) {
                document.removeEventListener('keydown', modal._onKeyDown);
                modal._onKeyDown = null;
            }
        };

        const showError = (message) => {
            if (!errorField) {
                return;
            }
            errorField.textContent = message;
            errorField.classList.remove('d-none');
        };

        closeBtn.onclick = () => {};
        backdrop.onclick = () => {};

        saveBtn.onclick = async () => {
            const values = {
                acknowledged: !!acknowledgeField?.checked,
                selectedPriority: (priorityField?.value || '').toString().trim(),
                selectedStage: (stageField?.value || '').toString().trim(),
                selectedStatus: (statusSelect?.value || report.status || '').toString().trim(),
                anonymizedSummary: (summaryField?.value || updateForm?.anonymized_summary?.value || '').toString().trim(),
                reporterFeedback: (updateForm?.reporter_feedback?.value || '').toString().trim(),
                actionTaken: (updateForm?.action_taken?.value || '').toString().trim(),
                outcomeComments: (updateForm?.outcome_comments?.value || '').toString().trim(),
            };

            const validationError = getAcknowledgeValidationError(values, report);
            if (validationError) {
                showError(validationError);
                return;
            }

            try {
                await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        priority: values.selectedPriority,
                        stage: values.selectedStage,
                        status: values.selectedStatus,
                        province: (provinceSelect?.value || '').toString().trim() || null,
                        ...(canEditAnonymizedSummary ? { anonymized_summary: values.anonymizedSummary } : {}),
                        reporter_feedback: values.reporterFeedback,
                        action_taken: values.actionTaken,
                        outcome_comments: values.outcomeComments,
                        acknowledge: 1,
                    }),
                });

                if (updateForm?.priority) {
                    updateForm.priority.value = values.selectedPriority;
                }
                if (stageSelect) {
                    stageSelect.value = values.selectedStage;
                }
                if (canEditAnonymizedSummary && updateForm?.anonymized_summary) {
                    updateForm.anonymized_summary.value = values.anonymizedSummary;
                }
                if (updateForm?.acknowledge) {
                    updateForm.acknowledge.checked = true;
                }

                closeModal();
                showNotification('Case acknowledged successfully.', 'success');
                await loadCase();
            } catch (err) {
                showError(err instanceof Error ? err.message : 'Failed to acknowledge case.');
            }
        };

        if (modal._onKeyDown) {
            document.removeEventListener('keydown', modal._onKeyDown);
        }
        modal._onKeyDown = () => {};
        document.addEventListener('keydown', modal._onKeyDown);

        modal.classList.add('is-open');
        document.body.classList.add('app-notification-open');
        saveBtn.focus();
    };

    const toggleFieldVisibility = (fieldId, visible) => {
        const field = byId(fieldId);
        const container = field?.closest('.mb-3');
        if (!field || !container) {
            return;
        }

        container.classList.toggle('d-none', !visible);
        field.disabled = !visible;
    };

    const updateConditionalFields = () => {
        if (!updateForm) {
            return;
        }

        const stageValue = String(stageSelect?.value || '').trim().toLowerCase();
        const statusValue = String(statusSelect?.value || '').trim().toLowerCase();

        const isEarlyStage = stageValue === 'logged' || stageValue === 'acknowledge case';
        const isClosedStage = stageValue === 'closed';
        const isInProgress = statusValue.includes('progress');
        const isCompleted = statusValue.includes('completed');

        const showActionTaken = (!isEarlyStage && !isInProgress) || isCompleted;
        const showOutcomeComments = isCompleted && isClosedStage;
        const showInternalNotes = true;
        const showAnonymizedSummary = canEditAnonymizedSummary;

        toggleFieldVisibility('anon-summary', showAnonymizedSummary);
        toggleFieldVisibility('action-taken', showActionTaken);
        toggleFieldVisibility('outcome-comments', showOutcomeComments);
        toggleFieldVisibility('internal-notes', showInternalNotes);

        if (updateForm.outcome_comments) {
            updateForm.outcome_comments.required = isCompleted && isClosedStage;
        }
        if (updateForm.anonymized_summary) {
            updateForm.anonymized_summary.required = canEditAnonymizedSummary;
        }
        if (updateForm.reporter_feedback) {
            updateForm.reporter_feedback.required = isCompleted;
        }
        if (updateForm.action_taken) {
            updateForm.action_taken.required = isCompleted;
        }
        if (updateForm.internal_notes) {
            updateForm.internal_notes.required = false;
        }

        if (!showOutcomeComments && updateForm.outcome_comments) {
            updateForm.outcome_comments.value = '';
        }
    };

    if (!TokenManager.hasToken()) {
        globalThis.location.href = buildHrLoginRedirectUrl();
        return;
    }

    const populateFormFromCase = (report) => {
        if (!report || !updateForm) {
            return;
        }

        updateForm.priority.value = report.priority || 'Normal';
        if (stageSelect) {
            stageSelect.value = report.stage || 'Logged';
        }
        updateForm.status.value = report.status || 'Investigation pending';
        if (provinceSelect) {
            provinceSelect.value = report.province || '';
        }
        if (assignedRoleSelect) {
            currentAssignedInvestigatorId = (report.assigned_to_user_id || '').toString().trim();
            assignedRoleSelect.value = currentAssignedInvestigatorId;
        }
        updateForm.anonymized_summary.value = report.anonymized_summary || '';
        if (updateForm.reporter_feedback) {
            updateForm.reporter_feedback.value = report.reporter_feedback || '';
        }
        updateForm.action_taken.value = report.action_taken || '';
        updateForm.outcome_comments.value = report.outcome_comments || '';
        updateForm.internal_notes.value = report.internal_notes || '';
        const checked = !!report.acknowledged_at;
        updateForm.acknowledge.checked = checked;
        updateConditionalFields();
    };

    const renderCaseSummary = (report, attachments, updates, auditEntries) => {
        const created = report.created_at ? new Date(report.created_at).toLocaleString() : '';
        const acknowledged = report.acknowledged_at ? new Date(report.acknowledged_at).toLocaleString() : 'Not acknowledged';
        const assignedAt = report.assigned_at ? new Date(report.assigned_at).toLocaleString() : 'Not assigned';
        const assignedEmail = report.assigned_to_email || 'Not assigned';
        const assignedTo = formatAssignedTo('', report.assigned_to_name, report.assigned_to_email);
        const attachmentLinks = (attachments || []).map((a) =>
            `<a href="/api/attachments/${encodeURIComponent(a.id)}" download="${escHtml(a.original_name)}" class="me-2"><i class="fas fa-paperclip me-1"></i>${escHtml(a.original_name)}</a>`
        ).join('');
        const followUpItems = (updates || []).map((item) => {
            const followUpDate = item.created_at ? new Date(item.created_at).toLocaleString() : 'Unknown date';
            const followUpText = (item.update_text || '').toString().trim();

            return `<li class="list-group-item">
                <div><strong>${escHtml(followUpDate)}</strong></div>
                <div class="mt-1">${escHtml(followUpText || 'No follow-up text provided.')}</div>
            </li>`;
        }).join('');

        const buildCaseUpdateHistory = (fieldName, fallbackValue = '') => {
            const items = (auditEntries || []).map((entry) => {
                if ((entry.action || '') !== 'case_updated') {
                    return '';
                }

                let details = null;
                try {
                    details = JSON.parse((entry.details || '').toString());
                } catch {
                    details = null;
                }

                if (!details || typeof details !== 'object') {
                    return '';
                }

                const fieldValue = (details[fieldName] || '').toString().trim();
                if (!fieldValue) {
                    return '';
                }

                const changedAt = entry.created_at ? new Date(entry.created_at).toLocaleString() : 'Unknown date';
                return `<li class="list-group-item">
                    <div class="mt-1">${escHtml(fieldValue)}</div>
                    <small class="text-muted">${escHtml(changedAt)}</small>
                </li>`;
            }).filter(Boolean).join('');

            if (items) {
                return items;
            }

            const fallback = (fallbackValue || '').toString().trim();
            return fallback
                ? `<li class="list-group-item"><div class="mt-1">${escHtml(fallback)}</div></li>`
                : '';
        };

        const reporterFeedbackHistoryItems = buildCaseUpdateHistory('reporter_feedback', report.reporter_feedback || '');
        const actionTakenHistoryItems = buildCaseUpdateHistory('action_taken', report.action_taken || '');
        const outcomeCommentsHistoryItems = buildCaseUpdateHistory('outcome_comments', report.outcome_comments || '');
        summary.innerHTML = `<div class="row g-3">
            <div class="col-md-6"><strong>Reference:</strong> ${report.reference_no || ''}</div>
            <div class="col-md-6"><strong>Category:</strong> ${report.category || ''}</div>
            <div class="col-md-6"><strong>Status:</strong> ${report.status || ''}</div>
            <div class="col-md-6"><strong>Priority:</strong> ${report.priority || ''}</div>
            <div class="col-md-6"><strong>Province:</strong> ${escHtml(report.province || 'Not specified')}</div>
            <div class="col-md-6"><strong>Created:</strong> ${created}</div>
            <div class="col-md-6"><strong>Acknowledged:</strong> ${acknowledged}</div>
            <div class="col-md-6"><strong>Assigned Investigator:</strong> ${escHtml(assignedTo)}</div>
            <div class="col-md-6"><strong>Investigator Email:</strong> ${escHtml(assignedEmail)}</div>
            <div class="col-md-6"><strong>Assigned At:</strong> ${assignedAt}</div>
            <div class="col-12"><strong>Description:</strong><div class="mt-1">${report.description || ''}</div></div>
            ${reporterFeedbackHistoryItems ? `<div class="col-12"><strong>Feedback to Reporter History:</strong><ul class="list-group mt-2">${reporterFeedbackHistoryItems}</ul></div>` : ''}
            ${actionTakenHistoryItems ? `<div class="col-12"><strong>Action Taken History:</strong><ul class="list-group mt-2">${actionTakenHistoryItems}</ul></div>` : ''}
            ${outcomeCommentsHistoryItems ? `<div class="col-12"><strong>Outcome Comments History:</strong><ul class="list-group mt-2">${outcomeCommentsHistoryItems}</ul></div>` : ''}
            ${attachmentLinks ? `<div class="col-12"><strong>Attachments:</strong><div class="mt-1">${attachmentLinks}</div></div>` : ''}
            ${followUpItems ? `<div class="col-12"><strong>Follow-up Updates:</strong><ul class="list-group mt-2">${followUpItems}</ul></div>` : ''}
        </div>`;
    };

    const renderCoInvestigatorsList = (coInvestigators) => {
        currentCoInvestigatorIds = (coInvestigators || []).map((ci) => String(ci.user_id || '')).filter((id) => id !== '');

        if (!coInvestigators || coInvestigators.length === 0) {
            coInvestigatorsList.innerHTML = '<p class="text-muted small mb-0">No co-investigators assigned.</p>';
            return;
        }

        const items = coInvestigators.map((ci) => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${escHtml(ci.name || '')}</strong>
                    ${ci.email ? `<br><small class="text-muted">${escHtml(ci.email)}</small>` : ''}
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-co-investigator-btn" data-user-id="${ci.user_id}" ${canAssignCases ? '' : 'disabled'}>
                    <i class="fas fa-times me-1"></i>Remove
                </button>
            </div>
        `).join('');

        coInvestigatorsList.innerHTML = items;

        coInvestigatorsList.querySelectorAll('.remove-co-investigator-btn').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!canAssignCases) {
                    showNotification('You do not have authority to assign cases.', 'warning');
                    return;
                }
                const userId = btn.dataset.userId;
                if (!confirm('Remove this co-investigator?')) return;
                try {
                    await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}/co-investigators/${encodeURIComponent(userId)}`, {
                        method: 'DELETE'
                    });
                    showNotification('Co-investigator removed.', 'success');
                    await loadCoInvestigators();
                } catch (err) {
                    showNotification(err.message, 'danger');
                }
            });
        });
    };

    const setReadOnlyMode = (enabled, reason = '') => {
        if (!updateForm) {
            return;
        }

        updateForm.querySelectorAll('input, select, textarea, button').forEach((el) => {
            if (el.type === 'hidden') {
                return;
            }
            el.disabled = enabled;
        });

        if (addCoInvestigatorBtn) {
            addCoInvestigatorBtn.disabled = enabled;
        }

        if (enabled) {
            hrOutput.classList.add('d-none');
            if (reason) {
                showNotification(reason, 'info');
            }
        } else {
            hrOutput.classList.add('d-none');
        }
    };

    const applyCaseAccessMode = (isCoInvestigatorCase = false) => {
        isCaseReadOnly = currentUserRole === 'ethics' || isCoInvestigatorCase;
        let reason = '';
        if (currentUserRole === 'ethics') {
            reason = 'Ethics Office users can view case details but are not allowed to edit cases.';
        } else if (isCoInvestigatorCase) {
            reason = 'Co-investigators can view this case but cannot edit it.';
        }

        setReadOnlyMode(isCaseReadOnly, reason);
    };

    const setAssignmentPermissionMode = () => {
        const disabled = !canAssignCases;

        if (assignedRoleSelect) {
            assignedRoleSelect.disabled = disabled;
        }
        if (coInvestigatorsSelect) {
            coInvestigatorsSelect.disabled = disabled;
        }
        if (addCoInvestigatorBtn) {
            addCoInvestigatorBtn.disabled = disabled;
        }
        coInvestigatorsList?.querySelectorAll('.remove-co-investigator-btn').forEach((btn) => {
            btn.disabled = disabled;
        });
    };

    const loadCurrentUser = async () => {
        const token = TokenManager.getToken();
        const payload = token ? decodeJwtPayload(token) : null;
        currentUserId = String(payload?.user_id || '').trim();
        currentUserRole = String(payload?.role || '').toLowerCase();
        const payloadCanAssign = payload?.can_assign_cases;
        const payloadCaseManager = payload?.is_case_manager;

        if (payloadCanAssign !== undefined && payloadCanAssign !== null) {
            canAssignCases = Number(payloadCanAssign) === 1;
        }
        if (payloadCaseManager !== undefined && payloadCaseManager !== null) {
            canEditAnonymizedSummary = Number(payloadCaseManager) === 1;
        }

        if ((payloadCanAssign === undefined || payloadCanAssign === null)
            || (payloadCaseManager === undefined || payloadCaseManager === null)) {
            try {
                const me = await api(`${API_BASE}/hr/me`);
                const currentUser = me?.user || {};
                currentUserId = String(currentUser.id || currentUserId || '').trim();
                canAssignCases = Number(currentUser.can_assign_cases || 0) === 1;
                canEditAnonymizedSummary = !!currentUser.is_case_manager;
            } catch {
                canAssignCases = true;
                canEditAnonymizedSummary = false;
            }
        }

        applyCaseAccessMode(false);
        setAssignmentPermissionMode();
        updateConditionalFields();
    };

    const loadCase = async () => {
        const data = await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}`);
        const detail = data.data?.report || {};
        const attachments = data.data?.attachments || [];
        const updates = data.data?.updates || [];
        const audit = data.data?.audit || [];
        const coInvestigators = data.data?.co_investigators || [];
        const isCoInvestigatorCase = coInvestigators
            .some((ci) => String(ci.user_id || '').trim() !== '' && String(ci.user_id || '').trim() === currentUserId);
        renderCaseSummary(detail, attachments, updates, audit);
        populateFormFromCase(detail);
        renderCoInvestigatorsList(coInvestigators);
        applyCaseAccessMode(isCoInvestigatorCase);

        if (!detail.acknowledged_at && !hasShownAcknowledgePrompt && !isCaseReadOnly && canEditAnonymizedSummary) {
            hasShownAcknowledgePrompt = true;
            openAcknowledgeModal(detail);
        }
    };

    const loadCoInvestigators = async () => {
        try {
            const data = await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}/co-investigators`);
            renderCoInvestigatorsList(data.data || []);
            populateCoInvestigatorSelect();
        } catch (err) {
            console.error('Failed to load co-investigators:', err);
        }
    };

    const loadPersonnel = async () => {
        try {
            const data = await api(`${API_BASE}/hr/personnel`);
            personnelList = data.data || [];
            populateCoInvestigatorSelect();
        } catch (err) {
            console.error('Failed to load personnel:', err);
        }
    };

    const populateCoInvestigatorSelect = () => {
        if (!coInvestigatorsSelect || !personnelList.length) {
            return;
        }

        const assignedInvestigatorId = (assignedRoleSelect?.value || '').toString().trim();
        const existingIds = new Set(currentCoInvestigatorIds.map((id) => String(id || '').trim()).filter((id) => id !== ''));

        const opts = personnelList
            .filter((person) => {
                const personId = (person.id || '').toString().trim();
                return personId !== '' && personId !== assignedInvestigatorId && !existingIds.has(personId);
            })
            .map((p) => `<option value="${escHtml(p.id || '')}">${escHtml(p.name || '')} (${escHtml(p.email || '')})</option>`)
            .join('');
        coInvestigatorsSelect.innerHTML = `<option value="">Add co-investigator...</option>${opts}`;
    };

    const loadStatuses = async () => {
        const data = await api(`${API_BASE}/statuses`);
        const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
        if (statusSelect) {
            statusSelect.innerHTML = opts;
            updateConditionalFields();
        }
    };

    const loadStages = async () => {
        const data = await api(`${API_BASE}/stages`);
        const opts = (data.data || []).map((s) => `<option value="${escHtml(s.name)}">${escHtml(s.name)}</option>`).join('');
        if (stageSelect) {
            stageSelect.innerHTML = opts;
            updateConditionalFields();
        }
    };

    const loadProvinces = async () => {
        const data = await api(`${API_BASE}/provinces`);
        const opts = (data.data || []).map((p) => `<option value="${escHtml(p.name)}">${escHtml(p.name)}</option>`).join('');
        if (provinceSelect) {
            provinceSelect.innerHTML = `<option value="">Not specified</option>${opts}`;
        }
    };

    stageSelect?.addEventListener('change', updateConditionalFields);
    statusSelect?.addEventListener('change', updateConditionalFields);

    const loadAssignableRoles = async () => {
        if (!assignedRoleSelect) {
            return;
        }
        const data = await api(`${API_BASE}/hr/personnel`);
        const opts = (data.data || []).map((person) => {
            const title = (person.position_title || '').toString().trim();
            const location = (person.office_location || '').toString().trim();
            const suffixParts = [];
            if (title) suffixParts.push(title);
            if (location) suffixParts.push(location);
            const suffix = suffixParts.length ? ` - ${suffixParts.join(', ')}` : '';
            return `<option value="${escHtml(person.id || '')}">${escHtml(person.name || '')} (${escHtml(person.email || '')})${escHtml(suffix)}</option>`;
        }).join('');
        assignedRoleSelect.innerHTML = `<option value="">Unassigned</option>${opts}`;
        if (currentAssignedInvestigatorId) {
            assignedRoleSelect.value = currentAssignedInvestigatorId;
        }
        populateCoInvestigatorSelect();
    };

    assignedRoleSelect?.addEventListener('change', () => {
        currentAssignedInvestigatorId = (assignedRoleSelect.value || '').toString().trim();
        populateCoInvestigatorSelect();
    });

    addCoInvestigatorBtn?.addEventListener('click', async () => {
        if (!canAssignCases) {
            showNotification('You do not have authority to assign cases.', 'warning');
            return;
        }
        const userId = (coInvestigatorsSelect?.value || '').trim();
        if (!userId) {
            showNotification('Please select a co-investigator.', 'warning');
            return;
        }

        try {
            await api(`${API_BASE}/hr/cases/${encodeURIComponent(reference)}/co-investigators`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId }),
            });
            showNotification('Co-investigator added successfully!', 'success');
            coInvestigatorsSelect.value = '';
            await loadCoInvestigators();
        } catch (err) {
            showNotification(err.message, 'danger');
        }
    });

    updateForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isCaseReadOnly) {
            const message = currentUserRole === 'ethics'
                ? 'Ethics Office cannot edit cases.'
                : 'Co-investigators can view this case but cannot edit it.';
            showNotification(message, 'warning');
            return;
        }
        const formData = new FormData(updateForm);
        const ref = getFormString(formData, 'reference_no');
        if (!ref) {
            showNotification('Reference is required.', 'warning');
            return;
        }

        const acknowledge = !!formData.get('acknowledge');
        const status = getFormString(formData, 'status');
        const anonymizedSummaryValue = formData.get('anonymized_summary');
        const anonymizedSummary = typeof anonymizedSummaryValue === 'string'
            ? anonymizedSummaryValue.trim()
            : '';
        const reporterFeedbackValue = formData.get('reporter_feedback');
        const reporterFeedback = typeof reporterFeedbackValue === 'string'
            ? reporterFeedbackValue.trim()
            : '';
        const actionTakenValue = formData.get('action_taken');
        const actionTaken = typeof actionTakenValue === 'string'
            ? actionTakenValue.trim()
            : '';
        const outcomeCommentsValue = formData.get('outcome_comments');
        const outcomeComments = typeof outcomeCommentsValue === 'string'
            ? outcomeCommentsValue.trim()
            : '';

        if (!acknowledge) {
            showNotification('Acknowledge Case is required before saving.', 'warning');
            hrOutput.classList.add('d-none');
            return;
        }

        const completedFieldError = getCompletedFieldError(status, formData.get('stage'), {
            anonymized_summary: anonymizedSummary,
            reporter_feedback: reporterFeedback,
            action_taken: actionTaken,
            outcome_comments: outcomeComments,
        });
        if (completedFieldError) {
            showNotification(completedFieldError, 'warning');
            hrOutput.classList.add('d-none');
            return;
        }

        const payload = {
            priority: formData.get('priority'),
            stage: formData.get('stage'),
            status,
            province: formData.get('province') || null,
            ...(canAssignCases ? { assigned_to_user_id: formData.get('assigned_to_user_id') || null } : {}),
            ...(canEditAnonymizedSummary ? { anonymized_summary: anonymizedSummary } : {}),
            reporter_feedback: reporterFeedback,
            action_taken: actionTaken,
            outcome_comments: outcomeComments,
            internal_notes: formData.get('internal_notes'),
            acknowledge: acknowledge ? 1 : 0,
        };

        try {
            await api(`${API_BASE}/hr/cases/${encodeURIComponent(ref)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            showNotification('Case updated successfully!', 'success');
            hrOutput.classList.add('d-none');
            await loadCase();
        } catch (err) {
            showNotification(err.message, 'danger');
            hrOutput.classList.add('d-none');
        }
    });

    (async () => {
        try {
            await loadCurrentUser();
            await Promise.all([loadStatuses(), loadStages(), loadProvinces()]);
            await loadCase();
            await loadCoInvestigators();

            if (currentUserRole !== 'ethics') {
                await Promise.all([loadAssignableRoles(), loadPersonnel()]);
            }
        } catch (err) {
            showNotification(err.message, 'danger');
            summary.innerHTML = '<div class="text-muted">Unable to load case details.</div>';
        }
    })();
}

document.addEventListener('DOMContentLoaded', () => {
    initHrCaseDetailPage();
});

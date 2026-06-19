<section class="panel" id="hr-case-page" data-reference="<?= htmlspecialchars($reference ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-edit me-2" style="color: #008AC4;"></i>Update Feedback Case</h2>
        <a href="/hr" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Feedback List
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header" style="background-color: #008AC4; color: white;">
            <h5 class="mb-0"><i class="fas fa-circle-info me-2"></i>Current Case Details</h5>
        </div>
        <div class="card-body" id="hr-case-summary">
            <div class="text-muted">Loading case details...</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background-color: #9d2722; color: white;">
            <h5 class="mb-0"><i class="fas fa-pen-to-square me-2"></i>Case Update Form</h5>
        </div>
        <div class="card-body">
            <form id="hr-update-form">
                <input type="hidden" name="reference_no" value="<?= htmlspecialchars($reference ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-3">
                    <label for="priority" class="form-label"><i class="fas fa-flag me-1"></i>Priority</label>
                    <select id="priority" name="priority" class="form-select">
                        <option>Low</option>
                        <option selected>Normal</option>
                        <option>High</option>
                        <option>Critical</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="stage" class="form-label"><i class="fas fa-tasks me-1"></i>Stage</label>
                    <select id="stage" name="stage" class="form-select">
                        <option value="">Loading stages...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label"><i class="fas fa-sync-alt me-1"></i>Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Loading statuses...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="province" class="form-label"><i class="fas fa-location-dot me-1"></i>Province</label>
                    <select id="province" name="province" class="form-select">
                        <option value="">Loading provinces...</option>
                    </select>
                    <div class="form-text">Optional.</div>
                </div>

                <div class="mb-3">
                    <label for="assigned-role-id" class="form-label"><i class="fas fa-user-tie me-1"></i>Assigned Investigator(s)</label>
                    <select id="assigned-role-id" name="assigned_to_user_id" class="form-select">
                        <option value="">Select Investigator</option>
                    </select>
                    <div class="form-text">Assign this case to an investigator. Co-investigators can be added below to work alongside the primary investigator.</div>
                </div>

                <div class="mb-3">
                    <fieldset>
                        <legend class="form-label"><i class="fas fa-users me-1"></i>Co-Investigators</legend>
                        <div id="co-investigators-container" class="card border-light mb-2">
                            <div id="co-investigators-list" class="card-body">
                                <p class="text-muted small mb-0">No co-investigators assigned.</p>
                            </div>
                        </div>
                        <div class="input-group">
                            <select id="co-investigator-select" class="form-select">
                                <option value="">Add co-investigator...</option>
                            </select>
                            <button type="button" id="add-co-investigator-btn" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                        <div class="form-text">Select one user and click Add. You can still assign multiple co-investigators by adding them one at a time.</div>
                    </fieldset>
                </div>

                <div class="mb-3">
                    <label for="anon-summary" class="form-label"><i class="fas fa-file-text me-1"></i>Anonymized Summary</label>
                    <textarea id="anon-summary" name="anonymized_summary" class="form-control" rows="3"></textarea>
                    <div class="form-text">Required when Status is Investigation completed.</div>
                </div>

                <div class="mb-3">
                    <label for="reporter-feedback" class="form-label"><i class="fas fa-comment-dots me-1"></i>Feedback to Reporter</label>
                    <textarea id="reporter-feedback" name="reporter_feedback" class="form-control" rows="3"></textarea>
                    <div class="form-text">Required when Status is Investigation completed.</div>
                </div>

                <div class="mb-3">
                    <label for="action-taken" class="form-label"><i class="fas fa-check-square me-1"></i>Action Taken</label>
                    <textarea id="action-taken" name="action_taken" class="form-control" rows="3"></textarea>
                    <div class="form-text">Required when Status is Investigation completed.</div>
                </div>

                <div class="mb-3">
                    <label for="outcome-comments" class="form-label"><i class="fas fa-comment me-1"></i>Outcome Comments</label>
                    <textarea id="outcome-comments" name="outcome_comments" class="form-control" rows="3"></textarea>
                    <div class="form-text">Required when Status is Investigation completed.</div>
                </div>

                <div class="mb-3">
                    <label for="internal-notes" class="form-label"><i class="fas fa-sticky-note me-1"></i>Internal Notes</label>
                    <textarea id="internal-notes" name="internal_notes" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-check mb-3">
                    <input id="acknowledge-check" type="checkbox" name="acknowledge" value="1" class="form-check-input">
                    <label for="acknowledge-check" class="form-check-label">
                        <i class="fas fa-check-circle me-1"></i>Acknowledge Case
                    </label>
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100">
                    <i class="fas fa-save me-1"></i>Save Update
                </button>
            </form>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

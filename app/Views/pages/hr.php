<section class="panel">
    <h2><i class="fas fa-shield-alt me-2" style="color: #008AC4;"></i>HR Management Console</h2>

    <!-- Login Section -->
    <div class="card mb-4" style="border-left: 4px solid #9d2722;">
        <div class="card-body">
            <form id="hr-login-form" class="row g-3">
                <div class="col-md-6">
                    <label for="hr-password" class="form-label"><i class="fas fa-key me-1"></i>Console Password</label>
                    <input id="hr-password" type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </button>
                    <button id="hr-logout" type="button" class="btn btn-secondary" style="display:none;">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <!-- Case Filter & List -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header" style="background-color: #9d2722; color: white;">
                    <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Case Management</h5>
                </div>
                <div class="card-body">
                    <form id="hr-filter-form" class="mb-3">
                        <div class="mb-3">
                            <label for="filter-ref" class="form-label">Reference Number</label>
                            <input id="filter-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123">
                        </div>
                        <div class="mb-3">
                            <label for="filter-category" class="form-label">Category</label>
                            <input id="filter-category" type="text" name="category" class="form-control" placeholder="e.g., Discrimination">
                        </div>
                        <div class="mb-3">
                            <label for="filter-status" class="form-label">Status</label>
                            <select id="filter-status" name="status" class="form-select">
                                <option value="">Any status</option>
                                <option>Investigation pending</option>
                                <option>Investigation in progress</option>
                                <option>Investigation completed</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Load Cases
                        </button>
                    </form>
                    <div id="hr-cases-table" class="table-responsive"></div>
                </div>
            </div>
        </div>

        <!-- Case Update Form -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header" style="background-color: #008AC4; color: white;">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Case</h5>
                </div>
                <div class="card-body">
                    <form id="hr-update-form">
                        <div class="mb-3">
                            <label for="update-ref" class="form-label">
                                <i class="fas fa-fingerprint me-1"></i>Reference
                            </label>
                            <input id="update-ref" type="text" name="reference_no" class="form-control" required>
                        </div>
                        
                        <hr class="my-3">
                        
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
                            <input id="stage" type="text" name="stage" class="form-control" value="Logged">
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label"><i class="fas fa-sync-alt me-1"></i>Status</label>
                            <select id="status" name="status" class="form-select">
                                <option>Investigation pending</option>
                                <option>Investigation in progress</option>
                                <option>Investigation completed</option>
                            </select>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="mb-3">
                            <label for="anon-summary" class="form-label">
                                <i class="fas fa-file-text me-1"></i>Anonymized Summary
                            </label>
                            <textarea id="anon-summary" name="anonymized_summary" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="action-taken" class="form-label">
                                <i class="fas fa-check-square me-1"></i>Action Taken
                            </label>
                            <textarea id="action-taken" name="action_taken" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="outcome-comments" class="form-label">
                                <i class="fas fa-comment me-1"></i>Outcome Comments
                            </label>
                            <textarea id="outcome-comments" name="outcome_comments" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="internal-notes" class="form-label">
                                <i class="fas fa-sticky-note me-1"></i>Internal Notes
                            </label>
                            <textarea id="internal-notes" name="internal_notes" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input id="acknowledge-check" type="checkbox" name="acknowledge" value="1" class="form-check-input">
                            <label for="acknowledge-check" class="form-check-label">
                                <i class="fas fa-check-circle me-1"></i>Acknowledge Case
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="fas fa-save me-1"></i>Update Case
                        </button>
                    </form>
                    <pre id="hr-output" class="output mt-3 d-none"></pre>
                </div>
            </div>
        </div>
    </div>
</section>

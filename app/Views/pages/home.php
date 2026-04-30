<section class="panel">
    <h2><i class="fas fa-comment-medical me-2"></i>Anonymous Feedback Submission</h2>
    <div class="alert alert-info" role="alert">
        <i class="fas fa-shield-alt me-2"></i><strong>Your Safety Matters:</strong> Retaliation and victimization are prohibited. This platform is confidential and intended for safe reporting.
    </div>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-new-btn" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">
                <i class="fas fa-pen me-1"></i>New Feedback
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-followup-btn" data-bs-toggle="tab" data-bs-target="#tab-followup" type="button" role="tab">
                <i class="fas fa-reply me-1"></i>Follow-up
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div id="tab-new" class="tab-pane fade show active" role="tabpanel">
            <form id="new-feedback-form" enctype="multipart/form-data" class="mt-3">
                <div class="mb-3">
                    <label for="category-new" class="form-label"><i class="fas fa-list me-1"></i>Category</label>
                    <select id="category-new" name="category" class="form-select" required>
                        <option value="">Loading categories...</option>
                    </select>
                </div>
                <div class="mb-3 d-none" id="category-other-wrapper">
                    <label for="category-other-text" class="form-label"><i class="fas fa-pen me-1"></i>Please specify</label>
                    <input id="category-other-text" type="text" class="form-control" maxlength="255" placeholder="Describe the category...">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label"><i class="fas fa-file-alt me-1"></i>Description (max 5000 characters)</label>
                    <textarea id="description" name="description" class="form-control" maxlength="5000" rows="5" required></textarea>
                    <small class="form-text text-muted">Please provide as much detail as possible while remaining anonymous.</small>
                </div>
                <div class="mb-3">
                    <label for="attachments-new" class="form-label"><i class="fas fa-paperclip me-1"></i>Attachments (optional)</label>
                    <input id="attachments-new" type="file" name="attachments[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.gif,.mp3,.wav,.m4a,.mp4,.webm,.mov,.zip,.rar,.7z" multiple>
                    <small class="form-text text-muted">Acceptable formats: documents, images, audio, video, archives. Max 25MB each.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check me-2"></i>Submit Anonymously
                </button>
            </form>

            <div id="new-feedback-confirmation" class="alert alert-success mt-3 d-none" role="alert">
                <i class="fas fa-circle-check me-2"></i>Feedback submitted. Your reference number is:
                <strong id="new-feedback-reference" class="ms-1"></strong>
            </div>
        </div>

        <div id="tab-followup" class="tab-pane fade" role="tabpanel">
            <div class="row mt-3">
                <div class="col-lg-6">
                    <h5><i class="fas fa-search me-2"></i>Lookup Your Case</h5>
                    <form id="lookup-form" class="mb-4">
                        <div class="mb-3">
                            <label for="lookup-ref" class="form-label">Reference Number</label>
                            <input id="lookup-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123" required>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-search me-1"></i>Retrieve
                        </button>
                    </form>
                    <div id="lookup-output" class="d-none"></div>
                </div>
                <div class="col-lg-6">
                    <h5><i class="fas fa-reply-all me-2"></i>Submit Follow-up</h5>
                    <form id="followup-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="followup-ref" class="form-label">Reference Number</label>
                            <input id="followup-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123" required>
                        </div>
                        <div class="mb-3">
                            <label for="update-text" class="form-label">Update Details (max 5000 characters)</label>
                            <textarea id="update-text" name="update_text" class="form-control" maxlength="5000" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="attachments-followup" class="form-label">Attachments (optional)</label>
                            <input id="attachments-followup" type="file" name="attachments[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.gif,.mp3,.wav,.m4a,.mp4,.webm,.mov,.zip,.rar,.7z" multiple>
                            <small class="form-text text-muted">Acceptable formats: documents, images, audio, video, archives. Max 25MB each.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Submit Follow-up
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div id="tab-reporting" class="tab-pane fade" role="tabpanel">
            <form id="public-report-filter" class="mt-3 mb-4 p-3" style="background-color: #f8f9fa; border-radius: 8px;">
                <h5><i class="fas fa-filter me-2"></i>Search Reports</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="reference_no" class="form-control" placeholder="Reference number">
                    </div>
                    <div class="col-md-6">
                        <select id="report-filter-category" name="category" class="form-select">
                            <option value="">Any category</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select id="report-filter-status" name="status" class="form-select">
                            <option value="">Any status</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="date" name="date" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
            </form>
            <div id="public-report-table"></div>
        </div>
    </div>

    <pre id="global-output" class="output d-none mt-3"></pre>
</section>

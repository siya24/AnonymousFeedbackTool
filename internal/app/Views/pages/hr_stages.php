<section class="panel" id="hr-stages-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-layer-group me-2" style="color: #008AC4;"></i>Manage Stages</h2>
        <div class="d-flex gap-2">
            <a href="/hr" class="btn" style="border:1px solid #9d2722; color:#9d2722;">
                <i class="fas fa-arrow-left me-1"></i>Back to HR Console
            </a>
            <a href="/hr/categories" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-tags me-1"></i>Categories
            </a>
            <a href="/hr/statuses" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-stream me-1"></i>Statuses
            </a>
        </div>
    </div>

    <div id="hr-stages-section" class="mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #008AC4; color: white;">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Workflow Stages</h5>
                <button id="stage-add-btn" class="btn btn-sm" style="background-color:#9d2722; border-color:#9d2722; color:#fff;"><i class="fas fa-plus me-1"></i>Add Stage</button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Stages represent where a case is in the internal workflow (e.g. <em>Under Review</em>, <em>Escalated</em>, <em>Resolved</em>).
                    They are separate from the case <strong>Status</strong> which reflects the investigation state visible to reporters.
                </p>
                <div id="stage-add-form" class="d-none mb-3 p-3" style="background:#f8f9fa;border-radius:6px;">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input id="stage-new-name" type="text" class="form-control" maxlength="120" placeholder="Stage name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input id="stage-new-order" type="number" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button id="stage-save-btn" class="btn flex-grow-1" style="background-color:#008AC4; border-color:#008AC4; color:#fff;"><i class="fas fa-save me-1"></i>Save</button>
                            <button id="stage-cancel-btn" class="btn" style="background-color:#9d2722; border-color:#9d2722; color:#fff;">Cancel</button>
                        </div>
                    </div>
                </div>
                <div id="stage-table"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

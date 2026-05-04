<section class="panel" id="hr-statuses-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-stream me-2" style="color: #9d2722;"></i>Manage Statuses</h2>
        <div class="d-flex gap-2">
            <a href="/hr" class="btn" style="border:1px solid #9d2722; color:#9d2722;">
                <i class="fas fa-arrow-left me-1"></i>Back to HR Console
            </a>
            <a href="/hr/categories" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-tags me-1"></i>Categories
            </a>
            <a href="/hr/stages" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-layer-group me-1"></i>Stages
            </a>
        </div>
    </div>

    <div id="hr-statuses-section" class="mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #9d2722; color: white;">
                <h5 class="mb-0"><i class="fas fa-stream me-2"></i>Statuses</h5>
                <button id="status-add-btn" class="btn btn-sm" style="background-color:#008AC4; border-color:#008AC4; color:#fff;"><i class="fas fa-plus me-1"></i>Add Status</button>
            </div>
            <div class="card-body">
                <div id="status-add-form" class="d-none mb-3 p-3" style="background:#f8f9fa;border-radius:6px;">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input id="status-new-name" type="text" class="form-control" maxlength="120" placeholder="Status name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input id="status-new-order" type="number" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button id="status-save-btn" class="btn flex-grow-1" style="background-color:#008AC4; border-color:#008AC4; color:#fff;"><i class="fas fa-save me-1"></i>Save</button>
                            <button id="status-cancel-btn" class="btn" style="background-color:#9d2722; border-color:#9d2722; color:#fff;">Cancel</button>
                        </div>
                    </div>
                </div>
                <div id="status-table"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

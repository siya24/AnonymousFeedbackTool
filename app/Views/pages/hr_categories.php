<section class="panel" id="hr-categories-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-tags me-2" style="color: #5a5a5a;"></i>Manage Categories</h2>
        <div class="d-flex gap-2">
            <a href="/hr" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to HR Console
            </a>
            <a href="/hr/statuses" class="btn btn-outline-primary">
                <i class="fas fa-stream me-1"></i>Statuses
            </a>
        </div>
    </div>

    <div id="hr-categories-section" class="mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #5a5a5a; color: white;">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Categories</h5>
                <button id="cat-add-btn" class="btn btn-sm btn-light"><i class="fas fa-plus me-1"></i>Add Category</button>
            </div>
            <div class="card-body">
                <div id="cat-add-form" class="d-none mb-3 p-3" style="background:#f8f9fa;border-radius:6px;">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input id="cat-new-name" type="text" class="form-control" maxlength="120" placeholder="Category name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input id="cat-new-order" type="number" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button id="cat-save-btn" class="btn btn-primary flex-grow-1"><i class="fas fa-save me-1"></i>Save</button>
                            <button id="cat-cancel-btn" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
                <div id="cat-table"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

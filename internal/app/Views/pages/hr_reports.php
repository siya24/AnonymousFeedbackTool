<section class="panel" id="hr-reports-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-chart-bar me-2" style="color: #008AC4;"></i>Case Reports</h2>
        <a href="/hr" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Cases
        </a>
    </div>

    <div class="card">
        <div class="card-header" style="background-color: #008AC4; color: white;">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Search Reports</h5>
        </div>
        <div class="card-body">
            <form id="hr-report-filter" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="report-ref" class="form-label">Reference Number</label>
                    <input id="report-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123">
                </div>
                <div class="col-md-3">
                    <label for="report-category" class="form-label">Category</label>
                    <select id="report-category" name="category" class="form-select">
                        <option value="">Any category</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="report-status" class="form-label">Status</label>
                    <select id="report-status" name="status" class="form-select">
                        <option value="">Any status</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="report-date" class="form-label">Date Logged</label>
                    <input id="report-date" type="date" name="date" class="form-control">
                </div>
                <div class="col-12 d-grid d-md-flex justify-content-md-start gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search Reports
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i>Clear Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header" style="background-color: #008AC4; color: white;">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Anonymized Case Reports</h5>
        </div>
        <div class="card-body">
            <div id="hr-report-table"></div>
        </div>
    </div>

    <pre id="hr-report-output" class="output mt-3 d-none"></pre>
</section>

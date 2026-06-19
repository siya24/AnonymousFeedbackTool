<section class="panel" id="hr-dashboard-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="mb-0"><i class="fas fa-chart-line me-2" style="color: #008AC4;"></i>HR Dashboard</h2>
        <div class="d-flex gap-2">
            <a href="/hr" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to HR Cases
            </a>
            <button id="hr-dashboard-refresh" type="button" class="btn btn-primary">
                <i class="fas fa-rotate me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header" style="background-color: #9d2722; color: white;">
                    <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Status Totals</h5>
                </div>
                <div class="card-body" id="hr-dashboard-status-totals">
                    <div class="text-muted">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header" style="background-color: #4a6f28; color: white;">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Province Grouping</h5>
                </div>
                <div class="card-body" id="hr-dashboard-province-totals">
                    <div class="text-muted">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background-color: #008AC4; color: white;">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Cases by Category per Quarter</h5>
        </div>
        <div class="card-body" id="hr-dashboard-quarterly-trends">
            <div class="text-muted">Loading...</div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header" style="background-color: #9d2722; color: white;">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Category Frequency &amp; Priority Guidance</h5>
        </div>
        <div class="card-body" id="hr-dashboard-category-frequency">
            <div class="text-muted">Loading...</div>
        </div>
    </div>

    <pre id="hr-dashboard-output" class="output mt-3 d-none"></pre>
</section>

<section class="panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-shield-alt me-2" style="color: #008AC4;"></i>HR Management Console</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/hr/dashboard" class="btn btn-outline-primary">
                <i class="fas fa-chart-line me-1"></i>Open Dashboard
            </a>
            <a href="/hr/categories" class="btn btn-outline-secondary">
                <i class="fas fa-tags me-1"></i>Categories
            </a>
            <a href="/hr/statuses" class="btn btn-outline-secondary">
                <i class="fas fa-stream me-1"></i>Statuses
            </a>
        </div>
    </div>

    <div class="card mb-4" style="border-left: 4px solid #9d2722;">
        <div class="card-body">
            <form id="hr-login-form" class="row g-3">
                <div class="col-md-4">
                    <label for="hr-email" class="form-label"><i class="fas fa-user me-1"></i>Email or Username</label>
                    <input id="hr-email" type="text" name="email" class="form-control" placeholder="email or AD username" required>
                </div>
                <div class="col-md-4">
                    <label for="hr-password" class="form-label"><i class="fas fa-key me-1"></i>Password</label>
                    <input id="hr-password" type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </button>
                </div>
            </form>
            <div id="hr-login-note" class="text-muted small mt-2">Login to view feedback cases and update them.</div>
        </div>
    </div>

    <div id="hr-cases-section" style="display:none;">
        <div class="card mb-4">
            <div class="card-header" style="background-color: #9d2722; color: white;">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Feedback</h5>
            </div>
            <div class="card-body">
                <form id="hr-filter-form" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-ref" class="form-label">Reference Number</label>
                        <input id="filter-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123">
                    </div>
                    <div class="col-md-2">
                        <label for="filter-category" class="form-label">Category</label>
                        <select id="filter-category" name="category" class="form-select">
                            <option value="">Any category</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-status" class="form-label">Status</label>
                        <select id="filter-status" name="status" class="form-select">
                            <option value="">Any status</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-date" class="form-label">Date Logged</label>
                        <input id="filter-date" type="date" name="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="filter-sort" class="form-label">Sort By</label>
                        <select id="filter-sort" name="sort_by" class="form-select">
                            <option value="created_at">Date Logged</option>
                            <option value="category">Category</option>
                            <option value="status">Status</option>
                            <option value="reference_no">Reference</option>
                            <option value="priority">Priority</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="filter-order" class="form-label">Order</label>
                        <select id="filter-order" name="sort_order" class="form-select">
                            <option value="DESC">Desc</option>
                            <option value="ASC">Asc</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-grid d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="background-color: #008AC4; color: white;">
                <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Feedback List</h5>
            </div>
            <div class="card-body">
                <div id="hr-cases-table" class="table-responsive"></div>
                <div id="hr-cases-pagination" class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

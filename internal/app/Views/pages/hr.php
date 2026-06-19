<section class="panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-shield-alt me-2" style="color: #008AC4;"></i>HR Management Console</h2>
        <a href="/" class="btn btn-outline-secondary">
            <i class="fas fa-home me-1"></i>Back to Home
        </a>
    </div>

    <p class="text-muted mb-4">Review, filter, and update feedback cases from the HR console.</p>

    <div id="hr-cases-section" style="display:none;">
        <div class="card mb-4">
            <div class="card-header" style="background-color: #9d2722; color: white;">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Feedback</h5>
            </div>
            <div class="card-body">
                <form id="hr-filter-form" class="row g-3 align-items-end">
                    <div class="col-xxl-2 col-xl-4 col-lg-6">
                        <label for="filter-ref" class="form-label">Reference Number</label>
                        <input id="filter-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123">
                    </div>
                    <div class="col-xxl-2 col-xl-2 col-lg-6">
                        <label for="filter-category" class="form-label">Category</label>
                        <select id="filter-category" name="category" class="form-select">
                            <option value="">Any category</option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-xl-2 col-lg-6">
                        <label for="filter-status" class="form-label">Status</label>
                        <select id="filter-status" name="status" class="form-select">
                            <option value="">Any status</option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-xl-2 col-lg-6">
                        <label for="filter-date" class="form-label">Date Logged</label>
                        <input id="filter-date" type="date" name="date" class="form-control">
                    </div>
                    <div class="col-xxl-1 col-xl-2 col-lg-6">
                        <label for="filter-sort" class="form-label">Sort By</label>
                        <select id="filter-sort" name="sort_by" class="form-select">
                            <option value="created_at">Date Logged</option>
                            <option value="category">Category</option>
                            <option value="status">Status</option>
                            <option value="reference_no">Reference</option>
                            <option value="priority">Priority</option>
                        </select>
                    </div>
                    <div class="w-100 d-block d-xxl-none"></div>
                    <div class="col-xxl-1 col-xl-2 col-lg-4">
                        <label for="filter-order" class="form-label">Order</label>
                        <select id="filter-order" name="sort_order" class="form-select">
                            <option value="DESC">Desc</option>
                            <option value="ASC">Asc</option>
                        </select>
                    </div>
                    <div class="col-xxl-2 col-xl-10 col-lg-8 d-grid d-md-flex justify-content-md-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i>Clear
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

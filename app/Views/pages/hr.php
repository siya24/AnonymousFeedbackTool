<section class="panel">
    <h2><i class="fas fa-shield-alt me-2" style="color: #008AC4;"></i>HR Management Console</h2>

    <div class="card mb-4" style="border-left: 4px solid #9d2722;">
        <div class="card-body">
            <form id="hr-login-form" class="row g-3">
                <div class="col-md-4">
                    <label for="hr-email" class="form-label"><i class="fas fa-envelope me-1"></i>Email</label>
                    <input id="hr-email" type="email" name="email" class="form-control" placeholder="your@email.com" required>
                </div>
                <div class="col-md-4">
                    <label for="hr-password" class="form-label"><i class="fas fa-key me-1"></i>Password</label>
                    <input id="hr-password" type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </button>
                    <button id="hr-logout" type="button" class="btn btn-secondary" style="display:none;">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </button>
                </div>
            </form>
            <div id="hr-login-note" class="text-muted small mt-2">Login to view feedback cases and update them on a separate page.</div>
        </div>
    </div>

    <div id="hr-cases-section" style="display:none;">
        <div class="card mb-4">
            <div class="card-header" style="background-color: #9d2722; color: white;">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Feedback</h5>
            </div>
            <div class="card-body">
                <form id="hr-filter-form" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="filter-ref" class="form-label">Reference Number</label>
                        <input id="filter-ref" type="text" name="reference_no" class="form-control" placeholder="e.g., AF-20260423-ABC123">
                    </div>
                    <div class="col-md-4">
                        <label for="filter-category" class="form-label">Category</label>
                        <input id="filter-category" type="text" name="category" class="form-control" placeholder="e.g., Discrimination">
                    </div>
                    <div class="col-md-3">
                        <label for="filter-status" class="form-label">Status</label>
                        <select id="filter-status" name="status" class="form-select">
                            <option value="">Any status</option>
                            <option>Investigation pending</option>
                            <option>Investigation in progress</option>
                            <option>Investigation completed</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
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
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

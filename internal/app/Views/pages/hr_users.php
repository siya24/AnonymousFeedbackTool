<section class="panel" id="hr-users-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-users me-2" style="color: #008AC4;"></i>Manage Users</h2>
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
            <a href="/hr/stages" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-layer-group me-1"></i>Stages
            </a>
            <a href="/hr/roles" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-user-tag me-1"></i>Roles
            </a>
        </div>
    </div>

    <div id="hr-users-section" class="mt-3">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #008AC4; color: white;">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>User Setup</h5>
                <button id="user-add-btn" class="btn btn-sm" style="background-color:#9d2722; border-color:#9d2722; color:#fff;"><i class="fas fa-plus me-1"></i>New</button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    HR Admin manages users here. Add users manually (AD-linked), assign role, and control whether they can assign cases.
                </p>
                <div id="user-form-panel" class="d-none p-3" style="background:#f8f9fa;border-radius:6px;">
                    <h6 id="user-form-title" class="mb-3">User Details</h6>
                    <form id="user-form" class="row g-3">
                        <div class="col-md-6">
                            <label for="user-name" class="form-label">Full Name</label>
                            <input id="user-name" type="text" class="form-control" maxlength="255" placeholder="User full name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="user-email" class="form-label">Email</label>
                            <input id="user-email" type="email" class="form-control" maxlength="255" placeholder="user@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label for="user-assignment-role" class="form-label">Role Assignments</label>
                            <select id="user-assignment-role" class="form-select" multiple size="5"></select>
                            <small class="form-text text-muted">Use Ctrl (Windows) or Cmd (Mac) to select multiple roles.</small>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-md-4 pt-md-2">
                                <input id="user-can-assign" type="checkbox" class="form-check-input">
                                <label for="user-can-assign" class="form-check-label">Can assign cases</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-md-4 pt-md-2">
                                <input id="user-is-active" type="checkbox" class="form-check-input" checked>
                                <label for="user-is-active" class="form-check-label">Active</label>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex gap-2 justify-content-md-end align-items-end">
                            <button id="user-save-btn" type="submit" class="btn flex-grow-1" style="background-color:#008AC4; border-color:#008AC4; color:#fff;"><i class="fas fa-save me-1"></i>Save User</button>
                            <button id="user-cancel-btn" type="button" class="btn" style="background-color:#9d2722; border-color:#9d2722; color:#fff;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="background-color: #9d2722; color: white;">
                <h5 class="mb-0"><i class="fas fa-id-badge me-2"></i>Users</h5>
            </div>
            <div class="card-body">
                <div id="user-table"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>


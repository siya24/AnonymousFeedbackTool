<section class="panel" id="hr-roles-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-user-tag me-2" style="color: #008AC4;"></i>Manage Roles</h2>
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
            <a href="/hr/users" class="btn" style="border:1px solid #008AC4; color:#008AC4;">
                <i class="fas fa-users me-1"></i>Users
            </a>
        </div>
    </div>

    <div id="hr-roles-section" class="mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #008AC4; color: white;">
                <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i>Roles</h5>
                <button id="role-add-btn" class="btn btn-sm" style="background-color:#9d2722; border-color:#9d2722; color:#fff;"><i class="fas fa-plus me-1"></i>Add Role</button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Create, update, delete, and view assignment roles used by the system.
                </p>
                <div id="role-add-form" class="d-none mb-3 p-3" style="background:#f8f9fa;border-radius:6px;">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label" for="role-new-name">Name</label>
                            <input id="role-new-name" type="text" class="form-control" maxlength="120" placeholder="Role name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="role-new-order">Sort Order</label>
                            <input id="role-new-order" type="number" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button id="role-save-btn" class="btn flex-grow-1" style="background-color:#008AC4; border-color:#008AC4; color:#fff;"><i class="fas fa-save me-1"></i>Save</button>
                            <button id="role-cancel-btn" class="btn" style="background-color:#9d2722; border-color:#9d2722; color:#fff;">Cancel</button>
                        </div>
                    </div>
                </div>
                <div id="role-table"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

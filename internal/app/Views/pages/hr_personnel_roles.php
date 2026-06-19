<section class="panel" id="hr-personnel-roles-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="mb-0"><i class="fas fa-users-cog me-2" style="color: #008AC4;"></i>Manage Personnel Roles</h2>
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

    <div id="hr-personnel-roles-section" class="mt-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #008AC4; color: white;">
                <h5 class="mb-0"><i class="fas fa-id-badge me-2"></i>Personnel Roles and Case Assignment Access</h5>
                <button id="sync-ad-personnel-btn" type="button" class="btn btn-sm" style="background-color:#9d2722; border-color:#9d2722; color:#fff;">
                    <i class="fas fa-arrows-rotate me-1"></i>Sync from AD
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Sync personnel from Active Directory, then manage who can assign cases.
                </p>
                <div id="personnel-roles-table" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <pre id="hr-output" class="output mt-3 d-none"></pre>
</section>

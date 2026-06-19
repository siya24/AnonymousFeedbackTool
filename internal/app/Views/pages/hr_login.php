<section class="panel" id="hr-login-page">
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="text-center mb-4">
                <h2 class="mb-2"><i class="fas fa-right-to-bracket me-2" style="color: #008AC4;"></i>HR Login</h2>
                <p class="text-muted mb-0">Sign in to access the dashboard, feedback queue, reports, and administration tools.</p>
            </div>

            <div class="card" style="border-left: 4px solid #9d2722;">
                <div class="card-body p-4">
                    <form id="hr-login-form" class="row g-3">
                        <div class="col-12">
                            <label for="hr-email" class="form-label"><i class="fas fa-user me-1"></i>Email or Username</label>
                            <input id="hr-email" type="text" name="email" class="form-control" placeholder="email or AD username" required>
                        </div>
                        <div class="col-12">
                            <label for="hr-password" class="form-label"><i class="fas fa-key me-1"></i>Password</label>
                            <input id="hr-password" type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </button>
                        </div>
                    </form>
                    <div id="hr-login-note" class="text-muted small mt-3">Use your assigned internal credentials to continue.</div>
                </div>
            </div>

            <pre id="hr-output" class="output mt-3 d-none"></pre>
        </div>
    </div>
</section>

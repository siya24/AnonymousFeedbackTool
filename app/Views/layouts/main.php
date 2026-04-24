<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Anonymous Feedback Tool', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top" style="background-color: #f8f9fa; border-bottom: 3px solid #9d2722;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/legal_aid_logo.png" alt="Legal Aid SA" height="40" class="me-2">
                <span class="fw-bold" style="color: #9d2722;">Voice Without Fear</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="/" style="color: #9d2722;"><i class="fas fa-home me-1"></i>Home</a></li>
                    <?php if ((strtolower(trim((string) (getenv('APP_MODE') ?: 'full'))) === 'full')): ?>
                    <li class="nav-item"><a class="nav-link" href="/hr" style="color: #008AC4;"><i class="fas fa-shield-alt me-1"></i>HR Console</a></li>
                    <li class="nav-item ms-2" id="nav-hr-login-item">
                        <a class="btn btn-primary btn-sm" href="/hr" id="nav-hr-login">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item ms-2" id="nav-hr-logout-item" style="display:none;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="nav-hr-logout">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main class="py-4">
    <div class="container-lg">
        <?php require $viewPath; ?>
    </div>
</main>
<footer class="py-4 mt-5" style="background-color: #f8f9fa; border-top: 1px solid #98A2B3;">
    <div class="container text-center text-muted">
        <p class="mb-0"><i class="fas fa-lock me-2"></i>All data is encrypted and confidential. No personal identifiers are collected.</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/assets/js/app.js" defer></script>
</body>
</html>

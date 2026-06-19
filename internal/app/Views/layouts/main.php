<?php declare(strict_types=1);

$appMode = strtolower(trim((string) (getenv('APP_MODE') ?: 'full')));
$isFullMode = ($appMode === 'full');
$hideHrAuthNav = !empty($hideHrAuthNav);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$homePath = '/';
$legacyDashboardPath = '/hr/dashboard';
$feedbacksPath = '/hr';
$reportsPath = '/anonymized/reports';
$categoriesPath = '/hr/categories';
$statusesPath = '/hr/statuses';
$stagesPath = '/hr/stages';
$usersPath = '/hr/users';
$legacyUsersPath = '/hr/personnel-roles';
$rolesPath = '/hr/roles';
$feedbackCasesPrefix = '/hr/cases/';
$hrLoginPath = '/hr/login';

$isActivePath = static function (array $paths) use ($requestPath): bool {
    foreach ($paths as $path) {
        if ($path !== '' && str_ends_with($path, '*')) {
            $prefix = substr($path, 0, -1);
            if ($prefix !== '' && str_starts_with($requestPath, $prefix)) {
                return true;
            }
            continue;
        }

        if ($requestPath === $path) {
            return true;
        }
    }

    return false;
};

$navLinkClass = static function (array $paths, string $baseClass = 'nav-link') use ($isActivePath): string {
    return $isActivePath($paths) ? $baseClass . ' active' : $baseClass;
};

$navAriaCurrent = static function (array $paths) use ($isActivePath): string {
    return $isActivePath($paths) ? ' aria-current="page"' : '';
};

$assetUrl = static function (string $assetPath): string {
    $resolved = $assetPath;

    if ($assetPath !== '' && $assetPath[0] === '/') {
        $internalRoot = dirname(__DIR__, 3);
        $diskPath = $internalRoot . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
        if (is_file($diskPath)) {
            $version = (string) filemtime($diskPath);
            if ($version !== '' && $version !== '0') {
                $separator = str_contains($assetPath, '?') ? '&' : '?';
                $resolved = $assetPath . $separator . 'v=' . rawurlencode($version);
            }
        }
    }

    return $resolved;
};

$adminListsCssAsset = '/public/assets/css/pages/hr-admin-lists.page.css';
$sharedCssAssets = [
    '/public/assets/css/app.css',
];
$pageCssAssets = [];
$sharedJsAssets = [
    '/public/assets/js/app.js',
    '/public/assets/js/pages/hr-global-navigation.page.js',
];
$pageJsAssets = [];

if ($requestPath === $homePath || $requestPath === $legacyDashboardPath) {
    $pageCssAssets[] = '/public/assets/css/pages/hr-dashboard.page.css';
    $pageJsAssets[] = '/public/assets/js/pages/hr-dashboard.page.js';
} elseif ($requestPath === $feedbacksPath || $requestPath === $hrLoginPath) {
    $pageCssAssets[] = '/public/assets/css/pages/hr-console.page.css';
    $pageJsAssets[] = '/public/assets/js/pages/hr-console.page.js';
} elseif (str_starts_with($requestPath, $feedbackCasesPrefix)) {
    $pageCssAssets[] = '/public/assets/css/pages/hr-case-detail.page.css';
    $pageJsAssets[] = '/public/assets/js/pages/hr-case-detail.page.js';
} elseif ($requestPath === $reportsPath) {
    $pageCssAssets[] = '/public/assets/css/pages/hr-reports.page.css';
    $pageJsAssets[] = '/public/assets/js/pages/hr-reports.page.js';
} elseif ($requestPath === $categoriesPath) {
    $pageCssAssets[] = $adminListsCssAsset;
    $pageJsAssets[] = '/public/assets/js/pages/hr-admin-categories.page.js';
} elseif ($requestPath === $statusesPath) {
    $pageCssAssets[] = $adminListsCssAsset;
    $pageJsAssets[] = '/public/assets/js/pages/hr-admin-statuses.page.js';
} elseif ($requestPath === $stagesPath) {
    $pageCssAssets[] = $adminListsCssAsset;
    $pageJsAssets[] = '/public/assets/js/pages/hr-admin-stages.page.js';
} elseif ($requestPath === $rolesPath) {
    $pageCssAssets[] = $adminListsCssAsset;
    $pageJsAssets[] = '/public/assets/js/pages/hr-admin-roles.page.js';
} elseif ($requestPath === $usersPath || $requestPath === $legacyUsersPath) {
    $pageCssAssets[] = $adminListsCssAsset;
    $pageJsAssets[] = '/public/assets/js/pages/hr-admin-users.page.js';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Anonymous Feedback Tool', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="<?= htmlspecialchars($assetUrl('/public/assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars($assetUrl('/public/assets/vendor/fontawesome/css/all.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php foreach ($sharedCssAssets as $asset): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetUrl($asset), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <?php foreach ($pageCssAssets as $asset): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetUrl($asset), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($assetUrl('/public/favicon.ico'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php if ($requestPath === $homePath || $requestPath === $legacyDashboardPath): ?>
<script>
(() => {
    const homePath = <?= json_encode($homePath, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const currentPath = <?= json_encode($requestPath, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const getCookieValue = (name) => {
        const escaped = String(name || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
        return match ? decodeURIComponent(match[1]) : '';
    };

    try {
        const csrfCookie = getCookieValue('hr_csrf_token');
        if (!csrfCookie) {
            globalThis.location.replace(`/hr/login?return_to=${encodeURIComponent(homePath)}`);
            return;
        }
    } catch {
        globalThis.location.replace(`/hr/login?return_to=${encodeURIComponent(homePath)}`);
        return;
    }

    if (currentPath === <?= json_encode($legacyDashboardPath, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>) {
        globalThis.location.replace(homePath);
    }
})();
</script>
<?php endif; ?>
<header>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top app-fixed-nav" style="background-color: #f8f9fa; border-bottom: 3px solid #9d2722;">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="d-lg-flex align-items-center w-100">
                    <ul class="navbar-nav mx-auto align-items-center justify-content-center flex-wrap text-center">
                        <?php if ($requestPath !== $hrLoginPath): ?>
                        <li class="nav-item" id="nav-home-item"><a class="<?= htmlspecialchars($navLinkClass([$homePath, $legacyDashboardPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($homePath, ENT_QUOTES, 'UTF-8') ?>" style="color: #9d2722;"<?= $navAriaCurrent([$homePath, $legacyDashboardPath]) ?>><i class="fas fa-home me-1"></i>Home</a></li>
                        <?php endif; ?>
                        <?php if ($isFullMode && !$hideHrAuthNav): ?>
                        <li class="nav-item ms-lg-2" id="nav-hr-console-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$feedbacksPath, $feedbackCasesPrefix . '*']), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($feedbacksPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$feedbacksPath, $feedbackCasesPrefix . '*']) ?>><i class="fas fa-shield-alt me-1"></i>Feedbacks</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-reports-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$reportsPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($reportsPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$reportsPath]) ?>><i class="fas fa-chart-bar me-1"></i>Case Report</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-categories-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$categoriesPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($categoriesPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$categoriesPath]) ?>><i class="fas fa-tags me-1"></i>Categories</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-statuses-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$statusesPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($statusesPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$statusesPath]) ?>><i class="fas fa-stream me-1"></i>Statuses</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-stages-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$stagesPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($stagesPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$stagesPath]) ?>><i class="fas fa-layer-group me-1"></i>Stages</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-users-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$usersPath, $legacyUsersPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($usersPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$usersPath, $legacyUsersPath]) ?>><i class="fas fa-users me-1"></i>Users</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-roles-item" style="display:none;"><a class="<?= htmlspecialchars($navLinkClass([$rolesPath]), ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($rolesPath, ENT_QUOTES, 'UTF-8') ?>" style="color: #008AC4;"<?= $navAriaCurrent([$rolesPath]) ?>><i class="fas fa-user-tag me-1"></i>Roles</a></li>
                        <li class="nav-item ms-lg-2" id="nav-hr-logout-item" style="display:none;">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="nav-hr-logout">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($isFullMode && !$hideHrAuthNav): ?>
                    <ul class="navbar-nav ms-lg-3 align-items-center flex-row gap-2 mt-3 mt-lg-0">
                        <li class="nav-item" id="nav-hr-login-item">
                            <a class="btn btn-primary btn-sm" href="/hr/login" id="nav-hr-login">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <a class="navbar-brand d-flex align-items-center ms-auto" href="/">
                <span class="fw-bold me-2" style="color: #9d2722;">Voice Without Fear</span>
                <img src="/legal_aid_logo.png" alt="Legal Aid SA" height="60">
            </a>
        </div>
    </nav>
</header>
<main class="py-4 site-main">
    <div class="container-xxl">
        <?php if (isset($viewPath)) { require_once $viewPath; } ?>
    </div>
</main>
<footer class="py-4 site-footer" style="background-color: #f8f9fa; border-top: 1px solid #98A2B3;">
    <div class="container text-center text-muted">
        <p class="mb-0"><i class="fas fa-lock me-2"></i>All data is encrypted and confidential. No personal identifiers are collected.</p>
    </div>
</footer>
<script src="<?= htmlspecialchars($assetUrl('/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php foreach ($sharedJsAssets as $asset): ?>
<script src="<?= htmlspecialchars($assetUrl($asset), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endforeach; ?>
<?php foreach ($pageJsAssets as $asset): ?>
<script src="<?= htmlspecialchars($assetUrl($asset), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endforeach; ?>
</body>
</html>

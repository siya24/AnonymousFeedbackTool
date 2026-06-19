<?php
declare(strict_types=1);

// When using the PHP built-in dev server, serve static files directly.
if (PHP_SAPI === 'cli-server') {
    $staticFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($staticFile)) {
        return false;
    }
}

use App\Controllers\Api\FeedbackApiController;
use App\Controllers\Api\CategoryApiController;
use App\Controllers\Api\ProvinceApiController;
use App\Controllers\Api\StatusApiController;
use App\Controllers\Api\StageApiController;
use App\Controllers\Api\HrAdminApiController;
use App\Controllers\Api\HrApiController;
use App\Controllers\Api\HrCategoryApiController;
use App\Controllers\Api\HrStatusApiController;
use App\Controllers\Api\HrStageApiController;
use App\Controllers\Api\HrRoleApiController;
use App\Controllers\Web\PageController;
use App\Core\Request;
use App\Core\Router;

require_once __DIR__ . '/app/bootstrap.php';

const JSON_CONTENT_TYPE = 'Content-Type: application/json; charset=utf-8';
const ROUTE_CO_INVESTIGATORS = '/api/hr/cases/{id}/co-investigators';
const ROUTE_HR_CATEGORIES_ID = '/api/hr/categories/{id}';
const ROUTE_HR_STATUSES_ID   = '/api/hr/statuses/{id}';
const ROUTE_HR_STAGES_ID     = '/api/hr/stages/{id}';
const ROUTE_HR_ROLES_ID      = '/api/hr/roles/{id}';

function clientIpAddress(): string
{
    $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwardedFor !== '') {
        $first = trim(explode(',', $forwardedFor)[0]);
        if ($first !== '') {
            return $first;
        }
    }

    $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if ($realIp !== '') {
        return $realIp;
    }

    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function hasPassiveDomainIdentity(): bool
{
    $candidates = [
        'REMOTE_USER',
        'AUTH_USER',
        'LOGON_USER',
        'HTTP_REMOTE_USER',
        'HTTP_X_FORWARDED_USER',
    ];

    foreach ($candidates as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value !== '') {
            return true;
        }
    }

    return false;
}

function allowedIntranetVpnCidrs(): array
{
    $raw = trim((string) (getenv('INTRANET_ALLOWED_CIDRS') ?: ''));
    if ($raw === '') {
        return [];
    }

    $parts = array_map(static fn(string $v): string => trim($v), explode(',', $raw));
    return array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
}

function isPrivateOrReservedAddress(string $ip): bool
{
    if ($ip === '') {
        return false;
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function ipInCidr(string $ip, string $cidr): bool
{
    if ($ip === '' || $cidr === '' || !str_contains($cidr, '/')) {
        return $ip === $cidr && $ip !== '';
    }

    [$subnet, $prefixLenRaw] = explode('/', $cidr, 2);
    $subnet    = trim($subnet);
    $prefixLen = (int) trim($prefixLenRaw);
    $ipBin     = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    $maxBits   = $ipBin !== false ? strlen($ipBin) * 8 : 0;

    if ($ipBin === false || $subnetBin === false
            || strlen($ipBin) !== strlen($subnetBin)
            || $prefixLen < 0 || $prefixLen > $maxBits) {
        return false;
    }

    $fullBytes     = intdiv($prefixLen, 8);
    $remainingBits = $prefixLen % 8;
    $prefixMatches = $fullBytes === 0 || substr($ipBin, 0, $fullBytes) === substr($subnetBin, 0, $fullBytes);
    $byteMatches   = $remainingBits === 0
        || ((ord($ipBin[$fullBytes]) & ((0xFF << (8 - $remainingBits)) & 0xFF))
            === (ord($subnetBin[$fullBytes]) & ((0xFF << (8 - $remainingBits)) & 0xFF)));

    return $prefixMatches && $byteMatches;
}

function isIntranetOrVpnClient(): bool
{
    $ip = clientIpAddress();
    $configuredCidrs = allowedIntranetVpnCidrs();
    if ($configuredCidrs === []) {
        return isPrivateOrReservedAddress($ip);
    }

    foreach ($configuredCidrs as $cidr) {
        if (ipInCidr($ip, $cidr)) {
            return true;
        }
    }

    return false;
}

function isLocalReportsBypassEnabled(): bool
{
    $enabled = filter_var(getenv('ALLOW_LOCAL_REPORTS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    if (!$enabled) {
        return false;
    }

    $ip = clientIpAddress();
    return $ip === '127.0.0.1' || $ip === '::1';
}

function isStateChangingMethod(string $method): bool
{
    return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function shouldValidateHrCsrf(string $path, string $method): bool
{
    if (!str_starts_with($path, '/api/hr') || !isStateChangingMethod($method)) {
        return false;
    }

    return $path !== '/api/hr/login';
}

function csrfHeaderToken(): string
{
    return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

function isValidHrCsrfRequest(): bool
{
    $cookieToken = trim((string) ($_COOKIE['hr_csrf_token'] ?? ''));
    $headerToken = csrfHeaderToken();
    if ($cookieToken === '' || $headerToken === '') {
        return false;
    }

    return hash_equals($cookieToken, $headerToken);
}


if (str_starts_with(\App\Core\Request::path(), '/uploads')) {
    http_response_code(403);
    header(JSON_CONTENT_TYPE);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}




$appMode    = strtolower(trim((string) (getenv('APP_MODE') ?: 'full')));
$isFullMode = ($appMode === 'full');


if (!$isFullMode && (
    str_starts_with(Request::path(), '/hr') ||
    str_starts_with(Request::path(), '/api/hr')
)) {
    http_response_code(404);
    header(JSON_CONTENT_TYPE);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$path = Request::path();
$method = Request::method();
$adProtected = ($path === '/anonymized/reports' || $path === '/api/reports');
if ($adProtected && !(isLocalReportsBypassEnabled() || hasPassiveDomainIdentity() || isIntranetOrVpnClient())) {
    http_response_code(403);
    if (str_starts_with($path, '/api/')) {
        header(JSON_CONTENT_TYPE);
        echo json_encode(['error' => 'Forbidden. This resource is available to domain/intranet/VPN employees only.']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden. This page is available to domain/intranet/VPN employees only.';
    }
    exit;
}

if (shouldValidateHrCsrf($path, $method) && !isValidHrCsrfRequest()) {
    http_response_code(419);
    header(JSON_CONTENT_TYPE);
    echo json_encode(['error' => 'Invalid or missing CSRF token.']);
    exit;
}

$router = new Router();


$router->add('GET', '/', [PageController::class, 'home']);
$router->add('GET', '/api/reports', [FeedbackApiController::class, 'publicReports']);
$router->add('GET', '/api/attachments/{id}', [FeedbackApiController::class, 'downloadAttachment']);
$router->add('GET', '/api/categories', [CategoryApiController::class, 'listActive']);
$router->add('GET', '/api/provinces', [ProvinceApiController::class, 'listActive']);
$router->add('GET', '/api/statuses', [StatusApiController::class, 'listActive']);
$router->add('GET', '/api/stages', [StageApiController::class, 'listActive']);
$router->add('GET', '/hr', [PageController::class, 'hr']);
$router->add('GET', '/hr/login', [PageController::class, 'hrLogin']);
$router->add('GET', '/hr/cases/{reference}', [PageController::class, 'hrCase']);
$router->add('GET', '/hr/dashboard', [PageController::class, 'hrDashboard']);
$router->add('GET', '/anonymized/reports', [PageController::class, 'hrReports']);
$router->add('GET', '/hr/categories', [PageController::class, 'hrCategories']);
$router->add('GET', '/hr/statuses', [PageController::class, 'hrStatuses']);
$router->add('GET', '/hr/stages', [PageController::class, 'hrStages']);
$router->add('GET', '/hr/roles', [PageController::class, 'hrRoles']);
$router->add('GET', '/hr/users', [PageController::class, 'hrUsers']);
$router->add('GET', '/hr/personnel-roles', [PageController::class, 'hrPersonnelRoles']);

$router->add('POST', '/api/hr/login', [HrApiController::class, 'login']);
$router->add('POST', '/api/hr/logout', [HrApiController::class, 'logout']);
$router->add('POST', '/api/hr/refresh', [HrApiController::class, 'refresh']);
$router->add('GET', '/api/hr/me', [HrApiController::class, 'getCurrentUser']);
$router->add('GET', '/api/hr/db-identity', [HrApiController::class, 'dbIdentity']);
$router->add('GET', '/api/hr/cases', [HrApiController::class, 'listCases']);
$router->add('GET', '/api/hr/cases/{reference}', [HrApiController::class, 'caseDetail']);
$router->add('POST', '/api/hr/cases/{reference}', [HrApiController::class, 'updateCase']);
$router->add('GET', ROUTE_CO_INVESTIGATORS, [FeedbackApiController::class, 'getCoInvestigators']);
$router->add('POST', ROUTE_CO_INVESTIGATORS, [FeedbackApiController::class, 'addCoInvestigator']);
$router->add('DELETE', '/api/hr/cases/{id}/co-investigators/{user_id}', [FeedbackApiController::class, 'removeCoInvestigator']);
$router->add('PUT', ROUTE_CO_INVESTIGATORS, [FeedbackApiController::class, 'replaceCoInvestigators']);
$router->add('GET', '/api/hr/personnel', [HrAdminApiController::class, 'listAssignablePersonnel']);
$router->add('GET', '/api/hr/users', [HrAdminApiController::class, 'listUsers']);
$router->add('POST', '/api/hr/users', [HrAdminApiController::class, 'createUser']);
$router->add('PUT', '/api/hr/users/{id}', [HrAdminApiController::class, 'updateUser']);
$router->add('DELETE', '/api/hr/users/{id}', [HrAdminApiController::class, 'deleteUser']);
$router->add('GET', '/api/hr/assignable-roles', [HrAdminApiController::class, 'listAssignableRoles']);
$router->add('GET', '/api/hr/dashboard/trends', [HrAdminApiController::class, 'dashboardTrends']);
$router->add('GET', '/api/hr/categories', [HrCategoryApiController::class, 'listAll']);
$router->add('GET', ROUTE_HR_CATEGORIES_ID, [HrCategoryApiController::class, 'getById']);
$router->add('POST', '/api/hr/categories', [HrCategoryApiController::class, 'create']);
$router->add('PUT', ROUTE_HR_CATEGORIES_ID, [HrCategoryApiController::class, 'update']);
$router->add('DELETE', ROUTE_HR_CATEGORIES_ID, [HrCategoryApiController::class, 'delete']);
$router->add('GET', '/api/hr/statuses', [HrStatusApiController::class, 'listAll']);
$router->add('GET', ROUTE_HR_STATUSES_ID, [HrStatusApiController::class, 'getById']);
$router->add('POST', '/api/hr/statuses', [HrStatusApiController::class, 'create']);
$router->add('PUT', ROUTE_HR_STATUSES_ID, [HrStatusApiController::class, 'update']);
$router->add('DELETE', ROUTE_HR_STATUSES_ID, [HrStatusApiController::class, 'delete']);
$router->add('GET', '/api/hr/stages', [HrStageApiController::class, 'listAll']);
$router->add('GET', ROUTE_HR_STAGES_ID, [HrStageApiController::class, 'getById']);
$router->add('POST', '/api/hr/stages', [HrStageApiController::class, 'create']);
$router->add('PUT', ROUTE_HR_STAGES_ID, [HrStageApiController::class, 'update']);
$router->add('DELETE', ROUTE_HR_STAGES_ID, [HrStageApiController::class, 'delete']);
$router->add('GET', '/api/hr/roles', [HrRoleApiController::class, 'listAll']);
$router->add('GET', ROUTE_HR_ROLES_ID, [HrRoleApiController::class, 'getById']);
$router->add('POST', '/api/hr/roles', [HrRoleApiController::class, 'create']);
$router->add('PUT', ROUTE_HR_ROLES_ID, [HrRoleApiController::class, 'update']);
$router->add('DELETE', ROUTE_HR_ROLES_ID, [HrRoleApiController::class, 'delete']);

$router->dispatch($method, $path);

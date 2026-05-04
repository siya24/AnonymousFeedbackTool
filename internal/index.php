<?php
declare(strict_types=1);

use App\Controllers\Api\FeedbackApiController;
use App\Controllers\Api\CategoryApiController;
use App\Controllers\Api\StatusApiController;
use App\Controllers\Api\StageApiController;
use App\Controllers\Api\HrApiController;
use App\Controllers\Api\HrCategoryApiController;
use App\Controllers\Api\HrStatusApiController;
use App\Controllers\Api\HrStageApiController;
use App\Controllers\Web\PageController;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/app/bootstrap.php';

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
        $raw = '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,100.64.0.0/10,127.0.0.0/8,169.254.0.0/16,::1/128,fc00::/7,fe80::/10';
    }

    $parts = array_map(static fn(string $v): string => trim($v), explode(',', $raw));
    return array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
}

function ipInCidr(string $ip, string $cidr): bool
{
    if ($ip === '' || $cidr === '') {
        return false;
    }

    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }

    [$subnet, $prefixLenRaw] = explode('/', $cidr, 2);
    $subnet = trim($subnet);
    $prefixLen = (int) trim($prefixLenRaw);

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $maxBits = strlen($ipBin) * 8;
    if ($prefixLen < 0 || $prefixLen > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($prefixLen, 8);
    $remainingBits = $prefixLen % 8;

    if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
}

function isIntranetOrVpnClient(): bool
{
    $ip = clientIpAddress();
    foreach (allowedIntranetVpnCidrs() as $cidr) {
        if (ipInCidr($ip, $cidr)) {
            return true;
        }
    }

    return false;
}


if (str_starts_with(\App\Core\Request::path(), '/uploads')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not found']);
    exit;
}

$path = Request::path();
$adProtected = ($path === '/anonymized/reports' || $path === '/api/reports');
if ($adProtected && !(hasPassiveDomainIdentity() || isIntranetOrVpnClient())) {
    http_response_code(403);
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden. This resource is available to domain/intranet/VPN employees only.']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden. This page is available to domain/intranet/VPN employees only.';
    }
    exit;
}

$router = new Router();


$router->add('GET', '/', [PageController::class, 'hr']);
$router->add('GET', '/api/reports', [FeedbackApiController::class, 'publicReports']);
$router->add('GET', '/api/attachments/{id}', [FeedbackApiController::class, 'downloadAttachment']);
$router->add('GET', '/api/categories', [CategoryApiController::class, 'listActive']);
$router->add('GET', '/api/statuses', [StatusApiController::class, 'listActive']);
$router->add('GET', '/api/stages', [StageApiController::class, 'listActive']);
$router->add('GET', '/hr', [PageController::class, 'hr']);
$router->add('GET', '/hr/cases/{reference}', [PageController::class, 'hrCase']);
$router->add('GET', '/hr/dashboard', [PageController::class, 'hrDashboard']);
$router->add('GET', '/anonymized/reports', [PageController::class, 'hrReports']);
$router->add('GET', '/hr/categories', [PageController::class, 'hrCategories']);
$router->add('GET', '/hr/statuses', [PageController::class, 'hrStatuses']);
$router->add('GET', '/hr/stages', [PageController::class, 'hrStages']);

$router->add('POST', '/api/hr/login', [HrApiController::class, 'login']);
$router->add('POST', '/api/hr/logout', [HrApiController::class, 'logout']);
$router->add('GET', '/api/hr/me', [HrApiController::class, 'getCurrentUser']);
$router->add('GET', '/api/hr/cases', [HrApiController::class, 'listCases']);
$router->add('GET', '/api/hr/cases/{reference}', [HrApiController::class, 'caseDetail']);
$router->add('POST', '/api/hr/cases/{reference}', [HrApiController::class, 'updateCase']);
$router->add('GET', '/api/hr/dashboard/trends', [HrApiController::class, 'dashboardTrends']);
$router->add('GET', '/api/hr/categories', [HrCategoryApiController::class, 'listAll']);
$router->add('GET', '/api/hr/categories/{id}', [HrCategoryApiController::class, 'getById']);
$router->add('POST', '/api/hr/categories', [HrCategoryApiController::class, 'create']);
$router->add('PUT', '/api/hr/categories/{id}', [HrCategoryApiController::class, 'update']);
$router->add('DELETE', '/api/hr/categories/{id}', [HrCategoryApiController::class, 'delete']);
$router->add('GET', '/api/hr/statuses', [HrStatusApiController::class, 'listAll']);
$router->add('GET', '/api/hr/statuses/{id}', [HrStatusApiController::class, 'getById']);
$router->add('POST', '/api/hr/statuses', [HrStatusApiController::class, 'create']);
$router->add('PUT', '/api/hr/statuses/{id}', [HrStatusApiController::class, 'update']);
$router->add('DELETE', '/api/hr/statuses/{id}', [HrStatusApiController::class, 'delete']);
$router->add('GET', '/api/hr/stages', [HrStageApiController::class, 'listAll']);
$router->add('GET', '/api/hr/stages/{id}', [HrStageApiController::class, 'getById']);
$router->add('POST', '/api/hr/stages', [HrStageApiController::class, 'create']);
$router->add('PUT', '/api/hr/stages/{id}', [HrStageApiController::class, 'update']);
$router->add('DELETE', '/api/hr/stages/{id}', [HrStageApiController::class, 'delete']);

$router->dispatch(Request::method(), Request::path());

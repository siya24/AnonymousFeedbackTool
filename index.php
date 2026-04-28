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

// Block direct web access to the uploads directory; files must be served via /api/attachments/{id}
if (str_starts_with(\App\Core\Request::path(), '/uploads')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// APP_MODE controls which routes are registered.
//   public — internet-facing server: feedback portal and its supporting APIs only.
//   full   — intranet server: all routes including the HR Console and HR APIs.
$appMode    = strtolower(trim((string) (getenv('APP_MODE') ?: 'full')));
$isFullMode = ($appMode === 'full');

// In public mode, reject HR paths immediately — no controller or class is loaded.
if (!$isFullMode && (
    str_starts_with(Request::path(), '/hr') ||
    str_starts_with(Request::path(), '/api/hr')
)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not found']);
    exit;
}

$router = new Router();

// ── Public routes (available in both modes) ───────────────────────────────────
$router->add('GET', '/', [PageController::class, 'home']);
$router->add('POST', '/api/feedback', [FeedbackApiController::class, 'submit']);
$router->add('POST', '/api/feedback/update', [FeedbackApiController::class, 'submitUpdate']);
$router->add('GET', '/api/feedback/{reference}', [FeedbackApiController::class, 'getByReference']);
$router->add('GET', '/api/reports', [FeedbackApiController::class, 'publicReports']);
$router->add('GET', '/api/attachments/{id}', [FeedbackApiController::class, 'downloadAttachment']);
$router->add('GET', '/api/categories', [CategoryApiController::class, 'listActive']);
$router->add('GET', '/api/categories/{id}', [CategoryApiController::class, 'getById']);
$router->add('GET', '/api/statuses', [StatusApiController::class, 'listActive']);
$router->add('GET', '/api/statuses/{id}', [StatusApiController::class, 'getById']);
$router->add('GET', '/api/stages', [StageApiController::class, 'listActive']);
$router->add('GET', '/api/stages/{id}', [StageApiController::class, 'getById']);

if ($isFullMode) {
    // ── HR Console web routes (intranet only) ─────────────────────────────────
    $router->add('GET', '/hr', [PageController::class, 'hr']);
    $router->add('GET', '/hr/cases/{reference}', [PageController::class, 'hrCase']);
    $router->add('GET', '/hr/dashboard', [PageController::class, 'hrDashboard']);
    $router->add('GET', '/hr/categories', [PageController::class, 'hrCategories']);
    $router->add('GET', '/hr/statuses', [PageController::class, 'hrStatuses']);
    $router->add('GET', '/hr/stages', [PageController::class, 'hrStages']);
    $router->add('GET', '/api/docs', [PageController::class, 'apiDocs']);
    $router->add('GET', '/api/openapi.json', [PageController::class, 'openApiSpec']);

    // ── HR API routes (intranet only, JWT-protected) ──────────────────────────
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
}

$router->dispatch(Request::method(), Request::path());

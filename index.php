<?php
declare(strict_types=1);

use App\Controllers\Api\FeedbackApiController;
use App\Controllers\Api\CategoryApiController;
use App\Controllers\Api\StatusApiController;
use App\Controllers\Api\HrApiController;
use App\Controllers\Api\HrCategoryApiController;
use App\Controllers\Api\HrStatusApiController;
use App\Controllers\Web\PageController;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

// Web routes
$router->add('GET', '/', [PageController::class, 'home']);
$router->add('GET', '/hr', [PageController::class, 'hr']);
$router->add('GET', '/hr/cases/{reference}', [PageController::class, 'hrCase']);
$router->add('GET', '/hr/dashboard', [PageController::class, 'hrDashboard']);
$router->add('GET', '/hr/categories', [PageController::class, 'hrCategories']);
$router->add('GET', '/hr/statuses', [PageController::class, 'hrStatuses']);

// Public API routes (anonymous feedback)
$router->add('POST', '/api/feedback', [FeedbackApiController::class, 'submit']);
$router->add('POST', '/api/feedback/update', [FeedbackApiController::class, 'submitUpdate']);
$router->add('GET', '/api/feedback/{reference}', [FeedbackApiController::class, 'getByReference']);
$router->add('GET', '/api/reports', [FeedbackApiController::class, 'publicReports']);
$router->add('GET', '/api/attachments/{id}', [FeedbackApiController::class, 'downloadAttachment']);
$router->add('GET', '/api/categories', [CategoryApiController::class, 'listActive']);
$router->add('GET', '/api/categories/{id}', [CategoryApiController::class, 'getById']);
$router->add('GET', '/api/statuses', [StatusApiController::class, 'listActive']);
$router->add('GET', '/api/statuses/{id}', [StatusApiController::class, 'getById']);

// HR API routes (JWT-based auth)
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

$router->dispatch(Request::method(), Request::path());

<?php
declare(strict_types=1);

use App\Controllers\Api\FeedbackApiController;
use App\Controllers\Api\HrApiController;
use App\Controllers\Web\PageController;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

$router->add('GET', '/', [PageController::class, 'home']);
$router->add('GET', '/hr', [PageController::class, 'hr']);

$router->add('POST', '/api/feedback', [FeedbackApiController::class, 'submit']);
$router->add('POST', '/api/feedback/update', [FeedbackApiController::class, 'submitUpdate']);
$router->add('GET', '/api/feedback/{reference}', [FeedbackApiController::class, 'getByReference']);
$router->add('GET', '/api/reports', [FeedbackApiController::class, 'publicReports']);

$router->add('POST', '/api/hr/login', [HrApiController::class, 'login']);
$router->add('POST', '/api/hr/logout', [HrApiController::class, 'logout']);
$router->add('GET', '/api/hr/cases', [HrApiController::class, 'listCases']);
$router->add('GET', '/api/hr/cases/{reference}', [HrApiController::class, 'caseDetail']);
$router->add('POST', '/api/hr/cases/{reference}', [HrApiController::class, 'updateCase']);

$router->dispatch(Request::method(), Request::path());

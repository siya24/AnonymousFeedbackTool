<?php
declare(strict_types=1);

use App\Controllers\Api\CategoryApiController;
use App\Controllers\Api\FeedbackApiController;
use App\Controllers\Api\StageApiController;
use App\Controllers\Api\StatusApiController;
use App\Controllers\Web\PageController;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/app/bootstrap.php';

$path = Request::path();

// Never allow direct access to stored uploads via web path.
if (str_starts_with($path, '/uploads')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// External deployment must never expose internal HR surfaces.
if (
    str_starts_with($path, '/hr') ||
    str_starts_with($path, '/api/hr') ||
    str_starts_with($path, '/anonymized') ||
    $path === '/api/reports'
) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not found']);
    exit;
}

$router = new Router();

// Public website + anonymous reporting surfaces.
$router->add('GET', '/', [PageController::class, 'home']);
$router->add('POST', '/api/feedback', [FeedbackApiController::class, 'submit']);
$router->add('POST', '/api/feedback/update', [FeedbackApiController::class, 'submitUpdate']);
$router->add('GET', '/api/feedback/{reference}', [FeedbackApiController::class, 'getByReference']);
$router->add('GET', '/api/attachments/{id}', [FeedbackApiController::class, 'downloadAttachment']);
$router->add('GET', '/api/categories', [CategoryApiController::class, 'listActive']);
$router->add('GET', '/api/categories/{id}', [CategoryApiController::class, 'getById']);
$router->add('GET', '/api/statuses', [StatusApiController::class, 'listActive']);
$router->add('GET', '/api/statuses/{id}', [StatusApiController::class, 'getById']);
$router->add('GET', '/api/stages', [StageApiController::class, 'listActive']);
$router->add('GET', '/api/stages/{id}', [StageApiController::class, 'getById']);
$router->dispatch(Request::method(), $path);

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\StatusRepository;

final class StatusApiController
{
    private StatusRepository $statusRepository;

    public function __construct()
    {
        $this->statusRepository = Container::get('statusRepository');
    }

    
    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->statusRepository->getActive()]);
    }

    
    public function getById(array $params): void
    {
        $id = trim((string) ($params['id'] ?? ''));
        if ($id === '') {
            Response::json(['error' => 'Invalid status ID'], 400);
        }

        $status = $this->statusRepository->findById($id);
        if (!$status || (int) ($status['is_active'] ?? 0) !== 1) {
            Response::json(['error' => 'Status not found'], 404);
        }

        Response::json(['data' => $status]);
    }
}

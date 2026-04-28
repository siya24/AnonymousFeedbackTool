<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\StageRepository;

final class StageApiController
{
    private StageRepository $stageRepository;

    public function __construct()
    {
        $this->stageRepository = Container::get('stageRepository');
    }

    /**
     * GET /api/stages
     */
    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->stageRepository->getActive()]);
    }

    /**
     * GET /api/stages/{id}
     */
    public function getById(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['error' => 'Invalid stage ID'], 400);
        }

        $stage = $this->stageRepository->findById($id);
        if (!$stage || (int) ($stage['is_active'] ?? 0) !== 1) {
            Response::json(['error' => 'Stage not found'], 404);
        }

        Response::json(['data' => $stage]);
    }
}

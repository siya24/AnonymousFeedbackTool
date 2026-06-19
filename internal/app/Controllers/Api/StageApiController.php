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

    
    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->stageRepository->getActive()]);
    }
}
